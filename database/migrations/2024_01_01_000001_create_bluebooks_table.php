<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('bluebooks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->json('authors');
            $table->integer('year');
            $table->string('department');
            $table->string('program');
            $table->json('keywords');
            $table->text('abstract');
            $table->string('adviser');
            $table->string('status')->default('Pending');
            $table->string('uploaded_by');
            $table->string('uploaded_by_name');
            $table->integer('pages')->default(0);
            $table->integer('views')->default(0);
            $table->string('date_added');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('bluebooks');
    }
};
