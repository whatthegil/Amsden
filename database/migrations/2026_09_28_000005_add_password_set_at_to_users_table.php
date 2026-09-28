<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When an account got a password its owner knows. Accounts made by Google
 * sign-in are given a random one, so until this is set they can only sign in
 * through Google; the profile page lets them choose one without the current
 * password nobody has.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_set_at')->nullable()->after('password');
        });

        // Made by an administrator with a password, or changed since by the
        // owner: either way someone knows it.
        DB::table('users')->whereNull('google_id')->update(['password_set_at' => now()]);
        DB::table('users')
            ->whereIn('id', DB::table('logs')->where('action', 'Changed Password')->whereNotNull('user_id')->select('user_id'))
            ->update(['password_set_at' => now()]);
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_set_at');
        });
    }
};
