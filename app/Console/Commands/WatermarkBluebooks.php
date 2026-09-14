<?php

namespace App\Console\Commands;

use App\Models\Bluebook;
use App\Services\Pdf\PdfWatermarker;
use App\Services\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Writes the archive's mark into documents that were stored before there was
 * one, and reports whether this host can do it at all.
 */
class WatermarkBluebooks extends Command
{
    protected $signature = 'bluebooks:watermark
                            {--dry-run   : Report what would be stamped, and whether this host can, without writing}
                            {--id=*      : Only these bluebook ids}
                            {--force     : Stamp again even if the document already carries a mark}';

    protected $description = 'Stamp stored bluebook PDFs with the archive provenance watermark';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        if (!PdfWatermarker::available()) {
            $this->error('No MuPDF binary found, so nothing here can be stamped.');
            $this->line('  Install mutool on this host, or pin it with OCR_MUTOOL_PATH in .env.');
            $this->line('  Until then documents are served unstamped - the viewer still draws the');
            $this->line('  reader\'s identity over every page it renders, but the file itself is clean.');

            return self::FAILURE;
        }

        $this->info('MuPDF found. ' . ($dry ? 'Dry run - nothing will be written.' : 'Stamping.'));

        $query = Bluebook::query()->whereNotNull('file_path')->where('file_path', '!=', '');

        if ($ids = $this->option('id')) {
            $query->whereIn('id', $ids);
        }

        $disk    = Storage::disk(Store::bluebookDisk());
        $done    = 0;
        $skipped = 0;
        $failed  = 0;

        foreach ($query->cursor() as $bluebook) {
            $label = "#{$bluebook->id} " . mb_strimwidth((string) $bluebook->title, 0, 46, '…');

            if (!$disk->exists($bluebook->file_path)) {
                $this->warn("  missing file   {$label}");
                $skipped++;
                continue;
            }

            if (!$this->option('force') && $this->alreadyStamped($bluebook)) {
                $this->line("  already marked {$label}");
                $skipped++;
                continue;
            }

            if ($dry) {
                $this->line("  would stamp    {$label}");
                $done++;
                continue;
            }

            [$line1, $line2] = PdfWatermarker::provenanceLines([
                'title' => $bluebook->title,
                'year'  => $bluebook->year,
            ]);

            if (PdfWatermarker::stampInPlace($bluebook->file_path, $line1, $line2)) {
                $bluebook->forceFill(['watermarked_at' => now()])->save();
                $this->info("  stamped        {$label}");
                $done++;
            } else {
                $this->error("  failed         {$label}");
                $failed++;
            }
        }

        $this->newLine();
        $this->line(($dry ? 'Would stamp' : 'Stamped') . ": {$done}   skipped: {$skipped}   failed: {$failed}");

        if (!$dry) {
            $pruned = PdfWatermarker::pruneTemp(0);
            if ($pruned) {
                $this->line("Cleared {$pruned} temporary file(s).");
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * A document is taken as marked once the column says so. Reading the PDF
     * back to look for the text would be the stronger check, but it means
     * pulling every object out of storage to answer a question the database can
     * answer - and --force is there for when the column is wrong.
     */
    private function alreadyStamped(Bluebook $bluebook): bool
    {
        return $bluebook->getAttribute('watermarked_at') !== null;
    }
}
