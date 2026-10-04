<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Readers no longer ask the System for the full text of a restricted or
 * partial bluebook, so the requests and their decisions go with the feature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('access_requests');
    }

    public function down(): void
    {
        Schema::create('access_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bluebook_id')->constrained()->cascadeOnDelete();
            $table->string('user_email');
            $table->string('user_name');
            $table->string('research_title', 500);
            $table->string('program', 255);
            $table->string('adviser', 255);
            $table->text('purpose');
            $table->string('status', 20)->default('Pending');
            $table->text('decision_note')->nullable();
            $table->string('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->foreign('user_email')->references('email')->on('users')
                ->cascadeOnUpdate()->cascadeOnDelete();
            $table->index(['user_email', 'bluebook_id', 'status']);
            $table->index('status');
        });
    }
};
