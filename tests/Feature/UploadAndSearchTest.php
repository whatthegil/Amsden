<?php

namespace Tests\Feature;

use App\Jobs\ProcessBluebookOcr;
use App\Models\Bluebook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ThesisPdf;
use Tests\TestCase;

/**
 * Module 2 — Upload Document (PDF only) and Search Document (keyword -> results).
 */
class UploadAndSearchTest extends TestCase
{
    use RefreshDatabase;

    /** Session payload the app expects for an upload-enabled student. */
    private function uploader(): array
    {
        return [
            'id'        => 1,
            'name'      => 'Test Student',
            'email'     => 'tester@my.cspc.edu.ph',
            'role'      => 'Student',
            'canUpload' => true,
        ];
    }

    private function validUploadFields(): array
    {
        // The year is all the form asks; the rest is read from the PDF.
        return ['year' => 2025];
    }

    /** Minimal but structurally real PDF bytes (starts with the %PDF- signature). */
    private function fakePdf(string $name = 'capstone.pdf'): UploadedFile
    {
        $content = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            . "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]>>endobj\n"
            . "trailer<</Size 4/Root 1 0 R>>\n%%EOF";

        return UploadedFile::fake()->createWithContent($name, $content);
    }

    // ─── Upload: PDF only ──────────────────────────────────────────────────────

    public function test_upload_accepts_a_valid_pdf(): void
    {
        Storage::fake('local');
        Queue::fake();

        $response = $this->withSession(['user' => $this->uploader()])
            ->post('/student/upload', $this->validUploadFields() + [
                'file' => UploadedFile::fake()->createWithContent('capstone.pdf', ThesisPdf::bytes()),
            ]);

        $response->assertOk();
        $response->assertSee('Bluebook uploaded successfully', false);
        $response->assertSee('Details read from your PDF', false);

        $this->assertDatabaseHas('bluebooks', [
            'title'       => ThesisPdf::TITLE,
            'department'  => 'CCS',
            'program'     => 'Bachelor of Science in Information Technology',
            'adviser'     => ThesisPdf::ADVISER,
            'abstract'    => ThesisPdf::ABSTRACT,
            'pages'       => 3,
            'year'        => 2025,
            'status'      => 'Pending',
            'uploaded_by' => 'tester@my.cspc.edu.ph',
        ]);

        $book = Bluebook::first();
        $this->assertSame(ThesisPdf::AUTHORS, $book->authors);
        $this->assertSame(ThesisPdf::KEYWORDS, $book->keywords);
        Storage::disk('local')->assertExists($book->file_path);
        Queue::assertPushed(ProcessBluebookOcr::class);
    }

    public function test_the_form_asks_only_for_the_file_and_the_year(): void
    {
        $res = $this->withSession(['user' => $this->uploader()])->get('/student/upload');

        $res->assertOk();
        $res->assertSee('name="file"', false);
        $res->assertSee('name="year"', false);
        foreach (['title', 'authors', 'adviser', 'keywords', 'abstract', 'department', 'program', 'pages'] as $field) {
            $res->assertDontSee('name="' . $field . '"', false);
        }
    }

    public function test_details_posted_with_the_form_are_ignored(): void
    {
        Storage::fake('local');
        Queue::fake();

        $this->withSession(['user' => $this->uploader()])
            ->post('/student/upload', $this->validUploadFields() + [
                'title' => 'Typed Title', 'adviser' => 'Typed, Adviser', 'pages' => 999,
                'file'  => UploadedFile::fake()->createWithContent('capstone.pdf', ThesisPdf::bytes()),
            ])->assertOk();

        $this->assertDatabaseHas('bluebooks', ['title' => ThesisPdf::TITLE, 'adviser' => ThesisPdf::ADVISER, 'pages' => 3]);
    }

    public function test_a_scans_details_are_filled_in_once_its_text_is_read(): void
    {
        Storage::fake('local');
        Queue::fake();

        // No text to read: filed under its file name, everything else blank.
        $this->withSession(['user' => $this->uploader()])
            ->post('/student/upload', $this->validUploadFields() + ['file' => $this->fakePdf('my_capstone.pdf')])
            ->assertOk()
            ->assertSee('scan', false);

        $book = Bluebook::first();
        $this->assertSame('my capstone', $book->title);
        $this->assertSame('', $book->adviser);

        // What OCR hands back for it later.
        $book->forceFill(['ocr_text' => ThesisPdf::text(), 'adviser' => ''])->save();
        \App\Services\BluebookDetails::fillMissing($book);

        $book->refresh();
        $this->assertSame(ThesisPdf::TITLE, $book->title);
        $this->assertSame(ThesisPdf::AUTHORS, $book->authors);
        $this->assertSame(ThesisPdf::ADVISER, $book->adviser);
        $this->assertSame('Bachelor of Science in Information Technology', $book->program);
        // The file's own count, set on upload, is kept over the abstract page's.
        $this->assertSame(1, $book->pages);
    }

    public function test_reading_text_later_never_overwrites_a_reviewers_details(): void
    {
        $book = Bluebook::create([
            'title' => 'Corrected By The Library', 'authors' => ['Cruz, J.'], 'year' => 2025, 'department' => 'CEA',
            'program' => 'Bachelor of Science in Civil Engineering', 'keywords' => ['k'], 'abstract' => 'Set by a reviewer.',
            'adviser' => 'Reyes, A.', 'status' => 'Pending', 'uploaded_by' => 'tester@my.cspc.edu.ph',
            'uploaded_by_name' => 'T', 'pages' => 10, 'views' => 0, 'date_added' => '2025-01-01',
            'file_original_name' => 'x.pdf', 'ocr_text' => ThesisPdf::text(),
        ]);

        \App\Services\BluebookDetails::fillMissing($book);

        $book->refresh();
        $this->assertSame('Corrected By The Library', $book->title);
        $this->assertSame('CEA', $book->department);
        $this->assertSame('Reyes, A.', $book->adviser);
    }

    public function test_upload_rejects_a_non_pdf_file(): void
    {
        Storage::fake('local');
        Queue::fake();

        $response = $this->withSession(['user' => $this->uploader()])
            ->post('/student/upload', $this->validUploadFields() + [
                'file' => UploadedFile::fake()->create('notes.txt', 20, 'text/plain'),
            ]);

        $response->assertOk();
        $response->assertSee('type: pdf', false); // "...must be a file of type: pdf."

        $this->assertDatabaseCount('bluebooks', 0);
        Queue::assertNotPushed(ProcessBluebookOcr::class);
    }

    public function test_upload_rejects_a_non_pdf_disguised_with_a_pdf_extension(): void
    {
        Storage::fake('local');
        Queue::fake();

        // Right name, wrong bytes — no %PDF- signature.
        $disguised = UploadedFile::fake()->createWithContent(
            'capstone.pdf',
            "PK\x03\x04 this is really a zip/docx, not a pdf"
        );

        $response = $this->withSession(['user' => $this->uploader()])
            ->post('/student/upload', $this->validUploadFields() + ['file' => $disguised]);

        $response->assertOk();
        $response->assertSee('valid PDF document', false);
        $this->assertDatabaseCount('bluebooks', 0);
        Queue::assertNotPushed(ProcessBluebookOcr::class);
    }

    public function test_upload_rejects_a_pdf_that_exceeds_the_size_limit(): void
    {
        Storage::fake('local');
        Queue::fake();

        $response = $this->withSession(['user' => $this->uploader()])
            ->post('/student/upload', $this->validUploadFields() + [
                'file' => $this->fakePdf('huge.pdf')->size(40000), // 40 MB > 35 MB cap
            ]);

        $response->assertOk();
        $response->assertSee('greater than', false);
        $this->assertDatabaseCount('bluebooks', 0);
    }

    public function test_upload_is_blocked_for_students_without_permission(): void
    {
        Storage::fake('local');
        Queue::fake();

        $response = $this->withSession(['user' => ['role' => 'Student', 'name' => 'X', 'email' => 'x@my.cspc.edu.ph', 'canUpload' => false]])
            ->post('/student/upload', $this->validUploadFields() + [
                'file' => UploadedFile::fake()->create('capstone.pdf', 200, 'application/pdf'),
            ]);

        $response->assertOk();
        $response->assertSee('upload', false);
        $this->assertDatabaseCount('bluebooks', 0);
    }

    // ─── Search: existing keyword -> relevant results ─────────────────────────

    private function seedApproved(): void
    {
        Bluebook::create([
            'title' => 'Implementation of an Automated Student Attendance System Using RFID Technology',
            'authors' => ['Dela Cruz, Maria', 'Reyes, John'],
            'year' => 2024, 'department' => 'CCS', 'program' => 'BSIT',
            'keywords' => ['RFID', 'Attendance System', 'Automation'],
            'abstract' => 'An automated attendance monitoring system using RFID cards.',
            'adviser' => 'Prof. A', 'status' => 'Approved',
            'uploaded_by' => 's@my.cspc.edu.ph', 'uploaded_by_name' => 'S',
            'pages' => 70, 'views' => 0, 'date_added' => '2024-03-20',
        ]);

        Bluebook::create([
            'title' => 'A Mobile Application for Tricycle Fare Calculation',
            'authors' => ['Torres, Ana'],
            'year' => 2023, 'department' => 'CCS', 'program' => 'BSIT',
            'keywords' => ['mobile', 'fare', 'GPS'],
            'abstract' => 'A mobile app that computes tricycle fares from GPS distance.',
            'adviser' => 'Prof. B', 'status' => 'Approved',
            'uploaded_by' => 's@my.cspc.edu.ph', 'uploaded_by_name' => 'S',
            'pages' => 60, 'views' => 0, 'date_added' => '2023-05-10',
        ]);

        // Must never appear in student search results.
        Bluebook::create([
            'title' => 'Pending RFID Warehouse Tracker',
            'authors' => ['Cruz, B'],
            'year' => 2025, 'department' => 'CCS', 'program' => 'BSIT',
            'keywords' => ['RFID', 'warehouse'],
            'abstract' => 'Not yet approved.',
            'adviser' => 'Prof. C', 'status' => 'Pending',
            'uploaded_by' => 's@my.cspc.edu.ph', 'uploaded_by_name' => 'S',
            'pages' => 40, 'views' => 0, 'date_added' => '2025-01-01',
        ]);
    }

    public function test_search_with_existing_keyword_returns_relevant_results(): void
    {
        $this->seedApproved();

        $response = $this->withSession(['user' => $this->uploader()])
            ->get('/student/bluebooks?search=RFID');

        $response->assertOk();
        $response->assertSee('Automated Student Attendance System', false);
        $response->assertDontSee('Tricycle Fare Calculation', false);
        $response->assertDontSee('Pending RFID Warehouse Tracker', false); // non-approved excluded
    }

    public function test_search_with_no_match_shows_empty_state(): void
    {
        $this->seedApproved();

        $response = $this->withSession(['user' => $this->uploader()])
            ->get('/student/bluebooks?search=zzzznonexistentterm');

        $response->assertOk();
        // A search that matched nothing is a filter problem, so the empty state
        // says so and offers to clear them - rather than the flat "No bluebooks
        // found" it used to show whatever the reason was.
        $response->assertSee('No papers match these filters', false);
        $response->assertSee('Clear all filters', false);
    }

    public function test_search_ranks_a_title_match_above_a_faint_match(): void
    {
        $this->seedApproved();

        $response = $this->withSession(['user' => $this->uploader()])
            ->get('/student/bluebooks?search=attendance system');

        $response->assertOk();
        $content = $response->getContent();
        $attendancePos = strpos($content, 'Automated Student Attendance System');
        $this->assertNotFalse($attendancePos);
    }
}
