<?php

namespace App\Console\Commands;

use App\Jobs\ProcessBluebookOcr;
use App\Models\Bluebook;
use Illuminate\Console\Command;

/**
 * Reads the text layer of bluebooks still waiting on OCR (or failed at it),
 * so the ones exported with their text become searchable without a worker
 * that has Tesseract. Scans are left as they are, for OCR.
 */
class ReadBluebookText extends Command
{
    protected $signature = 'bluebooks:read-text {id? : One bluebook, instead of every one not yet read}';

    protected $description = 'Read the text layer of bluebooks still pending or failed at OCR';

    public function handle(): int
    {
        $query = Bluebook::whereNotNull('file_path');
        $id = $this->argument('id');
        $id ? $query->where('id', (int) $id) : $query->whereIn('ocr_status', ['pending', 'failed']);

        $read = $scans = 0;
        foreach ($query->orderBy('id')->get() as $bluebook) {
            try {
                if (ProcessBluebookOcr::readTextLayer($bluebook)) {
                    $read++;
                    $this->info("  #{$bluebook->id}: read (" . number_format(strlen((string) $bluebook->ocr_text)) . ' characters)');
                } else {
                    $scans++;
                    $this->line("  #{$bluebook->id}: no text layer - left for OCR");
                }
            } catch (\Throwable $e) {
                $this->error("  #{$bluebook->id}: " . $e->getMessage());
            }
        }

        $this->line("Read {$read}; {$scans} left for OCR.");

        return self::SUCCESS;
    }
}
