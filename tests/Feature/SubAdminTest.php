<?php

namespace Tests\Feature;

use App\Models\Bluebook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Sub-Admins: admin-side accounts that hold only the privileges an Admin gives them. */
class SubAdminTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role, array $permissions = [], ?string $email = null): User
    {
        return User::create([
            'name' => $role . ' Person', 'email' => $email ?? strtolower(str_replace('-', '', $role)) . rand(1000, 9999) . '@cspc.edu.ph',
            'password' => Hash::make('secret'), 'role' => $role, 'permissions' => $permissions ?: null,
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

    public function test_every_sub_admin_can_read_the_admin_side(): void
    {
        $sub = $this->account('Sub-Admin');
        $b   = $this->pendingBluebook();

        $this->withSession($this->as($sub))->get('/admin/dashboard')->assertOk();
        $this->withSession($this->as($sub))->get('/admin/bluebooks')->assertOk();
        $this->withSession($this->as($sub))->get("/admin/bluebooks/{$b->id}")->assertOk();
    }

    public function test_a_sub_admin_without_a_privilege_is_turned_away(): void
    {
        $sub = $this->account('Sub-Admin', ['view_logs']);
        $b   = $this->pendingBluebook();

        $this->withSession($this->as($sub))->post("/admin/bluebooks/{$b->id}/approve")->assertRedirect(route('admin.dashboard'));
        $this->assertSame('Pending', $b->fresh()->status);

        $this->withSession($this->as($sub))->get('/admin/users')->assertRedirect(route('admin.dashboard'));
        $this->withSession($this->as($sub))->get('/admin/bluebooks/new')->assertRedirect(route('admin.dashboard'));
        $this->withSession($this->as($sub))->get('/admin/logs')->assertOk();
    }

    public function test_a_reviewer_can_approve_and_reject(): void
    {
        $sub = $this->account('Sub-Admin', ['review_bluebooks']);
        $b   = $this->pendingBluebook();

        $this->withSession($this->as($sub))->post("/admin/bluebooks/{$b->id}/approve");
        $this->assertSame(Bluebook::STATUS_AWAITING_WAIVER, $b->fresh()->status);
    }

    public function test_a_reviewer_can_record_the_waiver_but_nothing_else(): void
    {
        $sub = $this->account('Sub-Admin', ['review_bluebooks']);
        $b   = $this->pendingBluebook();

        $this->withSession($this->as($sub))->post("/admin/bluebooks/{$b->id}/edit", [
            'title' => 'Renamed By A Reviewer', 'authors' => 'X', 'year' => 1999, 'department' => 'CCS',
            'program' => 'BSIT', 'keywords' => 'k', 'abstract' => 'Changed.', 'adviser' => 'Dr. B', 'pages' => 999,
            'access_level' => Bluebook::ACCESS_CONSULTATION,
        ])->assertRedirect(route('admin.bluebooks'));

        $b->refresh();
        $this->assertSame(Bluebook::ACCESS_CONSULTATION, $b->access_level);
        $this->assertNotNull($b->waiver_recorded_at);
        $this->assertSame('A Pending Paper', $b->title);
        $this->assertSame(10, (int) $b->pages);
    }

    public function test_a_sub_admin_manages_students_but_never_staff(): void
    {
        $sub     = $this->account('Sub-Admin', ['manage_users']);
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

    public function test_an_admin_creates_a_sub_admin_with_chosen_privileges(): void
    {
        $admin = $this->account('Admin');

        $this->withSession($this->as($admin))->post('/admin/users/new', [
            'name' => 'Reviewer', 'email' => 'reviewer@cspc.edu.ph', 'password' => 'secret123',
            'role' => 'Sub-Admin', 'permissions' => ['review_bluebooks', 'view_logs', 'not_a_privilege'],
        ]);

        $made = User::where('email', 'reviewer@cspc.edu.ph')->firstOrFail();
        $this->assertSame('Sub-Admin', $made->role);
        $this->assertSame(['review_bluebooks', 'view_logs'], $made->permissions);
    }

    public function test_privileges_are_dropped_when_the_role_is_not_sub_admin(): void
    {
        $admin = $this->account('Admin');
        $sub   = $this->account('Sub-Admin', ['view_logs']);

        $this->withSession($this->as($admin))->post("/admin/users/{$sub->id}/edit", [
            'name' => $sub->name, 'email' => $sub->email, 'role' => 'Faculty', 'permissions' => ['view_logs'],
        ]);

        $this->assertSame('Faculty', $sub->fresh()->role);
        $this->assertNull($sub->fresh()->permissions);
    }

    public function test_the_sidebar_shows_only_what_the_account_can_use(): void
    {
        $sub = $this->account('Sub-Admin', ['review_bluebooks']);

        $res = $this->withSession($this->as($sub))->get('/admin/bluebooks');
        $res->assertDontSee(route('admin.users'), false);
        $res->assertDontSee(route('admin.logs'), false);
        $res->assertDontSee('Add Bluebook');
        $res->assertSee('Sub-Admin');
    }

    public function test_a_sub_admin_logs_in_to_the_admin_side(): void
    {
        $this->account('Sub-Admin', ['view_logs'], 'staff@cspc.edu.ph');

        $this->post('/login', ['email' => 'staff@cspc.edu.ph', 'password' => 'secret'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_a_privilege_taken_away_applies_at_once(): void
    {
        $sub = $this->account('Sub-Admin', ['view_logs']);
        $this->withSession($this->as($sub))->get('/admin/logs')->assertOk();

        $sub->update(['permissions' => []]);
        $this->withSession($this->as($sub))->get('/admin/logs')->assertRedirect(route('admin.dashboard'));
    }
}
