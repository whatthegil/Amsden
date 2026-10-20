<?php

namespace App\Mail;

use App\Models\User;
use App\Services\BluebookEvaluation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a student what became of a bluebook they uploaded.
 *
 * Sent after the response, so a slow or unreachable mail server never holds
 * up - or fails - the approval, rejection or upload that set it off.
 */
class BluebookStatusMail extends Mailable
{
    use Queueable, SerializesModels, SendsAfterResponse;

    public const RECEIVED  = 'received';   // uploaded or re-uploaded, now Pending
    public const APPROVED  = 'approved';   // approved, waiting on the signed waiver
    public const POSTED    = 'posted';     // in Browse
    public const REJECTED  = 'rejected';
    public const RECALLED  = 'recalled';   // taken back to Pending after approval

    private const SUBJECTS = [
        self::RECEIVED  => 'We received your bluebook',
        self::APPROVED  => 'Your bluebook was approved - please submit the signed waiver',
        self::POSTED    => 'Your bluebook is now posted in the archive',
        self::REJECTED  => 'Your bluebook needs changes',
        self::RECALLED  => 'Your bluebook is back under review',
    ];

    public function __construct(
        public string $event,
        public string $title,
        public string $name,
        public ?string $note = null,
        public array $results = [],
    ) {}

    /**
     * Send the uploader word of a change, if there is an uploader to tell.
     *
     * Bluebooks the library added itself have no student behind them, so
     * nothing is sent for those. A rejection carries the whole evaluation
     * checklist it was made with. Never throws: an email that cannot be queued
     * is reported and the action that caused it goes ahead regardless.
     */
    public static function notify(array $bluebook, string $event, ?string $note = null): void
    {
        $email = $bluebook['uploadedBy'] ?? null;
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || $email === 'library@cspc.edu.ph') {
            return;
        }
        if (User::isStaff(User::where('email', $email)->value('role'))) {
            return;
        }

        self::sendAfterResponse($email, new self(
            $event,
            (string) $bluebook['title'],
            (string) ($bluebook['uploadedByName'] ?? ''),
            $note,
            $event === self::REJECTED ? BluebookEvaluation::results($bluebook) : [],
        ));
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
