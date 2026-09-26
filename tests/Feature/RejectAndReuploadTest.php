<?php

namespace Tests\Feature;

use App\Jobs\ProcessBluebookOcr;
use App\Models\Bluebook;
use App\Services\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Rejecting with a reason the author sees, resubmitting with only a new PDF,
 * and keeping Reprocess OCR for posted bluebooks.
 */
class RejectAndReuploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Store::bluebookDisk());
        Queue::fake();
    }

    private function admin(): array
    {
        return ['id' => 1, 'name' => 'Adm', 'email' => 'adm@cspc.edu.ph', 'role' => 'Admin', 'canUpload' => true];
    }

    private function author(): array
    {
        return ['id' => 2, 'name' => 'S T', 'email' => 's@my.cspc.edu.ph', 'role' => 'Student', 'canUpload' => true];
    }

    private function fakePdf(string $name = 'corrected.pdf'): UploadedFile
    {
        $content = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            . "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]>>endobj\n"
            . "trailer<</Size 4/Root 1 0 R>>\n%%EOF";

        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function makeBluebook(string $status, array $overrides = []): Bluebook
    {
        Storage::disk(Store::bluebookDisk())->put('bluebooks/old.pdf', '%PDF-1.4 old');

        return Bluebook::create(array_merge([
            'title'              => 'Resubmission Paper',
            'authors'            => ['Dela Cruz, Maria'],
            'year'               => 2024,
            'department'         => 'CCS',
            'program'            => 'Bachelor of Science in Information Technology',
            'keywords'           => ['sample'],
            'abstract'           => 'A sample abstract.',
            'adviser'            => 'Dr. Adviser',
            'status'             => $status,
            'pages'              => 10,
            'uploaded_by'        => 's@my.cspc.edu.ph',
            'uploaded_by_name'   => 'S T',
            'date_added'         => '2026-01-01',
            'file_path'          => 'bluebooks/old.pdf',
            'file_original_name' => 'old.pdf',
            'ocr_status'         => 'completed',
        ], $overrides));
    }

    // ─── Reject needs a reason ────────────────────────────────────────────────

    public function test_reject_saves_the_reason(): void
    {
        $bluebook = $this->makeBluebook('Pending');

        $this->withSession(['user' => $this->admin()])
            ->post("/admin/bluebooks/{$bluebook->id}/reject", ['reason' => 'Chapter 3 is missing.'])
            ->assertRedirect(route('admin.bluebooks'));

        $bluebook->refresh();
        $this->assertSame('Rejected', $bluebook->status);
        $this->assertSame('Chapter 3 is missing.', $bluebook->rejection_reason);
    }

    public function test_reject_without_a_reason_is_refused(): void
    {
        $bluebook = $this->makeBluebook('Pending');

        $this->withSession(['user' => $this->admin()])
            ->post("/admin/bluebooks/{$bluebook->id}/reject", ['reason' => '   ']);

        $this->assertSame('Pending', $bluebook->fresh()->status);
    }

    public function test_the_pending_queue_asks_for_a_reason(): void
    {
        $this->makeBluebook('Pending');

        $this->withSession(['user' => $this->admin()])
            ->get('/admin/pending')
            ->assertOk()
            ->assertSee('name="reason"', false)
            ->assertSee('Confirm Reject');
    }

    // ─── What the author sees ─────────────────────────────────────────────────

    public function test_author_sees_the_reason_and_a_reupload_form(): void
    {
        $bluebook = $this->makeBluebook('Rejected', ['rejection_reason' => 'Chapter 3 is missing.']);

        $res = $this->withSession(['user' => $this->author()])->get('/student/my-uploads');

        $res->assertOk();
        $res->assertSee('Chapter 3 is missing.');
        $res->assertSee(route('student.my-uploads.reupload', $bluebook->id), false);
        $res->assertSee('Re-upload');
    }

    public function test_no_reupload_form_for_other_statuses(): void
    {
        $this->makeBluebook('Pending');

        $this->withSession(['user' => $this->author()])
            ->get('/student/my-uploads')
            ->assertDontSee('reupload-form', false);
    }

    // ─── Re-upload ────────────────────────────────────────────────────────────

    public function test_reupload_replaces_the_file_and_returns_to_review(): void
    {
        $bluebook = $this->makeBluebook('Rejected', ['rejection_reason' => 'Chapter 3 is missing.']);

        $this->withSession(['user' => $this->author()])
            ->post("/student/my-uploads/{$bluebook->id}/reupload", ['file' => $this->fakePdf()])
            ->assertRedirect(route('student.my-uploads'))
            ->assertSessionHas('success');

        $bluebook->refresh();
        $this->assertSame('Pending', $bluebook->status);
        $this->assertNull($bluebook->rejection_reason);
        $this->assertSame('corrected.pdf', $bluebook->file_original_name);
        $this->assertNotSame('bluebooks/old.pdf', $bluebook->file_path);
        // Details are kept, not asked for again.
        $this->assertSame('Resubmission Paper', $bluebook->title);
        $this->assertSame('Dr. Adviser', $bluebook->adviser);

        Storage::disk(Store::bluebookDisk())->assertMissing('bluebooks/old.pdf');
        Storage::disk(Store::bluebookDisk())->assertExists($bluebook->file_path);
        Queue::assertPushed(ProcessBluebookOcr::class);
        $this->assertDatabaseHas('logs', ['action' => 'Resubmitted Bluebook']);
    }

    public function test_reupload_rejects_a_non_pdf(): void
    {
        $bluebook = $this->makeBluebook('Rejected');

        $this->withSession(['user' => $this->author()])
            ->post("/student/my-uploads/{$bluebook->id}/reupload", [
                'file' => UploadedFile::fake()->createWithContent('notes.pdf', 'just text'),
            ])
            ->assertSessionHas('error');

        $bluebook->refresh();
        $this->assertSame('Rejected', $bluebook->status);
        $this->assertSame('bluebooks/old.pdf', $bluebook->file_path);
    }

    public function test_reupload_only_for_rejected_bluebooks(): void
    {
        $bluebook = $this->makeBluebook('Pending');

        $this->withSession(['user' => $this->author()])
            ->post("/student/my-uploads/{$bluebook->id}/reupload", ['file' => $this->fakePdf()]);

        $this->assertSame('bluebooks/old.pdf', $bluebook->fresh()->file_path);
    }

    public function test_cannot_reupload_someone_elses_bluebook(): void
    {
        $bluebook = $this->makeBluebook('Rejected', ['uploaded_by' => 'other@my.cspc.edu.ph']);

        $this->withSession(['user' => $this->author()])
            ->post("/student/my-uploads/{$bluebook->id}/reupload", ['file' => $this->fakePdf()]);

        $bluebook->refresh();
        $this->assertSame('Rejected', $bluebook->status);
        $this->assertSame('bluebooks/old.pdf', $bluebook->file_path);
    }

    // ─── Reprocess OCR only once posted ───────────────────────────────────────

    public function test_reprocess_ocr_button_only_for_posted_bluebooks(): void
    {
        $this->makeBluebook('Pending', ['title' => 'Pending One']);
        $this->makeBluebook(Bluebook::STATUS_AWAITING_WAIVER, ['title' => 'Waiting One']);

        $this->withSession(['user' => $this->admin()])
            ->get('/admin/bluebooks')
            ->assertDontSee('Reprocess OCR');

        $this->makeBluebook('Approved', ['title' => 'Posted One']);

        $this->withSession(['user' => $this->admin()])
            ->get('/admin/bluebooks')
            ->assertSee('Reprocess OCR');
    }

    public function test_reprocess_ocr_is_refused_before_posting(): void
    {
        $bluebook = $this->makeBluebook(Bluebook::STATUS_AWAITING_WAIVER);

        $this->withSession(['user' => $this->admin()])
            ->post("/admin/bluebooks/{$bluebook->id}/reprocess-ocr");

        Queue::assertNotPushed(ProcessBluebookOcr::class);
    }

    public function test_reprocess_ocr_runs_for_posted_bluebooks(): void
    {
        $bluebook = $this->makeBluebook('Approved');

        $this->withSession(['user' => $this->admin()])
            ->post("/admin/bluebooks/{$bluebook->id}/reprocess-ocr");

        Queue::assertPushed(ProcessBluebookOcr::class);
    }

    public function test_author_re_extract_button_only_for_posted_bluebooks(): void
    {
        $this->makeBluebook('Pending', ['title' => 'Pending One']);
        $this->makeBluebook(Bluebook::STATUS_AWAITING_WAIVER, ['title' => 'Waiting One']);
        $this->makeBluebook('Rejected', ['title' => 'Rejected One', 'ocr_status' => 'failed']);

        $res = $this->withSession(['user' => $this->author()])->get('/student/my-uploads');
        $res->assertOk();
        $res->assertDontSee('student/bluebooks/', false);

        $posted = $this->makeBluebook('Approved', ['title' => 'Posted One']);

        $this->withSession(['user' => $this->author()])
            ->get('/student/my-uploads')
            ->assertSee("student/bluebooks/{$posted->id}/reprocess-ocr", false);
    }

    public function test_author_reprocess_is_refused_before_posting(): void
    {
        $bluebook = $this->makeBluebook(Bluebook::STATUS_AWAITING_WAIVER);

        $this->withSession(['user' => $this->author()])
            ->post("/student/bluebooks/{$bluebook->id}/reprocess-ocr")
            ->assertRedirect(route('student.my-uploads'));

        Queue::assertNotPushed(ProcessBluebookOcr::class);
    }

    public function test_author_reprocess_runs_for_posted_bluebooks(): void
    {
        $bluebook = $this->makeBluebook('Approved');

        $this->withSession(['user' => $this->author()])
            ->post("/student/bluebooks/{$bluebook->id}/reprocess-ocr");

        Queue::assertPushed(ProcessBluebookOcr::class);
    }
}
