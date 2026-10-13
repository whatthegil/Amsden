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

    /**
     * The words of a text as a bag, so their order never matters: normalised
     * as the archive search does (case, accents, punctuation), common words
     * dropped, and each reduced to its stem so the forms of a word meet
     * (systems/system, monitoring/monitor). Two-letter terms such as "AI" or
     * "QR" are kept; they are often what a capstone is about.
     */
    public static function tokenize(string $text): array
    {
        $words = explode(' ', SearchService::normalize($text));
        $words = array_filter($words, fn($w) => strlen($w) >= 2 && !in_array($w, self::STOPWORDS, true));

        return array_values(array_map([SearchService::class, 'stem'], $words));
    }

    /**
     * Keywords as words rather than phrases, so "Attendance System" meets
     * "System Attendance" and a keyword shares credit with its parts.
     */
    public static function keywordTokens(array $keywords): array
    {
        return self::tokenize(implode(' ', $keywords));
    }

    /**
     * Shortest word matched as a wildcard (*word*). Shorter terms such as "AI",
     * "QR" or "iot" match whole words only: inside other words they would find
     * half the archive ("ai" in "detail", "iot" in "patriot").
     */
    private const WILDCARD_MIN = 4;

    /**
     * How many words of $set match a word of $other as a wildcard: the whole
     * word, the word anywhere inside one of $other's ("attend" in "attendance"),
     * or one of $other's inside it ("attendance" holds "attend").
     */
    private static function wildcardMatches(array $set, array $other): int
    {
        // " w1 w2 w3 ": one substring search finds a word inside any of them,
        // so a long OCR'd document isn't walked word by word.
        $haystack = ' ' . implode(' ', $other) . ' ';
        $lookup   = array_flip($other);

        $hits = 0;
        foreach ($set as $word) {
            $found = strlen($word) < self::WILDCARD_MIN
                ? str_contains($haystack, ' ' . $word . ' ')
                : str_contains($haystack, $word) || self::holdsWordOf($word, $lookup);
            if ($found) $hits++;
        }

        return $hits;
    }

    /** Whether some part of $word, WILDCARD_MIN letters or longer, is a word in $lookup. */
    private static function holdsWordOf(string $word, array $lookup): bool
    {
        $len = strlen($word);
        for ($size = $len - 1; $size >= self::WILDCARD_MIN; $size--) {
            for ($start = 0; $start + $size <= $len; $start++) {
                if (isset($lookup[substr($word, $start, $size)])) return true;
            }
        }
        return false;
    }

    /**
     * Matched words over the size of both sets (Sørensen-Dice), matched as
     * wildcards. Unlike shared over all words (Jaccard) it does not sink a
     * short query of a few words against a long title: "RFID attendance"
     * against a seven-word title scores 44%, not 29%.
     */
    public static function dice(array $a, array $b): float
    {
        $setA = array_unique($a);
        $setB = array_unique($b);
        if (empty($setA) || empty($setB)) return 0.0;

        // With whole words only this is 2|A∩B| / (|A|+|B|); a wildcard can
        // pair one word with several, so each side counts its own matches.
        $hits = self::wildcardMatches($setA, $setB) + self::wildcardMatches($setB, $setA);
        return $hits / (count($setA) + count($setB));
    }

    /**
     * Fraction of the smaller set's words that match the larger set as
     * wildcards. It doesn't collapse toward 0 when comparing a short query
     * against a much longer bag of words (e.g. a full OCR'd document), since
     * the denominator is min(|A|,|B|) rather than the size of both.
     */
    public static function overlapCoefficient(array $a, array $b): float
    {
        $setA = array_unique($a);
        $setB = array_unique($b);
        if (empty($setA) || empty($setB)) return 0.0;

        [$small, $large] = count($setA) <= count($setB) ? [$setA, $setB] : [$setB, $setA];
        return self::wildcardMatches($small, $large) / count($small);
    }

    public static function computeSimilarity(
        string $proposedTitle,
        array  $proposedKeywords,
        string $proposedAbstract,
        array  $bluebook
    ): float {
        // Title similarity — primary signal (50% weight)
        $titleSim = self::dice(
            self::tokenize($proposedTitle),
            self::tokenize($bluebook['title'] ?? '')
        );

        // Keyword overlap (20% weight) — skipped if either side has no keywords
        $kwSim = 0.0;
        $propKw = self::keywordTokens($proposedKeywords);
        $bookKw = self::keywordTokens($bluebook['keywords'] ?? []);
        if (!empty($propKw) && !empty($bookKw)) {
            $kwSim = self::dice($propKw, $bookKw);
        }

        // Abstract similarity (10% weight) — skipped if either side is blank
        $abstractSim = 0.0;
        if (!empty(trim($proposedAbstract)) && !empty(trim($bluebook['abstract'] ?? ''))) {
            $abstractSim = self::dice(
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
                $propKw,
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
            [self::keywordTokens($bluebook['keywords'] ?? []), 0.15],
            [self::tokenize($bluebook['abstract'] ?? ''), 0.15],
            [self::tokenize($bluebook['ocrText'] ?? ''), 0.25],
        ];

        $score     = 0.0;
        $weightSum = 0.0;
        foreach ($fields as [$tokens, $weight]) {
            if (empty($tokens)) continue;
            $score     += self::overlapCoefficient($tokens, $proposalTokens) * $weight;
            $weightSum += $weight;
        }

        return $weightSum > 0.0 ? $score / $weightSum : 0.0;
    }

}
