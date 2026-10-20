<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The version history of every bluebook: one row per file submitted for it -
 * the first upload, each corrected re-upload, each replacement by the library -
 * with who sent it and when, what the author says they changed, and what the
 * review decided: who reviewed it, when, the evaluation checklist and the
 * reason for a decline. `events` keeps every step taken on that version in
 * order (submitted, declined, approved, recalled, posted), so a recall and a
 * second decision do not overwrite the first.
 *
 * A file a newer version replaced is kept on the disk, so the library can
 * still open what was reviewed.
 *
 * Existing bluebooks get their version 1 from the record as it stands, its
 * decision and reviewer read from the audit log where it has them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bluebook_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bluebook_id')->constrained('bluebooks')->cascadeOnDelete();
            $table->unsignedInteger('version');
            // original, resubmission or replacement (by the library).
            $table->string('kind', 20);
            $table->string('file_path')->nullable();
            $table->string('file_original_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('pages')->nullable();
            $table->text('notes')->nullable();
            $table->string('submitted_by')->nullable();
            $table->string('submitted_by_name')->nullable();
            $table->timestamp('submitted_at')->nullable();
            // Null while under review; Approved, Declined or Replaced.
            $table->string('outcome', 20)->nullable();
            $table->string('reviewed_by')->nullable();
            $table->string('reviewed_by_name')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->json('evaluation')->nullable();
            $table->unsignedSmallInteger('issues_count')->default(0);
            $table->text('reason')->nullable();
            $table->json('events')->nullable();
            $table->timestamps();

            $table->unique(['bluebook_id', 'version']);
        });

        $logged = function (int $id, string $title, array $actions) {
            return DB::table('logs')
                ->where(fn($q) => $q->where('bluebook_id', $id)->orWhere(fn($q) => $q->whereNull('bluebook_id')->where('document', $title)))
                ->whereIn('action', $actions)
                ->orderByDesc('id')
                ->first();
        };

        DB::table('bluebooks')->orderBy('id')->each(function ($b) use ($logged) {
            $submitted = $b->created_at ?? now();

            $outcome = match ($b->status) {
                'Rejected'                         => 'Declined',
                'Approved', 'Awaiting Waiver'      => 'Approved',
                default                            => null,
            };
            $decision = $outcome
                ? $logged($b->id, $b->title, [$outcome === 'Declined' ? 'Rejected Bluebook' : 'Approved Bluebook'])
                : null;
            $reviewedAt = $outcome === 'Declined' ? ($b->evaluated_at ?? $b->updated_at) : $b->updated_at;

            $evaluation = $outcome === 'Declined' && $b->evaluation ? json_decode($b->evaluation, true) : null;
            $issues = $evaluation ? count(array_filter($evaluation, fn($e) => ($e['status'] ?? null) === 'issue')) : 0;

            $events = [['action' => 'Submitted', 'by' => $b->uploaded_by, 'name' => $b->uploaded_by_name, 'at' => (string) $submitted]];
            if ($outcome) {
                $events[] = ['action' => $outcome, 'by' => $decision->email ?? null, 'name' => $decision->user_name ?? null, 'at' => (string) $reviewedAt, 'note' => $outcome === 'Declined' ? $b->rejection_reason : null];
            }

            DB::table('bluebook_versions')->insert([
                'bluebook_id'        => $b->id,
                // Files replaced before this table existed left no trace, so
                // what is on file now is where each history starts.
                'version'            => 1,
                'kind'               => 'original',
                'file_path'          => $b->file_path,
                'file_original_name' => $b->file_original_name,
                'file_size'          => $b->file_size,
                'pages'              => $b->pages,
                'submitted_by'       => $b->uploaded_by,
                'submitted_by_name'  => $b->uploaded_by_name,
                'submitted_at'       => $submitted,
                'outcome'            => $outcome,
                'reviewed_by'        => $decision->email ?? null,
                'reviewed_by_name'   => $decision->user_name ?? null,
                'reviewed_at'        => $outcome ? $reviewedAt : null,
                'evaluation'         => $evaluation ? json_encode($evaluation) : null,
                'issues_count'       => $issues,
                'reason'             => $outcome === 'Declined' ? $b->rejection_reason : null,
                'events'             => json_encode($events),
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bluebook_versions');
    }
};
