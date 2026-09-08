<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The stock Laravel sessions table. It was never generated for this project
 * because local development runs on SESSION_DRIVER=file, so nothing needed it.
 * Hosted environments frequently default to SESSION_DRIVER=database instead,
 * and without this table every request fails while reading the session — which
 * looks like a 500 on every route, static pages included.
 *
 * Note this app does not use Laravel's auth guards (AuthController stores the
 * user in the session itself), so user_id stays null. The column is kept so the
 * schema matches what Laravel expects.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('sessions')) {
            return;
        }

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sessions');
    }
};
