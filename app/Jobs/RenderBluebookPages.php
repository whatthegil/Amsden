<?php

namespace App\Jobs;

use App\Models\Bluebook;
use App\Services\Pdf\PageImages;
use App\Services\Pdf\PdfWatermarker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Renders every page of a bluebook to a JPEG on the bluebook disk, so readers
 * can be sent watermarked pages instead of the PDF (see PageImages).
 *
 * Needs MuPDF, so it runs on the worker, not on Laravel Cloud - which is the
 * point: the host that serves pages only has to draw a mark onto an image,
 * and GD can do that anywhere. Until a bluebook's pages exist, readers get the
 * PDF viewer as before.
 */
class RenderBluebookPages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 900;

    public function __construct(private int $bluebookId)
    {
    }

    public function handle(): void
    {
        $bluebook = Bluebook::find($this->bluebookId);
        if (!$bluebook || !$bluebook->file_path) {
            return;
        }

        $mutool = PdfWatermarker::mutool();
        if ($mutool === null) {
            // Said plainly in failed_jobs rather than retried forever: this host
            // cannot render, and only a worker that has MuPDF can.
            throw new \RuntimeException('Page images need MuPDF (mutool), which this host does not have. Run the queue worker on a machine that does.');
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
            (new Process([$mutool, 'draw', '-q', '-r', (string) $dpi, '-o', $work . '/%d.png', $pdf]))
                ->setTimeout($this->timeout - 60)
                ->mustRun();

            $count = 0;
            for ($n = 1; is_file($png = "{$work}/{$n}.png"); $n++) {
                $img = imagecreatefrompng($png);
                if (!$img) {
                    throw new \RuntimeException("Page {$n} did not render.");
                }
                ob_start();
                imagejpeg($img, null, (int) config('watermark.page_quality', 80));
                $disk->put(PageImages::path($bluebook->id, $n), (string) ob_get_clean());
                imagedestroy($img);
                @unlink($png);
                $count = $n;
            }

            if ($count === 0) {
                throw new \RuntimeException('MuPDF rendered no pages.');
            }

            // A document that got shorter leaves its old tail behind; drop it.
            for ($n = $count + 1; $disk->exists(PageImages::path($bluebook->id, $n)); $n++) {
                $disk->delete(PageImages::path($bluebook->id, $n));
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
}
