<?php

namespace Tests\Feature;

use App\Jobs\ProcessBluebookOcr;
use App\Jobs\RenderBluebookPages;
use App\Models\Bluebook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Page rendering is queued the moment a bluebook gets a file, on its own
 * queue - not after OCR, which only a worker with Tesseract can do.
 */
class PageRenderQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['watermark.page_autorender' => true]);
        Queue::fake();
    }

    private function paper(array $overrides = []): Bluebook
    {
        return Bluebook::create(array_merge([
            'title' => 'T', 'authors' => ['A, B'], 'year' => 2025, 'department' => 'CCS', 'program' => 'P',
            'keywords' => [], 'abstract' => 'A.', 'adviser' => '', 'status' => 'Pending',
            'uploaded_by' => 's@my.cspc.edu.ph', 'uploaded_by_name' => 'S T', 'date_added' => '2025-01-01',
        ], $overrides));
    }

    public function test_a_new_file_is_queued_for_rendering_on_its_own_queue(): void
    {
        $this->paper(['file_path' => 'bluebooks/a.pdf']);

        Queue::assertPushedOn(RenderBluebookPages::QUEUE, RenderBluebookPages::class);
        $this->assertSame('pages', RenderBluebookPages::QUEUE);
    }

    public function test_a_replaced_file_is_rendered_again_but_other_edits_are_not(): void
    {
        $b = $this->paper(['file_path' => 'bluebooks/a.pdf']);
        Queue::assertPushed(RenderBluebookPages::class, 1);

        $b->update(['title' => 'Renamed']);
        Queue::assertPushed(RenderBluebookPages::class, 1);

        $b->update(['file_path' => 'bluebooks/b.pdf']);
        Queue::assertPushed(RenderBluebookPages::class, 2);
    }

    public function test_a_record_without_a_file_is_not_rendered(): void
    {
        $this->paper();

        Queue::assertNotPushed(RenderBluebookPages::class);
    }

    public function test_ocr_jobs_stay_off_the_render_queue(): void
    {
        ProcessBluebookOcr::dispatch(1);

        Queue::assertPushed(ProcessBluebookOcr::class, fn($job) => $job->queue !== RenderBluebookPages::QUEUE);
    }

    public function test_this_host_has_something_to_render_with(): void
    {
        if (RenderBluebookPages::rasterizer() === null) {
            $this->markTestSkipped('Neither MuPDF nor Poppler on this host.');
        }

        $this->assertContains(RenderBluebookPages::rasterizer()->name(), ['mutool', 'mupdf', 'poppler']);
    }
}
