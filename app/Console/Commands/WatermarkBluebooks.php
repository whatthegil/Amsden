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
                            {--self-test : Stamp a PDF this makes up, to prove the stamper works here without touching the archive}
                            {--id=*      : Only these bluebook ids}
                            {--force     : Stamp again even if the document already carries a mark}';

    protected $description = 'Stamp stored bluebook PDFs with the archive provenance watermark';

    public function handle(): int
    {
        if ($this->option('self-test')) {
            return $this->selfTest();
        }

        $dry = (bool) $this->option('dry-run');

        if (!PdfWatermarker::available()) {
            $this->error('No MuPDF binary found, so nothing here can be stamped.');
            $this->newLine();
            $this->line('What this host does have:');

            foreach (PdfWatermarker::hostReport() as $name => $info) {
                // Padded before the colour tags go on, or the escape codes are
                // counted into the column width and nothing lines up.
                $state  = str_pad($info['found'] ? 'found' : 'not installed', 14);
                $colour = $info['found'] ? 'green' : 'red';

                $this->line(sprintf(
                    '  %-9s <fg=%s>%s</> %s',
                    $name,
                    $colour,
                    $state,
                    $info['found'] ? ($info['version'] ?? $info['path']) : $info['note']
                ));
            }

            $this->newLine();
            $this->line('Any one of mutool, qpdf or Ghostscript is enough to stamp with;');
            $this->line('pdftoppm cannot, it only turns pages into images.');
            $this->line('Pin an unusual install path with OCR_MUTOOL_PATH in .env.');
            $this->newLine();
            $this->warn('Until one exists, documents are served unstamped. The viewer still draws');
            $this->warn('the reader\'s identity over every page it renders - that is the layer a');
            $this->warn('screenshot carries - but the stored file itself is clean.');

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
     * Exercise the stamper on a file nobody needs.
     *
     * The Ghostscript backend cannot be tried where it was written - there is
     * no Ghostscript there - so this exists to be run on the host that does
     * have it, without a real thesis being the thing that finds out it is
     * wrong.
     */
    private function selfTest(): int
    {
        $result = PdfWatermarker::selfTest();

        if (!($result['ok'] ?? false)) {
            $this->error('Self-test failed: ' . ($result['reason'] ?? 'unknown'));
            if ($result['backend'] ?? null) {
                $this->line('  Backend tried: ' . $result['backend']);
            }

            return self::FAILURE;
        }

        $this->info('Stamped a generated PDF using: ' . $result['backend']);
        $this->line(sprintf('  %s bytes in, %s bytes out', number_format($result['src_bytes']), number_format($result['out_bytes'])));
        $this->line('  output is a PDF: ' . ($result['is_pdf'] ? 'yes' : 'NO'));

        if ($result['mark_drawn'] === null) {
            $this->warn('  Nothing here can rasterize, so whether the mark actually appears is unknown.');
            $this->line('  A file came out and it is a PDF; that is all this host can tell you.');

            return self::SUCCESS;
        }

        $this->line(sprintf(
            '  ink on page 1: %.2f%% before, %.2f%% after',
            $result['ink_before'] * 100,
            $result['ink_after'] * 100
        ));
        $this->line('  mark rendered: ' . ($result['mark_drawn'] ? 'yes' : 'NO'));

        if ($result['content_survived'] !== null) {
            $this->line('  original text survived: ' . ($result['content_survived'] ? 'yes' : 'NO'));
        }

        // A stamper that loses the document is worse than one that does
        // nothing, so either of these is a failure rather than a warning.
        if (!$result['mark_drawn'] || $result['content_survived'] === false) {
            $this->newLine();
            $this->error($result['content_survived'] === false
                ? 'The document did not survive stamping. Do NOT run the backfill with this backend.'
                : 'The mark did not reach the page. Do not run the backfill with this backend.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('The mark reaches the page and the document survives. Safe to use on this host.');

        return self::SUCCESS;
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
