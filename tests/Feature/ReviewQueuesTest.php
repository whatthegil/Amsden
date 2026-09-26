<?php

namespace Tests\Feature;

use App\Models\Bluebook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Pending and Rejected pages beside Bluebooks, and what they help with. */
class ReviewQueuesTest extends TestCase
{
    use RefreshDatabase;

    private ?array $staff = null;

    private function paper(string $title, string $status, string $updatedAt, array $overrides = []): Bluebook
    {
        $b = Bluebook::create(array_merge([
            'title' => $title, 'authors' => ['Dela Cruz, Maria'], 'year' => 2025, 'department' => 'CCS',
            'program' => 'BSIT', 'keywords' => ['k'], 'abstract' => 'An abstract.', 'adviser' => 'Dr. A', 'status' => $status,
            'pages' => 10, 'uploaded_by' => 's@my.cspc.edu.ph', 'uploaded_by_name' => 'Uploader Name', 'date_added' => '2026-01-01',
        ], $overrides));
        $b->timestamps = false;
        $b->updated_at = $updatedAt;
        $b->save();

        return $b;
    }

    /** A staff session backed by a real account, since EnsureRole re-reads it. */
    private function staffSession(string $role = 'Admin', array $permissions = []): array
    {
        if ($this->staff === null) {
            $u = User::create(['name' => 'Adm', 'email' => 'adm@cspc.edu.ph', 'password' => bcrypt('x'), 'role' => $role, 'permissions' => $permissions ?: null]);
            $this->staff = ['user' => ['id' => $u->id, 'name' => 'Adm', 'email' => 'adm@cspc.edu.ph', 'role' => $role, 'canUpload' => true, 'permissions' => $permissions]];
        }

        return $this->staff;
    }

    public function test_pending_lists_the_longest_waiting_first(): void
    {
        $this->paper('Newer Submission', 'Pending', now()->subDay());
        $this->paper('Older Submission', 'Pending', now()->subDays(3));
        $this->paper('A Posted Paper', 'Approved', now()->subDays(2));

        $res = $this->withSession($this->staffSession())->get('/admin/pending');

        $res->assertOk();
        $res->assertSeeInOrder(['Older Submission', 'Newer Submission']);
        $res->assertDontSee('A Posted Paper');
        $res->assertSee('Uploader Name');

        $newest = $this->withSession($this->staffSession())->get('/admin/pending?sort=newest');
        $newest->assertSeeInOrder(['Newer Submission', 'Older Submission']);
    }

    public function test_overdue_submissions_are_counted_and_flagged(): void
    {
        $this->paper('Long Wait', 'Pending', now()->subDays(10));
        $this->paper('Short Wait', 'Pending', now()->subDays(2));

        $res = $this->withSession($this->staffSession())->get('/admin/pending');

        $res->assertSee('overdue (over 7 days)');
        $res->assertSee('10 days');
        $this->assertSame(1, substr_count($res->getContent(), '>Overdue</span>'));
    }

    public function test_a_likely_duplicate_of_a_posted_paper_is_flagged(): void
    {
        $posted = $this->paper('Automated Student Attendance Monitoring Using RFID', 'Approved', now()->subYear(),
            ['keywords' => ['rfid', 'attendance'], 'abstract' => 'An RFID attendance monitoring system for students.']);
        $this->paper('Automated Student Attendance Monitoring Using RFID Cards', 'Pending', now()->subDay(),
            ['keywords' => ['rfid', 'attendance'], 'abstract' => 'An RFID attendance monitoring system for students.']);
        $this->paper('Crop Disease Detection', 'Pending', now()->subDay());

        $res = $this->withSession($this->staffSession())->get('/admin/pending');

        $res->assertSee('like a posted paper');
        $res->assertSee(route('admin.bluebooks.view', $posted->id), false);
        $res->assertSee('No duplicate');
    }

    public function test_the_queue_can_be_searched_and_narrowed(): void
    {
        $this->paper('Crop Disease Detection', 'Pending', now()->subDay());
        $this->paper('Library Inventory', 'Pending', now()->subDay(), ['department' => 'CEA']);

        $this->withSession($this->staffSession())->get('/admin/pending?search=crop')
            ->assertSee('Crop Disease Detection')->assertDontSee('Library Inventory');
        $this->withSession($this->staffSession())->get('/admin/pending?department=CEA')
            ->assertSee('Library Inventory')->assertDontSee('Crop Disease Detection');
    }

    public function test_several_can_be_approved_at_once(): void
    {
        $a = $this->paper('One', 'Pending', now()->subDay());
        $b = $this->paper('Two', 'Pending', now()->subDay());
        $c = $this->paper('Three', 'Pending', now()->subDay());

        $this->withSession($this->staffSession())
            ->post('/admin/bluebooks/approve-selected', ['ids' => [$a->id, $b->id]])
            ->assertRedirect(route('admin.pending'));

        $this->assertSame(Bluebook::STATUS_AWAITING_WAIVER, $a->fresh()->status);
        $this->assertSame(Bluebook::STATUS_AWAITING_WAIVER, $b->fresh()->status);
        $this->assertSame('Pending', $c->fresh()->status);
    }

    public function test_bulk_approve_needs_the_review_privilege(): void
    {
        $a = $this->paper('One', 'Pending', now()->subDay());

        $this->withSession($this->staffSession('Sub-Admin', ['view_logs']))
            ->post('/admin/bluebooks/approve-selected', ['ids' => [$a->id]])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertSame('Pending', $a->fresh()->status);
    }

    public function test_reviewing_from_the_queue_returns_to_it(): void
    {
        $b = $this->paper('To Approve', 'Pending', now()->subDay());
        $this->withSession($this->staffSession())
            ->post("/admin/bluebooks/{$b->id}/approve", ['from' => 'pending'])
            ->assertRedirect(route('admin.pending'));

        $c = $this->paper('To Reject', 'Pending', now()->subDay());
        $this->withSession($this->staffSession())
            ->post("/admin/bluebooks/{$c->id}/reject", ['from' => 'pending', 'reason' => 'Incomplete.'])
            ->assertRedirect(route('admin.pending'));
    }

    public function test_rejected_lists_the_reason_and_flags_silent_authors(): void
    {
        $this->paper('Rejected Earlier', 'Rejected', now()->subDays(20), ['rejection_reason' => 'Missing chapter 3.']);
        $this->paper('Rejected Later', 'Rejected', now()->subDays(2), ['rejection_reason' => 'Blurry scan.']);

        $res = $this->withSession($this->staffSession())->get('/admin/rejected');

        $res->assertOk();
        $res->assertSeeInOrder(['Rejected Later', 'Rejected Earlier']);
        $res->assertSee('Missing chapter 3.');
        $res->assertSee('no re-upload in 14+ days');
        $this->assertSame(1, substr_count($res->getContent(), '>No re-upload</span>'));
    }

    public function test_a_rejected_submission_can_be_deleted_from_the_list(): void
    {
        $b = $this->paper('Old Rejected', 'Rejected', now()->subDays(40));

        $this->withSession($this->staffSession())
            ->post("/admin/bluebooks/{$b->id}/delete", ['from' => 'rejected'])
            ->assertRedirect(route('admin.rejected'));

        $this->assertNull(Bluebook::find($b->id));
    }

    public function test_a_sub_admin_without_review_sees_the_queue_but_no_buttons(): void
    {
        $this->paper('Waiting Paper', 'Pending', now()->subDay());

        $res = $this->withSession($this->staffSession('Sub-Admin', ['view_logs']))->get('/admin/pending');

        $res->assertOk();
        $res->assertSee('Waiting Paper');
        $res->assertDontSee('Confirm Reject');
        $res->assertDontSee('id="approve-selected-btn"', false);
    }

    public function test_the_sidebar_links_both_pages_with_the_count_on_pending(): void
    {
        $this->paper('Waiting Paper', 'Pending', now()->subDay());

        $res = $this->withSession($this->staffSession())->get('/admin/bluebooks');

        $res->assertSee(route('admin.pending'), false);
        $res->assertSee(route('admin.rejected'), false);
        $res->assertSee('waiting for review');
    }
}
