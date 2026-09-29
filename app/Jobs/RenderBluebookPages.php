<?php

namespace App\Jobs;

use App\Models\Bluebook;
use App\Services\Pdf\PageImages;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\Ocr\Rasterizers\MuPdfRasterizer;
use App\Services\Ocr\Rasterizers\PdfRasterizer;
use App\Services\Ocr\Rasterizers\PopplerRasterizer;
use Illuminate\Support\Facades\File;

/**
 * Renders every page of a bluebook to an image on the bluebook disk, so
 * readers can be sent watermarked pages instead of the PDF (see PageImages).
 *
 * Drawn with MuPDF where there is one, and with Poppler's pdftoppm otherwise -
 * which Laravel Cloud has, so production can draw its own pages without the
 * worker machine. Not Ghostscript, though it is there too: that build is old
 * enough to have had security fixes since, and these are uploaded files.
 * Until a bluebook's pages exist, readers get the PDF viewer as before.
 */
class RenderBluebookPages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 900;

    /**
     * Its own queue, apart from OCR. A worker on Laravel Cloud can render
     * pages (it has Poppler) but cannot read text (no Tesseract); on the shared
     * queue it would take OCR jobs too and fail every one of them.
     */
    public const QUEUE = 'pages';

    public function __construct(private int $bluebookId)
    {
        $this->onQueue(self::QUEUE);
    }

    public function handle(): void
    {
        $bluebook = Bluebook::find($this->bluebookId);
        if (!$bluebook || !$bluebook->file_path) {
            return;
        }

        $rasterizer = self::rasterizer();
        if ($rasterizer === null) {
            // Said plainly in failed_jobs rather than retried forever: this host
            // cannot render, and only a worker that can should take the job.
            throw new \RuntimeException('Page images need MuPDF (mutool) or Poppler (pdftoppm), and this host has neither. Run the queue worker on a machine that does.');
        }

        $source = $bluebook->file_path;
        $disk   = PageImages::disk();
        $work   = storage_path('app/page-render/' . $bluebook->id . '-' . bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($work);

        try {
            $pdf = $work . '/source.pdf';
            $in  = $disk->readStream($source);
            $out = fopen($pdf, 'wb');
            stream_copy_to_stream($in, $out);
            fclose($out);
            if (is_resource($in)) {
                fclose($in);
            }

            // Sharp at the viewer's widest (fit-width on a laptop, at 1.5x
            // density), without making each page heavier than it need be.
            $dpi = (int) config('watermark.page_dpi', 150);

            // The rasterizers take their time limit from OCR's, which is set
            // for a few pages at a time; a whole thesis needs this job's.
            $ocrTimeout = config('ocr.timeout');
            config(['ocr.timeout' => $this->timeout - 60]);
            try {
                $pngs = $rasterizer->rasterize($pdf, $work, $dpi);
            } finally {
                config(['ocr.timeout' => $ocrTimeout]);
            }

            $count = 0;
            foreach (array_values($pngs) as $i => $png) {
                $n   = $i + 1;
                $img = imagecreatefrompng($png);
                if (!$img) {
                    throw new \RuntimeException("Page {$n} did not render.");
                }
                ob_start();
                imagewebp($img, null, (int) config('watermark.page_store_quality', 80));
                $disk->put(PageImages::path($bluebook->id, $n), (string) ob_get_clean());
                imagedestroy($img);
                @unlink($png);
                $count = $n;
            }

            if ($count === 0) {
                throw new \RuntimeException($rasterizer->name() . ' rendered no pages.');
            }

            // Anything else in the folder is left over: the tail of a document
            // that got shorter, or pages drawn before they were kept as WebP.
            $keep = array_map(fn($n) => PageImages::path($bluebook->id, $n), range(1, $count));
            $stale = array_diff($disk->files(PageImages::directory($bluebook->id)), $keep);
            if ($stale) {
                $disk->delete(array_values($stale));
            }

            // Only if the file is still the one that was drawn: a re-upload
            // while this ran has queued its own render, which will mark it.
            Bluebook::where('id', $bluebook->id)->where('file_path', $source)->update([
                'page_images_count'  => $count,
                'page_images_source' => $source,
                'page_images_at'     => now(),
            ]);
        } finally {
            File::deleteDirectory($work);
        }
    }

    /** MuPDF if this host has it, else Poppler; null when it has neither. */
    public static function rasterizer(): ?PdfRasterizer
    {
        foreach ([new MuPdfRasterizer(), new PopplerRasterizer()] as $candidate) {
            if ($candidate->isAvailable()) {
                return $candidate;
            }
        }

        return null;
    }
}
