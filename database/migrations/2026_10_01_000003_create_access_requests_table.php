<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requests to read more of a bluebook than its waiver opens to everyone, and
 * what the library decided (Library Manual 4.3.1.4). One row per reader and
 * bluebook: a request for several theses is decided title by title.
 *
 * An approved request lets that reader view the full text in the watermarked
 * viewer. It never permits a copy - the system does not hand those out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bluebook_id')->constrained()->cascadeOnDelete();
            $table->string('user_email');
            $table->string('user_name');
            // What the manual asks a researcher to give for a request.
            $table->string('research_title', 500);
            $table->string('program', 255);
            $table->string('adviser', 255);
            $table->text('purpose');
            $table->string('status', 20)->default('Pending');
            // On approval, the authorization it rests on (the author's written
            // consent, a consultation); on a denial or revocation, why.
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

    public function down(): void
    {
        Schema::dropIfExists('access_requests');
    }
};
