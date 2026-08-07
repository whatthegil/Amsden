<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * One-way migration: passwords were previously stored in plaintext. Any row
 * whose password is not already a bcrypt hash gets hashed in place. Rows that
 * are already hashed (e.g. re-running after a partial migration) are skipped,
 * so this is safe to run more than once. There is no down() — the plaintext
 * values are unrecoverable by design.
 */
return new class extends Migration
{
    public function up()
    {
        DB::table('users')->orderBy('id')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                if (preg_match('/^\$2[aby]\$/', $user->password)) {
                    continue;
                }
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['password' => Hash::make($user->password)]);
            }
        });
    }

    public function down()
    {
        // Intentionally empty: hashing cannot be reversed.
    }
};
