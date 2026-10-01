<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Http\UploadedFile;

/**
 * The Library Manual (5.2.1) requires the electronic copy to be free from
 * password protection, editing restrictions and encryption: the library has
 * to read, preserve and serve it, and an encrypted file is grounds for
 * non-acceptance.
 *
 * A PDF is encrypted when its trailer (or, in a cross-reference stream, that
 * stream's dictionary) names an /Encrypt dictionary. The trailer sits at the
 * end of the file, or near the start of a linearized one, so both ends are
 * read rather than the whole file.
 */
class PdfNotEncrypted implements Rule
{
    private const WINDOW = 1048576;

    public function passes($attribute, $value): bool
    {
        if (!$value instanceof UploadedFile || !$value->isValid()) {
            return true; // PdfFile reports an unusable upload.
        }

        $path = $value->getRealPath();
        $size = @filesize($path);
        $handle = @fopen($path, 'rb');
        if ($handle === false || $size === false) {
            return true;
        }

        $head = (string) fread($handle, self::WINDOW);
        $tail = '';
        if ($size > self::WINDOW) {
            fseek($handle, max(self::WINDOW, $size - self::WINDOW));
            $tail = (string) fread($handle, self::WINDOW);
        }
        fclose($handle);

        // "/Encrypt" followed by a reference ("5 0 R") or an inline dictionary.
        return !preg_match('#/Encrypt\s*(?:\d+\s+\d+\s+R|<<)#', $head . $tail);
    }

    public function message(): string
    {
        return 'The PDF is password-protected or encrypted. Please upload a copy with no password, encryption or editing restrictions.';
    }
}
