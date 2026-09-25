<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When an admin recorded the access level from the author's signed waiver.
 *
 * access_level defaults to 'public', so on its own it cannot tell "the author
 * chose public" from "nobody has set it yet". A bluebook cannot be posted
 * (Waiver Received) until this is filled in.
 *
 * Bluebooks already posted keep their level and count as recorded; anything
 * still in review has to have its level set from the paper waiver.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bluebooks', function (Blueprint $table) {
            $table->timestamp('waiver_recorded_at')->nullable()->after('access_parts');
        });

        DB::table('bluebooks')->where('status', 'Approved')->update(['waiver_recorded_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('bluebooks', function (Blueprint $table) {
            $table->dropColumn('waiver_recorded_at');
        });
    }
};
