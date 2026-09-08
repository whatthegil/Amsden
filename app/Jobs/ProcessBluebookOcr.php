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

    public function handle(): void
    {
        $bluebook = Bluebook::find($this->bluebookId);
        if (!$bluebook || !$bluebook->file_path) {
            return;
        }

        // Sweep work dirs abandoned by previously killed runs; their own
        // finally-block cleanup never got to run.
        OcrService::pruneOrphanedWorkDirs();

        $bluebook->ocr_status = 'processing';
        $bluebook->save();

        // OcrService shells out to mutool/Ghostscript/Tesseract, so it needs a
        // real path on disk. Only local-style disks can supply one; object
        // storage cannot, so the PDF is streamed to a temp file for the run and
        // removed afterwards.
        $disk    = Storage::disk(Store::bluebookDisk());
        $tempPdf = null;

        try {
            try {
                $absolutePath = $disk->path($bluebook->file_path);
            } catch (\RuntimeException) {
                $tempPdf = tempnam(sys_get_temp_dir(), 'bluebook_') . '.pdf';
                file_put_contents($tempPdf, $disk->get($bluebook->file_path));
                $absolutePath = $tempPdf;
            }

            $result = OcrService::extractText($absolutePath);
        } finally {
            if ($tempPdf !== null && is_file($tempPdf)) {
                @unlink($tempPdf);
            }
        }

        $bluebook->ocr_text = $result['text'];
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
