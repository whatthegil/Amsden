<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ties a log entry to the bluebook it is about.
 *
 * logs.document held only the paper's title as text, so once a title was
 * edited its older entries could no longer be matched to the paper. The title
 * stays - it is what the log showed at the time - and bluebook_id now says
 * which paper that was. Deleting the paper clears the link, not the entry: the
 * audit trail outlives what it records.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            $table->foreignId('bluebook_id')->nullable()->after('user_id')
                ->constrained('bluebooks')->nullOnDelete();
        });

        // Past entries, where the title names exactly one paper. A title two
        // papers share is left unlinked rather than guessed.
        $unique = DB::table('bluebooks')->select('title')->groupBy('title')->havingRaw('COUNT(*) = 1');

        DB::table('logs')
            ->whereNull('bluebook_id')
            ->whereIn('document', $unique)
            ->orderBy('id')
            ->each(function ($log) {
                $id = DB::table('bluebooks')->where('title', $log->document)->value('id');
                if ($id) {
                    DB::table('logs')->where('id', $log->id)->update(['bluebook_id' => $id]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bluebook_id');
        });
    }
};
