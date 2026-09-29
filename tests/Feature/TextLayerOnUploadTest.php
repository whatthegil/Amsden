<?php

namespace Tests\Feature;

use App\Jobs\ProcessBluebookOcr;
use App\Models\Bluebook;
use App\Services\Pdf\PdfWatermarker;
use App\Services\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A PDF with its text in it is read at upload, so it is never "pending";
 * only a scan waits for OCR.
 */
class TextLayerOnUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake(Store::bluebookDisk());

        if (PdfWatermarker::mutool() === null) {
            $this->markTestSkipped('No MuPDF on this host to read or cut PDFs with.');
        }
    }

    private function paper(string $path): Bluebook
    {
        return Bluebook::create([
            'title' => 'T', 'authors' => ['A, B'], 'year' => 2025, 'department' => 'CCS', 'program' => 'P',
            'keywords' => [], 'abstract' => 'A.', 'adviser' => '', 'status' => 'Pending',
            'uploaded_by' => 's@my.cspc.edu.ph', 'uploaded_by_name' => 'S T', 'date_added' => '2025-01-01',
            'file_path' => $path,
        ]);
    }

    /** Five pages of a real thesis, exported with its text. */
    private function thesisExtract(): string
    {
        $src = collect(glob(base_path('Bluebooks/*/*.pdf')))->sortBy(fn($f) => filesize($f))->first();
        if (!$src) {
            $this->markTestSkipped('No bundled bluebook to read.');
        }
        $cut = storage_path('app/text-layer-test.pdf');
        (new \Symfony\Component\Process\Process([PdfWatermarker::mutool(), 'clean', $src, $cut, '5-9']))->mustRun();
        return $cut;
    }

    /** A page with no text on it at all, as a scan looks to a text reader. */
    private function scan(): string
    {
        $pdf = "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
             . "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
             . "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >> endobj\n"
             . "trailer << /Root 1 0 R >>\n%%EOF\n";
        $path = storage_path('app/scan-test.pdf');
        file_put_contents($path, $pdf);
        return $path;
    }

    public function test_a_pdf_with_text_is_read_at_once_and_never_pending(): void
    {
        $local = $this->thesisExtract();
        $b = $this->paper('bluebooks/t.pdf');

        ProcessBluebookOcr::readNowOrQueue($b->id, $local);
        @unlink($local);

        $b->refresh();
        $this->assertSame('completed', $b->ocr_status);
        $this->assertSame('text layer', $b->ocr_engine);
        $this->assertGreaterThan(1500, strlen($b->ocr_text));
        Queue::assertNotPushed(ProcessBluebookOcr::class);
    }

    public function test_a_scan_goes_to_ocr(): void
    {
        $local = $this->scan();
        $b = $this->paper('bluebooks/s.pdf');

        ProcessBluebookOcr::readNowOrQueue($b->id, $local);
        @unlink($local);

        $this->assertSame('pending', $b->fresh()->ocr_status);
        Queue::assertPushed(ProcessBluebookOcr::class);
    }

    public function test_the_stored_file_is_read_when_no_local_copy_is_given(): void
    {
        $local = $this->thesisExtract();
        Storage::disk(Store::bluebookDisk())->put('bluebooks/t.pdf', file_get_contents($local));
        @unlink($local);
        $b = $this->paper('bluebooks/t.pdf');

        ProcessBluebookOcr::readNowOrQueue($b->id);

        $this->assertSame('completed', $b->fresh()->ocr_status);
    }

    public function test_it_can_be_switched_off(): void
    {
        config(['ocr.text_layer' => false]);
        $local = $this->thesisExtract();
        $b = $this->paper('bluebooks/t.pdf');

        ProcessBluebookOcr::readNowOrQueue($b->id, $local);
        @unlink($local);

        $this->assertSame('pending', $b->fresh()->ocr_status);
        Queue::assertPushed(ProcessBluebookOcr::class);
    }
}
