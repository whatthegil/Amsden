<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the author chose a waiver on the upload form.
 *
 * Without it, an upload made before the form asked is indistinguishable from
 * one where the author chose "public" - the column default - and the admin
 * edit form would pre-fill a choice nobody made.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('bluebooks', function (Blueprint $table) {
            $table->timestamp('waiver_requested_at')->nullable()->after('access_parts');
        });
    }

    public function down()
    {
        Schema::table('bluebooks', function (Blueprint $table) {
            $table->dropColumn('waiver_requested_at');
        });
    }
};
