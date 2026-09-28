<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many times each account used a limited tool on a given day, so the
 * daily limits in config/limits.php hold across devices and redeploys.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('feature_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('feature', 40);
            $table->date('used_on');
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'feature', 'used_on']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('feature_usage');
    }
};
