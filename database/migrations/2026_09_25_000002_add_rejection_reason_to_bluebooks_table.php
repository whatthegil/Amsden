<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Why an admin rejected a submission, shown to the author in My Uploads so
 * they know what to fix before re-uploading. Cleared when they resubmit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bluebooks', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('bluebooks', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });
    }
};
