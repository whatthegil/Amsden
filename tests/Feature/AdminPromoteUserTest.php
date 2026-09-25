<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admins granting the Admin role to other users.
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

    private function sessionFor(User $u): array
    {
        return ['user' => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role, 'canUpload' => true]];
    }

    public function test_admin_can_make_a_student_an_admin(): void
    {
        $admin   = $this->makeUser('Admin', 'admin@cspc.edu.ph');
        $student = $this->makeUser('Student', 'student@my.cspc.edu.ph');

        $this->withSession($this->sessionFor($admin))
            ->post("/admin/users/{$student->id}/make-admin")
            ->assertRedirect(route('admin.users'));

        $this->assertSame('Admin', $student->fresh()->role);
        $this->assertDatabaseHas('logs', ['action' => 'Granted Admin Role', 'document' => $student->name]);
    }

    public function test_student_cannot_make_anyone_an_admin(): void
    {
        $student = $this->makeUser('Student', 'student@my.cspc.edu.ph');
        $other   = $this->makeUser('Faculty', 'faculty@cspc.edu.ph');

        $this->withSession($this->sessionFor($student))
            ->post("/admin/users/{$other->id}/make-admin");

        $this->assertSame('Faculty', $other->fresh()->role);
    }

    public function test_users_list_offers_make_admin_for_non_admins(): void
    {
        $admin = $this->makeUser('Admin', 'admin@cspc.edu.ph');
        $this->makeUser('Faculty', 'faculty@cspc.edu.ph');

        $this->withSession($this->sessionFor($admin))
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('Make Admin');
    }

    public function test_admin_cannot_remove_their_own_admin_role(): void
    {
        $admin = $this->makeUser('Admin', 'admin@cspc.edu.ph');

        $this->withSession($this->sessionFor($admin))
            ->post("/admin/users/{$admin->id}/edit", ['name' => $admin->name, 'email' => $admin->email, 'role' => 'Student']);

        $this->assertSame('Admin', $admin->fresh()->role);
    }

    public function test_edit_rejects_unknown_roles(): void
    {
        $admin   = $this->makeUser('Admin', 'admin@cspc.edu.ph');
        $student = $this->makeUser('Student', 'student@my.cspc.edu.ph');

        $this->withSession($this->sessionFor($admin))
            ->post("/admin/users/{$student->id}/edit", ['name' => $student->name, 'email' => $student->email, 'role' => 'SuperUser']);

        $this->assertSame('Student', $student->fresh()->role);
    }
}
