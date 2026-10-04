<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /**
     * No waiver on file: added by the library, or submitted before the waiver
     * existed (Library Manual 4.3.1.4). Readers view it in the watermarked
     * viewer, but it is never treated as an open copy - the absence of a waiver
     * is not the author's consent.
     */
    public const ACCESS_LEGACY = 'legacy';

    /** The choices on the signed waiver form, in its own words. */
    public const ACCESS_LEVELS = [
        self::ACCESS_PUBLIC       => 'All parts of the unpublished material are accessible for public use',
        self::ACCESS_CONSULTATION => 'It is not permitted for general use but is accessible after consultation with the author',
        self::ACCESS_PARTIAL      => 'Only certain parts of the material',
    ];

    /** Each level by the name the Library Manual gives it. */
    public const ACCESS_NAMES = [
        self::ACCESS_PUBLIC       => 'Open Access',
        self::ACCESS_CONSULTATION => 'Restricted Access',
        self::ACCESS_PARTIAL      => 'Partial Access',
        self::ACCESS_LEGACY       => 'Legacy – No Access Permission on File',
    ];

    public static function accessName(?string $level): string
    {
        return self::ACCESS_NAMES[$level ?: self::ACCESS_PUBLIC] ?? (string) $level;
    }

    /**
     * The parts an author can open to readers under ACCESS_PARTIAL. Preliminary
     * pages are not among them: the admin handles those.
     */
    public const ACCESS_PARTS = [
        'abstract'    => 'Abstract',
        'chapter1'    => 'Chapter 1',
        'chapter2'    => 'Chapter 2',
        'chapter3'    => 'Chapter 3',
        'chapter4'    => 'Chapter 4',
        'chapter5'    => 'Chapter 5',
    ];

    /**
     * Labels for every part a saved waiver may name, including ones no longer
     * offered - a bluebook that opened its preliminary pages before they were
     * dropped from the list still shows readers what they are.
     */
    public const ACCESS_PART_LABELS = self::ACCESS_PARTS + [
        'preliminary' => 'Preliminary pages',
    ];

    protected $fillable = [
        'title', 'authors', 'year', 'department', 'program',
        'keywords', 'abstract', 'adviser', 'status',
        'uploaded_by', 'uploaded_by_name', 'pages', 'views', 'date_added',
        'file_path', 'file_original_name', 'file_size',
        'ocr_status', 'ocr_text', 'ocr_error', 'ocr_engine', 'ocr_rasterizer', 'ocr_processed_at',
        'watermarked_at',
        'page_images_count', 'page_images_source', 'page_images_at',
        'access_level', 'access_parts', 'withheld_pages', 'waiver_requested_at', 'waiver_recorded_at', 'rejection_reason',
        'evaluation', 'evaluated_at', 'pdf_encrypted',
    ];

    protected $casts = [
        'authors'          => 'array',
        'keywords'         => 'array',
        'views'            => 'integer',
        'year'             => 'integer',
        'pages'            => 'integer',
        'ocr_processed_at' => 'datetime',
        'watermarked_at'   => 'datetime',
        'page_images_count' => 'integer',
        'page_images_at'   => 'datetime',
        'access_parts'     => 'array',
        'waiver_requested_at' => 'datetime',
        'waiver_recorded_at' => 'datetime',
        'evaluation'       => 'array',
        'evaluated_at'     => 'datetime',
        'pdf_encrypted'    => 'boolean',
    ];

    /**
     * A new or replaced file has its pages drawn for the watermarked page
     * viewer, on the render queue - whatever the path that stored it (student
     * upload, re-upload, admin add or edit, import). It used to wait for OCR to
     * finish first, which only a worker with Tesseract can do; drawing pages
     * needs no text, so it no longer waits.
     */
    protected static function booted(): void
    {
        $render = function (Bluebook $bluebook) {
            if ($bluebook->file_path && config('watermark.page_images', true) && config('watermark.page_autorender', true)) {
                \App\Jobs\RenderBluebookPages::dispatch($bluebook->id)->afterCommit();
            }
        };

        // Created with a file, or given a different one. (On a new record
        // wasChanged() is false, hence the two events.)
        static::created($render);
        static::updated(function (Bluebook $bluebook) use ($render) {
            if ($bluebook->wasChanged('file_path')) {
                $render($bluebook);
            }
        });
    }

    /** The account that submitted this paper (uploaded_by → users.email). */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'email');
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    /**
     * The original page numbers a reader may see, in order, out of $count.
     *
     * The waiver decides the base - every page, the partial ranges, or none.
     * Withheld pages (CV,
     * signatures, ID numbers) then come out whatever the base, since the
     * manual keeps them from public view under every level.
     *
     * @param array $bluebook a Store::bookToArray()-shaped array
     * @return int[]
     */
    public static function readerPages(array $bluebook, int $count): array
    {
        if ($count < 1) {
            return [];
        }

        $level = $bluebook['accessLevel'] ?? self::ACCESS_PUBLIC;
        $pages = match (true) {
            $level === self::ACCESS_CONSULTATION  => [],
            $level === self::ACCESS_PARTIAL       => self::expandPageList(self::visiblePageList($bluebook['accessParts'] ?? []), $count),
            default                               => range(1, $count),
        };

        $withheld = self::expandPageList(self::normalizePageList($bluebook['withheldPages'] ?? null), $count);

        return array_values(array_diff($pages, $withheld));
    }

    /**
     * Whether a reader is limited to less than the whole document - by the
     * waiver or by withheld pages. When false, the whole file may be sent
     * without knowing its page count.
     */
    public static function isCutForReader(array $bluebook): bool
    {
        $level = $bluebook['accessLevel'] ?? self::ACCESS_PUBLIC;

        return self::normalizePageList($bluebook['withheldPages'] ?? null) !== null
            || in_array($level, [self::ACCESS_CONSULTATION, self::ACCESS_PARTIAL], true);
    }

    /** [1, 2, 3, 7] as "1-3,7", the form MuPDF and Ghostscript take. */
    public static function compressPages(array $pages): ?string
    {
        if (!$pages) {
            return null;
        }
        sort($pages);

        $ranges = [];
        foreach ($pages as $p) {
            $last = count($ranges) - 1;
            if ($last >= 0 && $p === $ranges[$last][1] + 1) {
                $ranges[$last][1] = $p;
            } else {
                $ranges[] = [$p, $p];
            }
        }

        return implode(',', array_map(fn($r) => $r[0] === $r[1] ? (string) $r[0] : "{$r[0]}-{$r[1]}", $ranges));
    }

    /**
     * A page list as an admin types it ("3, 148 - 152") in the form the rest
     * of the code reads ("3,148-152"). Null when blank; false when it is not a
     * page list at all, which callers validating input report as an error.
     */
    public static function normalizePageList(?string $list): string|null|false
    {
        $list = trim((string) $list);
        if ($list === '') {
            return null;
        }

        $parts = [];
        foreach (preg_split('/\s*[,;]\s*/', $list) as $part) {
            if ($part === '') continue;
            if (!preg_match('/^(\d+)(?:\s*[-–]\s*(\d+))?$/u', $part, $m)) {
                return false;
            }
            $from = (int) $m[1];
            $to   = isset($m[2]) ? (int) $m[2] : $from;
            if ($from < 1 || $to < $from) {
                return false;
            }
            $parts[] = $from === $to ? (string) $from : "{$from}-{$to}";
        }

        return $parts ? implode(',', $parts) : null;
    }

    /** "1-3,7" within 1..$count as [1, 2, 3, 7]. */
    public static function expandPageList(string|null|false $list, int $count): array
    {
        if (!$list || $count < 1) {
            return [];
        }

        $pages = [];
        foreach (explode(',', $list) as $part) {
            [$from, $to] = array_pad(array_map('intval', explode('-', $part)), 2, null);
            $to ??= $from;
            for ($p = max(1, $from); $p <= min($count, $to); $p++) {
                $pages[$p] = $p;
            }
        }
        ksort($pages);

        return array_values($pages);
    }

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
