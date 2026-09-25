<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admins changing other users' roles from the Edit User form.
 */
class AdminPromoteUserTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, string $email): User
    {
        return User::create([
            'name'     => "Test $role",
            'email'    => $email,
            'password' => bcrypt('secret'),
            'role'     => $role,
        ]);
    }

    private function sessionUser(User $u): array
    {
        return ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role, 'canUpload' => true];
    }

    public function test_admin_can_make_a_student_an_admin(): void
    {
        $admin   = $this->makeUser('Admin', 'admin@cspc.edu.ph');
        $student = $this->makeUser('Student', 'student@my.cspc.edu.ph');

        $this->withSession(['user' => $this->sessionUser($admin)])
            ->post("/admin/users/{$student->id}/edit", ['name' => $student->name, 'email' => $student->email, 'role' => 'Admin'])
            ->assertRedirect(route('admin.users'));

        $this->assertSame('Admin', $student->fresh()->role);
        $this->assertDatabaseHas('logs', ['action' => 'Changed Role to Admin', 'document' => $student->name]);
    }

    public function test_promotion_applies_to_a_user_who_is_already_logged_in(): void
    {
        $student = $this->makeUser('Student', 'student@my.cspc.edu.ph');
        // Their session was started while they were still a Student...
        $this->session(['user' => $this->sessionUser($student)]);

        // ...and an admin promotes them afterwards.
        $student->update(['role' => 'Admin']);

        $this->get('/admin/dashboard')->assertOk();
        $this->assertSame('Admin', session('user')['role']);
    }

    public function test_demoted_admin_loses_access_without_logging_out(): void
    {
        $admin = $this->makeUser('Admin', 'admin@cspc.edu.ph');
        $this->session(['user' => $this->sessionUser($admin)]);

        $admin->update(['role' => 'Faculty']);

        $this->get('/admin/users')->assertRedirect(route('student.dashboard'));
    }

    public function test_deleted_account_is_logged_out(): void
    {
        $student = $this->makeUser('Student', 'student@my.cspc.edu.ph');
        $this->session(['user' => $this->sessionUser($student)]);

        $student->delete();

        $this->get('/profile')->assertRedirect(route('login'));
        $this->assertNull(session('user'));
    }

    public function test_student_cannot_change_roles(): void
    {
        $student = $this->makeUser('Student', 'student@my.cspc.edu.ph');
        $other   = $this->makeUser('Faculty', 'faculty@cspc.edu.ph');

        $this->withSession(['user' => $this->sessionUser($student)])
            ->post("/admin/users/{$other->id}/edit", ['name' => $other->name, 'email' => $other->email, 'role' => 'Admin']);

        $this->assertSame('Faculty', $other->fresh()->role);
    }

    public function test_users_list_has_no_make_admin_button(): void
    {
        $admin = $this->makeUser('Admin', 'admin@cspc.edu.ph');
        $this->makeUser('Faculty', 'faculty@cspc.edu.ph');

        $this->withSession(['user' => $this->sessionUser($admin)])
            ->get('/admin/users')
            ->assertOk()
            ->assertDontSee('Make Admin');
    }

    public function test_admin_cannot_remove_their_own_admin_role(): void
    {
        $admin = $this->makeUser('Admin', 'admin@cspc.edu.ph');

        $this->withSession(['user' => $this->sessionUser($admin)])
            ->post("/admin/users/{$admin->id}/edit", ['name' => $admin->name, 'email' => $admin->email, 'role' => 'Student']);

        $this->assertSame('Admin', $admin->fresh()->role);
    }

    public function test_edit_rejects_unknown_roles(): void
    {
        $admin   = $this->makeUser('Admin', 'admin@cspc.edu.ph');
        $student = $this->makeUser('Student', 'student@my.cspc.edu.ph');

        $this->withSession(['user' => $this->sessionUser($admin)])
            ->post("/admin/users/{$student->id}/edit", ['name' => $student->name, 'email' => $student->email, 'role' => 'SuperUser']);

        $this->assertSame('Student', $student->fresh()->role);
    }
}
