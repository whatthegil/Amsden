<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * Send a message once the page has gone out to the browser, in the same
 * request, rather than through the queue. Nothing on the live site works the
 * mail out of the queue - its only worker is the OCR machine, which reads the
 * local database - so a queued message was never sent. After the response, a
 * slow or unreachable mail server still holds up no one's click, and a
 * failure is reported without undoing the action that set it off.
 */
trait SendsAfterResponse
{
    protected static function sendAfterResponse(string $to, Mailable $mail): void
    {
        app()->terminating(function () use ($to, $mail) {
            try {
                Mail::to($to)->send($mail);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }
}
