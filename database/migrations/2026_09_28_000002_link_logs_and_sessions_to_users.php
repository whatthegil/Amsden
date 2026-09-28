<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ties the audit log and the session store to the account behind them.
 *
 * A log keeps its own copy of the name and email, since it records what
 * happened even for a visitor with no account (user_id stays empty) and must
 * still read true after that account is renamed or removed (user_id is
 * emptied, the entry stays). A session goes with its account.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('logs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')
                ->constrained('users')->nullOnDelete();
        });

        // Every entry naming a real account's email was made by that account.
        foreach (DB::table('users')->select('id', 'email')->get() as $user) {
            DB::table('logs')->where('email', $user->email)->update(['user_id' => $user->id]);
        }

        DB::table('sessions')
            ->whereNotNull('user_id')
            ->whereNotIn('user_id', DB::table('users')->select('id'))
            ->delete();

        Schema::table('sessions', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
