<?php

namespace App\Services;

use App\Models\Bluebook;

class LiteratureReviewService
{
    // Most results a search returns, and so most papers loaded in full.
    private const MAX_RESULTS = 25;

    /**
     * Find the approved bluebooks that bear on a research topic, best first,
     * each with a summary of how it relates.
     *
     * Matching is the archive search's (SearchService): each word of the topic
     * on its own, in any order, forgiving typos and other forms of a word,
     * across the title, keywords, authors, abstract and the document text. A
     * topic is usually a phrase or a sentence, so a paper need not contain all
     * of it - but it must contain a third of its words (one, for a topic of
     * one or two), which keeps out papers sharing only a word like "students".
     *
     * Everything - matching, ranking and summaries - runs on the server itself;
     * no paper's text is sent to an outside service.
     */
    public static function search(string $topic, ?int $withinYears = null, string $sort = 'relevance', ?string $department = null): array
    {
        $empty = ['results' => []];
        $terms = SearchService::terms($topic);
        if ($terms === []) {
            return $empty;
        }

        // Only approved bluebooks, ever.
        $pool = Bluebook::where('status', 'Approved');

        // A review of related literature usually has to cite recent work.
        if ($withinYears !== null && $withinYears > 0) {
            $pool->where('year', '>=', (int) date('Y') - $withinYears + 1);
        }

        // One college's papers only, when the student narrows it down.
        if ($department !== null && $department !== '') {
            $pool->where('department', $department);
        }

        $matches = SearchService::match($topic, $pool);

        $needed  = count($terms) <= 2 ? 1 : (int) ceil(count($terms) / 3);
        $matches = array_slice(
            array_filter($matches, fn($m) => $m['found'] >= $needed),
            0, self::MAX_RESULTS, true
        );
        if ($matches === []) {
            return $empty;
        }

        $books   = Bluebook::whereIn('id', array_keys($matches))->where('status', 'Approved')->get()->keyBy('id');
        $results = [];

        foreach ($matches as $id => $m) {
            if (!isset($books[$id])) {
                continue;
            }
            $book = Store::bookToArray($books[$id]);

            $results[] = [
                'bluebook'    => $book,
                'score'       => $m['rank'],
                'relevance'   => self::relevance($m),
                'summary'     => self::summarize($book, $terms),
                'citation'    => self::citation($book),
            ];
        }

        if ($sort === 'newest') {
            // Newest first; the stable sort keeps relevance order within a year.
            usort($results, fn($a, $b) => ($b['bluebook']['year'] ?? 0) <=> ($a['bluebook']['year'] ?? 0));
        }

        return ['results' => $results];
    }

    /**
     * The percentage shown beside a result: mostly how much of the topic the
     * paper covers, the rest how strongly - a word in the title counts for
     * more than the same word deep in the document text.
     */
    private static function relevance(array $m): float
    {
        $coverage = $m['found'] / max(1, $m['terms']);
        $strength = min(1.0, $m['score'] / (max(1, $m['terms']) * 10));

        return round($coverage * 75 + $strength * 25, 1);
    }

    /**
     * The paper cited in APA 7th edition, as an unpublished bachelor's thesis
     * of the college - which is what every bluebook in the archive is.
     *
     *   Dela Cruz, M. A., & Santos, J. (2024). Title of the thesis
     *   [Unpublished bachelor's thesis]. Camarines Sur Polytechnic Colleges.
     *
     * @return array{text: string, html: string, inText: string}
     */
    public static function citation(array $book): array
    {
        $people = array_values(array_filter(array_map('trim', $book['authors'] ?? [])));
        $names  = array_map([self::class, 'apaName'], $people);
        $surnames = array_map(fn($p) => self::surname($p), $people);
        $year   = $book['year'] ?: 'n.d.';

        $authors = match (true) {
            count($names) === 0 => '',
            count($names) === 1 => $names[0],
            default             => implode(', ', array_slice($names, 0, -1)) . ', & ' . end($names),
        };

        // APA wants a thesis title in sentence case, keeping proper nouns.
        $title = rtrim(\App\Services\Citation\TitleCase::sentence((string) ($book['title'] ?? '')) ?: 'Untitled', '.');

        $lead = ($authors !== '' ? $authors . ' ' : '') . "({$year}).";
        $tail = "[Unpublished bachelor's thesis]. " . config('citation.school', 'Camarines Sur Polytechnic Colleges') . '.';

        $inText = match (true) {
            count($surnames) === 0 => "(\"" . \Illuminate\Support\Str::limit($title, 30) . "\", {$year})",
            count($surnames) === 1 => "({$surnames[0]}, {$year})",
            count($surnames) === 2 => "({$surnames[0]} & {$surnames[1]}, {$year})",
            default                => "({$surnames[0]} et al., {$year})",
        };

        return [
            'text'   => "{$lead} {$title} {$tail}",
            'html'   => e($lead) . ' <em>' . e($title) . '</em> ' . e($tail),
            'inText' => $inText,
        ];
    }

    /**
     * "Dela Cruz, Maria Anne" or "Maria Anne Dela Cruz Jr." as
     * "Dela Cruz, M. A." / "Dela Cruz, M. A., Jr."
     */
    private static function apaName(string $person): string
    {
        $name = \App\Services\Citation\PersonName::parse($person);

        $initials = implode(' ', array_map(
            fn($part) => mb_strtoupper(mb_substr($part, 0, 1)) . '.',
            array_filter(preg_split('/[\s.]+/u', $name['given']) ?: [], fn($p) => $p !== '')
        ));

        $out = $initials !== '' ? "{$name['surname']}, {$initials}" : $name['surname'];
        return $name['suffix'] !== '' ? "{$out}, {$name['suffix']}" : $out;
    }

    /** The family name, particles and all: "Dela Cruz", not "Cruz". */
    private static function surname(string $person): string
    {
        return \App\Services\Citation\PersonName::parse($person)['surname'];
    }

    /**
     * Extractive summary: the abstract sentences that carry most of the topic,
     * taken word for word from the paper.
     */
    public static function summarize(array $book, array $terms): string
    {
        $abstract = trim($book['abstract'] ?? '');
        if ($abstract === '') {
            return 'No abstract available for this bluebook.';
        }

        $sentences = preg_split('/(?<=[.!?])\s+/', $abstract, -1, PREG_SPLIT_NO_EMPTY);
        if (count($sentences) <= 2) {
            return $abstract;
        }

        // The two sentences that carry most of the topic, kept in their order.
        $overlaps = [];
        foreach ($sentences as $i => $sentence) {
            $overlaps[$i] = SearchService::countFound($terms, $sentence);
        }

        arsort($overlaps);
        $topIndexes = array_slice(array_keys($overlaps), 0, 2);
        sort($topIndexes);

        return implode(' ', array_map(fn($i) => $sentences[$i], $topIndexes));
    }
}
