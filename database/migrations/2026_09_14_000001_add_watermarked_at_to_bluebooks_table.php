<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the archive's provenance mark was written into the stored file.
 *
 * Needed because the mark is written once, at upload, and the documents already
 * in the archive predate it - so the backfill has to be able to tell which are
 * still clean without pulling every object out of storage to look inside it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bluebooks', function (Blueprint $table) {
            $table->timestamp('watermarked_at')->nullable()->after('ocr_processed_at');
        });
    }

    public function down(): void
    {
        Schema::table('bluebooks', function (Blueprint $table) {
            $table->dropColumn('watermarked_at');
        });
    }
};
