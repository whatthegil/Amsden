<?php

namespace Tests\Feature;

use App\Models\Bluebook;
use App\Models\User;
use App\Rules\PdfNotEncrypted;
use App\Services\Pdf\PageImages;
use App\Services\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The Library Manual's policies on unpublished materials (4.3.1, 5.2.1): the
 * access levels by their manual names, Legacy records, withheld personal
 * pages, requests for the full text decided title by title, and encrypted
 * PDFs turned away at submission.
 */
class UnpublishedMaterialsPolicyTest extends TestCase
{
    use RefreshDatabase;

    private const READER = 'reader@my.cspc.edu.ph';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Store::bluebookDisk());
        User::create(['name' => 'R D', 'email' => self::READER, 'password' => 'secret-pass', 'role' => 'Student']);
        User::create(['name' => 'Adm', 'email' => 'adm@cspc.edu.ph', 'password' => 'secret-pass', 'role' => 'Admin']);
    }

    private function reader(): array
    {
        return ['id' => 1, 'name' => 'R D', 'email' => self::READER, 'role' => 'Student', 'canUpload' => false];
    }

    private function admin(): array
    {
        return ['id' => 2, 'name' => 'Adm', 'email' => 'adm@cspc.edu.ph', 'role' => 'Admin', 'canUpload' => true];
    }

    /** A posted bluebook whose page n is stored as an image 400+n pixels wide. */
    private function bluebook(array $overrides = [], int $pages = 6): Bluebook
    {
        $b = Bluebook::create(array_merge([
            'title' => 'A Restricted Paper', 'authors' => ['Dela Cruz, Maria'], 'year' => 2025,
            'department' => 'CCS', 'program' => 'Bachelor of Science in Information Technology',
            'keywords' => ['k'], 'abstract' => 'A.', 'adviser' => 'Adv', 'status' => 'Approved',
            'uploaded_by' => self::READER, 'uploaded_by_name' => 'R D', 'date_added' => '2025-01-01',
            'pages' => $pages, 'file_path' => 'bluebooks/paper.pdf',
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

    /** The original page number of the reader's page $n, read from its width. */
    private function pageServed(int $id, int $n): int
    {
        $res = $this->withSession(['user' => $this->reader()])->get("/student/bluebooks/{$id}/pages/{$n}");
        $res->assertOk();

        return imagesx(imagecreatefromstring($res->getContent())) - 400;
    }

    // ── Page selection ──────────────────────────────────────────────────────

    public function test_withheld_pages_are_left_out_under_every_level(): void
    {
        $open = ['accessLevel' => 'public', 'withheldPages' => '2,5-6'];
        $this->assertSame([1, 3, 4], Bluebook::readerPages($open, 6));

        $partial = ['accessLevel' => 'partial', 'accessParts' => ['chapter1' => ['from' => 1, 'to' => 4]], 'withheldPages' => '2'];
        $this->assertSame([1, 3, 4], Bluebook::readerPages($partial, 6));

        $restricted = ['accessLevel' => 'consultation', 'withheldPages' => '6'];
        $this->assertSame([], Bluebook::readerPages($restricted, 6));
    }

    public function test_page_lists_are_read_leniently_and_rejected_when_not_page_lists(): void
    {
        $this->assertSame('3,148-152', Bluebook::normalizePageList(' 3 , 148 - 152 '));
        $this->assertNull(Bluebook::normalizePageList(''));
        $this->assertFalse(Bluebook::normalizePageList('the CV'));
        $this->assertFalse(Bluebook::normalizePageList('9-4'));
        $this->assertSame('1-3,7', Bluebook::compressPages([7, 1, 2, 3]));
    }

    public function test_a_reader_is_never_sent_a_withheld_page(): void
    {
        $b = $this->bluebook(['access_level' => 'public', 'withheld_pages' => '2,6']);

        $this->assertSame([1, 3, 4, 5], [$this->pageServed($b->id, 1), $this->pageServed($b->id, 2), $this->pageServed($b->id, 3), $this->pageServed($b->id, 4)]);
        $this->withSession(['user' => $this->reader()])->get("/student/bluebooks/{$b->id}/pages/5")->assertNotFound();
    }

    // ── Legacy ──────────────────────────────────────────────────────────────

    public function test_a_bluebook_the_library_adds_is_legacy_unless_a_waiver_is_recorded(): void
    {
        $this->withSession(['user' => $this->admin()])->post('/admin/bluebooks/new', [
            'title' => 'Old Thesis', 'authors' => 'Reyes, Ana', 'year' => 2010,
            'department' => 'CCS', 'program' => 'Bachelor of Science in Information Technology',
            'keywords' => 'old', 'abstract' => 'An old thesis.', 'adviser' => 'Adv', 'pages' => 80,
        ])->assertRedirect();

        $this->assertSame(Bluebook::ACCESS_LEGACY, Bluebook::where('title', 'Old Thesis')->value('access_level'));
    }

    public function test_a_student_submission_cannot_be_recorded_as_legacy(): void
    {
        $b = $this->bluebook(['status' => 'Pending']);

        $this->withSession(['user' => $this->admin()])->post("/admin/bluebooks/{$b->id}/edit", [
            'title' => $b->title, 'authors' => 'Dela Cruz, Maria', 'year' => 2025,
            'department' => 'CCS', 'program' => 'Bachelor of Science in Information Technology',
            'keywords' => 'k', 'abstract' => 'A.', 'adviser' => 'Adv', 'pages' => 6,
            'access_level' => 'legacy',
        ])->assertSessionHasErrors('access_level');
    }

    public function test_a_legacy_bluebook_is_viewable_and_labelled(): void
    {
        $b = $this->bluebook(['access_level' => 'legacy']);

        $res = $this->withSession(['user' => $this->reader()])->get("/student/bluebooks/{$b->id}");

        $res->assertOk();
        $res->assertSee('Legacy – No Access Permission on File');
        $res->assertDontSee('Request full-text access');
        $this->assertSame(1, $this->pageServed($b->id, 1));
    }

    // ── Restricted access ───────────────────────────────────────────────────

    public function test_a_restricted_bluebook_offers_no_request_and_sends_no_pages(): void
    {
        $b = $this->bluebook(['access_level' => 'consultation']);

        $res = $this->withSession(['user' => $this->reader()])->get("/student/bluebooks/{$b->id}");
        $res->assertSee('Restricted Access');
        $res->assertDontSee('Request full-text access');

        $this->withSession(['user' => $this->reader()])->get("/student/bluebooks/{$b->id}/pages/1")->assertForbidden();
    }

    public function test_there_are_no_access_requests(): void
    {
        $b = $this->bluebook(['access_level' => 'consultation']);

        $this->withSession(['user' => $this->reader()])->post("/student/bluebooks/{$b->id}/request-access")->assertNotFound();
        $this->withSession(['user' => $this->reader()])->get('/student/access-requests')->assertNotFound();
        $this->withSession(['user' => $this->admin()])->get('/admin/access-requests')->assertNotFound();
        $this->withSession(['user' => $this->admin()])->get('/admin/bluebooks')->assertDontSee('Access Requests');
    }

    // ── Submission ──────────────────────────────────────────────────────────

    public function test_an_encrypted_pdf_is_turned_away(): void
    {
        $rule      = new PdfNotEncrypted();
        $encrypted = UploadedFile::fake()->createWithContent('e.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer\n<</Root 1 0 R /Encrypt 5 0 R>>\n%%EOF");
        $plain     = UploadedFile::fake()->createWithContent('p.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer\n<</Root 1 0 R>>\n%%EOF");

        $this->assertFalse($rule->passes('file', $encrypted));
        $this->assertTrue($rule->passes('file', $plain));
    }

    public function test_the_terms_and_privacy_policy_carry_the_library_manual(): void
    {
        $this->get('/terms')->assertOk()
            ->assertSee('Access to and use of unpublished materials')
            ->assertSee('Access is not a copy')
            ->assertSee('Legacy &ndash; No Access Permission on File', false)
            ->assertSee('three (3) working days');

        $this->get('/privacy')->assertOk()
            ->assertDontSee('Access requests')
            ->assertSee('Personal information inside manuscripts');
    }
}
