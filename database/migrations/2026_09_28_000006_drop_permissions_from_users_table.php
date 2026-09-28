<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users.permissions held privileges chosen per Sub-Admin. What an account may
 * do now follows its role alone (User::allows, User::ADMIN_ONLY), so nothing
 * has read the column since, and it goes.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('permissions')->nullable()->after('can_upload');
        });
    }
};
