<?php

namespace Tests\Feature;

use App\Models\Bluebook;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** The daily clean-up is scheduled, and does what it says. */
class TidyArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_is_scheduled_daily(): void
    {
        $commands = collect(app(Schedule::class)->events())->map->command->implode("\n");

        $this->assertStringContainsString('archive:tidy', $commands);
        $this->assertStringContainsString('queue:prune-failed', $commands);
    }

    public function test_an_ocr_run_that_died_is_released_for_reprocessing(): void
    {
        $b = Bluebook::create([
            'title' => 'T', 'authors' => ['A, B'], 'year' => 2025, 'department' => 'CCS', 'program' => 'P',
            'keywords' => [], 'abstract' => 'A.', 'adviser' => '', 'status' => 'Approved',
            'uploaded_by' => 's@my.cspc.edu.ph', 'uploaded_by_name' => 'S T', 'date_added' => '2025-01-01',
            'file_path' => 'bluebooks/t.pdf', 'ocr_status' => 'processing',
        ]);
        Bluebook::where('id', $b->id)->update(['updated_at' => now()->subHours(3)]);

        $this->artisan('archive:tidy')->assertSuccessful();

        $this->assertSame('failed', $b->fresh()->ocr_status);
    }

    public function test_a_running_ocr_is_left_alone(): void
    {
        $b = Bluebook::create([
            'title' => 'T', 'authors' => ['A, B'], 'year' => 2025, 'department' => 'CCS', 'program' => 'P',
            'keywords' => [], 'abstract' => 'A.', 'adviser' => '', 'status' => 'Approved',
            'uploaded_by' => 's@my.cspc.edu.ph', 'uploaded_by_name' => 'S T', 'date_added' => '2025-01-01',
            'file_path' => 'bluebooks/t.pdf', 'ocr_status' => 'processing',
        ]);

        $this->artisan('archive:tidy')->assertSuccessful();

        $this->assertSame('processing', $b->fresh()->ocr_status);
    }

    public function test_an_abandoned_render_folder_is_removed_but_a_fresh_one_kept(): void
    {
        $old = storage_path('app/page-render/test-old');
        $new = storage_path('app/page-render/test-new');
        File::ensureDirectoryExists($old);
        File::ensureDirectoryExists($new);
        file_put_contents($old . '/1.png', 'x');
        touch($old, time() - 2 * 86400);

        $this->artisan('archive:tidy')->assertSuccessful();

        $this->assertDirectoryDoesNotExist($old);
        $this->assertDirectoryExists($new);
        File::deleteDirectory($new);
    }
}
