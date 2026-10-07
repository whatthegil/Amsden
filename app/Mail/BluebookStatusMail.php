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
 * Tells a student what became of a bluebook they uploaded.
 *
 * Queued, so a slow or unreachable mail server never holds up - or fails - the
 * approval, rejection or upload that set it off. The queue worker sends it.
 */
class BluebookStatusMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public const RECEIVED  = 'received';   // uploaded or re-uploaded, now Pending
    public const APPROVED  = 'approved';   // approved, waiting on the signed waiver
    public const POSTED    = 'posted';     // in Browse
    public const REJECTED  = 'rejected';
    public const RECALLED  = 'recalled';   // taken back to Pending after approval
    public const EVALUATED = 'evaluated';  // checklist saved with items to fix

    private const SUBJECTS = [
        self::RECEIVED  => 'We received your bluebook',
        self::APPROVED  => 'Your bluebook was approved - please submit the signed waiver',
        self::POSTED    => 'Your bluebook is now posted in the archive',
        self::REJECTED  => 'Your bluebook needs changes',
        self::RECALLED  => 'Your bluebook is back under review',
        self::EVALUATED => 'Your bluebook was evaluated',
    ];

    public function __construct(
        public string $event,
        public string $title,
        public string $name,
        public ?string $note = null,
        public int $issues = 0,
    ) {}

    /**
     * Send the uploader word of a change, if there is an uploader to tell.
     *
     * Bluebooks the library added itself have no student behind them, so
     * nothing is sent for those. Never throws: an email that cannot be queued
     * is reported and the action that caused it goes ahead regardless.
     */
    public static function notify(array $bluebook, string $event, ?string $note = null, int $issues = 0): void
    {
        $email = $bluebook['uploadedBy'] ?? null;
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || $email === 'library@cspc.edu.ph') {
            return;
        }
        if (User::isStaff(User::where('email', $email)->value('role'))) {
            return;
        }

        try {
            Mail::to($email)->queue(new self(
                $event,
                (string) $bluebook['title'],
                (string) ($bluebook['uploadedByName'] ?? ''),
                $note,
                $issues,
            ));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: self::SUBJECTS[$this->event] ?? 'An update on your bluebook');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.bluebook-status', with: [
            'url' => route('student.my-uploads'),
        ]);
    }
}
