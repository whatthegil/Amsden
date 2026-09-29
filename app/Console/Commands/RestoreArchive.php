<?php

namespace App\Console\Commands;

use App\Services\ArchiveBackup;
use Illuminate\Console\Command;

/**
 * Puts a backup back. It replaces the users, bluebooks, bookmarks, logs and
 * usage tables with the backup's rows, so it asks first - and says which
 * stored documents the backup names that are no longer on the disk.
 */
class RestoreArchive extends Command
{
    protected $signature = 'archive:restore
                            {backup? : The backup to restore (see archive:backup --list); the newest if left out}
                            {--force : Do not ask for confirmation}';

    protected $description = 'Replace the archive database with a backup';

    public function handle(): int
    {
        $name = $this->argument('backup') ?: (ArchiveBackup::list()[0] ?? null);
        if (!$name || !ArchiveBackup::disk()->exists($name)) {
            $this->error($name ? "No backup at {$name}." : 'There are no backups to restore.');
            return self::FAILURE;
        }

        $this->warn("This replaces the current users, bluebooks, bookmarks, logs and usage with {$name}.");
        $this->line('Database: ' . config('database.connections.' . config('database.default') . '.database'));
        if (!$this->option('force') && !$this->confirm('Restore it?', false)) {
            $this->line('Nothing was changed.');
            return self::SUCCESS;
        }

        $result = ArchiveBackup::restore($name);

        foreach ($result['tables'] as $table => $count) {
            $this->line("  {$table}: {$count} rows");
        }
        if ($result['missingFiles']) {
            $this->warn(count($result['missingFiles']) . ' stored documents the backup names are not on the disk:');
            foreach ($result['missingFiles'] as $path) {
                $this->line("  {$path}");
            }
        }
        $this->info('Restored.');

        return self::SUCCESS;
    }
}
