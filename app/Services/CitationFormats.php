<?php

namespace App\Services;

/**
 * A bluebook cited in the styles a student is asked for: APA 7, MLA 9 and ACM.
 *
 * Every bluebook is an unpublished bachelor's thesis of the college, so each
 * style's thesis form is the one used. APA comes from LiteratureReviewService,
 * which the literature review also cites with; the other two are built here.
 */
class CitationFormats
{
    private const SCHOOL   = 'Camarines Sur Polytechnic Colleges';
    private const LOCATION = 'Nabua, Camarines Sur, Philippines';

    /**
     * @return array<string, array{label: string, text: string, html: string}>
     */
    public static function all(array $book): array
    {
        $apa = LiteratureReviewService::citation($book);

        return [
            'apa' => ['label' => 'APA 7', 'text' => $apa['text'], 'html' => $apa['html']],
            'mla' => ['label' => 'MLA 9'] + self::mla($book),
            'acm' => ['label' => 'ACM'] + self::acm($book),
        ];
    }

    /**
     *   Tombado, Marygrace L., et al. Readiness of Academic Libraries in
     *   Makerspace Implementation. 2025. Camarines Sur Polytechnic Colleges,
     *   Bachelor's thesis.
     *
     * @return array{text: string, html: string}
     */
    public static function mla(array $book): array
    {
        $people = self::people($book);

        $authors = match (count($people)) {
            0       => '',
            1       => self::inverted($people[0]),
            2       => self::inverted($people[0]) . ', and ' . self::natural($people[1]),
            default => self::inverted($people[0]) . ', et al',
        };

        $title = self::title($book);
        $year  = $book['year'] ?: 'n.d.';
        $lead  = $authors !== '' ? self::sentence($authors) . ' ' : '';
        $tail  = "{$year}. " . self::SCHOOL . ", Bachelor's thesis.";

        return [
            'text' => $lead . self::sentence($title) . ' ' . $tail,
            'html' => e($lead) . '<em>' . e(self::trimEnd($title)) . '</em>' . e(self::endMark($title)) . ' ' . e($tail),
        ];
    }

    /**
     *   Marygrace L. Tombado, Maurine A. Cariño, and Jodie Bea B. Celaje.
     *   2025. Readiness of Academic Libraries in Makerspace Implementation.
     *   Bachelor's thesis. Camarines Sur Polytechnic Colleges, Nabua,
     *   Camarines Sur, Philippines.
     *
     * @return array{text: string, html: string}
     */
    public static function acm(array $book): array
    {
        $names = array_map([self::class, 'natural'], self::people($book));

        $authors = match (count($names)) {
            0       => '',
            1       => $names[0],
            2       => $names[0] . ' and ' . $names[1],
            default => implode(', ', array_slice($names, 0, -1)) . ', and ' . end($names),
        };

        $title = self::title($book);
        $year  = $book['year'] ?: 'n.d.';
        $lead  = ($authors !== '' ? self::sentence($authors) . ' ' : '') . "{$year}.";
        $tail  = "Bachelor's thesis. " . self::SCHOOL . ', ' . self::LOCATION . '.';

        return [
            'text' => $lead . ' ' . self::sentence($title) . ' ' . $tail,
            'html' => e($lead) . ' <em>' . e(self::trimEnd($title)) . '</em>' . e(self::endMark($title)) . ' ' . e($tail),
        ];
    }

    /** @return string[] */
    private static function people(array $book): array
    {
        return array_values(array_filter(array_map('trim', $book['authors'] ?? [])));
    }

    /** [given names, surname] from "Surname, Given M." or "Given M. Surname". */
    private static function split(string $person): array
    {
        if (str_contains($person, ',')) {
            return [trim(substr($person, strpos($person, ',') + 1)), trim(substr($person, 0, strpos($person, ',')))];
        }

        $parts = preg_split('/\s+/u', trim($person)) ?: [$person];
        $surname = array_pop($parts);

        return [implode(' ', $parts), $surname];
    }

    /** "Tombado, Marygrace L." */
    private static function inverted(string $person): string
    {
        [$given, $surname] = self::split($person);
        return $given !== '' ? "{$surname}, {$given}" : $surname;
    }

    /** "Marygrace L. Tombado" */
    private static function natural(string $person): string
    {
        [$given, $surname] = self::split($person);
        return trim("{$given} {$surname}");
    }

    /**
     * The title in title case, which both styles use. A title stored in
     * capitals is recased; any other is kept as written, so acronyms such as
     * KKBN or YOLOv11 survive.
     */
    private static function title(array $book): string
    {
        $title = trim((string) ($book['title'] ?? '')) ?: 'Untitled';

        if ($title !== mb_strtoupper($title)) {
            return $title;
        }

        $minor = ['a', 'an', 'the', 'and', 'but', 'or', 'nor', 'for', 'so', 'yet',
                  'at', 'by', 'in', 'of', 'on', 'to', 'up', 'as', 'via', 'with', 'from', 'into'];
        $words = preg_split('/(\s+)/u', mb_strtolower($title), -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $last  = count($words) - 1;

        foreach ($words as $i => $word) {
            $after = $i > 0 && str_ends_with(rtrim($words[$i - 2] ?? ''), ':');
            if ($i === 0 || $i === $last || $after || !in_array($word, $minor, true)) {
                $words[$i] = mb_strtoupper(mb_substr($word, 0, 1)) . mb_substr($word, 1);
            }
        }

        return implode('', $words);
    }

    /** Text closed with a full stop, unless it already ends in one or a ?/!. */
    private static function sentence(string $text): string
    {
        return self::trimEnd($text) . self::endMark($text);
    }

    private static function trimEnd(string $text): string
    {
        return rtrim(trim($text), '.');
    }

    private static function endMark(string $text): string
    {
        return preg_match('/[?!]$/u', trim($text)) ? '' : '.';
    }
}
