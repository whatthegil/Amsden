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
        return count(self::matchedWords($set, $other));
    }

    /** The words of $set that match a word of $other, as wildcardMatches() counts them. */
    private static function matchedWords(array $set, array $other): array
    {
        // " w1 w2 w3 ": one substring search finds a word inside any of them,
        // so a long OCR'd document isn't walked word by word.
        $haystack = ' ' . implode(' ', $other) . ' ';
        $lookup   = array_flip($other);

        $matched = [];
        foreach ($set as $word) {
            $found = strlen($word) < self::WILDCARD_MIN
                ? str_contains($haystack, ' ' . $word . ' ')
                : str_contains($haystack, $word) || self::holdsWordOf($word, $lookup);
            if ($found) $matched[] = $word;
        }

        return $matched;
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

    /**
     * Why computeSimilarity() gave its score: what each field added, the words
     * that matched, and the bluebook's and proposal's text with those words
     * marked. Only the results being shown are explained, so the archive-wide
     * pass stays on the cheaper scorer. Every text in the result is escaped HTML.
     */
    public static function explain(
        string $proposedTitle,
        array  $proposedKeywords,
        string $proposedAbstract,
        array  $bluebook
    ): array {
        $propTitle    = array_unique(self::tokenize($proposedTitle));
        $propKw       = array_unique(self::keywordTokens($proposedKeywords));
        $propAbstract = array_unique(self::tokenize($proposedAbstract));
        $propAll      = array_unique(array_merge($propTitle, $propKw, $propAbstract));

        $bookTitle    = array_unique(self::tokenize($bluebook['title'] ?? ''));
        $bookKw       = array_unique(self::keywordTokens($bluebook['keywords'] ?? []));
        $bookAbstract = array_unique(self::tokenize($bluebook['abstract'] ?? ''));
        $ocrText      = trim($bluebook['ocrText'] ?? '');
        $bookOcr      = $ocrText === '' ? [] : array_unique(self::tokenize($ocrText));

        // Same pairings and weights as computeSimilarity().
        $pairs = [
            ['Title',     0.50, $propTitle,    $bookTitle,    false],
            ['Keywords',  0.20, $propKw,       $bookKw,       false],
            ['Abstract',  0.10, $propAbstract, $bookAbstract, false],
            ['Full text', 0.20, $propAll,      $bookOcr,      true],
        ];

        $fields     = [];
        $propTerms  = [];
        $bookTerms  = [];
        $ocrTerms   = [];
        foreach ($pairs as [$label, $weight, $prop, $book, $isOcr]) {
            $compared = !empty($prop) && !empty($book);
            $score    = !$compared ? 0.0 : ($isOcr ? self::overlapCoefficient($prop, $book) : self::dice($prop, $book));
            $propHits = $compared ? self::matchedWords($prop, $book) : [];

            if ($isOcr) {
                $ocrTerms = $compared ? self::matchedWords($book, $prop) : [];
            } else {
                $bookTerms = array_merge($bookTerms, $compared ? self::matchedWords($book, $prop) : []);
            }
            $propTerms = array_merge($propTerms, $propHits);

            $fields[] = self::fieldRow($label, $weight, $score, $compared,
                self::surfaceForms($proposedTitle . ' ' . implode(' ', $proposedKeywords) . ' ' . $proposedAbstract, $propHits));
        }

        return [
            'fields'   => $fields,
            'book'     => self::bookHighlights($bluebook, $bookTerms),
            'excerpts' => self::excerpts($ocrText, $ocrTerms),
            'proposal' => [
                'title'    => self::highlight($proposedTitle, $propTerms),
                'abstract' => trim($proposedAbstract) === '' ? null : self::highlight($proposedAbstract, $propTerms),
            ],
            'proposalExcerpts' => [],
        ];
    }

    /**
     * Why computeSimilarityFromText() gave its score, in the shape explain()
     * returns: the words of each bluebook field found in the pre-proposal, and
     * passages of both documents with those words marked.
     */
    public static function explainFromText(string $proposalText, array $bluebook): array
    {
        $proposal = array_unique(self::tokenize($proposalText));
        $ocrText  = trim($bluebook['ocrText'] ?? '');

        $pairs = [
            ['Title',     0.45, array_unique(self::tokenize($bluebook['title'] ?? ''))],
            ['Keywords',  0.15, array_unique(self::keywordTokens($bluebook['keywords'] ?? []))],
            ['Abstract',  0.15, array_unique(self::tokenize($bluebook['abstract'] ?? ''))],
            ['Full text', 0.25, $ocrText === '' ? [] : array_unique(self::tokenize($ocrText))],
        ];

        // Fields the bluebook lacks drop out and the rest share their weight.
        $weightSum = 0.0;
        foreach ($pairs as [, $weight, $tokens]) {
            if (!empty($tokens) && !empty($proposal)) $weightSum += $weight;
        }

        $fields    = [];
        $bookTerms = [];
        $ocrTerms  = [];
        $propTerms = [];
        foreach ($pairs as [$label, $weight, $tokens]) {
            $compared = !empty($tokens) && !empty($proposal);
            $score    = $compared ? self::overlapCoefficient($tokens, $proposal) : 0.0;
            $bookHits = $compared ? self::matchedWords($tokens, $proposal) : [];

            if ($label === 'Full text') {
                $ocrTerms = $bookHits;
                $source   = $ocrText;
            } else {
                $bookTerms = array_merge($bookTerms, $bookHits);
                $source    = $label === 'Keywords' ? implode(' ', $bluebook['keywords'] ?? []) : ($bluebook[strtolower($label)] ?? '');
            }
            if ($compared) {
                $propTerms = array_merge($propTerms, self::matchedWords($proposal, $tokens));
            }

            $fields[] = self::fieldRow($label, $weightSum > 0.0 ? $weight / $weightSum : 0.0, $score, $compared,
                self::surfaceForms($source, $bookHits));
        }

        return [
            'fields'           => $fields,
            'book'             => self::bookHighlights($bluebook, $bookTerms),
            'excerpts'         => self::excerpts($ocrText, $ocrTerms),
            'proposal'         => null,
            'proposalExcerpts' => self::excerpts($proposalText, array_unique($propTerms)),
        ];
    }

    private static function fieldRow(string $label, float $weight, float $score, bool $compared, array $words): array
    {
        return [
            'label'    => $label,
            'compared' => $compared,
            'weight'   => round($weight * 100, 1),
            'score'    => round($score * 100, 1),
            'points'   => round($score * $weight * 100, 1),
            'words'    => array_slice($words, 0, 15),
        ];
    }

    private static function bookHighlights(array $bluebook, array $terms): array
    {
        return [
            'title'    => self::highlight($bluebook['title'] ?? '', $terms),
            'keywords' => array_map(fn($kw) => self::highlight($kw, $terms), $bluebook['keywords'] ?? []),
            'abstract' => trim($bluebook['abstract'] ?? '') === '' ? null : self::highlight($bluebook['abstract'], $terms),
        ];
    }

    /**
     * $text as escaped HTML with every word whose stem is in $terms wrapped
     * in <mark>.
     */
    public static function highlight(string $text, array $terms): string
    {
        $lookup = array_flip($terms);
        $parts  = preg_split('/([\p{L}\p{N}]+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];

        $html = '';
        foreach ($parts as $i => $part) {
            $safe  = htmlspecialchars($part, ENT_QUOTES, 'UTF-8');
            $html .= ($i % 2 === 1 && self::isTerm($part, $lookup)) ? '<mark>' . $safe . '</mark>' : $safe;
        }

        return $html;
    }

    private static function isTerm(string $word, array $lookup): bool
    {
        $normal = SearchService::normalize($word);
        return $normal !== '' && !str_contains($normal, ' ') && isset($lookup[SearchService::stem($normal)]);
    }

    /** The words of $text that stand for $terms, as written, each once. */
    private static function surfaceForms(string $text, array $terms): array
    {
        if (empty($terms)) return [];
        $lookup = array_flip($terms);

        $forms = [];
        preg_match_all('/[\p{L}\p{N}]+/u', $text, $m);
        foreach ($m[0] as $word) {
            $lower = mb_strtolower($word);
            if (!isset($forms[$lower]) && self::isTerm($word, $lookup)) $forms[$lower] = true;
        }

        return array_keys($forms);
    }

    /**
     * Up to $max passages of $text with the most distinct $terms, in reading
     * order, highlighted. OCR text often has no sentence breaks, so long runs
     * are cut into windows of words.
     */
    private static function excerpts(string $text, array $terms, int $max = 3): array
    {
        if ($text === '' || empty($terms)) return [];
        $lookup = array_flip($terms);

        $passages = [];
        foreach (preg_split('/(?<=[.!?])\s+|\R+/u', $text, -1, PREG_SPLIT_NO_EMPTY) as $sentence) {
            $words = preg_split('/\s+/u', trim($sentence), -1, PREG_SPLIT_NO_EMPTY);
            foreach (array_chunk($words, 40) as $chunk) {
                $passages[] = implode(' ', $chunk);
            }
        }

        $scored = [];
        foreach ($passages as $i => $passage) {
            if (mb_strlen($passage) < 20) continue;
            $hits = count(array_intersect_key(array_flip(self::tokenize($passage)), $lookup));
            if ($hits >= 2) $scored[] = [$hits, $i, $passage];
        }

        usort($scored, fn($a, $b) => [$b[0], $a[1]] <=> [$a[0], $b[1]]);
        $top = array_slice($scored, 0, $max);
        usort($top, fn($a, $b) => $a[1] <=> $b[1]);

        return array_map(fn($p) => self::highlight($p[2], $terms), $top);
    }

}
