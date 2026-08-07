<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('policy_accepted_at')->nullable()->after('can_upload');
        });

        // Existing accounts predate this column — treat them as already
        // acknowledged so the policy screen only appears for genuinely new
        // registrations from now on, not on every login for current users.
        DB::table('users')->update(['policy_accepted_at' => now()]);
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('policy_accepted_at');
        });
    }
};
