<?php

namespace Tests\Feature;

use App\Models\Bluebook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The browse page, and what it says about the list it is showing.
 *
 * The filter bar holds the current values, but a select reading "CCS" among
 * three other controls does not tell anyone why a search returned four papers
 * out of ninety. The chips do, and each one has to remove its own term without
 * taking the others with it.
 */
class BrowseBluebooksTest extends TestCase
{
    use RefreshDatabase;

    private function student(): array
    {
        return [
            'id' => 1, 'name' => 'S T', 'email' => 's@my.cspc.edu.ph',
            'role' => 'Student', 'canUpload' => false,
        ];
    }

    private function makeBluebook(array $overrides = []): Bluebook
    {
        return Bluebook::create(array_merge([
            'title' => 'An Automated Attendance System', 'authors' => ['Dela Cruz, Maria'],
            'year' => 2024, 'department' => 'CCS',
            'program' => 'Bachelor of Science in Information Technology',
            'keywords' => ['RFID', 'Automation'], 'abstract' => 'A study of attendance.',
            'adviser' => '', 'status' => 'Approved', 'uploaded_by' => 's@my.cspc.edu.ph',
            'uploaded_by_name' => 'S T', 'date_added' => '2024-06-15',
        ], $overrides));
    }

    public function test_the_list_renders_as_result_rows(): void
    {
        $this->makeBluebook();
        $this->makeBluebook(['title' => 'A Farm Management System', 'year' => 2023]);

        $res = $this->withSession(['user' => $this->student()])->get('/student/bluebooks');

        $res->assertOk();
        $res->assertSee('result-item', false);
        $res->assertSee('An Automated Attendance System', false);
        $res->assertSee('A Farm Management System', false);
        // Nothing is filtered, so nothing claims to be.
        $res->assertDontSee('filter-chip', false);
        $res->assertSee('approved', false);
    }

    /**
     * The point of the chips: each drops its own term and leaves the rest, so a
     * reader can widen one axis of a search without starting the search again.
     */
    public function test_each_chip_removes_only_its_own_filter(): void
    {
        $this->makeBluebook();

        $res = $this->withSession(['user' => $this->student()])
            ->get('/student/bluebooks?search=system&department=CCS&year=2024');

        $res->assertOk();
        $html = $res->getContent();

        // Three filters, so three chips and an escape hatch for all of them.
        $this->assertSame(3, substr_count($html, 'class="filter-chip"'));
        $this->assertStringContainsString('clear-all', $html);

        // The search chip's link keeps department and year.
        $this->assertMatchesRegularExpression(
            '/href="[^"]*department=CCS[^"]*year=2024[^"]*"/',
            $html,
            'Removing the search term must keep the other two filters.'
        );
        // And the year chip's link keeps search and department.
        $this->assertMatchesRegularExpression(
            '/href="[^"]*search=system[^"]*department=CCS[^"]*"/',
            $html,
            'Removing the year must keep the other two filters.'
        );
    }

    /** One filter needs no "clear all" - the single chip already is one. */
    public function test_a_single_filter_shows_no_clear_all(): void
    {
        $this->makeBluebook();

        $html = $this->withSession(['user' => $this->student()])
            ->get('/student/bluebooks?department=CCS')
            ->getContent();

        $this->assertSame(1, substr_count($html, 'class="filter-chip"'));
        $this->assertStringNotContainsString('clear-all', $html);
    }

    /**
     * An empty list has two quite different causes, and the way out of one is
     * not the way out of the other. A reader whose filters matched nothing
     * needs them cleared; a reader looking at an archive with nothing approved
     * in it yet just needs telling.
     */
    public function test_the_empty_state_depends_on_why_it_is_empty(): void
    {
        $this->makeBluebook();

        $filtered = $this->withSession(['user' => $this->student()])
            ->get('/student/bluebooks?search=nothingmatchesthis');
        $filtered->assertOk();
        $filtered->assertSee('No papers match these filters', false);
        $filtered->assertSee('Clear all filters', false);

        Bluebook::query()->delete();

        $bare = $this->withSession(['user' => $this->student()])->get('/student/bluebooks');
        $bare->assertOk();
        $bare->assertSee('No papers have been approved yet', false);
        $bare->assertDontSee('Clear all filters', false);
    }

    public function test_search_finds_words_in_any_order(): void
    {
        $this->makeBluebook(['title' => 'AI-Powered Crop Disease Detection Using Neural Networks']);
        $this->makeBluebook(['title' => 'A Library Inventory System', 'keywords' => ['inventory']]);

        $res = $this->withSession(['user' => $this->student()])
            ->getJson('/student/bluebooks/search?q=' . urlencode('detection crop disease'));

        $res->assertOk();
        $this->assertSame(['AI-Powered Crop Disease Detection Using Neural Networks'], array_column($res->json('results'), 'title'));
    }

    public function test_search_matches_words_that_are_not_next_to_each_other(): void
    {
        $this->makeBluebook([
            'title'    => 'Attendance Monitoring',
            'abstract' => 'Students tap an RFID card; the system records attendance for each class.',
        ]);

        $res = $this->withSession(['user' => $this->student()])
            ->getJson('/student/bluebooks/search?q=' . urlencode('class rfid monitoring'));

        $this->assertSame(['Attendance Monitoring'], array_column($res->json('results'), 'title'));
    }

    public function test_search_leaves_out_papers_that_are_not_posted(): void
    {
        $this->makeBluebook(['title' => 'Crop Disease Detection', 'status' => 'Pending']);

        $res = $this->withSession(['user' => $this->student()])
            ->getJson('/student/bluebooks/search?q=' . urlencode('disease crop'));

        $this->assertSame([], $res->json('results'));
    }

    public function test_the_paper_page_has_reading_controls_details_and_a_citation(): void
    {
        $bluebook = $this->makeBluebook(['file_path' => 'bluebooks/p.pdf', 'pages' => 42, 'authors' => ['Dela Cruz, Maria']]);

        $res = $this->withSession(['user' => $this->student()])->get("/student/bluebooks/{$bluebook->id}");

        $res->assertOk();
        foreach (['data-pdf="prev"', 'data-pdf="next"', 'id="pdf-page-input"', 'data-pdf="zoom-in"',
                  'data-pdf="zoom-out"', 'data-pdf="fit"', 'data-pdf="fullscreen"', 'id="pdf-contents"'] as $control) {
            $res->assertSee($control, false);
        }
        $res->assertSee('CCS — College of Computer Studies');
        $res->assertSee('42 pages');
        $res->assertSee('Full document');
        $res->assertSee('(Dela Cruz, 2024)');
        $res->assertSee("Dela Cruz, M. (2024).", false);
    }

    public function test_the_paper_page_keeps_program_and_adviser_but_not_upload_details(): void
    {
        $bluebook = $this->makeBluebook([
            'adviser' => 'Dr. Adviser Name', 'uploaded_by_name' => 'Uploader Person', 'date_added' => '2024-06-15',
        ]);

        $res = $this->withSession(['user' => $this->student()])->get("/student/bluebooks/{$bluebook->id}");

        $res->assertOk();
        $res->assertSee('Bachelor of Science in Information Technology');
        $res->assertSee('Dr. Adviser Name');
        $res->assertDontSee('Uploaded By');
        $res->assertDontSee('Uploader Person');
        $res->assertDontSee('Date Added');
        $res->assertDontSee('2024-06-15');
        $res->assertDontSee('meta-pill badge-green', false);
    }
}
