<?php

namespace App\Services\Citation;

/**
 * A thesis title in the case each citation style wants.
 *
 * APA: sentence case - only the first word, the first word after a colon, and
 * proper nouns keep their capital. MLA and ACM: title case. Titles here arrive
 * either already in title case or shouted in capitals, and both are handled.
 */
class TitleCase
{
    /**
     * "Readiness of Academic Libraries in Makerspace Implementation: Challenges
     * and Benefits" as "Readiness of academic libraries in makerspace
     * implementation: Challenges and benefits".
     */
    public static function sentence(string $title): string
    {
        $title = trim($title);
        if ($title === '') {
            return $title;
        }

        // A title in capitals carries no case to keep, so nothing in it counts
        // as an acronym; the listed names still get theirs back below.
        $shouted = $title === mb_strtoupper($title);

        $words = preg_split('/(\s+)/u', $title, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $startOfSentence = true;

        foreach ($words as $i => $word) {
            if ($i % 2 === 1) {
                continue;                       // the whitespace between words
            }

            $words[$i] = $startOfSentence
                ? self::upperFirst($shouted ? mb_strtolower($word) : self::keepOrLower($word))
                : ($shouted ? mb_strtolower($word) : self::keepOrLower($word));

            $startOfSentence = (bool) preg_match('/[:?!]$/u', $word);
        }

        return self::restoreProperNouns(implode('', $words));
    }

    /** Title case, for MLA and ACM. A title not in capitals is kept as written. */
    public static function title(string $title): string
    {
        $title = trim($title);
        if ($title === '' || $title !== mb_strtoupper($title)) {
            return $title;
        }

        $minor = ['a', 'an', 'the', 'and', 'but', 'or', 'nor', 'for', 'so', 'yet',
                  'at', 'by', 'in', 'of', 'on', 'to', 'up', 'as', 'via', 'with', 'from', 'into'];
        $words = preg_split('/(\s+)/u', mb_strtolower($title), -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $last  = count($words) - 1;

        foreach ($words as $i => $word) {
            $afterColon = $i > 1 && str_ends_with(rtrim($words[$i - 2]), ':');
            if ($i === 0 || $i === $last || $afterColon || !in_array($word, $minor, true)) {
                $words[$i] = self::upperFirst($word);
            }
        }

        return self::restoreProperNouns(implode('', $words));
    }

    /**
     * A word keeps its case when it is plainly a name or an acronym - two or
     * more capitals (KKBN, NPK, (RPA)), a capital after the first letter
     * (PetKho, TrackCSPC) or a digit (YOLOv11). Otherwise it is lowered.
     * Hyphenated words are judged part by part: Multi-Branch, Point-of-Sale.
     */
    private static function keepOrLower(string $word): string
    {
        return implode('-', array_map(function (string $part) {
            $letters = preg_replace('/[^\p{L}\p{N}]/u', '', $part);
            $keep = preg_match('/\p{N}/u', $letters)
                 || preg_match('/^\p{L}.*\p{Lu}/u', $letters)
                 || (mb_strlen($letters) >= 2 && $letters === mb_strtoupper($letters));
            return $keep ? $part : mb_strtolower($part);
        }, explode('-', $word)));
    }

    /** The configured names, put back as written wherever they appear. */
    private static function restoreProperNouns(string $text): string
    {
        $names = config('citation.proper_nouns', []);
        usort($names, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ($names as $name) {
            $text = preg_replace('/(?<![\p{L}\p{N}])' . preg_quote($name, '/') . '(?![\p{L}\p{N}])/iu', $name, $text);
        }

        return $text;
    }

    private static function upperFirst(string $word): string
    {
        // Past any opening punctuation: "(RPA)" or a quote.
        return preg_replace_callback('/^([^\p{L}]*)(\p{L})/u', fn($m) => $m[1] . mb_strtoupper($m[2]), $word, 1);
    }
}
