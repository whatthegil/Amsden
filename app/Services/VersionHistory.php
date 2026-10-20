<?php

namespace App\Services;

use App\Models\Bluebook;
use App\Models\BluebookVersion;
use Illuminate\Support\Carbon;

/**
 * The version history of a bluebook: each file submitted for it, with what the
 * review decided, for the library's reference.
 *
 * A version is opened whenever a file is submitted - the first upload, a
 * corrected re-upload, or the library replacing the file - and closed by the
 * decision on it. Every step is also appended to the version's events, so a
 * decision undone by a recall stays on record.
 *
 * Revisions required counts the declines (each asked the author for a new
 * file); revisions made counts the files that came after the first.
 */
class VersionHistory
{
    public const ORIGINAL     = 'original';
    public const RESUBMISSION = 'resubmission';
    public const REPLACEMENT  = 'replacement';

    public const APPROVED = 'Approved';
    public const DECLINED = 'Declined';
    /** Superseded by a newer file before anyone decided on it. */
    public const REPLACED = 'Replaced';

    public const KINDS = [
        self::ORIGINAL     => 'First submission',
        self::RESUBMISSION => 'Revised by the author',
        self::REPLACEMENT  => 'File replaced by the library',
    ];

    /** The most an author may write about what they changed. */
    public const NOTES_MAX = 1000;

    /**
     * Record a newly stored file as the next version. Call it after the
     * bluebook has the new file; call current() before changing the file of a
     * bluebook that may have no history yet, so the old file is recorded first.
     */
    public static function submitted(int $bluebookId, array $by, string $kind = self::ORIGINAL, ?string $notes = null): ?BluebookVersion
    {
        $bluebook = Bluebook::find($bluebookId);
        if (!$bluebook) {
            return null;
        }

        $latest = BluebookVersion::where('bluebook_id', $bluebookId)->orderByDesc('version')->first();
        if ($latest && $latest->outcome === null) {
            self::close($latest, self::REPLACED, $by);
        }

        $notes = trim((string) $notes);

        return self::open($bluebook, ($latest->version ?? 0) + 1, $latest ? $kind : self::ORIGINAL, $by, $notes === '' ? null : mb_substr($notes, 0, self::NOTES_MAX));
    }

    /**
     * The version under review now, or the last one decided. A bluebook from
     * before the history was kept starts one from its record as it stands.
     */
    public static function current(int $bluebookId): ?BluebookVersion
    {
        $latest = BluebookVersion::where('bluebook_id', $bluebookId)->orderByDesc('version')->first();
        if ($latest) {
            return $latest;
        }

        $bluebook = Bluebook::find($bluebookId);
        if (!$bluebook) {
            return null;
        }

        return self::open($bluebook, 1, self::ORIGINAL, [
            'email' => $bluebook->uploaded_by,
            'name'  => $bluebook->uploaded_by_name,
        ], null, $bluebook->created_at);
    }

    /** An Admin approved the version under review. */
    public static function approved(int $bluebookId, array $by): void
    {
        if ($version = self::current($bluebookId)) {
            self::close($version, self::APPROVED, $by);
        }
    }

    /** The version under review was declined, with its evaluation and reason. */
    public static function declined(int $bluebookId, array $by, array $evaluation, string $reason): void
    {
        $version = self::current($bluebookId);
        if (!$version) {
            return;
        }

        $version->evaluation   = $evaluation;
        $version->issues_count = count(array_filter($evaluation, fn($e) => ($e['status'] ?? null) === BluebookEvaluation::ISSUE));
        $version->reason       = $reason;
        self::close($version, self::DECLINED, $by, $reason);
    }

    /**
     * An approval undone: the same file is under review again. The decision
     * is cleared from the version but stays in its events.
     */
    public static function recalled(int $bluebookId, array $by): void
    {
        $version = self::current($bluebookId);
        if (!$version) {
            return;
        }

        $version->forceFill(['outcome' => null, 'reviewed_by' => null, 'reviewed_by_name' => null, 'reviewed_at' => null]);
        self::event($version, 'Recalled', $by);
        $version->save();
    }

    /** The signed waiver came in and the approved version was posted. */
    public static function posted(int $bluebookId, array $by): void
    {
        if ($version = self::current($bluebookId)) {
            self::event($version, 'Posted', $by);
            $version->save();
        }
    }

    /**
     * The history as the pages show it: the versions newest first, and the
     * counts that sum it up.
     */
    public static function forBluebook(int $bluebookId): array
    {
        $versions = BluebookVersion::where('bluebook_id', $bluebookId)->orderByDesc('version')->get();

        return [
            'versions'          => $versions->map(fn($v) => self::toArray($v))->all(),
            'count'             => $versions->count(),
            'revisionsRequired' => $versions->where('outcome', self::DECLINED)->count(),
            'revisionsMade'     => max(0, $versions->count() - 1),
            'issuesRaised'      => (int) $versions->sum('issues_count'),
        ];
    }

    /** One version, by its number, as forBluebook() shows it. */
    public static function find(int $bluebookId, int $version): ?array
    {
        $v = BluebookVersion::where('bluebook_id', $bluebookId)->where('version', $version)->first();

        return $v ? self::toArray($v) : null;
    }

    /** Every stored file a version of this bluebook points at. */
    public static function filePaths(int $bluebookId): array
    {
        return BluebookVersion::where('bluebook_id', $bluebookId)->whereNotNull('file_path')->distinct()->pluck('file_path')->all();
    }

    private static function open(Bluebook $bluebook, int $number, string $kind, array $by, ?string $notes, ?Carbon $at = null): BluebookVersion
    {
        $at ??= now();
        $version = new BluebookVersion([
            'bluebook_id'        => $bluebook->id,
            'version'            => $number,
            'kind'               => $kind,
            'file_path'          => $bluebook->file_path,
            'file_original_name' => $bluebook->file_original_name,
            'file_size'          => $bluebook->file_size,
            'pages'              => $bluebook->pages,
            'notes'              => $notes,
            'submitted_by'       => $by['email'] ?? null,
            'submitted_by_name'  => $by['name'] ?? null,
            'submitted_at'       => $at,
        ]);
        self::event($version, 'Submitted', $by, $notes, $at);
        $version->save();

        return $version;
    }

    private static function close(BluebookVersion $version, string $outcome, array $by, ?string $note = null): void
    {
        $version->forceFill([
            'outcome'          => $outcome,
            'reviewed_by'      => $by['email'] ?? null,
            'reviewed_by_name' => $by['name'] ?? null,
            'reviewed_at'      => now(),
        ]);
        self::event($version, $outcome, $by, $note);
        $version->save();
    }

    private static function event(BluebookVersion $version, string $action, array $by, ?string $note = null, ?Carbon $at = null): void
    {
        $events   = $version->events ?? [];
        $events[] = [
            'action' => $action,
            'by'     => $by['email'] ?? null,
            'name'   => $by['name'] ?? null,
            'at'     => ($at ?? now())->toIso8601String(),
            'note'   => $note,
        ];
        $version->events = $events;
    }

    private static function when($at): ?string
    {
        return $at ? Carbon::parse($at)->timezone('Asia/Manila')->format('M j, Y · g:i A') : null;
    }

    private static function toArray(BluebookVersion $v): array
    {
        return [
            'version'          => $v->version,
            'kind'             => $v->kind,
            'kindLabel'        => self::KINDS[$v->kind] ?? ucfirst($v->kind),
            'filePath'         => $v->file_path,
            'fileOriginalName' => $v->file_original_name,
            'fileSize'         => $v->file_size,
            'pages'            => $v->pages,
            'notes'            => $v->notes,
            'submittedBy'      => $v->submitted_by,
            'submittedByName'  => $v->submitted_by_name,
            'submittedAt'      => self::when($v->submitted_at),
            'outcome'          => $v->outcome,
            'reviewedBy'       => $v->reviewed_by,
            'reviewedByName'   => $v->reviewed_by_name,
            'reviewedAt'       => self::when($v->reviewed_at),
            // Shaped as a bluebook's, for BluebookEvaluation::results().
            'evaluation'       => $v->evaluation ?? [],
            'evaluatedAt'      => $v->outcome === self::DECLINED ? self::when($v->reviewed_at) : null,
            'issuesCount'      => $v->issues_count,
            'reason'           => $v->reason,
            'events'           => array_map(fn($e) => [
                'action' => $e['action'] ?? '',
                'name'   => $e['name'] ?? null,
                'by'     => $e['by'] ?? null,
                'at'     => self::when($e['at'] ?? null),
                'note'   => $e['note'] ?? null,
            ], $v->events ?? []),
        ];
    }
}
