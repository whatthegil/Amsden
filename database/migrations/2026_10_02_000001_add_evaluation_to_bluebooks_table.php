<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The admin's evaluation of a submission against the Library Manual's
 * criteria (5.2.1): each criterion marked okay or not, with a comment to the
 * author on those that are not. pdf_encrypted is read from the file when its
 * text is, since that one criterion cannot be told from the text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bluebooks', function (Blueprint $table) {
            $table->json('evaluation')->nullable()->after('rejection_reason');
            $table->timestamp('evaluated_at')->nullable()->after('evaluation');
            $table->boolean('pdf_encrypted')->nullable()->after('evaluated_at');
        });
    }

    public function down(): void
    {
        Schema::table('bluebooks', function (Blueprint $table) {
            $table->dropColumn(['evaluation', 'evaluated_at', 'pdf_encrypted']);
        });
    }
};
