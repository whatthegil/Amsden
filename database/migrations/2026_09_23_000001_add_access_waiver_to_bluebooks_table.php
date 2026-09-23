<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The access permission waiver the author signs at upload: whether readers may
 * see the whole document, none of it without consulting the author, or only the
 * parts named - each with the page range it occupies, since the viewer can only
 * withhold what it can locate.
 *
 * Existing rows default to public, which is how they have been served so far.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bluebooks', function (Blueprint $table) {
            $table->string('access_level', 20)->default('public')->after('status');
            $table->json('access_parts')->nullable()->after('access_level');
        });
    }

    public function down(): void
    {
        Schema::table('bluebooks', function (Blueprint $table) {
            $table->dropColumn(['access_level', 'access_parts']);
        });
    }
};
