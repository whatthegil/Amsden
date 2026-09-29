<?php

namespace Tests\Feature;

use App\Models\Bluebook;
use App\Models\Log;
use App\Services\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A log entry names the paper it is about by id as well as by title, so it
 * still finds the paper after the title is edited.
 */
class LogBluebookLinkTest extends TestCase
{
    use RefreshDatabase;

    private function paper(string $title): Bluebook
    {
        return Bluebook::create([
            'title' => $title, 'authors' => ['A, B'], 'year' => 2025, 'department' => 'CCS', 'program' => 'P',
            'keywords' => [], 'abstract' => 'A.', 'adviser' => '', 'status' => 'Approved',
            'uploaded_by' => 's@my.cspc.edu.ph', 'uploaded_by_name' => 'S T', 'date_added' => '2025-01-01',
        ]);
    }

    private function log(string $document): Log
    {
        Store::addLog(['userName' => 'S T', 'email' => 's@my.cspc.edu.ph', 'action' => 'Viewed Bluebook', 'document' => $document]);
        return Log::latest('id')->first();
    }

    public function test_an_entry_keeps_its_paper_after_the_title_changes(): void
    {
        $paper = $this->paper('Old Title');
        $entry = $this->log('Old Title');

        $paper->update(['title' => 'New Title']);

        $this->assertSame($paper->id, $entry->fresh()->bluebook_id);
        $this->assertSame('New Title', $entry->fresh()->bluebook->title);
        $this->assertSame('Old Title', $entry->fresh()->document, 'The entry still says what it said at the time.');
    }

    public function test_a_title_two_papers_share_is_not_guessed(): void
    {
        $this->paper('Same Title');
        $this->paper('Same Title');

        $this->assertNull($this->log('Same Title')->bluebook_id);
    }

    public function test_entries_about_no_paper_stay_unlinked(): void
    {
        $this->assertNull($this->log('/admin/users')->bluebook_id);
    }

    public function test_deleting_a_paper_keeps_its_log_entries(): void
    {
        $paper = $this->paper('Gone Soon');
        $entry = $this->log('Gone Soon');

        Store::deleteBluebook($paper->id);

        $this->assertNotNull($entry->fresh(), 'The audit trail outlives what it records.');
        $this->assertNull($entry->fresh()->bluebook_id);
    }
}
