<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two tables from Laravel's starter kit that this app never wrote to:
 * password_resets (passwords are reset by an administrator, not by emailed
 * link) and personal_access_tokens (there is no token API). Both are empty.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::dropIfExists('password_resets');
        Schema::dropIfExists('personal_access_tokens');
    }

    public function down()
    {
        Schema::create('password_resets', function (Blueprint $table) {
            $table->string('email')->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }
};
