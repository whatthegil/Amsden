<?php

namespace Tests\Feature;

use App\Models\Bluebook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Undoing an approval: a posted or awaiting-waiver bluebook goes back to Pending. */
class RecallBluebookTest extends TestCase
{
    use RefreshDatabase;

    private function paper(string $status): Bluebook
    {
        return Bluebook::create([
            'title' => 'Recalled Paper', 'authors' => ['Dela Cruz, Maria'], 'year' => 2025, 'department' => 'CCS',
            'program' => 'BSIT', 'keywords' => ['k'], 'abstract' => 'An abstract.', 'adviser' => 'Dr. A', 'status' => $status,
            'pages' => 10, 'views' => 7, 'uploaded_by' => 's@my.cspc.edu.ph', 'uploaded_by_name' => 'Maria', 'date_added' => '2026-01-01',
            'waiver_recorded_at' => now(),
        ]);
    }

    private function login(string $role): array
    {
        $email = strtolower(str_replace('-', '', $role)) . '@cspc.edu.ph';
        $u = User::create(['name' => $role, 'email' => $email, 'password' => bcrypt('x'), 'role' => $role]);

        return ['user' => ['id' => $u->id, 'name' => $role, 'email' => $email, 'role' => $role, 'canUpload' => false]];
    }

    public function test_the_admin_recalls_a_posted_bluebook_to_pending(): void
    {
        $b = $this->paper('Approved');

        $this->withSession($this->login('Admin'))->post("/admin/bluebooks/{$b->id}/recall")
            ->assertRedirect(route('admin.bluebooks.view', $b->id));

        $b->refresh();
        $this->assertSame('Pending', $b->status);
        $this->assertSame(7, $b->views);
        $this->assertNotNull($b->waiver_recorded_at);
    }

    public function test_an_awaiting_waiver_bluebook_can_be_recalled(): void
    {
        $b = $this->paper(Bluebook::STATUS_AWAITING_WAIVER);

        $this->withSession($this->login('Admin'))->post("/admin/bluebooks/{$b->id}/recall");

        $this->assertSame('Pending', $b->fresh()->status);
    }

    public function test_a_rejected_bluebook_is_not_recalled(): void
    {
        $b = $this->paper('Rejected');

        $this->withSession($this->login('Admin'))->post("/admin/bluebooks/{$b->id}/recall");

        $this->assertSame('Rejected', $b->fresh()->status);
    }

    public function test_a_sub_admin_cannot_recall(): void
    {
        $b = $this->paper('Approved');

        $this->withSession($this->login('Sub-Admin'))->post("/admin/bluebooks/{$b->id}/recall");

        $this->assertSame('Approved', $b->fresh()->status);
    }

    public function test_the_recall_button_shows_for_the_admin_on_a_posted_bluebook(): void
    {
        $b = $this->paper('Approved');
        $admin = $this->login('Admin');

        $this->withSession($admin)->get("/admin/bluebooks/{$b->id}")
            ->assertOk()->assertSee(route('admin.bluebooks.recall', $b->id), false);
        $this->withSession($admin)->get('/admin/bluebooks')
            ->assertOk()->assertSee(route('admin.bluebooks.recall', $b->id), false);
    }
}
