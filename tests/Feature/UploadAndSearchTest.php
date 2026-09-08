<?php

namespace Tests\Feature;

use App\Jobs\ProcessBluebookOcr;
use App\Models\Bluebook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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
        return [
            'title'      => 'A Web-Based Inventory System for Campus Laboratories',
            'authors'    => 'Dela Cruz, Juan; Santos, Maria',
            'department' => 'CCS',
            'program'   => 'BSIT',
            'year'       => 2025,
            'adviser'    => 'Prof. Reyes',
            'pages'      => 80,
            'keywords'   => 'inventory, web-based, laboratory',
            'abstract'   => 'This study designed and built a web-based inventory system for campus laboratories.',
        ];
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
                'file' => $this->fakePdf(),
            ]);

        $response->assertOk();
        $response->assertSee('Bluebook uploaded successfully', false);

        $this->assertDatabaseHas('bluebooks', [
            'title'       => 'A Web-Based Inventory System for Campus Laboratories',
            'status'      => 'Pending',
            'uploaded_by' => 'tester@my.cspc.edu.ph',
        ]);

        $book = Bluebook::first();
        Storage::disk('local')->assertExists($book->file_path);
        Queue::assertPushed(ProcessBluebookOcr::class);
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
        $response->assertSee('No bluebooks found', false);
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
