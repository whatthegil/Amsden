<?php

namespace Tests\Feature;

use App\Models\Bluebook;
use App\Services\Pdf\PageImages;
use App\Services\Pdf\PageRenderer;
use App\Services\Pdf\PdfWatermarker;
use App\Services\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * The admin's "Render pages" button draws a bluebook a few pages per request
 * until the document runs out, and only then switches readers to the pages.
 */
class RenderPagesButtonTest extends TestCase
{
    use RefreshDatabase;

    private array $admin = ['id' => 1, 'name' => 'A D', 'email' => 'a@cspc.edu.ph', 'role' => 'Admin', 'canUpload' => true];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Store::bluebookDisk());
        if (PageRenderer::tool() === null) {
            $this->markTestSkipped('Nothing on this host to draw pages with.');
        }
    }

    /** A bluebook whose stored file is the first $pages pages of a real thesis. */
    private function paper(int $pages): Bluebook
    {
        $src = collect(glob(base_path('Bluebooks/*/*.pdf')))->sortBy(fn($f) => filesize($f))->first()
            ?? $this->markTestSkipped('No bundled bluebook to cut from.');
        $cut = storage_path("app/render-button-{$pages}.pdf");
        (new Process([PdfWatermarker::mutool(), 'clean', $src, $cut, "1-{$pages}"]))->mustRun();

        Storage::disk(Store::bluebookDisk())->put("bluebooks/cut{$pages}.pdf", file_get_contents($cut));
        @unlink($cut);

        return Bluebook::create([
            'title' => 'T', 'authors' => ['A, B'], 'year' => 2025, 'department' => 'CCS', 'program' => 'P',
            'keywords' => [], 'abstract' => 'A.', 'adviser' => '', 'status' => 'Approved',
            'uploaded_by' => 'a@cspc.edu.ph', 'uploaded_by_name' => 'A D', 'date_added' => '2025-01-01',
            'file_path' => "bluebooks/cut{$pages}.pdf", 'pages' => 99,
        ]);
    }

    private function chunk(Bluebook $b, int $from)
    {
        return $this->withSession(['user' => $this->admin])
            ->postJson("/admin/bluebooks/{$b->id}/render-pages", ['from' => $from]);
    }

    public function test_it_renders_in_chunks_and_switches_readers_over_only_at_the_end(): void
    {
        $b = $this->paper(10);

        $this->chunk($b, 1)->assertOk()->assertJson(['done' => false, 'rendered' => 8, 'next' => 9]);
        $this->assertFalse(PageImages::ready(Store::getBluebook($b->id)), 'Half a document is never sent to readers.');

        $this->chunk($b, 9)->assertOk()->assertJson(['done' => true, 'rendered' => 10]);

        $b->refresh();
        $this->assertSame(10, $b->page_images_count);
        $this->assertSame(10, $b->pages, 'The typed page count is corrected.');
        $this->assertTrue(PageImages::ready(Store::getBluebook($b->id)));
        Storage::disk(Store::bluebookDisk())->assertExists(PageImages::path($b->id, 10));
        $this->assertDatabaseHas('logs', ['action' => 'Rendered Pages', 'bluebook_id' => $b->id]);
    }

    public function test_a_document_of_exactly_one_chunk_still_ends(): void
    {
        // MuPDF answers a range past the end by drawing the last page again;
        // that must read as "no more pages", not as page 8 once more.
        $b = $this->paper(PageRenderer::CHUNK);

        $this->chunk($b, 1)->assertJson(['done' => false, 'rendered' => 8]);
        $this->chunk($b, 9)->assertOk()->assertJson(['done' => true, 'rendered' => 8]);

        $this->assertSame(8, $b->fresh()->page_images_count);
    }

    public function test_only_staff_who_manage_bluebooks_can_render(): void
    {
        $b = $this->paper(2);
        $student = ['id' => 2, 'name' => 'S T', 'email' => 's@my.cspc.edu.ph', 'role' => 'Student', 'canUpload' => false];

        $this->withSession(['user' => $student])->postJson("/admin/bluebooks/{$b->id}/render-pages", ['from' => 1])
            ->assertStatus(302);
        $this->assertNull($b->fresh()->page_images_count);
    }

    public function test_the_admin_page_offers_the_button_with_the_state(): void
    {
        $b = $this->paper(2);

        $this->withSession(['user' => $this->admin])->get("/admin/bluebooks/{$b->id}")
            ->assertOk()
            ->assertSee('Watermarked pages: not rendered')
            ->assertSee('data-render-pages', false)
            ->assertSee(route('admin.bluebooks.renderPages', $b->id), false);
    }
}
