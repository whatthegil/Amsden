<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bluebook extends Model
{
    /**
     * Approved by an admin but not yet posted: the author still has to hand the
     * signed, printed access permission waiver in to the library. Only
     * 'Approved' bluebooks are shown to readers, so this keeps it out of Browse.
     */
    public const STATUS_AWAITING_WAIVER = 'Awaiting Waiver';

    /** The library's official waiver form, downloaded by authors to print. */
    public static function waiverFormPath(): string
    {
        return resource_path('forms/access-permission-waiver.pdf');
    }

    /** Access permission waiver: every part may be read. */
    public const ACCESS_PUBLIC = 'public';
    /** Not for general use; readable only after consulting the author. */
    public const ACCESS_CONSULTATION = 'consultation';
    /** Only the parts listed in access_parts may be read. */
    public const ACCESS_PARTIAL = 'partial';

    public const ACCESS_LEVELS = [
        self::ACCESS_PUBLIC       => 'All parts of the unpublished material are accessible for public use',
        self::ACCESS_CONSULTATION => 'It is not permitted for general use but is accessible after consultation with the author',
        self::ACCESS_PARTIAL      => 'Only certain parts of the material',
    ];

    /** The parts an author can open to readers under ACCESS_PARTIAL. */
    public const ACCESS_PARTS = [
        'preliminary' => 'Preliminary pages',
        'abstract'    => 'Abstract',
        'chapter1'    => 'Chapter 1',
        'chapter2'    => 'Chapter 2',
        'chapter3'    => 'Chapter 3',
        'chapter4'    => 'Chapter 4',
        'chapter5'    => 'Chapter 5',
    ];

    protected $fillable = [
        'title', 'authors', 'year', 'department', 'program',
        'keywords', 'abstract', 'adviser', 'status',
        'uploaded_by', 'uploaded_by_name', 'pages', 'views', 'date_added',
        'file_path', 'file_original_name', 'file_size',
        'ocr_status', 'ocr_text', 'ocr_error', 'ocr_engine', 'ocr_rasterizer', 'ocr_processed_at',
        'watermarked_at',
        'access_level', 'access_parts',
    ];

    protected $casts = [
        'authors'          => 'array',
        'keywords'         => 'array',
        'views'            => 'integer',
        'year'             => 'integer',
        'pages'            => 'integer',
        'ocr_processed_at' => 'datetime',
        'watermarked_at'   => 'datetime',
        'access_parts'     => 'array',
    ];

    /**
     * The pages a reader may see under a partial waiver, as a page list both
     * MuPDF and Ghostscript accept ("1-12,40-58"). Overlapping or adjacent
     * ranges are merged so the served copy never repeats a page.
     *
     * Null when no usable range is named - callers must read that as "nothing
     * may be shown", never as "everything".
     */
    public static function visiblePageList(?array $parts): ?string
    {
        $ranges = [];
        foreach ($parts ?? [] as $range) {
            $from = (int) ($range['from'] ?? 0);
            $to   = (int) ($range['to'] ?? 0);
            if ($from >= 1 && $to >= $from) {
                $ranges[] = [$from, $to];
            }
        }

        if (!$ranges) {
            return null;
        }

        usort($ranges, fn($a, $b) => $a[0] <=> $b[0]);

        $merged = [array_shift($ranges)];
        foreach ($ranges as [$from, $to]) {
            $last = count($merged) - 1;
            if ($from <= $merged[$last][1] + 1) {
                $merged[$last][1] = max($merged[$last][1], $to);
            } else {
                $merged[] = [$from, $to];
            }
        }

        return implode(',', array_map(fn($r) => $r[0] === $r[1] ? (string) $r[0] : "{$r[0]}-{$r[1]}", $merged));
    }

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
