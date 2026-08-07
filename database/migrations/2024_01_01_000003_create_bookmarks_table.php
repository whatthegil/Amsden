<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->string('user_email');
            $table->string('user_name');
            $table->unsignedBigInteger('bluebook_id');
            $table->string('added_at');
            $table->timestamps();

            $table->foreign('bluebook_id')->references('id')->on('bluebooks')->onDelete('cascade');
            $table->unique(['user_email', 'bluebook_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('bookmarks');
    }
};
