<?php

namespace Tests\Feature;

use App\Models\Bluebook;
use App\Models\Bookmark;
use App\Services\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A reader's saved papers.
 *
 * The list is a reading list, so the order it comes back in is part of what it
 * is for - and the page has to say enough about each paper for the reader to
 * remember why they saved it.
 */
class BookmarksTest extends TestCase
{
    use RefreshDatabase;

    private function student(): array
    {
        return [
            'id' => 1, 'name' => 'S T', 'email' => 's@my.cspc.edu.ph',
            'role' => 'Student', 'canUpload' => false,
        ];
    }

    private function paper(string $title, string $abstract = 'An abstract.'): Bluebook
    {
        return Bluebook::create([
            'title' => $title, 'authors' => ['Dela Cruz, Maria'], 'year' => 2024,
            'department' => 'CCS', 'program' => 'BSIT', 'keywords' => ['x'],
            'abstract' => $abstract, 'adviser' => '', 'status' => 'Approved',
            'uploaded_by' => 'other@cspc.edu.ph', 'uploaded_by_name' => 'Other',
            'date_added' => '2024-01-01',
        ]);
    }

    private function save(Bluebook $b, string $addedAt): void
    {
        Bookmark::create([
            'user_email' => 's@my.cspc.edu.ph', 'user_name' => 'S T',
            'bluebook_id' => $b->id, 'added_at' => $addedAt,
        ]);
    }

    /**
     * The list used to come back in bluebook id order - the order the archive
     * received them, which has nothing to do with the order this reader saved
     * them in. A paper bookmarked this morning sat under one saved a year ago.
     */
    public function test_the_most_recently_saved_comes_first(): void
    {
        // The two orderings have to disagree or this proves nothing: the paper
        // the archive received first is the one saved longest ago, so id order
        // and save order are opposites here.
        $first  = $this->paper('Reached The Archive First');
        $second = $this->paper('Reached The Archive Second');

        $this->save($first,  '2024-03-02 09:00:00');
        $this->save($second, '2026-01-05 09:00:00');

        $saved = Store::getBookmarkedBluebooks('s@my.cspc.edu.ph');

        $this->assertSame('Reached The Archive Second', $saved[0]['title'], 'The most recently saved paper must lead the list.');
        $this->assertSame('Reached The Archive First', $saved[1]['title']);
    }

    /** Each row says when it was saved, which is the only ordering cue there is. */
    public function test_each_saved_paper_carries_its_date(): void
    {
        $this->save($this->paper('A Paper'), '2026-02-11 14:30:00');

        $saved = Store::getBookmarkedBluebooks('s@my.cspc.edu.ph');

        $this->assertSame('2026-02-11 14:30:00', $saved[0]['bookmarkedAt']);
    }

    /** One reader's list is not another's. */
    public function test_a_list_holds_only_its_own_readers_saves(): void
    {
        $mine = $this->paper('Mine');
        $theirs = $this->paper('Theirs');

        $this->save($mine, '2026-01-01 00:00:00');
        Bookmark::create([
            'user_email' => 'someone@else.edu.ph', 'user_name' => 'Else',
            'bluebook_id' => $theirs->id, 'added_at' => '2026-01-02 00:00:00',
        ]);

        $saved = Store::getBookmarkedBluebooks('s@my.cspc.edu.ph');

        $this->assertCount(1, $saved);
        $this->assertSame('Mine', $saved[0]['title']);
    }

    /**
     * The page showed a title, the authors and three small figures. Without the
     * abstract a saved list is a column of titles with no way to tell why any
     * of them was saved.
     */
    public function test_the_page_shows_enough_to_recognise_a_paper(): void
    {
        $this->save($this->paper('A Studied Paper', 'This study examines coastal erosion in Bicol.'), '2026-01-01 00:00:00');

        $res = $this->withSession(['user' => $this->student()])->get('/student/bookmarks');

        $res->assertOk();
        $res->assertSee('A Studied Paper', false);
        $res->assertSee('This study examines coastal erosion', false);
        $res->assertSee('Saved 2026-01-01', false);
    }

    /**
     * Removing a bookmark undoes one click and is undone by one click. A filled
     * danger button beside the primary action invites the misclick it is styled
     * to warn about.
     */
    public function test_removing_is_not_dressed_as_a_destructive_action(): void
    {
        $this->save($this->paper('A Paper'), '2026-01-01 00:00:00');

        $html = $this->withSession(['user' => $this->student()])
            ->get('/student/bookmarks')->getContent();

        $this->assertStringContainsString('btn-quiet', $html);
        $this->assertStringNotContainsString('btn-danger', $html);
    }

    public function test_an_empty_list_points_at_the_archive(): void
    {
        $res = $this->withSession(['user' => $this->student()])->get('/student/bookmarks');

        $res->assertOk();
        $res->assertSee('You have not saved any papers yet', false);
        $res->assertSee('Browse the archive', false);
    }
}
