<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Where backups are kept
    |--------------------------------------------------------------------------
    |
    | Any disk from config/filesystems.php. By default the same disk the PDFs
    | live on, under backups/ - which survives losing the database, but not
    | losing that disk. Point this at a second bucket (another provider, or at
    | least another bucket) to survive that too.
    |
    */

    'disk'   => env('BACKUP_DISK', env('BLUEBOOK_DISK', env('FILESYSTEM_DISK', 'local'))),
    'folder' => env('BACKUP_FOLDER', 'backups'),

    // How many daily backups to keep; older ones are deleted after each run.
    'keep'   => (int) env('BACKUP_KEEP', 14),

    /*
    | The tables holding the archive's own data, in the order they can be
    | restored (a table before any that points at it). Sessions, queued jobs
    | and the migrations list are left out: they are rebuilt, not restored.
    */
    'tables' => ['users', 'bluebooks', 'bookmarks', 'logs', 'feature_usage'],

];
