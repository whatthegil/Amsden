<?php

namespace Tests\Feature;

use App\Models\Bluebook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Similarity check — the pre-proposal PDF upload path added alongside the
 * existing "type a title" path.
 */
class SimilarityCheckUploadTest extends TestCase
{
    use RefreshDatabase;

    private function student(): array
    {
        return [
            'id'    => 1,
            'name'  => 'Test Student',
            'email' => 'tester@my.cspc.edu.ph',
            'role'  => 'Student',
        ];
    }

    private function fakePdf(string $name = 'pre-proposal.pdf'): UploadedFile
    {
        $content = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            . "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]>>endobj\n"
            . "trailer<</Size 4/Root 1 0 R>>\n%%EOF";

        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_page_offers_both_the_title_and_upload_modes(): void
    {
        $response = $this->withSession(['user' => $this->student()])
            ->get('/student/similarity-check');

        $response->assertOk();
        $response->assertSee('Enter a title', false);
        $response->assertSee('Upload pre-proposal', false);
    }

    public function test_upload_rejects_a_non_pdf(): void
    {
        $response = $this->withSession(['user' => $this->student()])
            ->post('/student/similarity-check', [
                'file' => UploadedFile::fake()->create('proposal.txt', 20, 'text/plain'),
            ]);

        $response->assertOk();
        $response->assertSee('type: pdf', false);
    }

    public function test_upload_of_a_pdf_with_no_readable_text_shows_a_helpful_error(): void
    {
        // The minimal fake PDF carries no text layer, and no OCR binaries are
        // available in the test environment — the check should fail cleanly
        // with guidance rather than 500.
        $response = $this->withSession(['user' => $this->student()])
            ->post('/student/similarity-check', ['file' => $this->fakePdf()]);

        $response->assertOk();
        $response->assertSee('read any text from that PDF', false);
        $response->assertSee('scanned image without a selectable text layer', false);
    }

    public function test_typed_title_path_still_works(): void
    {
        Bluebook::create([
            'title' => 'An Automated Student Attendance System Using RFID Technology',
            'authors' => ['Dela Cruz, Maria'],
            'year' => 2024, 'department' => 'CCS', 'program' => 'BSIT',
            'keywords' => ['RFID', 'Attendance'],
            'abstract' => 'An automated attendance monitoring system using RFID cards.',
            'adviser' => 'Prof. A', 'status' => 'Approved',
            'uploaded_by' => 's@my.cspc.edu.ph', 'uploaded_by_name' => 'S',
            'pages' => 70, 'views' => 0, 'date_added' => '2024-03-20',
        ]);

        $response = $this->withSession(['user' => $this->student()])
            ->post('/student/similarity-check', [
                'title' => 'Automated Student Attendance System Using RFID',
            ]);

        $response->assertOk();
        $response->assertSee('Automated Student Attendance System', false);
    }
}
