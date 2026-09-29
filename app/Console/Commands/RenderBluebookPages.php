<?php

namespace App\Console\Commands;

use App\Jobs\RenderBluebookPages as RenderJob;
use App\Models\Bluebook;
use Illuminate\Console\Command;

/**
 * Draws the page images for bluebooks that do not have them yet - the ones in
 * the archive before the watermarked page viewer existed, or any whose images
 * are of an older file. New uploads are drawn automatically after their OCR.
 */
class RenderBluebookPages extends Command
{
    protected $signature = 'bluebooks:render-pages
                            {id? : One bluebook, instead of every one missing its pages}
                            {--queue : Queue the work for the worker instead of doing it here}
                            {--force : Redraw even bluebooks whose pages are current}';

    protected $description = 'Render bluebook pages to images for the watermarked page viewer';

    public function handle(): int
    {
        $query = Bluebook::whereNotNull('file_path');

        if ($id = $this->argument('id')) {
            $query->where('id', (int) $id);
        } elseif (!$this->option('force')) {
            $query->where(function ($q) {
                $q->whereNull('page_images_source')->orWhereColumn('page_images_source', '!=', 'file_path');
            });
        }

        $ids = $query->orderBy('id')->pluck('id');
        if ($ids->isEmpty()) {
            $this->info('Every bluebook already has its pages.');
            return self::SUCCESS;
        }

        foreach ($ids as $id) {
            if ($this->option('queue')) {
                RenderJob::dispatch($id);
                $this->line("  queued #{$id}");
                continue;
            }

            $this->line("  rendering #{$id}…");
            try {
                RenderJob::dispatchSync($id);
                $count = Bluebook::find($id)?->page_images_count;
                $this->info("  #{$id}: {$count} pages");
            } catch (\Throwable $e) {
                // One document that will not render should not stop the rest.
                $this->error("  #{$id}: " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
