<?php

namespace Tests\Unit;

use App\Services\SimilarityService;
use PHPUnit\Framework\TestCase;

/** The similarity check compares words, never their order. */
class SimilarityWordOrderTest extends TestCase
{
    private array $bluebook = [
        'title'    => 'Implementation of an Automated Student Attendance System Using RFID Technology',
        'keywords' => ['RFID', 'Attendance System', 'Automation'],
        'abstract' => '',
        'ocrText'  => '',
    ];

    public function test_a_shuffled_title_scores_as_the_title_does(): void
    {
        $inOrder  = SimilarityService::computeSimilarity('Automated Student Attendance System Using RFID Technology', [], '', $this->bluebook);
        $shuffled = SimilarityService::computeSimilarity('Technology RFID Using System Attendance Student Automated', [], '', $this->bluebook);

        $this->assertEqualsWithDelta($inOrder, $shuffled, 0.0001);
    }

    public function test_keywords_match_whatever_the_order_of_their_words(): void
    {
        $exact    = SimilarityService::computeSimilarity('', ['Attendance System'], '', $this->bluebook);
        $reversed = SimilarityService::computeSimilarity('', ['System Attendance'], '', $this->bluebook);

        $this->assertGreaterThan(0, $exact);
        $this->assertEqualsWithDelta($exact, $reversed, 0.0001);
    }

    public function test_a_few_random_title_words_clear_the_display_threshold(): void
    {
        // StudentController shows typed-title matches from 8%.
        $this->assertGreaterThanOrEqual(0.08, SimilarityService::computeSimilarity('RFID', [], '', $this->bluebook));
        $this->assertGreaterThanOrEqual(0.08, SimilarityService::computeSimilarity('attendance rfid', [], '', $this->bluebook));
    }

    public function test_other_forms_of_a_word_still_match(): void
    {
        $this->assertGreaterThan(0, SimilarityService::computeSimilarity('attendance systems', [], '', $this->bluebook));
        $this->assertSame(
            SimilarityService::tokenize('Attendance System'),
            SimilarityService::tokenize('attendance systems'),
        );
    }

    public function test_multi_word_keywords_count_in_an_uploaded_proposal(): void
    {
        $book = ['title' => '', 'keywords' => ['Attendance System'], 'abstract' => '', 'ocrText' => ''];

        $this->assertGreaterThan(0.9, SimilarityService::computeSimilarityFromText('a system for attendance', $book));
    }
}
