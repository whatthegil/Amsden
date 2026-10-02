<?php

namespace App\Jobs;

use App\Models\Bluebook;
use App\Services\OcrService;
use App\Services\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessBluebookOcr implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /**
     * A 60-page run measures ~205s on a dev machine; a slower host can easily
     * double that, and blowing this timeout is what leaves rows stranded in
     * 'processing'. Config-driven so a slow deploy can raise it without a
     * code change — keep it below ocr.stuck_after.
     */
    public int $timeout = 600;

    public function __construct(private int $bluebookId)
    {
        $this->timeout = (int) config('ocr.job_timeout', 600);
    }

    /**
     * Read a newly stored file's text now if it has a text layer, and queue
     * OCR only if it does not.
     *
     * Almost every thesis is a PDF exported from Word, with its text already
     * in it. That text can be read in a second or two with no OCR at all - on
     * Laravel Cloud too, which has pdftotext but no Tesseract - so such an
     * upload is searchable at once instead of "pending" until a worker with
     * Tesseract comes along. Only a scanned document, pictures of pages with
     * no text in them, goes to the OCR queue.
     *
     * $localPdf is the uploaded file while it is still on this machine, which
     * saves fetching it back from storage.
     */
    public static function readNowOrQueue(int $bluebookId, ?string $localPdf = null): bool
    {
        try {
            $bluebook = Bluebook::find($bluebookId);
            if ($bluebook && self::readTextLayer($bluebook, $localPdf)) {
                return true;
            }
        } catch (Throwable $e) {
            report($e);         // the queued OCR below is the fallback
        }

        self::dispatch($bluebookId);

        return false;
    }

    /**
     * Save the PDF's own text as the bluebook's text, if there is enough of it
     * to be a real text layer rather than a scan with a stray caption. True
     * when it did.
     */
    public static function readTextLayer(Bluebook $bluebook, ?string $localPdf = null): bool
    {
        if (!$bluebook->file_path || !config('ocr.text_layer', true)) {
            return false;
        }

        $temp = null;
        try {
            $path = $localPdf && is_file($localPdf) ? $localPdf : null;
            if ($path === null) {
                [$path, $temp] = self::localCopy($bluebook->file_path);
            }

            $text      = \App\Services\PdfTextExtractor::extract($path, (int) config('ocr.max_text_length', 500000));
            $encrypted = \App\Services\BluebookEvaluation::isEncrypted($path);
        } finally {
            if ($temp !== null) {
                @unlink($temp);
            }
        }

        // Letters, not bytes: a scan can still carry a line of page numbers.
        $letters = preg_match_all('/\p{L}/u', $text);
        if ($letters < (int) config('ocr.text_layer_min_letters', 1500)) {
            // Still worth knowing for the evaluation checklist, which cannot
            // tell encryption from the text OCR will read.
            $bluebook->forceFill(['pdf_encrypted' => $encrypted])->save();
            return false;
        }

        $bluebook->forceFill([
            'ocr_text'         => \App\Services\Pdf\PdfWatermarker::stripMarks($text, ['title' => $bluebook->title, 'year' => $bluebook->year]),
            'ocr_status'       => 'completed',
            'ocr_error'        => null,
            'ocr_engine'       => 'text layer',
            'ocr_rasterizer'   => null,
            'ocr_processed_at' => now(),
            'pdf_encrypted'    => $encrypted,
        ])->save();

        return true;
    }

    /**
     * A stored document as a file on this machine: [path, temp or null].
     *
     * On a local disk that is the file itself. On object storage it has to be
     * fetched - and that cannot be told from path() failing, because on an S3
     * disk it does not fail: it returns the object's key, a path to nothing on
     * this machine, and every tool handed it quietly read an empty document.
     * So the file is checked for, and downloaded when it is not there. The
     * caller deletes the temp copy.
     *
     * @return array{0: string, 1: ?string}
     */
    public static function localCopy(string $storedPath): array
    {
        $disk = Storage::disk(Store::bluebookDisk());

        try {
            $local = $disk->path($storedPath);
            if (is_file($local)) {
                return [$local, null];
            }
        } catch (\RuntimeException) {
            // a disk with no local path at all: fetch it below
        }

        $temp = tempnam(sys_get_temp_dir(), 'bluebook_') . '.pdf';
        $in   = $disk->readStream($storedPath);
        if (!is_resource($in)) {
            throw new \RuntimeException("Could not read {$storedPath} from storage.");
        }
        $out = fopen($temp, 'wb');
        stream_copy_to_stream($in, $out);
        fclose($out);
        fclose($in);

        return [$temp, $temp];
    }

    public function handle(): void
    {
        $bluebook = Bluebook::find($this->bluebookId);
        if (!$bluebook || !$bluebook->file_path) {
            return;
        }

        // Sweep work dirs abandoned by previously killed runs; their own
        // finally-block cleanup never got to run.
        OcrService::pruneOrphanedWorkDirs();

        // A text layer, where there is one, is the whole thesis read exactly -
        // OCR is capped at the first pages and can misread. So it is tried
        // first here as well, which is what reprocessing now gets too.
        try {
            if (self::readTextLayer($bluebook)) {
                return;
            }
        } catch (Throwable $e) {
            report($e);
        }

        $bluebook->ocr_status = 'processing';
        $bluebook->save();

        // OcrService shells out to mutool/Ghostscript/Tesseract, so it needs a
        // real file on this machine.
        $tempPdf = null;

        try {
            [$absolutePath, $tempPdf] = self::localCopy($bluebook->file_path);

            $bluebook->pdf_encrypted = \App\Services\BluebookEvaluation::isEncrypted($absolutePath);
            $result = OcrService::extractText($absolutePath);
        } finally {
            if ($tempPdf !== null && is_file($tempPdf)) {
                @unlink($tempPdf);
            }
        }

        // The archive's own mark is real text in the file, so it comes back out
        // of it - six times a page. Left in the index it is a phrase every
        // document shares, which is both useless to search and actively
        // misleading to the similarity checker.
        $bluebook->ocr_text = \App\Services\Pdf\PdfWatermarker::stripMarks(
            $result['text'],
            ['title' => $bluebook->title, 'year' => $bluebook->year]
        );
        $bluebook->ocr_status = 'completed';
        $bluebook->ocr_error = null;
        $bluebook->ocr_engine = $result['engine'];
        $bluebook->ocr_rasterizer = $result['rasterizer'];
        $bluebook->ocr_processed_at = now();
        $bluebook->save();
    }

    public function failed(Throwable $exception): void
    {
        Bluebook::where('id', $this->bluebookId)->update([
            'ocr_status'        => 'failed',
            'ocr_error'         => substr($exception->getMessage(), 0, 2000),
            'ocr_processed_at'  => now(),
        ]);
    }
}
