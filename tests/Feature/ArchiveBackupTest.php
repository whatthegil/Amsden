<?php

namespace Tests\Feature;

use App\Models\Bluebook;
use App\Models\Bookmark;
use App\Models\User;
use App\Services\ArchiveBackup;
use App\Services\Store;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** A backup can be made on any host, and puts the archive back exactly. */
class ArchiveBackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('backup.disk'));
        Storage::fake(Store::bluebookDisk());
    }

    private function seedArchive(): Bluebook
    {
        User::create(['name' => 'S T', 'email' => 's@my.cspc.edu.ph', 'password' => Hash::make('x'), 'role' => 'Student']);
        $b = Bluebook::create([
            'title' => 'Cariño’s Study', 'authors' => ['Cariño, Maurine A.'], 'year' => 2025, 'department' => 'CCS', 'program' => 'P',
            'keywords' => ['k'], 'abstract' => 'A.', 'adviser' => '', 'status' => 'Approved',
            'uploaded_by' => 's@my.cspc.edu.ph', 'uploaded_by_name' => 'S T', 'date_added' => '2025-01-01',
            'file_path' => 'bluebooks/kept.pdf',
        ]);
        Bookmark::create(['user_email' => 's@my.cspc.edu.ph', 'user_name' => 'S T', 'bluebook_id' => $b->id, 'added_at' => now()]);
        Store::addLog(['userName' => 'S T', 'email' => 's@my.cspc.edu.ph', 'action' => 'Viewed Bluebook', 'document' => 'Cariño’s Study']);
        Storage::disk(Store::bluebookDisk())->put('bluebooks/kept.pdf', '%PDF');

        return $b;
    }

    public function test_a_backup_restores_the_archive_as_it_was(): void
    {
        $b = $this->seedArchive();
        $name = ArchiveBackup::create();

        // Lose it all.
        Bookmark::query()->delete();
        \App\Models\Log::query()->delete();
        Bluebook::query()->delete();
        User::query()->delete();

        $this->artisan('archive:restore', ['backup' => $name, '--force' => true])->assertSuccessful();

        $this->assertSame('Cariño’s Study', Bluebook::find($b->id)->title, 'Accents survive the round trip.');
        $this->assertSame(['Cariño, Maurine A.'], Bluebook::find($b->id)->authors);
        $this->assertSame(1, User::count());
        $this->assertSame(1, Bookmark::count());
        $this->assertSame($b->id, \App\Models\Log::first()->bluebook_id);
    }

    public function test_the_restore_names_documents_no_longer_on_the_disk(): void
    {
        $this->seedArchive();
        $name = ArchiveBackup::create();
        Storage::disk(Store::bluebookDisk())->delete('bluebooks/kept.pdf');

        $result = ArchiveBackup::restore($name);

        $this->assertSame(['bluebooks/kept.pdf'], $result['missingFiles']);
    }

    public function test_the_restore_asks_first(): void
    {
        $this->seedArchive();
        $name = ArchiveBackup::create();
        Bluebook::query()->delete();

        $this->artisan('archive:restore', ['backup' => $name])
            ->expectsConfirmation('Restore it?', 'no')
            ->assertSuccessful();

        $this->assertSame(0, Bluebook::count(), 'Declining changes nothing.');
    }

    public function test_only_the_newest_backups_are_kept(): void
    {
        config(['backup.keep' => 2]);
        foreach (['2026-01-01-000000', '2026-01-02-000000', '2026-01-03-000000'] as $stamp) {
            Storage::disk(config('backup.disk'))->put("backups/cbams-{$stamp}.jsonl.gz", 'x');
        }

        ArchiveBackup::prune();

        $this->assertSame(['backups/cbams-2026-01-03-000000.jsonl.gz', 'backups/cbams-2026-01-02-000000.jsonl.gz'], ArchiveBackup::list());
    }

    public function test_it_is_scheduled_daily(): void
    {
        $commands = collect(app(Schedule::class)->events())->map->command->implode("\n");

        $this->assertStringContainsString('archive:backup', $commands);
    }
}
