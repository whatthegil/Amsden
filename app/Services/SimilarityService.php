<?php

namespace App\Services;

class SimilarityService
{
    private const STOPWORDS = [
        'a','an','the','and','or','but','in','on','at','to','for','of','with',
        'by','from','is','are','was','were','be','been','being','have','has',
        'had','do','does','did','will','would','could','should','may','might',
        'shall','can','this','that','these','those','it','its','as','if','then',
        'than','so','yet','each','every','all','any','few','more','most','other',
        'some','such','no','not','only','own','same','too','very','just','into',
        'through','during','before','after','above','below','between','out','off',
        'over','under','about','up','use','using','used','based','based',
    ];

    public static function tokenize(string $text): array
    {
        $text  = strtolower($text);
        $text  = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_filter($words, fn($w) => strlen($w) > 2 && !in_array($w, self::STOPWORDS)));
    }

    public static function jaccard(array $a, array $b): float
    {
        $setA = array_unique($a);
        $setB = array_unique($b);
        if (empty($setA) || empty($setB)) return 0.0;
        $intersection = array_intersect($setA, $setB);
        $union = array_unique(array_merge($setA, $setB));
        return count($intersection) / count($union);
    }

    /**
     * Fraction of the smaller set's tokens found in the larger set — unlike
     * jaccard(), this doesn't collapse toward 0 when comparing a short query
     * against a much longer bag of words (e.g. a full OCR'd document), since
     * the denominator is min(|A|,|B|) rather than |A ∪ B|.
     */
    public static function overlapCoefficient(array $a, array $b): float
    {
        $setA = array_unique($a);
        $setB = array_unique($b);
        if (empty($setA) || empty($setB)) return 0.0;
        $intersection = array_intersect($setA, $setB);
        return count($intersection) / min(count($setA), count($setB));
    }

    public static function computeSimilarity(
        string $proposedTitle,
        array  $proposedKeywords,
        string $proposedAbstract,
        array  $bluebook
    ): float {
        // Title similarity — primary signal (50% weight)
        $titleSim = self::jaccard(
            self::tokenize($proposedTitle),
            self::tokenize($bluebook['title'] ?? '')
        );

        // Keyword overlap (20% weight) — skipped if either side has no keywords
        $kwSim = 0.0;
        $propKw = array_values(array_filter(array_map(
            fn($k) => strtolower(trim($k)),
            $proposedKeywords
        )));
        $bookKw = array_values(array_filter(array_map(
            fn($k) => strtolower(trim($k)),
            $bluebook['keywords'] ?? []
        )));
        if (!empty($propKw) && !empty($bookKw)) {
            $kwSim = self::jaccard($propKw, $bookKw);
        }

        // Abstract similarity (10% weight) — skipped if either side is blank
        $abstractSim = 0.0;
        if (!empty(trim($proposedAbstract)) && !empty(trim($bluebook['abstract'] ?? ''))) {
            $abstractSim = self::jaccard(
                self::tokenize($proposedAbstract),
                self::tokenize($bluebook['abstract'])
            );
        }

        // OCR full-text overlap (20% weight) — catches proposals that echo
        // wording from inside an existing document, not just its metadata.
        // Skipped if the bluebook has no completed OCR text yet.
        $ocrSim = 0.0;
        $ocrText = trim($bluebook['ocrText'] ?? '');
        if ($ocrText !== '') {
            $proposedTokens = array_merge(
                self::tokenize($proposedTitle),
                array_map('strtolower', $propKw),
                self::tokenize($proposedAbstract)
            );
            if (!empty($proposedTokens)) {
                $ocrSim = self::overlapCoefficient($proposedTokens, self::tokenize($ocrText));
            }
        }

        return ($titleSim * 0.50) + ($kwSim * 0.20) + ($abstractSim * 0.10) + ($ocrSim * 0.20);
    }

    /**
     * Duplicate-risk score for a whole pre-proposal document (its full
     * extracted text) against an existing bluebook — the upload path of the
     * similarity checker, where the proposal isn't split into title /
     * keywords / abstract fields.
     *
     * For each field the bluebook actually has, we measure what fraction of
     * that field's vocabulary also appears somewhere in the proposal
     * (overlapCoefficient — denominator is the short field, not the long
     * proposal, so a match isn't punished for the proposal being verbose),
     * then combine the fields by weight, renormalising over whichever fields
     * were present. Title carries the most weight, mirroring computeSimilarity().
     */
    public static function computeSimilarityFromText(string $proposalText, array $bluebook): float
    {
        $proposalTokens = self::tokenize($proposalText);
        if (empty($proposalTokens)) return 0.0;

        $fields = [
            // [field tokens, weight]
            [self::tokenize($bluebook['title'] ?? ''), 0.45],
            [array_map(fn($k) => strtolower(trim($k)), $bluebook['keywords'] ?? []), 0.15],
            [self::tokenize($bluebook['abstract'] ?? ''), 0.15],
            [self::tokenize($bluebook['ocrText'] ?? ''), 0.25],
        ];

        $score     = 0.0;
        $weightSum = 0.0;
        foreach ($fields as [$tokens, $weight]) {
            $tokens = array_values(array_filter($tokens, fn($t) => strlen($t) > 2));
            if (empty($tokens)) continue;
            $score     += self::overlapCoefficient($tokens, $proposalTokens) * $weight;
            $weightSum += $weight;
        }

        return $weightSum > 0.0 ? $score / $weightSum : 0.0;
    }

    /**
     * Relevance of a free-text search query against a bluebook, for ranking
     * search results (as opposed to computeSimilarity(), which compares two
     * documents against each other for duplicate detection).
     *
     * Uses overlapCoefficient() per field — "what fraction of the query's
     * words appear in this field" — since search queries are typically a
     * couple of keywords, and jaccard() would unfairly punish a match against
     * a long title/abstract/OCR text just for having other words too.
     */
    public static function searchRelevance(string $query, array $bluebook): float
    {
        $queryTokens = self::tokenize($query);
        if (empty($queryTokens)) return 0.0;

        $titleScore = self::overlapCoefficient($queryTokens, self::tokenize($bluebook['title'] ?? ''));

        $keywordTokens = array_map(fn($k) => strtolower(trim($k)), $bluebook['keywords'] ?? []);
        $keywordScore  = self::overlapCoefficient($queryTokens, $keywordTokens);

        $abstractScore = self::overlapCoefficient($queryTokens, self::tokenize($bluebook['abstract'] ?? ''));

        $authorTokens = self::tokenize(implode(' ', $bluebook['authors'] ?? []));
        $authorScore  = self::overlapCoefficient($queryTokens, $authorTokens);

        $ocrScore = 0.0;
        $ocrText  = trim($bluebook['ocrText'] ?? '');
        if ($ocrText !== '') {
            $ocrScore = self::overlapCoefficient($queryTokens, self::tokenize($ocrText));
        }

        // Small bonus when the whole query appears as a literal phrase in the
        // title, so an exact match ranks above documents that only share
        // scattered individual words.
        $phraseBonus = stripos($bluebook['title'] ?? '', trim($query)) !== false ? 0.25 : 0.0;

        return $phraseBonus
            + ($titleScore    * 0.40)
            + ($keywordScore  * 0.20)
            + ($abstractScore * 0.15)
            + ($authorScore   * 0.10)
            + ($ocrScore      * 0.15);
    }
}
