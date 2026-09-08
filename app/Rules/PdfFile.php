<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Http\UploadedFile;

/**
 * Defence-in-depth PDF check for bluebook uploads. Laravel's `mimes:pdf`
 * already validates the extension against the finfo-guessed MIME type; this
 * rule adds a content check — the file must actually begin with the PDF
 * signature (`%PDF-`) — so a non-PDF that slips past MIME detection (or a
 * truncated / empty file) is still rejected before it is stored and queued
 * for OCR.
 */
class PdfFile implements Rule
{
    public function passes($attribute, $value): bool
    {
        if (!$value instanceof UploadedFile || !$value->isValid()) {
            return false;
        }

        if (strtolower((string) $value->getClientOriginalExtension()) !== 'pdf') {
            return false;
        }

        $handle = @fopen($value->getRealPath(), 'rb');
        if ($handle === false) {
            return false;
        }
        $header = fread($handle, 5);
        fclose($handle);

        // The spec allows the %PDF- header within the first 1024 bytes, but in
        // practice every real-world PDF starts with it at byte 0.
        return $header === '%PDF-';
    }

    public function message(): string
    {
        return 'The :attribute must be a valid PDF document.';
    }
}
