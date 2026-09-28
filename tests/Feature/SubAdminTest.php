<?php

namespace Tests\Feature;

use App\Models\Bluebook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Sub-Admins: admin-side accounts that keep the archive running when the
 * Admin is away. Two things stay with the Admin: approving uploads so they
 * reach readers, and managing Admin and Sub-Admin accounts.
 */
class SubAdminTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role, ?string $email = null): User
    {
        return User::create([
            'name' => $role . ' Person', 'email' => $email ?? strtolower(str_replace('-', '', $role)) . rand(1000, 9999) . '@cspc.edu.ph',
            'password' => Hash::make('secret'), 'role' => $role,
        ]);
    }

    private function as(User $u): array
    {
        return ['user' => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role, 'canUpload' => false]];
    }

    private function pendingBluebook(): Bluebook
    {
        return Bluebook::create([
            'title' => 'A Pending Paper', 'authors' => ['Dela Cruz, Maria'], 'year' => 2025, 'department' => 'CCS',
            'program' => 'BSIT', 'keywords' => ['k'], 'abstract' => 'A.', 'adviser' => 'Dr. A', 'status' => 'Pending',
            'pages' => 10, 'uploaded_by' => 's@my.cspc.edu.ph', 'uploaded_by_name' => 'S T', 'date_added' => '2026-01-01',
        ]);
    }

    public function test_a_sub_admin_opens_every_admin_page(): void
    {
        $sub = $this->account('Sub-Admin');
        $b   = $this->pendingBluebook();

        foreach (['/admin/dashboard', '/admin/bluebooks', "/admin/bluebooks/{$b->id}", '/admin/pending', '/admin/rejected',
                  '/admin/bluebooks/new', "/admin/bluebooks/{$b->id}/edit", '/admin/users', '/admin/users/new', '/admin/logs'] as $url) {
            $this->withSession($this->as($sub))->get($url)->assertOk();
        }
    }

    public function test_only_the_admin_can_approve(): void
    {
        $sub   = $this->account('Sub-Admin');
        $admin = $this->account('Admin');
        $b     = $this->pendingBluebook();

        $this->withSession($this->as($sub))->post("/admin/bluebooks/{$b->id}/approve")
            ->assertSessionHas('error');
        $this->withSession($this->as($sub))->post('/admin/bluebooks/approve-selected', ['ids' => [$b->id]]);
        $this->assertSame('Pending', $b->fresh()->status);
        $this->assertDatabaseHas('logs', ['action' => 'Unauthorized Access Attempt', 'status' => 'Denied']);

        $this->withSession($this->as($admin))->post("/admin/bluebooks/{$b->id}/approve");
        $this->assertSame(Bluebook::STATUS_AWAITING_WAIVER, $b->fresh()->status);
    }

    public function test_a_sub_admin_sees_no_approve_buttons_but_can_still_reject(): void
    {
        $sub = $this->account('Sub-Admin');
        $b   = $this->pendingBluebook();

        $this->withSession($this->as($sub))->get('/admin/pending')
            ->assertSee('Only the Admin can approve submissions.')
            ->assertDontSee('id="approve-selected-btn"', false)
            ->assertDontSee(route('admin.bluebooks.approve', $b->id), false)
            ->assertSee(route('admin.bluebooks.reject', $b->id), false);

        $this->withSession($this->as($sub))->post("/admin/bluebooks/{$b->id}/reject", ['reason' => 'The abstract does not match the paper.']);
        $this->assertSame('Rejected', $b->fresh()->status);
    }

    public function test_only_the_admin_is_shown_the_waiting_count(): void
    {
        $sub   = $this->account('Sub-Admin');
        $admin = $this->account('Admin');
        $this->pendingBluebook();

        foreach (['/admin/dashboard', '/admin/bluebooks'] as $url) {
            $this->withSession($this->as($admin))->get($url)
                ->assertSee('waiting for review')      // the sidebar badge
                ->assertSee(route('admin.pending'), false);

            $this->withSession($this->as($sub))->get($url)
                ->assertDontSee('waiting for review')
                ->assertDontSee('pending review')
                ->assertDontSee('Review now')
                ->assertSee(route('admin.pending'), false); // the Pending link itself stays
        }

        $this->withSession($this->as($admin))->get('/admin/dashboard')->assertSee('Review now');
        $this->withSession($this->as($admin))->get('/admin/bluebooks')->assertSee('pending review');
    }

    public function test_a_bluebook_a_sub_admin_adds_waits_for_the_admin_then_posts(): void
    {
        $sub   = $this->account('Sub-Admin');
        $admin = $this->account('Admin');
        $form  = [
            'title' => 'Library Copy', 'authors' => 'Cruz, Ana', 'year' => 2024, 'department' => 'CCS',
            'program' => 'Bachelor of Science in Information Technology', 'keywords' => 'library',
            'abstract' => 'A paper the library holds.', 'adviser' => 'Reyes, Jose P.', 'pages' => 40,
        ];

        $this->withSession($this->as($sub))->post('/admin/bluebooks/new', $form)->assertRedirect(route('admin.pending'));
        $b = Bluebook::where('title', 'Library Copy')->firstOrFail();
        $this->assertSame('Pending', $b->status);

        // The library holds the paper, so there is no author's waiver to wait for.
        $this->withSession($this->as($admin))->post("/admin/bluebooks/{$b->id}/approve");
        $this->assertSame('Approved', $b->fresh()->status);
    }

    public function test_a_bluebook_the_admin_adds_is_posted_at_once(): void
    {
        $admin = $this->account('Admin');

        $this->withSession($this->as($admin))->post('/admin/bluebooks/new', [
            'title' => 'Admin Copy', 'authors' => 'Cruz, Ana', 'year' => 2024, 'department' => 'CCS',
            'program' => 'Bachelor of Science in Information Technology', 'keywords' => 'library',
            'abstract' => 'A paper the library holds.', 'adviser' => 'Reyes, Jose P.', 'pages' => 40,
        ]);

        $this->assertSame('Approved', Bluebook::where('title', 'Admin Copy')->value('status'));
    }

    public function test_a_sub_admin_can_edit_a_bluebook_in_full(): void
    {
        $sub = $this->account('Sub-Admin');
        $b   = $this->pendingBluebook();

        $this->withSession($this->as($sub))->post("/admin/bluebooks/{$b->id}/edit", [
            'title' => 'Renamed By A Sub-Admin', 'authors' => 'X', 'year' => 2024, 'department' => 'CCS',
            'program' => 'BSIT', 'keywords' => 'k', 'abstract' => 'Changed.', 'adviser' => 'Dr. B', 'pages' => 12,
            'access_level' => Bluebook::ACCESS_CONSULTATION,
        ])->assertRedirect(route('admin.bluebooks'));

        $this->assertSame('Renamed By A Sub-Admin', $b->fresh()->title);
    }

    public function test_a_sub_admin_manages_students_but_never_staff(): void
    {
        $sub     = $this->account('Sub-Admin');
        $admin   = $this->account('Admin');
        $student = $this->account('Student');

        $this->withSession($this->as($sub))->get("/admin/users/{$student->id}/edit")->assertOk();
        $this->withSession($this->as($sub))->get("/admin/users/{$admin->id}/edit")->assertRedirect(route('admin.users'));

        // Cannot promote anyone to staff...
        $this->withSession($this->as($sub))->post("/admin/users/{$student->id}/edit", [
            'name' => $student->name, 'email' => $student->email, 'role' => 'Admin',
        ]);
        $this->assertSame('Student', $student->fresh()->role);

        // ...nor create staff.
        $this->withSession($this->as($sub))->post('/admin/users/new', [
            'name' => 'New', 'email' => 'new@cspc.edu.ph', 'password' => 'secret123', 'role' => 'Sub-Admin',
        ]);
        $this->assertDatabaseMissing('users', ['email' => 'new@cspc.edu.ph']);

        // ...nor change an admin's upload permission.
        $this->withSession($this->as($sub))->post("/admin/users/{$admin->id}/enable-upload");
        $this->assertFalse((bool) $admin->fresh()->can_upload);
    }

    public function test_an_admin_creates_a_sub_admin(): void
    {
        $admin = $this->account('Admin');

        $this->withSession($this->as($admin))->post('/admin/users/new', [
            'name' => 'Reviewer', 'email' => 'reviewer@cspc.edu.ph', 'password' => 'secret123', 'role' => 'Sub-Admin',
        ]);

        $this->assertSame('Sub-Admin', User::where('email', 'reviewer@cspc.edu.ph')->firstOrFail()->role);
    }

    public function test_the_user_form_has_no_privilege_checkboxes(): void
    {
        $admin = $this->account('Admin');

        $this->withSession($this->as($admin))->get('/admin/users/new')
            ->assertOk()
            ->assertDontSee('name="permissions[]"', false);
    }

    public function test_the_sidebar_shows_everything(): void
    {
        $sub = $this->account('Sub-Admin');

        $res = $this->withSession($this->as($sub))->get('/admin/bluebooks');
        $res->assertSee(route('admin.users'), false);
        $res->assertSee(route('admin.logs'), false);
        $res->assertSee('Add Bluebook');
        $res->assertSee('Sub-Admin');
    }

    public function test_a_sub_admin_logs_in_to_the_admin_side(): void
    {
        $this->account('Sub-Admin', 'staff@cspc.edu.ph');

        $this->post('/login', ['email' => 'staff@cspc.edu.ph', 'password' => 'secret'])
            ->assertRedirect(route('admin.dashboard'));
    }
}
