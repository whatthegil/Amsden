<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bluebooks the library added itself - through Add Bluebook or the import -
 * were stored as open to the public with their waiver "recorded", although no
 * author ever signed one. The Library Manual (4.3.1.4) says such a work is a
 * Legacy Unpublished Material: the absence of a waiver is not consent, and the
 * record is marked "Legacy - No Access Permission on File".
 *
 * Readers still view them in the watermarked viewer, as before; what changes
 * is that they are no longer treated as open copies. An admin who holds a
 * signed waiver for one records it on the edit form.
 */
return new class extends Migration
{
    public function up(): void
    {
        $library = DB::table('users')
            ->whereIn('role', ['Admin', 'Sub-Admin'])
            ->pluck('email')
            ->push('library@cspc.edu.ph')
            ->all();

        DB::table('bluebooks')
            ->whereIn('uploaded_by', $library)
            ->where(fn($q) => $q->where('access_level', 'public')->orWhereNull('access_level'))
            ->update(['access_level' => 'legacy']);
    }

    public function down(): void
    {
        DB::table('bluebooks')->where('access_level', 'legacy')->update(['access_level' => 'public']);
    }
};
