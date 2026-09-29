<?php

namespace App\Console\Commands;

use App\Services\ArchiveBackup;
use Illuminate\Console\Command;

/** Writes a backup of the archive's database; scheduled daily in routes/console.php. */
class BackupArchive extends Command
{
    protected $signature = 'archive:backup {--list : Show the backups kept instead of making one}';

    protected $description = 'Back up the archive database (users, bluebooks, bookmarks, logs) to the backup disk';

    public function handle(): int
    {
        if ($this->option('list')) {
            $all = ArchiveBackup::list();
            $this->line($all ? implode(PHP_EOL, $all) : 'No backups yet.');
            return self::SUCCESS;
        }

        $name = ArchiveBackup::create();
        $size = ArchiveBackup::disk()->size($name);
        $this->info("Backed up to {$name} (" . number_format($size / 1024, 1) . ' KB) on the "' . config('backup.disk') . '" disk.');

        return self::SUCCESS;
    }
}
