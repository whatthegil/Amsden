<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Bluebooks and bookmarks point at their user by email, but nothing held the
 * two together: an email could change, or never have had an account, and the
 * rows kept pointing at nobody. Both now reference users.email, following an
 * email change and never outliving the account they name.
 *
 * Rows that already point at a missing account are settled first so the keys
 * can be added: an uploader gets an account in their name with a random password, since
 * the paper must keep its author; a bookmark of nobody is dropped.
 */
return new class extends Migration
{
    public function up()
    {
        $orphans = DB::table('bluebooks')
            ->leftJoin('users', 'users.email', '=', 'bluebooks.uploaded_by')
            ->whereNull('users.id')
            ->select('bluebooks.uploaded_by', DB::raw('MAX(bluebooks.uploaded_by_name) as name'))
            ->groupBy('bluebooks.uploaded_by')
            ->get();

        foreach ($orphans as $orphan) {
            DB::table('users')->insert([
                'name'       => $orphan->name ?: $orphan->uploaded_by,
                'email'      => $orphan->uploaded_by,
                'password'   => Hash::make(Str::random(40)),
                'role'       => 'Student',
                'can_upload' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('bookmarks')
            ->whereNotIn('user_email', DB::table('users')->select('email'))
            ->delete();

        Schema::table('bluebooks', function (Blueprint $table) {
            $table->foreign('uploaded_by')->references('email')->on('users')
                ->cascadeOnUpdate()->restrictOnDelete();
        });

        Schema::table('bookmarks', function (Blueprint $table) {
            $table->foreign('user_email')->references('email')->on('users')
                ->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::table('bookmarks', function (Blueprint $table) {
            $table->dropForeign(['user_email']);
        });

        Schema::table('bluebooks', function (Blueprint $table) {
            $table->dropForeign(['uploaded_by']);
        });
    }
};
