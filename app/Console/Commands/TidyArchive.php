<?php

namespace App\Console\Commands;

use App\Models\Bluebook;
use App\Services\OcrService;
use App\Services\Pdf\PdfWatermarker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * The daily clean-up, run by the scheduler (routes/console.php).
 *
 * Each of these used to happen only when something else did - the next
 * watermark, the next OCR run, the next read of a document - so a quiet week
 * let temporary files pile up, and an OCR run killed mid-way stayed
 * "processing" until someone noticed the paper was not searchable.
 */
class TidyArchive extends Command
{
    protected $signature = 'archive:tidy';

    protected $description = 'Clear leftover temporary files and release OCR runs that died mid-way';

    public function handle(): int
    {
        $this->line('Watermark temp files removed: ' . PdfWatermarker::pruneTemp(60));
        $this->line('Abandoned OCR work folders removed: ' . OcrService::pruneOrphanedWorkDirs(3600));
        $this->line('Expired document copies removed: ' . $this->pruneOlderThan(storage_path('app/document-cache'), 86400, false));
        // A render stopped part-way (a restart, a machine short of memory)
        // leaves its whole working folder of page PNGs behind.
        $this->line('Abandoned page-render folders removed: ' . $this->pruneOlderThan(storage_path('app/page-render'), 86400, true));

        // Released as failed, which is what lets them be reprocessed: the
        // reprocess guard will not touch a row it believes is still running.
        $stuck = Bluebook::where('ocr_status', 'processing')->get()->filter->isOcrStuck();
        foreach ($stuck as $bluebook) {
            $bluebook->forceFill([
                'ocr_status' => 'failed',
                'ocr_error'  => 'The text reading stopped without finishing (the worker was probably restarted). Reprocess it to try again.',
            ])->save();
        }
        $this->line('Stuck OCR runs released: ' . $stuck->count());

        return self::SUCCESS;
    }

    /** Delete entries of $dir last changed more than $seconds ago; folders too when $dirs. */
    private function pruneOlderThan(string $dir, int $seconds, bool $dirs): int
    {
        if (!is_dir($dir)) {
            return 0;
        }

        $removed = 0;
        foreach (File::glob($dir . DIRECTORY_SEPARATOR . '*') as $path) {
            if (@filemtime($path) > time() - $seconds) {
                continue;
            }
            if (is_dir($path)) {
                if ($dirs && File::deleteDirectory($path)) {
                    $removed++;
                }
            } elseif (@unlink($path)) {
                $removed++;
            }
        }

        return $removed;
    }
}
