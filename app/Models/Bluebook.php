<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bluebook extends Model
{
    protected $fillable = [
        'title', 'authors', 'year', 'department', 'program',
        'keywords', 'abstract', 'adviser', 'status',
        'uploaded_by', 'uploaded_by_name', 'pages', 'views', 'date_added',
        'file_path', 'file_original_name', 'file_size',
        'ocr_status', 'ocr_text', 'ocr_error', 'ocr_engine', 'ocr_rasterizer', 'ocr_processed_at',
    ];

    protected $casts = [
        'authors'          => 'array',
        'keywords'         => 'array',
        'views'            => 'integer',
        'year'             => 'integer',
        'pages'            => 'integer',
        'ocr_processed_at' => 'datetime',
    ];

    /**
     * True when this row claims to be mid-OCR but the run that set it can no
     * longer be alive. ProcessBluebookOcr::failed() resets the status on a
     * normal failure, but a hard-killed worker (host restart, kill -9) never
     * gets there — leaving the row stuck in 'processing', which the reprocess
     * guards would otherwise honour forever.
     *
     * The row is stamped when the job sets 'processing', so updated_at is the
     * start time. Anything older than the job's own timeout is abandoned.
     */
    public function isOcrStuck(): bool
    {
        if ($this->ocr_status !== 'processing' || !$this->updated_at) {
            return false;
        }

        $graceSeconds = (int) config('ocr.stuck_after', 900);

        return $this->updated_at->diffInSeconds(now()) > $graceSeconds;
    }
}
