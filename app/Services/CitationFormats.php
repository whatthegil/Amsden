<?php

namespace App\Services;

use App\Services\Citation\PersonName;
use App\Services\Citation\TitleCase;

/**
 * A bluebook cited in the styles a student is asked for: APA 7, MLA 9 and ACM.
 *
 * Every bluebook is an unpublished bachelor's thesis of the college, so each
 * style's thesis form is the one used. APA comes from LiteratureReviewService,
 * which the literature review also cites with; the other two are built here.
 */
class CitationFormats
{
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
     * A reference list of several papers in each style, alphabetical by its
     * first author as all three styles order them.
     *
     * @return array<string, array{label: string, text: string, html: string}>
     */
    public static function lists(array $books): array
    {
        $lists = [];

        // By the first author's surname, then title - not by each entry's text,
        // which in ACM starts with a first name.
        $key = function (array $book) {
            $first = self::people($book)[0] ?? '';
            return ($first !== '' ? PersonName::parse($first)['surname'] : '') . ' ' . self::title($book);
        };
        usort($books, fn($a, $b) => strnatcasecmp($key($a), $key($b)));

        foreach ($books as $book) {
            foreach (self::all($book) as $key => $style) {
                $lists[$key]['label'] = $style['label'];
                $lists[$key]['entries'][] = $style;
            }
        }

        return array_map(function (array $list) {
            $entries = $list['entries'];

            return [
                'label' => $list['label'],
                'text'  => implode("\n\n", array_column($entries, 'text')),
                'html'  => implode('', array_map(fn($e) => '<p class="cite-entry">' . $e['html'] . '</p>', $entries)),
            ];
        }, $lists);
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
        $tail  = "{$year}. " . config('citation.school') . ", Bachelor's thesis.";

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
        $tail  = "Bachelor's thesis. " . config('citation.school') . ', ' . config('citation.location') . '.';

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

    /** "Dela Cruz, Juan A., Jr." - MLA's first author. */
    private static function inverted(string $person): string
    {
        $n   = PersonName::parse($person);
        $out = $n['given'] !== '' ? "{$n['surname']}, {$n['given']}" : $n['surname'];
        return $n['suffix'] !== '' ? "{$out}, {$n['suffix']}" : $out;
    }

    /** "Juan A. Dela Cruz Jr." - every ACM author, and MLA's second. */
    private static function natural(string $person): string
    {
        $n = PersonName::parse($person);
        return trim("{$n['given']} {$n['surname']} {$n['suffix']}");
    }

    /** Title case, which both styles use; see TitleCase. */
    private static function title(array $book): string
    {
        return TitleCase::title((string) ($book['title'] ?? '')) ?: 'Untitled';
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
