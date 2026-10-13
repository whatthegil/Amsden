<?php

namespace Tests\Unit;

use App\Services\SimilarityService;
use PHPUnit\Framework\TestCase;

/** The similarity check matches words as wildcards (*word*), not only whole. */
class SimilarityWildcardTest extends TestCase
{
    public function test_a_word_matches_inside_a_longer_word(): void
    {
        $this->assertSame(1.0, SimilarityService::overlapCoefficient(['attend'], ['attendance', 'system']));
        $this->assertSame(1.0, SimilarityService::overlapCoefficient(['monitor'], ['biomonitor']));
    }

    public function test_a_longer_word_matches_a_word_it_contains(): void
    {
        $this->assertSame(1.0, SimilarityService::overlapCoefficient(['attendance'], ['attend', 'system', 'school']));
    }

    public function test_dice_counts_wildcard_matches_from_both_sides(): void
    {
        // "attend" sits inside "attendance"; "rfid" matches whole; "school" nothing.
        $this->assertEqualsWithDelta(4 / 5, SimilarityService::dice(['attend', 'rfid'], ['attendance', 'rfid', 'school']), 0.0001);
    }

    public function test_whole_word_scores_are_unchanged(): void
    {
        $this->assertEqualsWithDelta(2 * 2 / 5, SimilarityService::dice(['rfid', 'attendance'], ['rfid', 'attendance', 'student']), 0.0001);
    }

    public function test_short_terms_match_whole_words_only(): void
    {
        $this->assertSame(0.0, SimilarityService::overlapCoefficient(['ai'], ['detail', 'trained']));
        $this->assertSame(0.0, SimilarityService::overlapCoefficient(['iot'], ['patriot']));
        $this->assertSame(1.0, SimilarityService::overlapCoefficient(['ai'], ['ai', 'chatbot']));
    }

    public function test_a_partial_title_word_raises_the_score(): void
    {
        $book = ['title' => 'Attendance Monitoring System', 'keywords' => [], 'abstract' => '', 'ocrText' => ''];

        $this->assertGreaterThan(0, SimilarityService::computeSimilarity('attend', [], '', $book));
    }
}
