<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * A backup of the archive's database, written in PHP rather than by mysqldump.
 *
 * Laravel Cloud's containers have no mysqldump, and the app should not depend
 * on a binary it cannot count on. So each table is read in chunks and written
 * as gzipped JSON lines, with a manifest of every bluebook file on the disk -
 * the PDFs themselves are already files in storage and are not copied, but a
 * restore can say which ones are missing.
 *
 * Format, one JSON value per line:
 *   {"backup": 1, "created": "...", "tables": {...counts}}
 *   {"table": "users", "row": {...}}      ... for every row of every table
 *   {"file": "bluebooks/x.pdf", "size": 123}  ... for every stored document
 */
class ArchiveBackup
{
    public static function disk(): Filesystem
    {
        return Storage::disk(config('backup.disk'));
    }

    /** Write a backup; returns its path on the backup disk. */
    public static function create(): string
    {
        $tables = array_values(array_filter(config('backup.tables', []), fn($t) => Schema::hasTable($t)));
        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        $tmp = tempnam(sys_get_temp_dir(), 'cbams-backup-');
        $gz  = gzopen($tmp, 'wb6');

        $write = fn($value) => gzwrite($gz, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . "\n");

        $write(['backup' => 1, 'created' => now()->toIso8601String(), 'app' => config('app.name'), 'tables' => $counts]);

        foreach ($tables as $table) {
            // By primary key where there is one, so a large table streams in
            // pieces instead of being held in memory whole.
            DB::table($table)->orderBy(Schema::hasColumn($table, 'id') ? 'id' : DB::raw('1'))
                ->chunk(500, function ($rows) use ($write, $table) {
                    foreach ($rows as $row) {
                        $write(['table' => $table, 'row' => (array) $row]);
                    }
                });
        }

        $files = Storage::disk(Store::bluebookDisk());
        foreach (DB::table('bluebooks')->whereNotNull('file_path')->pluck('file_path') as $path) {
            $write(['file' => $path, 'size' => $files->exists($path) ? $files->size($path) : null]);
        }

        gzclose($gz);

        $name = trim(config('backup.folder', 'backups'), '/') . '/cbams-' . now()->format('Y-m-d-His') . '.jsonl.gz';
        $stream = fopen($tmp, 'rb');
        self::disk()->writeStream($name, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        @unlink($tmp);

        self::prune();

        return $name;
    }

    /** Keep the newest `backup.keep`, delete the rest. */
    public static function prune(): int
    {
        $all = self::list();
        $old = array_slice($all, max(1, (int) config('backup.keep', 14)));
        if ($old) {
            self::disk()->delete($old);
        }
        return count($old);
    }

    /** Backups on the disk, newest first. */
    public static function list(): array
    {
        $files = array_filter(
            self::disk()->files(trim(config('backup.folder', 'backups'), '/')),
            fn($f) => str_ends_with($f, '.jsonl.gz')
        );
        rsort($files);          // the name carries the timestamp
        return array_values($files);
    }

    /**
     * Replace the archive's tables with a backup's rows. Destructive by
     * design: the caller asks first.
     *
     * @return array{tables: array<string,int>, missingFiles: string[]}
     */
    public static function restore(string $name): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cbams-restore-');
        file_put_contents($tmp, self::disk()->get($name));
        $gz = gzopen($tmp, 'rb');

        $header = json_decode((string) gzgets($gz), true);
        if (($header['backup'] ?? null) !== 1) {
            gzclose($gz);
            @unlink($tmp);
            throw new \RuntimeException("{$name} is not a C-BAMS backup.");
        }

        $restored = [];
        $missing  = [];
        $files    = Storage::disk(Store::bluebookDisk());

        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($gz, $header, &$restored, &$missing, $files) {
                foreach (array_reverse(array_keys($header['tables'] ?? [])) as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->delete();
                    }
                }

                $batch = [];
                $flush = function () use (&$batch, &$restored) {
                    foreach ($batch as $table => $rows) {
                        DB::table($table)->insert($rows);
                        $restored[$table] = ($restored[$table] ?? 0) + count($rows);
                    }
                    $batch = [];
                };

                while (($line = gzgets($gz)) !== false) {
                    $item = json_decode($line, true);
                    if (isset($item['table'], $item['row']) && Schema::hasTable($item['table'])) {
                        $batch[$item['table']][] = $item['row'];
                        if (count($batch[$item['table']]) >= 200) {
                            $flush();
                        }
                    } elseif (isset($item['file']) && !$files->exists($item['file'])) {
                        $missing[] = $item['file'];
                    }
                }
                $flush();
            });
        } finally {
            Schema::enableForeignKeyConstraints();
            gzclose($gz);
            @unlink($tmp);
        }

        return ['tables' => $restored, 'missingFiles' => $missing];
    }
}
