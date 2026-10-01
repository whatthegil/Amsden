<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

/**
 * A person's name: letters only - any alphabet, so ñ and accented letters
 * pass - with the spaces, hyphens, apostrophes and periods real names carry
 * ("Ma. Teresa Dela Cruz-Santos Jr.", "O'Brien"). No digits or other symbols,
 * and at least two letters.
 */
class PersonName implements Rule
{
    /** The same check as passes(), for the input's HTML pattern attribute (v flag). */
    public const HTML_PATTERN = "[\\p{L}\\p{M}\\s.'’\\-]+";

    public function passes($attribute, $value): bool
    {
        return is_string($value)
            && preg_match("/^[\\p{L}\\p{M}\\s.'’\\-]+$/u", $value) === 1
            && preg_match_all('/\p{L}/u', $value) >= 2;
    }

    public function message(): string
    {
        return 'The name may contain letters only (spaces, hyphens, apostrophes and periods are allowed) - no numbers or symbols.';
    }
}
