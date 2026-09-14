<?php

namespace Tests\Feature;

use App\Services\Pdf\PdfWatermarker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The mark written into the PDF itself.
 *
 * The viewer draws the reader's identity into every page it renders, which
 * survives a screenshot but does nothing for the file: anyone fetching the
 * document route got the stored PDF unmarked. Two marks are written into the
 * file instead - where it came from, once at upload, and who asked for it, on
 * each read.
 */
class PdfWatermarkTest extends TestCase
{
    use RefreshDatabase;

    private function realPdf(): string
    {
        // A small valid PDF is enough: what is being checked is that the tool
        // runs, rewrites the file, and leaves something a PDF reader accepts.
        $path = storage_path('app/watermark-tmp/test-source.pdf');

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << >> >>\nendobj\n",
            "4 0 obj\n<< /Length 44 >>\nstream\nBT /F1 12 Tf 72 700 Td (Original text) Tj ET\nendstream\nendobj\n",
        ];

        $pdf     = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $i => $object) {
            $offsets[$i + 1] = strlen($pdf);
            $pdf .= $object;
        }

        $start = strlen($pdf);
        $pdf  .= "xref\n0 5\n0000000000 65535 f \n";

        for ($i = 1; $i <= 4; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n{$start}\n%%EOF\n";

        file_put_contents($path, $pdf);

        return $path;
    }

    // ── Taking the marks back out of extracted text ──────────────────────────

    /**
     * The mark is real text in the content stream, so it comes back out with
     * everything else - measured at six times a page, which on a 180-page
     * document is around a thousand injected phrases.
     *
     * Left in, it fills the search index with a phrase every document contains
     * and inflates the similarity checker, which is built to read exactly that
     * kind of repeated identical text as copying.
     */
    public function test_the_marks_are_taken_back_out_of_extracted_text(): void
    {
        $extracted = <<<TEXT
        CSPC ARCHIVE · NOT FOR REDISTRIBUTION
        This study developed the Intelligent Library Management System to improve
        Leveraging Data Analytics for Intelligent Libra… · 2025
        library operations at the Camarines Sur Polytechnic Colleges Library by
        tester@my.cspc.edu.ph · 2026-09-14 21:12:49 · CSPC ARCHIVE
        integrating book circulation, reporting, and overdue risk assessment.
        CSPC ARCHIVE · NOT FOR REDISTRIBUTION
        TEXT;

        $clean = PdfWatermarker::stripMarks($extracted, [
            'title' => 'Leveraging Data Analytics for Intelligent Library Management',
            'year'  => 2025,
        ]);

        $this->assertStringNotContainsString('NOT FOR REDISTRIBUTION', $clean);
        $this->assertStringNotContainsString('CSPC ARCHIVE', $clean);
        $this->assertStringNotContainsString('tester@my.cspc.edu.ph', $clean);

        // The document's own words have to survive it.
        $this->assertStringContainsString('Intelligent Library Management System', $clean);
        $this->assertStringContainsString('overdue risk assessment', $clean);
        $this->assertStringContainsString('Camarines Sur Polytechnic Colleges', $clean);
    }

    /**
     * The provenance line is identical in every document in the archive, so
     * leaving it in would give any two of them a thousand shared phrases - the
     * one failure here that would actively mislead rather than merely clutter.
     */
    public function test_two_stamped_documents_do_not_look_alike_because_of_the_mark(): void
    {
        $mark = "CSPC ARCHIVE · NOT FOR REDISTRIBUTION\n";

        $one = PdfWatermarker::stripMarks(str_repeat($mark, 40) . 'A study of coastal erosion in Bicol.');
        $two = PdfWatermarker::stripMarks(str_repeat($mark, 40) . 'An inventory management system for retail.');

        foreach ([$one, $two] as $text) {
            $this->assertStringNotContainsString('CSPC ARCHIVE', $text);
            $this->assertStringNotContainsString('REDISTRIBUTION', $text);
        }

        // Nothing in common left but the words the documents actually differ by.
        $shared = array_intersect(
            preg_split('/\W+/', strtolower($one), -1, PREG_SPLIT_NO_EMPTY),
            preg_split('/\W+/', strtolower($two), -1, PREG_SPLIT_NO_EMPTY)
        );

        $this->assertLessThanOrEqual(3, count($shared), 'Stripped text should share almost nothing.');
    }

    /** Empty and mark-free text must come through untouched. */
    public function test_text_without_marks_is_left_alone(): void
    {
        $this->assertSame('', PdfWatermarker::stripMarks(''));

        $plain = 'A study of coastal erosion. Contact maria@example.com for the dataset.';
        $this->assertSame($plain, PdfWatermarker::stripMarks($plain));
    }

    // ── What the marks say ───────────────────────────────────────────────────

    /** A 200-character title tiled across every page is not a watermark. */
    public function test_a_long_title_is_trimmed_for_the_mark(): void
    {
        [$line1, $line2] = PdfWatermarker::provenanceLines([
            'title' => str_repeat('Very Long Title ', 20),
            'year'  => 2025,
        ]);

        $this->assertStringContainsString('CSPC ARCHIVE', $line1);
        $this->assertStringContainsString('NOT FOR REDISTRIBUTION', $line1);
        $this->assertLessThan(70, mb_strlen($line2));
        $this->assertStringContainsString('2025', $line2);
    }

    /** A reader with no session still has to leave something in the mark. */
    public function test_the_viewer_mark_names_someone_even_when_it_cannot(): void
    {
        [$named] = PdfWatermarker::viewerLines(['email' => 'tester@my.cspc.edu.ph']);
        $this->assertSame('tester@my.cspc.edu.ph', $named);

        [$anon] = PdfWatermarker::viewerLines(null);
        $this->assertNotSame('', trim($anon));
    }

    // ── Actually writing it into a file ──────────────────────────────────────

    /**
     * Needs a MuPDF binary. Every document in this archive is PDF 1.7 with
     * object streams, which is why no PHP library does this.
     */
    public function test_a_document_is_rewritten_with_the_mark_in_it(): void
    {
        if (!PdfWatermarker::available()) {
            $this->markTestSkipped('No MuPDF binary on this host.');
        }

        $src  = $this->realPdf();
        $dest = storage_path('app/watermark-tmp/test-stamped.pdf');

        $this->assertTrue(PdfWatermarker::stampFile($src, $dest, 'CSPC ARCHIVE', 'a mark'));
        $this->assertFileExists($dest);

        $stamped = file_get_contents($dest);
        $this->assertStringStartsWith('%PDF', $stamped);
        $this->assertGreaterThan(filesize($src), strlen($stamped));

        @unlink($src);
        @unlink($dest);
    }

    /**
     * A title carrying a bracket would close the PDF string literal early and
     * corrupt the content stream of every page it was written into.
     */
    public function test_awkward_text_does_not_corrupt_the_file(): void
    {
        if (!PdfWatermarker::available()) {
            $this->markTestSkipped('No MuPDF binary on this host.');
        }

        $src  = $this->realPdf();
        $dest = storage_path('app/watermark-tmp/test-awkward.pdf');

        $this->assertTrue(PdfWatermarker::stampFile(
            $src, $dest, 'Title with ) and ( and \\ in it', 'Cariño · 2025'
        ));
        $this->assertStringStartsWith('%PDF', (string) file_get_contents($dest));

        @unlink($src);
        @unlink($dest);
    }

    /** A failure has to leave nothing behind: these are whole documents. */
    public function test_a_failed_stamp_leaves_no_file_and_no_temp(): void
    {
        $before = glob(storage_path('app/watermark-tmp/wm_*.pdf')) ?: [];

        $notAPdf = storage_path('app/watermark-tmp/not-a.pdf');
        file_put_contents($notAPdf, 'this is not a pdf');

        $dest = storage_path('app/watermark-tmp/test-should-not-exist.pdf');

        $this->assertFalse(PdfWatermarker::stampFile($notAPdf, $dest, 'x', 'y'));
        $this->assertFileDoesNotExist($dest);

        $after = glob(storage_path('app/watermark-tmp/wm_*.pdf')) ?: [];
        $this->assertSame(count($before), count($after), 'A failed stamp must not leak temp files.');

        @unlink($notAPdf);
    }

    /** Whole documents cannot be left lying around when a request dies. */
    public function test_abandoned_temp_files_are_prunable(): void
    {
        $stale = storage_path('app/watermark-tmp/wm_' . bin2hex(random_bytes(8)) . '.pdf');

        if (!is_dir(dirname($stale))) {
            mkdir(dirname($stale), 0755, true);
        }

        file_put_contents($stale, 'leftover');
        touch($stale, time() - 7200);

        $this->assertGreaterThanOrEqual(1, PdfWatermarker::pruneTemp(60));
        $this->assertFileDoesNotExist($stale);
    }
}
