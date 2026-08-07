<?php

namespace App\Services;

use App\Services\Ai\AnthropicClient;

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

    /**
     * Rank approved bluebooks by relevance to a free-text research topic and
     * attach a per-paper summary to each match. Ranking is purely local
     * (token overlap — see SimilarityService); only the summary text for the
     * top AI_SUMMARY_LIMIT results is swapped for an AI-written one, and only
     * when ANTHROPIC_API_KEY is configured. AI failures of any kind (no key,
     * refusal, network error, malformed response) fall back to the existing
     * extractive summary — search() never errors or returns fewer results
     * because of the AI layer.
     */
    public static function search(string $topic, array $bluebooks, float $minScore = 0.05): array
    {
        $queryTokens = SimilarityService::tokenize($topic);
        $results     = [];

        foreach ($bluebooks as $book) {
            $bookTokens = array_merge(
                SimilarityService::tokenize($book['title'] ?? ''),
                SimilarityService::tokenize($book['abstract'] ?? ''),
                array_map('strtolower', $book['keywords'] ?? [])
            );
            $metaScore = SimilarityService::jaccard($queryTokens, $bookTokens);

            // OCR full-text containment — how much of the query is found inside
            // the document body, as a boost on top of the metadata match. Kept
            // separate from jaccard() above: merging a whole document's tokens
            // into one big set would swamp the union and crush every score.
            $ocrScore = 0.0;
            $ocrText = trim($book['ocrText'] ?? '');
            if ($ocrText !== '') {
                $ocrScore = SimilarityService::overlapCoefficient($queryTokens, SimilarityService::tokenize($ocrText));
            }

            $score = ($metaScore * 0.7) + ($ocrScore * 0.3);
            if ($score < $minScore) continue;

            $results[] = [
                'bluebook'    => $book,
                'score'       => $score,
                'relevance'   => round($score * 100, 1),
                'summary'     => self::summarize($book, $queryTokens),
                'aiGenerated' => false,
            ];
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        if (!empty($results) && AnthropicClient::isConfigured()) {
            self::applyAiSummaries($results, $topic);
        }

        return $results;
    }

    /**
     * Overwrites the 'summary' field (in place) for the top AI_SUMMARY_LIMIT
     * results with an AI-written summary grounded in each paper's actual
     * content — title, abstract, and an excerpt of its OCR'd full text, not
     * just keyword overlap. One batched request covers every candidate.
     */
    private static function applyAiSummaries(array &$results, string $topic): void
    {
        $candidates = array_slice($results, 0, self::AI_SUMMARY_LIMIT, preserve_keys: true);

        $papersBlock = '';
        foreach ($candidates as $r) {
            $book = $r['bluebook'];
            $ocrExcerpt = mb_substr(trim($book['ocrText'] ?? ''), 0, self::AI_OCR_EXCERPT_CHARS);

            $papersBlock .= "### Paper id={$book['id']}\n";
            $papersBlock .= "Title: {$book['title']}\n";
            $papersBlock .= 'Authors: ' . implode(', ', $book['authors'] ?? []) . "\n";
            $papersBlock .= "Department/Program: {$book['department']} / {$book['program']}\n";
            $papersBlock .= "Abstract: " . ($book['abstract'] ?: '(none provided)') . "\n";
            $papersBlock .= $ocrExcerpt !== ''
                ? "Excerpt from uploaded document text:\n{$ocrExcerpt}\n"
                : "(No OCR'd document text available for this paper — base the summary on the abstract only.)\n";
            $papersBlock .= "\n";
        }

        $userContent = "Research topic: \"{$topic}\"\n\nCandidate papers from the archive, ranked by keyword relevance (do not re-rank them):\n\n{$papersBlock}";

        $system = <<<SYS
You are a literature-review assistant for AMSDEN, a capstone/thesis archive at Camarines Sur Polytechnic Colleges (CSPC). For each candidate paper, write a 2-3 sentence summary explaining specifically how that paper relates to the student's research topic. Ground every claim in the title, abstract, or document excerpt actually provided — never invent findings, methods, or results that aren't in the given text. If only the abstract is available for a paper, summarize from that alone and do not claim to describe its full methodology or results. Write in a neutral, academic tone suitable for an undergraduate literature review. Do not re-rank or re-score the papers — only summarize.
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
            ],
            'required' => ['papers'],
            'additionalProperties' => false,
        ];

        $response = AnthropicClient::createJsonMessage($system, $userContent, $schema, maxTokens: 3072);
        if ($response === null || !isset($response['papers'])) {
            return; // extractive summaries already in place — nothing to do
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
    }

    /**
     * Extractive summary: pulls the abstract sentences most relevant to the
     * search topic. Used as the initial summary for every result, and as
     * the permanent fallback for results applyAiSummaries() doesn't reach
     * (beyond AI_SUMMARY_LIMIT, or when the AI call fails for any reason).
     */
    public static function summarize(array $book, array $queryTokens): string
    {
        $abstract = trim($book['abstract'] ?? '');
        if ($abstract === '') {
            return 'No abstract available for this bluebook.';
        }

        $sentences = preg_split('/(?<=[.!?])\s+/', $abstract, -1, PREG_SPLIT_NO_EMPTY);
        if (count($sentences) <= 2) {
            return $abstract;
        }

        $overlaps = [];
        foreach ($sentences as $i => $sentence) {
            $tokens        = SimilarityService::tokenize($sentence);
            $overlaps[$i]  = count(array_intersect($tokens, $queryTokens));
        }

        arsort($overlaps);
        $topIndexes = array_slice(array_keys($overlaps), 0, 2);
        sort($topIndexes);

        return implode(' ', array_map(fn($i) => $sentences[$i], $topIndexes));
    }
}
