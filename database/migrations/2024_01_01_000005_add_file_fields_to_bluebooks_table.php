<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('bluebooks', function (Blueprint $table) {
            $table->string('file_path')->nullable()->after('pages');
            $table->string('file_original_name')->nullable()->after('file_path');
            $table->unsignedBigInteger('file_size')->nullable()->after('file_original_name');
        });
    }

    public function down()
    {
        Schema::table('bluebooks', function (Blueprint $table) {
            $table->dropColumn(['file_path', 'file_original_name', 'file_size']);
        });
    }
};
