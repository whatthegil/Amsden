<?php

namespace Tests\Feature;

use App\Models\Bluebook;
use App\Services\SearchService;
use App\Services\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Search finds a paper however the reader happens to type what they want. */
class SearchTest extends TestCase
{
    use RefreshDatabase;

    private function paper(array $overrides = []): Bluebook
    {
        return Bluebook::create(array_merge([
            'title' => 'Untitled', 'authors' => ['Dela Cruz, Maria'], 'year' => 2024,
            'department' => 'CCS', 'program' => 'Bachelor of Science in Information Technology',
            'keywords' => [], 'abstract' => '', 'adviser' => 'Dr. Reyes', 'status' => 'Approved',
            'uploaded_by' => 's@my.cspc.edu.ph', 'uploaded_by_name' => 'S T', 'date_added' => '2024-06-15',
        ], $overrides));
    }

    private function titles(string $q): array
    {
        return array_column(Store::getApprovedBluebooks($q), 'title');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->paper(['title' => 'AI-Powered Crop Disease Detection Using Convolutional Neural Networks',
                      'keywords' => ['machine learning', 'agriculture'], 'year' => 2023]);
        $this->paper(['title' => 'Automated Student Attendance Monitoring Systems Using RFID',
                      'keywords' => ['RFID', 'attendance'], 'authors' => ['Cariño, Ana'], 'department' => 'CEA']);
        $this->paper(['title' => 'Library Inventory Management', 'abstract' => 'Tracks the books each library holds.']);
    }

    public function test_words_in_any_order(): void
    {
        $this->assertSame(['AI-Powered Crop Disease Detection Using Convolutional Neural Networks'],
            $this->titles('detection crop disease'));
    }

    public function test_misspelt_words(): void
    {
        $this->assertSame(['Automated Student Attendance Monitoring Systems Using RFID'], $this->titles('attendence'));
        $this->assertSame(['AI-Powered Crop Disease Detection Using Convolutional Neural Networks'], $this->titles('convolutonal'));
    }

    public function test_other_forms_of_a_word(): void
    {
        $this->assertContains('Automated Student Attendance Monitoring Systems Using RFID', $this->titles('system monitor'));
        $this->assertContains('AI-Powered Crop Disease Detection Using Convolutional Neural Networks', $this->titles('detect'));
    }

    public function test_short_terms_match_whole_words_only(): void
    {
        // "ai" is a word in "AI-Powered", and only part of "Maintain" or "Detail".
        $this->assertSame(['AI-Powered Crop Disease Detection Using Convolutional Neural Networks'], $this->titles('AI'));
    }

    public function test_case_accents_and_punctuation_do_not_matter(): void
    {
        $this->assertSame(['Automated Student Attendance Monitoring Systems Using RFID'], $this->titles('carino'));
        $this->assertSame(['AI-Powered Crop Disease Detection Using Convolutional Neural Networks'], $this->titles('ai powered'));
        $this->assertSame(['Automated Student Attendance Monitoring Systems Using RFID'], $this->titles('rfid!!'));
    }

    public function test_a_year_or_department_is_a_search_term(): void
    {
        $this->assertSame(['AI-Powered Crop Disease Detection Using Convolutional Neural Networks'], $this->titles('2023'));
        $this->assertSame(['Automated Student Attendance Monitoring Systems Using RFID'], $this->titles('cea'));
    }

    public function test_every_word_found_ranks_above_some(): void
    {
        $titles = $this->titles('library attendance');
        $this->paper(['title' => 'Library Attendance Tracking']);

        $this->assertSame('Library Attendance Tracking', $this->titles('library attendance')[0]);
        $this->assertCount(2, $titles);
    }

    public function test_the_text_inside_the_document_is_searched(): void
    {
        $this->paper(['title' => 'A Plain Title', 'ocr_text' => 'Chapter 3 describes the hydroponics setup in detail.']);

        $this->assertSame(['A Plain Title'], $this->titles('hydroponic'));
    }

    public function test_wildcard_characters_are_just_characters(): void
    {
        $this->assertSame([], $this->titles('%'));
        $this->assertSame([], $this->titles('_'));
    }

    public function test_nothing_matching_finds_nothing(): void
    {
        $this->assertSame([], $this->titles('blockchain'));
    }

    public function test_unposted_papers_are_not_found(): void
    {
        $this->paper(['title' => 'Blockchain Credentials', 'status' => 'Pending']);

        $this->assertSame([], $this->titles('blockchain'));
    }

    public function test_stems_bring_word_forms_together(): void
    {
        $this->assertSame('system', SearchService::stem('systems'));
        $this->assertSame('study', SearchService::stem('studies'));
        $this->assertSame('monitor', SearchService::stem('monitoring'));
        $this->assertSame('process', SearchService::stem('process'));
    }
}
