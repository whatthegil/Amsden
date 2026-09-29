<?php

namespace App\Services\Citation;

/**
 * An author's name split into given names, surname and suffix, as every
 * citation style needs it.
 *
 * Names arrive either as "Dela Cruz, Maria A." or as "Maria A. Dela Cruz". The
 * first says where the surname ends; the second does not, and taking its last
 * word gave "Cruz" for Dela Cruz and "Jr." for anyone with a suffix. Filipino
 * surnames often start with a particle - Dela, De los, San - so those are read
 * as part of the surname, and a trailing Jr., Sr. or numeral as a suffix.
 */
class PersonName
{
    /** Particles that begin a surname, longest first so "de los" wins over "de". */
    private const PARTICLES = [
        'de los', 'de las', 'de la', 'delos', 'delas', 'dela', 'del', 'de',
        'san', 'santa', 'sta.', 'van der', 'van den', 'van', 'von', 'da', 'di', 'du',
    ];

    // No plain "V": a middle initial V. ("Ronan V. Paat") is far commoner than
    // a fifth of the name, and reading it as a suffix drops the initial.
    private const SUFFIX = '/^(jr|sr|ii|iii|iv)\.?$/i';

    /** @return array{given: string, surname: string, suffix: string} */
    public static function parse(string $person): array
    {
        $person = trim(preg_replace('/\s+/u', ' ', $person));

        if (str_contains($person, ',')) {
            [$surname, $rest] = array_map('trim', explode(',', $person, 2));
            // "Dela Cruz, Juan, Jr." or "Dela Cruz, Juan Jr."
            $words  = preg_split('/[\s,]+/u', $rest, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $suffix = '';
            if ($words && preg_match(self::SUFFIX, end($words))) {
                $suffix = array_pop($words);
            }
            return ['given' => implode(' ', $words), 'surname' => $surname, 'suffix' => self::suffix($suffix)];
        }

        $words  = explode(' ', $person);
        $suffix = '';
        if (count($words) > 1 && preg_match(self::SUFFIX, end($words))) {
            $suffix = array_pop($words);
        }

        if (count($words) < 2) {
            return ['given' => '', 'surname' => $words[0] ?? '', 'suffix' => self::suffix($suffix)];
        }

        // The surname is the last word, plus any particle words just before it.
        $start = count($words) - 1;
        foreach (self::PARTICLES as $particle) {
            $n = substr_count($particle, ' ') + 1;
            $from = count($words) - 1 - $n;
            if ($from >= 1 && mb_strtolower(implode(' ', array_slice($words, $from, $n))) === $particle) {
                $start = $from;
                break;
            }
        }

        return [
            'given'   => implode(' ', array_slice($words, 0, $start)),
            'surname' => implode(' ', array_slice($words, $start)),
            'suffix'  => self::suffix($suffix),
        ];
    }

    /** "jr" as "Jr.", "iii" as "III". */
    private static function suffix(string $s): string
    {
        $s = rtrim($s, '.');
        if ($s === '') {
            return '';
        }
        return preg_match('/^[iv]+$/i', $s) ? strtoupper($s) : ucfirst(strtolower($s)) . '.';
    }
}
