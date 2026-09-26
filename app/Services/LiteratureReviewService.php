<?php

namespace App\Services;

use App\Models\Bluebook;
use App\Services\Ai\OpenAiClient;

class LiteratureReviewService
{
    // How many of the top-ranked results get an AI-written summary per search.
    // Keeps latency and API cost bounded regardless of how many bluebooks match —
    // the remainder still get the local extractive summary, never a blank result.
    private const AI_SUMMARY_LIMIT = 6;

    // Per-paper cap on how much OCR'd document text is sent to the model. Keeps a
    // multi-paper request well within a single call while still giving the model
    // real body content (not just the abstract) to ground its summary in.
    private const AI_OCR_EXCERPT_CHARS = 5000;

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
     * The summary for the top AI_SUMMARY_LIMIT results is AI-written when
     * OPENAI_API_KEY is configured; any AI failure falls back to the local
     * extractive summary, so search() never errors or loses results because of
     * the AI layer.
     */
    public static function search(string $topic, ?int $withinYears = null, string $sort = 'relevance'): array
    {
        $empty = ['results' => [], 'overview' => null];
        $terms = SearchService::terms($topic);
        if ($terms === []) {
            return $empty;
        }

        // Only approved bluebooks, ever: this text may be sent to OpenAI.
        $pool = Bluebook::where('status', 'Approved');

        // A review of related literature usually has to cite recent work.
        if ($withinYears !== null && $withinYears > 0) {
            $pool->where('year', '>=', (int) date('Y') - $withinYears + 1);
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
                'aiGenerated' => false,
                'citation'    => self::citation($book),
            ];
        }

        if ($sort === 'newest') {
            // Newest first; the stable sort keeps relevance order within a year.
            usort($results, fn($a, $b) => ($b['bluebook']['year'] ?? 0) <=> ($a['bluebook']['year'] ?? 0));
        }

        $overview = null;
        if (!empty($results) && OpenAiClient::isConfigured()) {
            $overview = self::applyAiSummaries($results, $topic);
        }

        return ['results' => $results, 'overview' => $overview];
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
     * Overwrites the 'summary' field (in place) for the top AI_SUMMARY_LIMIT
     * results with an AI-written summary grounded in each paper's actual
     * content — title, abstract, and an excerpt of its OCR'd full text, not
     * just keyword overlap. One batched request covers every candidate.
     */
    private static function applyAiSummaries(array &$results, string $topic): ?string
    {
        $candidates = array_slice($results, 0, self::AI_SUMMARY_LIMIT, preserve_keys: true);

        $papersBlock = '';
        foreach ($candidates as $r) {
            $book = $r['bluebook'];
            // The author's waiver governs the document text: only an open paper's
            // text is sent, so a summary cannot repeat pages readers are not given.
            $open       = ($book['accessLevel'] ?? Bluebook::ACCESS_PUBLIC) === Bluebook::ACCESS_PUBLIC;
            $ocrExcerpt = $open ? mb_substr(trim($book['ocrText'] ?? ''), 0, self::AI_OCR_EXCERPT_CHARS) : '';

            $papersBlock .= "### Paper id={$book['id']}\n";
            $papersBlock .= "Title: {$book['title']}\n";
            $papersBlock .= 'Authors: ' . implode(', ', $book['authors'] ?? []) . "\n";
            $papersBlock .= 'Cite in text as: ' . $r['citation']['inText'] . "\n";
            $papersBlock .= "Department/Program: {$book['department']} / {$book['program']}\n";
            $papersBlock .= "Abstract: " . ($book['abstract'] ?: '(none provided)') . "\n";
            $papersBlock .= $ocrExcerpt !== ''
                ? "Excerpt from uploaded document text:\n{$ocrExcerpt}\n"
                : "(No OCR'd document text available for this paper — base the summary on the abstract only.)\n";
            $papersBlock .= "\n";
        }

        $userContent = "Research topic: \"{$topic}\"\n\nCandidate papers from the archive, ranked by keyword relevance (do not re-rank them):\n\n{$papersBlock}";

        $system = <<<SYS
You are a literature-review assistant for C-BAMS, a capstone/thesis archive at Camarines Sur Polytechnic Colleges (CSPC). For each candidate paper, write a 2-3 sentence summary explaining specifically how that paper relates to the student's research topic. Ground every claim in the title, abstract, or document excerpt actually provided — never invent findings, methods, or results that aren't in the given text. If only the abstract is available for a paper, summarize from that alone and do not claim to describe its full methodology or results. Write in a neutral, academic tone suitable for an undergraduate literature review. Do not re-rank or re-score the papers — only summarize.

Also write "overview": one paragraph of 4-6 sentences that a student could adapt for the Review of Related Literature section of their paper. Synthesize rather than list: group the papers by what they have in common (approach, technology, setting or finding), show how together they bear on the topic, and end with what these papers leave open for the student's own study, if the text supports saying so. Cite each paper you mention using exactly the in-text citation given for it, e.g. (Dela Cruz & Santos, 2024). Mention only the papers provided and claim nothing they do not say.
SYS;

        $schema = [
            'type' => 'object',
            'properties' => [
                'papers' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id'      => ['type' => 'integer'],
                            'summary' => ['type' => 'string'],
                        ],
                        'required' => ['id', 'summary'],
                        'additionalProperties' => false,
                    ],
                ],
                'overview' => ['type' => 'string'],
            ],
            'required' => ['papers', 'overview'],
            'additionalProperties' => false,
        ];

        $response = OpenAiClient::createJsonMessage($system, $userContent, $schema, maxTokens: 3072);
        if ($response === null || !isset($response['papers'])) {
            return null; // extractive summaries already in place — nothing to do
        }

        $summariesById = [];
        foreach ($response['papers'] as $entry) {
            if (isset($entry['id'], $entry['summary'])) {
                $summariesById[(int) $entry['id']] = trim($entry['summary']);
            }
        }

        foreach ($results as $i => $r) {
            $id = $r['bluebook']['id'];
            if (isset($summariesById[$id]) && $summariesById[$id] !== '') {
                $results[$i]['summary']     = $summariesById[$id];
                $results[$i]['aiGenerated'] = true;
            }
        }

        $overview = trim((string) ($response['overview'] ?? ''));
        return $overview !== '' ? $overview : null;
    }

    /**
     * Extractive summary: pulls the abstract sentences most relevant to the
     * search topic. Used as the initial summary for every result, and as
     * the permanent fallback for results applyAiSummaries() doesn't reach
     * (beyond AI_SUMMARY_LIMIT, or when the AI call fails for any reason).
     */
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

        $title = trim((string) ($book['title'] ?? 'Untitled'));
        // A title stored in capitals is shouting, and APA wants sentence case.
        if ($title !== '' && $title === mb_strtoupper($title)) {
            $title = mb_strtoupper(mb_substr($title, 0, 1)) . mb_strtolower(mb_substr($title, 1));
        }
        $title = rtrim($title, '.');

        $lead = ($authors !== '' ? $authors . ' ' : '') . "({$year}).";
        $tail = "[Unpublished bachelor's thesis]. Camarines Sur Polytechnic Colleges.";

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

    /** "Dela Cruz, Maria Anne" (or "Maria Anne Dela Cruz") as "Dela Cruz, M. A." */
    private static function apaName(string $person): string
    {
        $surname = self::surname($person);
        $given   = str_contains($person, ',')
            ? trim(substr($person, strpos($person, ',') + 1))
            : trim(mb_substr($person, 0, max(0, mb_strlen($person) - mb_strlen($surname))));

        $initials = implode(' ', array_map(
            fn($part) => mb_strtoupper(mb_substr($part, 0, 1)) . '.',
            array_filter(preg_split('/[\s.]+/u', $given) ?: [], fn($p) => $p !== '' && !preg_match('/^(jr|sr|ii|iii|iv)$/i', $p))
        ));

        return $initials !== '' ? "{$surname}, {$initials}" : $surname;
    }

    /** The family name: before the comma when there is one, else the last word. */
    private static function surname(string $person): string
    {
        if (str_contains($person, ',')) {
            return trim(substr($person, 0, strpos($person, ',')));
        }

        $parts = preg_split('/\s+/u', trim($person)) ?: [$person];
        return (string) end($parts);
    }

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
