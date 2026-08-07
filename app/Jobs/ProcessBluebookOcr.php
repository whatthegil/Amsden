<?php

namespace App\Jobs;

use App\Models\Bluebook;
use App\Services\OcrService;
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
    public int $timeout = 300;

    public function __construct(private int $bluebookId)
    {
    }

    public function handle(): void
    {
        $bluebook = Bluebook::find($this->bluebookId);
        if (!$bluebook || !$bluebook->file_path) {
            return;
        }

        $bluebook->ocr_status = 'processing';
        $bluebook->save();

        $absolutePath = Storage::disk('local')->path($bluebook->file_path);

        $result = OcrService::extractText($absolutePath);

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
