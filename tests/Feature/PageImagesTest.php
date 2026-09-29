<?php

namespace Tests\Feature;

use App\Jobs\RenderBluebookPages;
use App\Models\Bluebook;
use App\Services\Pdf\PageImages;
use App\Services\Pdf\PdfWatermarker;
use App\Services\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Students are sent watermarked page images, never the PDF, once a bluebook's
 * pages have been drawn - so there is no clean copy to save from the browser.
 */
class PageImagesTest extends TestCase
{
    use RefreshDatabase;

    private function student(): array
    {
        return ['id' => 1, 'name' => 'S T', 'email' => 'reader@my.cspc.edu.ph', 'role' => 'Student', 'canUpload' => false];
    }

    /** A bluebook whose $pages page images are on the disk, page n being 400+n pixels wide. */
    private function bluebookWithPages(int $pages = 5, array $overrides = []): Bluebook
    {
        $b = Bluebook::create(array_merge([
            'title' => 'A Paper', 'authors' => ['Dela Cruz, Maria'], 'year' => 2025,
            'department' => 'CCS', 'program' => 'Bachelor of Science in Information Technology',
            'keywords' => ['k'], 'abstract' => 'A.', 'adviser' => '', 'status' => 'Approved',
            'uploaded_by' => 's@my.cspc.edu.ph', 'uploaded_by_name' => 'S T', 'date_added' => '2025-01-01',
            'file_path' => 'bluebooks/paper.pdf',
            'page_images_count' => $pages, 'page_images_source' => 'bluebooks/paper.pdf',
        ], $overrides));

        $disk = Storage::disk(Store::bluebookDisk());
        $disk->put('bluebooks/paper.pdf', '%PDF-1.4 fake');
        for ($n = 1; $n <= $pages; $n++) {
            $img = imagecreatetruecolor(400 + $n, 500);
            imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
            ob_start();
            imagejpeg($img);
            $disk->put(PageImages::path($b->id, $n), ob_get_clean());
        }

        return $b;
    }

    private function widthOf(string $jpeg): int
    {
        return imagesx(imagecreatefromstring($jpeg));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Store::bluebookDisk());
    }

    public function test_the_reader_is_sent_a_marked_page_not_the_stored_one(): void
    {
        $b = $this->bluebookWithPages();

        $res = $this->withSession(['user' => $this->student()])->get("/student/bluebooks/{$b->id}/pages/2");

        $res->assertOk();
        $res->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringContainsString('private', $res->headers->get('Cache-Control'));
        $this->assertSame(402, $this->widthOf($res->getContent()));
        $this->assertNotSame(Storage::disk(Store::bluebookDisk())->get(PageImages::path($b->id, 2)), $res->getContent(),
            'The page must carry the mark, not be the stored image as is.');
    }

    public function test_the_viewer_is_given_pages_and_no_file_link(): void
    {
        config(['filesystems.bluebook_direct_fetch' => true]);
        $b = $this->bluebookWithPages(7);

        $res = $this->withSession(['user' => $this->student()])->get("/student/bluebooks/{$b->id}");

        $res->assertOk();
        $res->assertSee('data-page-count="7"', false);
        $res->assertSee('data-pages-url="' . url("/student/bluebooks/{$b->id}/pages/__N__") . '?v=', false);
        $res->assertDontSee('data-direct="1"', false);
        // No PDF engine to download, and page one is asked for straight away.
        $res->assertDontSee('pdf.min.js', false);
        $res->assertSee('<link rel="preload" as="image" href="' . url("/student/bluebooks/{$b->id}/pages/1") . '?v=', false);
    }

    public function test_page_addresses_change_with_the_reader_the_file_and_the_waiver(): void
    {
        $book = ['pageImagesSource' => 'bluebooks/a.pdf', 'pageImagesCount' => 5, 'accessLevel' => 'public', 'accessParts' => []];
        $base = PageImages::version($book, 'one@my.cspc.edu.ph');

        // A second student on the same computer must not be shown the first one's cached pages.
        $this->assertNotSame($base, PageImages::version($book, 'two@my.cspc.edu.ph'));
        $this->assertNotSame($base, PageImages::version(['pageImagesSource' => 'bluebooks/b.pdf'] + $book, 'one@my.cspc.edu.ph'));
        $this->assertNotSame($base, PageImages::version(['accessLevel' => 'partial', 'accessParts' => ['abstract' => ['from' => 1, 'to' => 2]]] + $book, 'one@my.cspc.edu.ph'));
        $this->assertSame($base, PageImages::version($book, 'one@my.cspc.edu.ph'));
    }

    public function test_pages_may_be_kept_by_the_browser_but_never_shared(): void
    {
        $b = $this->bluebookWithPages();

        $cache = $this->withSession(['user' => $this->student()])
            ->get("/student/bluebooks/{$b->id}/pages/1")->headers->get('Cache-Control');

        $this->assertStringContainsString('private', $cache);
        $this->assertStringContainsString('max-age=86400', $cache);
    }

    public function test_the_pdf_itself_is_no_longer_handed_to_students(): void
    {
        $b = $this->bluebookWithPages();

        $this->withSession(['user' => $this->student()])->get("/student/bluebooks/{$b->id}/file")->assertNotFound();
    }

    public function test_a_partial_waiver_numbers_only_the_permitted_pages(): void
    {
        $b = $this->bluebookWithPages(10, [
            'access_level' => Bluebook::ACCESS_PARTIAL,
            'access_parts' => ['abstract' => ['from' => 2, 'to' => 3], 'chapter1' => ['from' => 7, 'to' => 7]],
        ]);
        $as = $this->withSession(['user' => $this->student()]);

        // Reader pages 1, 2, 3 are thesis pages 2, 3, 7; there is no page 4.
        $this->assertSame(402, $this->widthOf($as->get("/student/bluebooks/{$b->id}/pages/1")->getContent()));
        $this->assertSame(403, $this->widthOf($as->get("/student/bluebooks/{$b->id}/pages/2")->getContent()));
        $this->assertSame(407, $this->widthOf($as->get("/student/bluebooks/{$b->id}/pages/3")->getContent()));
        $as->get("/student/bluebooks/{$b->id}/pages/4")->assertNotFound();
        $as->get("/student/bluebooks/{$b->id}")->assertSee('data-page-count="3"', false);
    }

    public function test_a_consultation_only_paper_sends_no_pages(): void
    {
        $b = $this->bluebookWithPages(3, ['access_level' => Bluebook::ACCESS_CONSULTATION]);

        $this->withSession(['user' => $this->student()])->get("/student/bluebooks/{$b->id}/pages/1")->assertForbidden();
    }

    public function test_pages_of_a_replaced_file_are_not_served(): void
    {
        $b = $this->bluebookWithPages(3, ['page_images_source' => 'bluebooks/older.pdf']);

        $this->assertFalse(PageImages::ready(Store::getBluebook($b->id)));
        $this->withSession(['user' => $this->student()])->get("/student/bluebooks/{$b->id}/pages/1")->assertNotFound();
    }

    public function test_pages_of_an_unposted_paper_are_not_served(): void
    {
        $b = $this->bluebookWithPages(3, ['status' => 'Pending']);

        $this->withSession(['user' => $this->student()])->get("/student/bluebooks/{$b->id}/pages/1")->assertNotFound();
    }

    private function admin(): array
    {
        return ['id' => 9, 'name' => 'A D', 'email' => 'admin@cspc.edu.ph', 'role' => 'Admin', 'canUpload' => true];
    }

    public function test_the_admin_can_preview_exactly_what_a_reader_is_sent(): void
    {
        $b = $this->bluebookWithPages(10, [
            'access_level' => Bluebook::ACCESS_PARTIAL,
            'access_parts' => ['abstract' => ['from' => 2, 'to' => 3], 'chapter1' => ['from' => 7, 'to' => 7]],
        ]);
        $as = $this->withSession(['user' => $this->admin()]);

        // The ordinary admin view keeps the whole PDF and offers the preview.
        $as->get("/admin/bluebooks/{$b->id}")->assertOk()
            ->assertSee('Preview as a reader')
            ->assertDontSee('data-pages-url', false);

        // The preview is the reader's pages, numbered as they see them.
        $as->get("/admin/bluebooks/{$b->id}?preview=reader")->assertOk()
            ->assertSee('data-page-count="3"', false)
            ->assertSee('3 of 10 pages', false);
        $this->assertSame(402, $this->widthOf($as->get("/admin/bluebooks/{$b->id}/pages/1")->getContent()));
        $this->assertSame(407, $this->widthOf($as->get("/admin/bluebooks/{$b->id}/pages/3")->getContent()));
        $as->get("/admin/bluebooks/{$b->id}/pages/4")->assertNotFound();
    }

    public function test_the_preview_says_when_readers_are_sent_nothing(): void
    {
        $b = $this->bluebookWithPages(3, ['access_level' => Bluebook::ACCESS_CONSULTATION]);

        $this->withSession(['user' => $this->admin()])->get("/admin/bluebooks/{$b->id}?preview=reader")
            ->assertOk()
            ->assertSee('readers are sent no pages of this bluebook', false)
            ->assertDontSee('id="pdf-view"', false);
    }

    public function test_the_page_route_is_not_open_to_students(): void
    {
        $b = $this->bluebookWithPages();

        $this->withSession(['user' => $this->student()])->get("/admin/bluebooks/{$b->id}/pages/1")->assertStatus(302);
    }

    public function test_the_worker_renders_every_page(): void
    {
        if (PdfWatermarker::mutool() === null) {
            $this->markTestSkipped('No MuPDF binary on this host.');
        }

        $src = collect(glob(base_path('Bluebooks/*/*.pdf')))->sortBy(fn($f) => filesize($f))->first();
        if (!$src) {
            $this->markTestSkipped('No bundled bluebook to render.');
        }

        // A two-page cut of a real thesis, so the render stays quick.
        $cut = storage_path('app/page-render-test.pdf');
        (new \Symfony\Component\Process\Process([PdfWatermarker::mutool(), 'clean', $src, $cut, '1-2']))->mustRun();

        $b = Bluebook::create([
            'title' => 'T', 'authors' => ['A, B'], 'year' => 2025, 'department' => 'CCS', 'program' => 'P',
            'keywords' => [], 'abstract' => 'A.', 'adviser' => '', 'status' => 'Approved',
            'uploaded_by' => 's@my.cspc.edu.ph', 'uploaded_by_name' => 'S T', 'date_added' => '2025-01-01',
            'file_path' => 'bluebooks/cut.pdf',
            'pages'     => 120,           // as typed by the uploader - wrong
        ]);
        Storage::disk(Store::bluebookDisk())->put('bluebooks/cut.pdf', file_get_contents($cut));
        @unlink($cut);

        (new RenderBluebookPages($b->id))->handle();

        $b->refresh();
        $this->assertSame(2, $b->page_images_count);
        $this->assertSame(2, $b->pages, 'The record takes the page count the render found.');
        $this->assertSame('bluebooks/cut.pdf', $b->page_images_source);
        Storage::disk(Store::bluebookDisk())->assertExists([PageImages::path($b->id, 1), PageImages::path($b->id, 2)]);
    }
}
