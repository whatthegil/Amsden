<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One file submitted for a bluebook and what its review decided. See
 * App\Services\VersionHistory, which is the only thing that writes these.
 */
class BluebookVersion extends Model
{
    protected $fillable = [
        'bluebook_id', 'version', 'kind',
        'file_path', 'file_original_name', 'file_size', 'pages', 'notes',
        'submitted_by', 'submitted_by_name', 'submitted_at',
        'outcome', 'reviewed_by', 'reviewed_by_name', 'reviewed_at',
        'evaluation', 'issues_count', 'reason', 'events',
    ];

    protected $casts = [
        'version'      => 'integer',
        'file_size'    => 'integer',
        'pages'        => 'integer',
        'submitted_at' => 'datetime',
        'reviewed_at'  => 'datetime',
        'evaluation'   => 'array',
        'issues_count' => 'integer',
        'events'       => 'array',
    ];

    public function bluebook(): BelongsTo
    {
        return $this->belongsTo(Bluebook::class);
    }
}
