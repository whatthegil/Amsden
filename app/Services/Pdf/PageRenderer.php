<?php

namespace App\Services\Pdf;

use App\Jobs\ProcessBluebookOcr;
use App\Models\Bluebook;
use App\Services\Ocr\BinaryFinder;
use App\Services\Ocr\Rasterizers\LowPriority;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Draws a bluebook's pages a few at a time, for the admin's "Render pages"
 * button.
 *
 * A whole thesis takes minutes, longer than a web request may run, and on
 * Laravel Cloud no worker takes the render queue - so a button that queued the
 * job would appear to do nothing. Instead the admin's page asks for a handful
 * of pages per request and carries on until the document runs out, showing
 * progress as it goes. The PDF is fetched once and kept on this machine for the
 * following requests; the nightly tidy clears any copy a closed tab left.
 *
 * Pages are written as they are drawn, but the bluebook is only switched to
 * them by finish(), once every page exists - readers are never sent a
 * half-rendered document.
 */
class PageRenderer
{
    use LowPriority;

    /** Pages per request: a few seconds of work, well inside a request's limit. */
    public const CHUNK = 8;

    /** Which tool this host would draw with, or null. */
    public static function tool(): ?string
    {
        if (PdfWatermarker::mutool()) {
            return 'mutool';
        }
        return self::pdftoppm() ? 'pdftoppm' : null;
    }

    private static function pdftoppm(): ?string
    {
        return BinaryFinder::find(config('ocr.pdftoppm_path'), ['pdftoppm'], [
            'C:\\poppler*\\Library\\bin\\pdftoppm.exe',
            'C:\\Program Files\\poppler*\\bin\\pdftoppm.exe',
        ]);
    }

    /**
     * Draw pages $first..$first+CHUNK-1 (fewer at the end of the document) and
     * store them. Returns the page numbers stored, in order; an empty list
     * means $first is past the last page.
     *
     * @return int[]
     */
    public function renderChunk(Bluebook $bluebook, int $first, int $count = self::CHUNK): array
    {
        $pdf  = $this->sourceCopy($bluebook);
        $work = storage_path('app/page-render/chunk-' . $bluebook->id . '-' . bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($work);

        try {
            $last = $first + $count - 1;
            $dpi  = (string) (int) config('watermark.page_dpi', 150);

            $args = match (self::tool()) {
                'mutool'   => [PdfWatermarker::mutool(), 'draw', '-q', '-r', $dpi, '-o', $work . '/page-%d.png', $pdf, "{$first}-{$last}"],
                'pdftoppm' => [self::pdftoppm(), '-png', '-r', $dpi, '-f', (string) $first, '-l', (string) $last, $pdf, $work . '/page'],
                default    => throw new \RuntimeException('This host has neither MuPDF nor Poppler to draw pages with.'),
            };

            $process = new Process($this->lowPriority($args));
            $process->setTimeout(90);
            $process->run();
            // Asking past the last page is how the end is found, and neither tool
            // treats it as an error: pdftoppm draws nothing, and mutool clamps
            // the range and draws the last page again. So only pages inside the
            // range asked for count - past the end, that is none.

            $stored = [];
            foreach (glob($work . '/page-*.png') ?: [] as $png) {
                if (!preg_match('/page-0*(\d+)\.png$/', $png, $m)) {
                    continue;
                }
                $n = (int) $m[1];
                if ($n < $first || $n > $last) {
                    continue;
                }
                $img = @imagecreatefrompng($png);
                if (!$img) {
                    throw new \RuntimeException("Page {$n} did not render.");
                }
                ob_start();
                imagewebp($img, null, (int) config('watermark.page_store_quality', 80));
                PageImages::disk()->put(PageImages::path($bluebook->id, $n), (string) ob_get_clean());
                imagedestroy($img);
                $stored[] = $n;
            }
            sort($stored);

            if (!$stored && $first === 1 && !$process->isSuccessful()) {
                throw new \RuntimeException('The document could not be drawn: ' . trim($process->getErrorOutput()));
            }

            return $stored;
        } finally {
            File::deleteDirectory($work);
        }
    }

    /** Switch the bluebook to its $total freshly drawn pages, and tidy up. */
    public function finish(Bluebook $bluebook, int $total): void
    {
        $disk  = PageImages::disk();
        $keep  = array_map(fn($n) => PageImages::path($bluebook->id, $n), range(1, $total));
        $stale = array_diff($disk->files(PageImages::directory($bluebook->id)), $keep);
        if ($stale) {
            $disk->delete(array_values($stale));
        }

        Bluebook::where('id', $bluebook->id)->where('file_path', $bluebook->file_path)->update([
            'page_images_count'  => $total,
            'page_images_source' => $bluebook->file_path,
            'page_images_at'     => now(),
            'pages'              => $total,
        ]);

        @unlink($this->sourceCopyPath($bluebook));
    }

    /** The PDF on this machine, fetched once and reused by the following chunks. */
    private function sourceCopy(Bluebook $bluebook): string
    {
        $path = $this->sourceCopyPath($bluebook);
        if (is_file($path) && filesize($path) > 0) {
            return $path;
        }

        File::ensureDirectoryExists(dirname($path));
        [$local, $temp] = ProcessBluebookOcr::localCopy($bluebook->file_path);
        if ($temp === null || !@rename($temp, $path)) {
            copy($local, $path);
            if ($temp !== null) {
                @unlink($temp);
            }
        }

        return $path;
    }

    private function sourceCopyPath(Bluebook $bluebook): string
    {
        return storage_path('app/page-render/source-' . $bluebook->id . '-' . substr(sha1($bluebook->file_path), 0, 12) . '.pdf');
    }
}
