<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Tells the Admins a student has submitted a bluebook for review.
 *
 * Only Admins approve, reject and evaluate submissions, so only they are told.
 * Queued like BluebookStatusMail, so the student's upload never waits on it.
 */
class NewSubmissionMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public int $bluebookId,
        public string $title,
        public string $uploaderName,
        public string $uploaderEmail,
        public string $department,
        public bool $resubmitted = false,
    ) {}

    /**
     * Queue one message per Admin, each addressed to them alone. Never throws:
     * a message that cannot be queued is reported and the upload stands.
     */
    public static function notifyAdmins(array $bluebook, bool $resubmitted = false): void
    {
        try {
            $admins = User::where('role', User::ROLE_ADMIN)
                ->where('email', '!=', 'library@cspc.edu.ph')   // the import's account, not a person
                ->pluck('email');

            foreach ($admins as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;

                Mail::to($email)->queue(new self(
                    (int) $bluebook['id'],
                    (string) $bluebook['title'],
                    (string) ($bluebook['uploadedByName'] ?? ''),
                    (string) ($bluebook['uploadedBy'] ?? ''),
                    (string) ($bluebook['department'] ?? ''),
                    $resubmitted,
                ));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: ($this->resubmitted ? 'Bluebook resubmitted for review: ' : 'New bluebook for review: ')
            . \Illuminate\Support\Str::limit($this->title, 80));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.new-submission', with: [
            'url'   => route('admin.bluebooks.view', $this->bluebookId),
            'queue' => route('admin.pending'),
        ]);
    }
}
