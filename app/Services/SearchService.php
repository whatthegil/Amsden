<?php

namespace App\Services;

use App\Models\Bluebook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Archive search that forgives the way people actually type.
 *
 * Every word of the query is matched on its own, in any order, and a word
 * counts as found when it appears:
 *
 *   - exactly                      "system"      in "system"
 *   - as the start of a word       "detect"      in "detection"
 *   - in another form of itself    "systems"     in "system", "studies" in "study"
 *   - misspelt by a letter or two  "attendence"  in "attendance"
 *
 * across the title, keywords, authors, abstract, program, adviser and the text
 * inside the document. Case, accents (Cariño = carino) and punctuation
 * (AI-Powered = ai powered) are ignored, a four-digit number matches the year,
 * and a department code matches the department. Two-letter terms such as "AI"
 * or "QR" are kept, but match only whole words.
 *
 * Results that contain every word come first, then the ones with most of them;
 * within those, a match in the title outranks one in the keywords, which
 * outranks one in the abstract, and so on down to the document text.
 */
class SearchService
{
    /** How much a match in each field is worth. */
    private const WEIGHTS = [
        'title'      => 10,
        'keywords'   => 7,
        'authors'    => 6,
        'abstract'   => 4,
        'program'    => 2,
        'adviser'    => 2,
        'department' => 3,
        'year'       => 3,
        'content'    => 2,
    ];

    /** Words that carry no meaning in a search; dropped unless they are all there is. */
    private const STOPWORDS = [
        'a', 'an', 'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of',
        'with', 'by', 'from', 'is', 'are', 'was', 'were', 'be', 'been', 'as', 'it',
        'its', 'this', 'that', 'these', 'those', 'into', 'using', 'via', 'about',
    ];

    /**
     * Rank the bluebooks a query finds.
     *
     * @param  Builder $query  bluebooks already narrowed by any filters
     * @return array<int, float>  id => score, best first; only matches
     */
    public static function rank(string $search, Builder $query): array
    {
        return array_map(fn($m) => $m['rank'], self::match($search, $query));
    }

    /**
     * Every bluebook the query finds, with how it was found.
     *
     * @return array<int, array{rank: float, found: int, terms: int, score: float}>
     *         id => details, best first. found is how many of the query's
     *         terms the paper contains; score how strongly (field weights).
     */
    public static function match(string $search, Builder $query): array
    {
        $terms = self::terms($search);
        if ($terms === []) {
            return [];
        }

        // The document text runs to hundreds of kilobytes a paper, so it is
        // never loaded here: the database is asked which papers contain each
        // term, and everything else is scored in PHP from the short fields.
        $contentHits = self::contentHits($terms, clone $query);

        $rows = (clone $query)
            ->select(['id', 'title', 'keywords', 'authors', 'abstract', 'program', 'adviser', 'department', 'year'])
            ->get();

        $phrase = self::normalize($search);
        $scores = [];

        foreach ($rows as $row) {
            $fields = [
                'title'      => self::words($row->title),
                'keywords'   => self::words(implode(' ', (array) $row->keywords)),
                'authors'    => self::words(implode(' ', (array) $row->authors)),
                'abstract'   => self::words($row->abstract),
                'program'    => self::words($row->program),
                'adviser'    => self::words($row->adviser),
                'department' => self::words($row->department),
                'year'       => [(string) $row->year],
            ];

            $found = 0;
            $score = 0.0;

            foreach ($terms as $term) {
                $best = 0.0;

                foreach ($fields as $field => $words) {
                    $quality = self::matchQuality($term, $words);
                    if ($quality > 0) {
                        $best = max($best, $quality * self::WEIGHTS[$field]);
                    }
                }
                if (isset($contentHits[$term][$row->id])) {
                    $best = max($best, self::WEIGHTS['content']);
                }

                if ($best > 0) {
                    $found++;
                    $score += $best;
                }
            }

            if ($found === 0) {
                continue;
            }

            // Every word found outranks most words found, whatever the fields.
            $total = $found / count($terms) * 1000 + $score;

            // The whole query, as typed, inside the title.
            if (count($terms) > 1 && str_contains(' ' . implode(' ', $fields['title']) . ' ', ' ' . $phrase . ' ')) {
                $total += 50;
            }

            $scores[$row->id] = ['rank' => $total, 'found' => $found, 'terms' => count($terms), 'score' => $score];
        }

        uasort($scores, fn($a, $b) => $b['rank'] <=> $a['rank']);
        return $scores;
    }

    /**
     * The words to look for: normalised, meaningless ones dropped, each once.
     *
     * @return string[]
     */
    public static function terms(string $search): array
    {
        $words = self::words(mb_substr($search, 0, 200));
        $kept  = array_values(array_filter($words, fn($w) => !in_array($w, self::STOPWORDS, true)));

        // A query of nothing but common words still means something to whoever
        // typed it: search for those rather than for nothing.
        return array_values(array_unique($kept !== [] ? $kept : $words));
    }

    /** How many of the terms a piece of text contains, matched as the search matches. */
    public static function countFound(array $terms, ?string $text): int
    {
        $words = self::words($text);

        return count(array_filter($terms, fn($t) => self::matchQuality($t, $words) > 0));
    }

    /** Lowercase, accents and punctuation gone, single-spaced. */
    public static function normalize(?string $text): string
    {
        $text = Str::lower(Str::ascii((string) $text));
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text);

        return trim($text);
    }

    /** @return string[] */
    private static function words(?string $text): array
    {
        $normal = self::normalize($text);

        return $normal === '' ? [] : array_values(array_filter(explode(' ', $normal), fn($w) => $w !== ''));
    }

    /**
     * How well a term matches a field's words: 1 exact, 0.85 another form of
     * the same word, 0.75 the start of a word, 0.6 a near spelling, 0 none.
     */
    private static function matchQuality(string $term, array $words): float
    {
        if ($words === []) {
            return 0.0;
        }

        $len  = strlen($term);
        $stem = self::stem($term);
        $best = 0.0;

        foreach ($words as $word) {
            if ($word === $term) {
                return 1.0;
            }

            // Short terms ("ai", "qr", "iot") match whole words only: as a
            // prefix they would find half the archive.
            if ($len < 3) {
                continue;
            }

            if (self::stem($word) === $stem) {
                $best = max($best, 0.85);
            } elseif (str_starts_with($word, $term)) {
                $best = max($best, 0.75);
            } elseif ($len >= 5 && abs(strlen($word) - $len) <= 2
                && levenshtein($term, $word) <= ($len >= 8 ? 2 : 1)) {
                $best = max($best, 0.6);
            }
        }

        return $best;
    }

    /**
     * A word with its common endings taken off, so the forms of a word meet:
     * systems/system, studies/study, monitoring/monitor, detected/detect.
     */
    public static function stem(string $word): string
    {
        if (strlen($word) <= 4) {
            return $word;
        }

        foreach (['ational' => 'ate', 'ization' => 'ize', 'ations' => 'ate', 'ation' => 'ate',
                  'ies' => 'y', 'ing' => '', 'ers' => '', 'er' => '', 'ed' => '', 'es' => '', 's' => ''] as $suffix => $swap) {
            if (str_ends_with($word, $suffix) && strlen($word) - strlen($suffix) >= 3) {
                // "classes" loses "es", "process" keeps its "ss".
                if ($suffix === 's' && str_ends_with($word, 'ss')) {
                    return $word;
                }
                return substr($word, 0, -strlen($suffix)) . $swap;
            }
        }

        return $word;
    }

    /**
     * Which bluebooks carry each term in their document text, asked of the
     * database so the text itself never leaves it.
     *
     * @return array<string, array<int, true>>  term => [id => true]
     */
    private static function contentHits(array $terms, Builder $query): array
    {
        $hits = [];

        foreach ($terms as $term) {
            // Short terms are too common inside running text to mean anything.
            if (strlen($term) < 3) {
                continue;
            }

            // The stem finds the other forms of the word too. Escaped, so a
            // % or _ in a query is a character to find, not a wildcard.
            $needle = '%' . addcslashes(self::stem($term), '%_\\') . '%';

            $ids = (clone $query)
                ->whereNotNull('ocr_text')
                ->where('ocr_text', 'LIKE', $needle)
                ->pluck('id');

            foreach ($ids as $id) {
                $hits[$term][$id] = true;
            }
        }

        return $hits;
    }
}
