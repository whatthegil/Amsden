<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('bluebooks', function (Blueprint $table) {
            $table->string('ocr_status')->default('pending')->after('file_size'); // pending|processing|completed|failed
            $table->longText('ocr_text')->nullable()->after('ocr_status');
            $table->text('ocr_error')->nullable()->after('ocr_text');
            $table->string('ocr_engine')->nullable()->after('ocr_error');       // e.g. tesseract, windows-ocr
            $table->string('ocr_rasterizer')->nullable()->after('ocr_engine'); // e.g. mutool, ghostscript, poppler
            $table->timestamp('ocr_processed_at')->nullable()->after('ocr_rasterizer');
        });
    }

    public function down()
    {
        Schema::table('bluebooks', function (Blueprint $table) {
            $table->dropColumn(['ocr_status', 'ocr_text', 'ocr_error', 'ocr_engine', 'ocr_rasterizer', 'ocr_processed_at']);
        });
    }
};
