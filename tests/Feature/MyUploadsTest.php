<?php

namespace Tests\Feature;

use App\Models\Bluebook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What an author is told about a paper they sent in.
 *
 * The page used to be an eight-column table whose fifth column was the
 * submission's status and whose eighth was the OCR status - two badges side by
 * side, one reading "Approved" and the other "Completed", about entirely
 * different things. The state is the block's leading edge now, and each one
 * says what it means for the person who submitted it.
 */
class MyUploadsTest extends TestCase
{
    use RefreshDatabase;

    private function student(): array
    {
        return [
            'id' => 1, 'name' => 'S T', 'email' => 's@my.cspc.edu.ph',
            'role' => 'Student', 'canUpload' => true,
        ];
    }

    private function upload(array $overrides = []): Bluebook
    {
        return Bluebook::create(array_merge([
            'title' => 'An Automated Attendance System', 'authors' => ['S T'],
            'year' => 2024, 'department' => 'CCS',
            'program' => 'Bachelor of Science in Information Technology',
            'keywords' => ['RFID'], 'abstract' => 'A study.', 'adviser' => '',
            'status' => 'Pending', 'uploaded_by' => 's@my.cspc.edu.ph',
            'uploaded_by_name' => 'S T', 'date_added' => '2024-06-15',
        ], $overrides));
    }

    private function visit()
    {
        return $this->withSession(['user' => $this->student()])->get('/student/my-uploads');
    }

    public function test_each_submission_carries_its_state(): void
    {
        $this->upload(['status' => 'Approved', 'title' => 'Approved One']);
        $this->upload(['status' => 'Pending',  'title' => 'Pending One']);
        $this->upload(['status' => 'Rejected', 'title' => 'Rejected One']);

        $html = $this->visit()->getContent();

        $this->assertSame(1, substr_count($html, 'submission is-approved'));
        $this->assertSame(1, substr_count($html, 'submission is-pending'));
        $this->assertSame(1, substr_count($html, 'submission is-rejected'));
    }

    /**
     * A one-word badge leaves an author guessing whether anything is expected of
     * them. Each state says.
     */
    public function test_every_state_explains_itself(): void
    {
        $this->upload(['status' => 'Approved']);
        $res = $this->visit();
        $res->assertSee('Published to the archive', false);

        Bluebook::query()->delete();
        $this->upload(['status' => 'Pending']);
        $this->visit()->assertSee('Nothing is needed from you', false);

        Bluebook::query()->delete();
        $this->upload(['status' => 'Rejected']);
        $this->visit()->assertSee('Not published to the archive', false);
    }

    /**
     * OCR is our word for it. What the author cares about is whether the paper
     * can be found by the words inside it, so that is what the label says.
     */
    public function test_the_text_extraction_state_is_shown_in_the_authors_terms(): void
    {
        $this->upload(['file_path' => 'bluebooks/p.pdf', 'ocr_status' => 'completed']);

        $res = $this->visit();
        $res->assertSee('Searchable text', false);
        $res->assertSee('Ready', false);
        $res->assertDontSee('OCR', false);
    }

    /** A failure is worth something only if it says what failed. */
    public function test_a_failed_extraction_shows_the_reason_and_offers_a_retry(): void
    {
        // Retrying is only offered once the bluebook is posted.
        $this->upload([
            'status'     => 'Approved',
            'file_path'  => 'bluebooks/p.pdf',
            'ocr_status' => 'failed',
            'ocr_error'  => 'No rasterizer binary was found on this host',
        ]);

        $res = $this->visit();
        $res->assertSee('Failed', false);
        $res->assertSee('No rasterizer binary', false);
        $res->assertSee('Try again', false);
    }

    /** Retrying a run that is already going would only queue a second one. */
    public function test_a_run_in_progress_cannot_be_retried(): void
    {
        $this->upload(['file_path' => 'bluebooks/p.pdf', 'ocr_status' => 'processing']);

        $res = $this->visit();
        $res->assertSee('Processing', false);
        $res->assertDontSee('Try again', false);
        $res->assertDontSee('Re-extract', false);
    }

    /** Nothing to extract from a record with no file attached. */
    public function test_a_record_with_no_file_shows_no_extraction_row(): void
    {
        $this->upload();

        $this->visit()->assertDontSee('Searchable text', false);
    }

    public function test_the_empty_state_invites_a_first_upload(): void
    {
        $res = $this->visit();

        $res->assertOk();
        $res->assertSee('You have not submitted a bluebook yet', false);
        $res->assertSee('Upload your first bluebook', false);
        // The tally is about submissions, so with none it has nothing to say.
        $res->assertDontSee('hero-tally', false);
    }
}
