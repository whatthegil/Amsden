<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pages no reader is sent, whatever the waiver allows: the curriculum vitae,
 * contact details, signatures and ID numbers the Library Manual (4.3.1.3) says
 * must never be made publicly accessible. A page list such as "3, 148-152".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bluebooks', function (Blueprint $table) {
            $table->string('withheld_pages', 255)->nullable()->after('access_parts');
        });
    }

    public function down(): void
    {
        Schema::table('bluebooks', function (Blueprint $table) {
            $table->dropColumn('withheld_pages');
        });
    }
};
