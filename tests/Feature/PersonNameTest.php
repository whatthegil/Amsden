<?php

namespace Tests\Feature;

use App\Models\User;
use App\Rules\PersonName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A user's name may hold letters only - with the spaces, hyphens, apostrophes
 * and periods names carry - wherever it is typed: the profile page and the
 * admin's user form.
 */
class PersonNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_names_pass_and_digits_or_symbols_do_not(): void
    {
        $rule = new PersonName();

        foreach (['Maria Dela Cruz', 'Ma. Teresa Santos-Reyes Jr.', "Shane O'Brien", 'José Peña', 'Niño Ibañez'] as $name) {
            $this->assertTrue($rule->passes('name', $name), $name);
        }
        foreach (['Juan 2', 'Maria123', 'R2-D2', 'Ana@cspc', 'Mark_Lee', 'J', '...', ''] as $name) {
            $this->assertFalse($rule->passes('name', $name), $name);
        }
    }

    private function account(string $role = 'Student'): User
    {
        return User::create(['name' => 'Maria Santos', 'email' => strtolower($role) . '@my.cspc.edu.ph', 'password' => 'secret-pass', 'role' => $role]);
    }

    private function signedIn(User $u): array
    {
        return ['user' => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role, 'canUpload' => false]];
    }

    public function test_the_profile_refuses_a_name_with_numbers(): void
    {
        $u = $this->account();

        $this->withSession($this->signedIn($u))->post('/profile', ['name' => 'Maria 123'])
            ->assertSessionHasErrors('name');
        $this->assertSame('Maria Santos', $u->fresh()->name);

        $this->withSession($this->signedIn($u))->post('/profile', ['name' => 'Maria Dela Cruz'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Maria Dela Cruz', $u->fresh()->name);
    }

    public function test_the_admin_cannot_create_or_rename_an_account_with_numbers(): void
    {
        $admin = $this->account('Admin');

        $this->withSession($this->signedIn($admin))->post('/admin/users/new', [
            'name' => 'Student 01', 'email' => 'new@my.cspc.edu.ph', 'password' => 'secret-pass', 'role' => 'Student',
        ])->assertSessionHasErrors('name');
        $this->assertNull(User::where('email', 'new@my.cspc.edu.ph')->first());

        $target = User::create(['name' => 'Juan Reyes', 'email' => 'juan@my.cspc.edu.ph', 'password' => 'secret-pass', 'role' => 'Student']);
        $this->withSession($this->signedIn($admin))->post("/admin/users/{$target->id}/edit", [
            'name' => 'Juan Reyes 2', 'email' => 'juan@my.cspc.edu.ph', 'role' => 'Student',
        ])->assertSessionHasErrors('name');
        $this->assertSame('Juan Reyes', $target->fresh()->name);
    }

    public function test_the_name_fields_carry_the_same_check_in_the_browser(): void
    {
        $u = $this->account();

        $this->withSession($this->signedIn($u))->get('/profile')
            ->assertSee('pattern="' . e(PersonName::HTML_PATTERN) . '"', false);
    }
}
