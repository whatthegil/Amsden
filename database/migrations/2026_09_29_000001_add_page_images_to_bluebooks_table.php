<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each bluebook's pages rendered to images, so readers can be sent pages with
 * their email drawn into them instead of the clean PDF (see PageImages).
 *
 * page_images_source is the file the images were drawn from: a re-uploaded
 * document keeps its old images until the worker redraws them, and comparing
 * this with file_path is how the old ones are recognised and not served.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bluebooks', function (Blueprint $table) {
            $table->unsignedInteger('page_images_count')->nullable()->after('watermarked_at');
            $table->string('page_images_source')->nullable()->after('page_images_count');
            $table->timestamp('page_images_at')->nullable()->after('page_images_source');
        });
    }

    public function down(): void
    {
        Schema::table('bluebooks', function (Blueprint $table) {
            $table->dropColumn(['page_images_count', 'page_images_source', 'page_images_at']);
        });
    }
};
