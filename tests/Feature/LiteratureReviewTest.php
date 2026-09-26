<?php

namespace Tests\Feature;

use App\Models\Bluebook;
use App\Services\LiteratureReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** What a student writing a review of related literature gets from the assistant. */
class LiteratureReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openai.api_key' => null]);   // the local summaries, no network
    }

    private function paper(array $overrides = []): Bluebook
    {
        return Bluebook::create(array_merge([
            'title' => 'Untitled', 'authors' => ['Dela Cruz, Maria Anne'], 'year' => (int) date('Y'),
            'department' => 'CCS', 'program' => 'Bachelor of Science in Information Technology',
            'keywords' => [], 'abstract' => '', 'adviser' => 'Dr. Reyes', 'status' => 'Approved',
            'uploaded_by' => 's@my.cspc.edu.ph', 'uploaded_by_name' => 'S T', 'date_added' => '2024-06-15',
        ], $overrides));
    }

    private function titles(array $found): array
    {
        return array_map(fn($r) => $r['bluebook']['title'], $found['results']);
    }

    public function test_a_sentence_topic_finds_papers_that_cover_part_of_it(): void
    {
        $this->paper(['title' => 'Social Media Use and Academic Performance of Students']);
        $this->paper(['title' => 'A Library Inventory System', 'abstract' => 'Used by students and staff.']);

        $found = LiteratureReviewService::search('effects of social media on the academic performance of students');

        // The inventory paper shares only "students": a sixth of the topic.
        $this->assertSame(['Social Media Use and Academic Performance of Students'], $this->titles($found));
    }

    public function test_typos_and_word_forms_are_forgiven(): void
    {
        $this->paper(['title' => 'Attendance Monitoring Using RFID']);

        $this->assertCount(1, LiteratureReviewService::search('attendence monitor')['results']);
    }

    public function test_recent_work_only_when_asked(): void
    {
        $this->paper(['title' => 'Crop Disease Detection', 'year' => (int) date('Y') - 12]);
        $this->paper(['title' => 'Crop Disease Detection Revisited', 'year' => (int) date('Y') - 1]);

        $this->assertCount(2, LiteratureReviewService::search('crop disease')['results']);
        $this->assertSame(['Crop Disease Detection Revisited'], $this->titles(LiteratureReviewService::search('crop disease', 5)));
    }

    public function test_newest_first(): void
    {
        $this->paper(['title' => 'Crop Disease Detection', 'year' => 2020]);
        $this->paper(['title' => 'Crop Disease Survey', 'year' => 2025]);

        $this->assertSame(['Crop Disease Survey', 'Crop Disease Detection'],
            $this->titles(LiteratureReviewService::search('crop disease', null, 'newest')));
    }

    public function test_unposted_papers_are_never_included(): void
    {
        $this->paper(['title' => 'Crop Disease Detection', 'status' => 'Pending']);

        $this->assertSame([], LiteratureReviewService::search('crop disease')['results']);
    }

    public function test_each_result_carries_an_apa_citation(): void
    {
        $this->paper([
            'title'   => 'READINESS OF ACADEMIC LIBRARIES IN MAKERSPACE IMPLEMENTATION',
            'authors' => ['Tombado, Marygrace L.', 'Cariño, Maurine A.', 'Celaje, Jodie Bea B.'],
            'year'    => 2025,
        ]);

        $citation = LiteratureReviewService::search('makerspace libraries')['results'][0]['citation'];

        $this->assertSame(
            "Tombado, M. L., Cariño, M. A., & Celaje, J. B. B. (2025). Readiness of academic libraries in makerspace implementation [Unpublished bachelor's thesis]. Camarines Sur Polytechnic Colleges.",
            $citation['text']
        );
        $this->assertSame('(Tombado et al., 2025)', $citation['inText']);
        $this->assertStringContainsString('<em>Readiness of academic libraries in makerspace implementation</em>', $citation['html']);
    }

    public function test_in_text_citations_follow_the_number_of_authors(): void
    {
        $one = LiteratureReviewService::citation(['authors' => ['Dela Cruz, Maria'], 'year' => 2024, 'title' => 'T']);
        $two = LiteratureReviewService::citation(['authors' => ['Dela Cruz, Maria', 'Santos, Juan'], 'year' => 2024, 'title' => 'T']);

        $this->assertSame('(Dela Cruz, 2024)', $one['inText']);
        $this->assertSame('(Dela Cruz & Santos, 2024)', $two['inText']);
        $this->assertStringStartsWith('Dela Cruz, M., & Santos, J. (2024).', $two['text']);
    }

    public function test_the_page_offers_citations_to_copy(): void
    {
        $this->paper(['title' => 'Crop Disease Detection']);
        $student = ['id' => 2, 'name' => 'S T', 'email' => 's@my.cspc.edu.ph', 'role' => 'Student', 'canUpload' => false];

        $res = $this->withSession(['user' => $student])
            ->post('/student/literature-review', ['topic' => 'crop disease', 'within' => '', 'sort' => 'relevance']);

        $res->assertOk();
        $res->assertSee('APA 7 citation');
        $res->assertSee('Copy all 1 citations (APA 7)');
        $res->assertSee('data-copy=', false);
    }
}
