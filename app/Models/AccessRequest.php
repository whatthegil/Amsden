<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reader's request to view the full text of a Restricted or Partial Access
 * bluebook, and the library's decision (Library Manual 4.3.1.4). Each request
 * names one bluebook, so a researcher asking for several theses gets a
 * decision on each.
 */
class AccessRequest extends Model
{
    public const STATUS_PENDING  = 'Pending';
    public const STATUS_APPROVED = 'Approved';
    public const STATUS_DENIED   = 'Denied';
    public const STATUS_REVOKED  = 'Revoked';

    protected $fillable = [
        'bluebook_id', 'user_email', 'user_name',
        'research_title', 'program', 'adviser', 'purpose',
        'status', 'decision_note', 'decided_by', 'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function bluebook(): BelongsTo
    {
        return $this->belongsTo(Bluebook::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_email', 'email');
    }

    /** Whether this reader holds an approved request for this bluebook. */
    public static function granted(int $bluebookId, ?string $email): bool
    {
        return $email !== null && $email !== ''
            && self::where('bluebook_id', $bluebookId)
                ->where('user_email', $email)
                ->where('status', self::STATUS_APPROVED)
                ->exists();
    }

    /** The reader's live request (pending or approved) for a bluebook, if any. */
    public static function current(int $bluebookId, string $email): ?self
    {
        return self::where('bluebook_id', $bluebookId)
            ->where('user_email', $email)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_APPROVED])
            ->latest('id')
            ->first();
    }
}
