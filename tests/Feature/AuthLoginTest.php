<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Module 1 — Login (valid credentials succeed, invalid are rejected) and the
 * removal of self-service registration.
 */
class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $overrides = []): User
    {
        // policy_accepted_at isn't mass-assignable — apply it after create.
        $policyAcceptedAt = $overrides['policy_accepted_at'] ?? null;
        unset($overrides['policy_accepted_at']);

        $user = User::create(array_merge([
            'name'     => 'Gil Realubit',
            'email'    => 'student@my.cspc.edu.ph',
            'password' => Hash::make('student123'),
            'role'     => 'Student',
        ], $overrides));

        if ($policyAcceptedAt) {
            $user->forceFill(['policy_accepted_at' => $policyAcceptedAt])->save();
        }

        return $user;
    }

    public function test_valid_credentials_log_the_user_in(): void
    {
        $this->makeUser(['policy_accepted_at' => now()]);

        $response = $this->post('/login', [
            'email'    => 'student@my.cspc.edu.ph',
            'password' => 'student123',
        ]);

        $response->assertRedirect(route('student.dashboard'));
        $this->assertSame('student@my.cspc.edu.ph', session('user')['email']);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->makeUser();

        $response = $this->post('/login', [
            'email'    => 'student@my.cspc.edu.ph',
            'password' => 'wrong-password',
        ]);

        $response->assertOk();
        $response->assertSee('Invalid email or password', false);
        $this->assertNull(session('user'));
    }

    public function test_unknown_email_is_rejected(): void
    {
        $response = $this->post('/login', [
            'email'    => 'nobody@my.cspc.edu.ph',
            'password' => 'whatever',
        ]);

        $response->assertOk();
        $response->assertSee('Invalid email or password', false);
        $this->assertNull(session('user'));
    }

    public function test_non_institutional_email_is_rejected(): void
    {
        $response = $this->post('/login', [
            'email'    => 'someone@gmail.com',
            'password' => 'whatever',
        ]);

        $response->assertOk();
        $response->assertSee('cspc.edu.ph', false);
        $this->assertNull(session('user'));
    }

    public function test_admin_lands_on_the_admin_dashboard(): void
    {
        $this->makeUser([
            'email' => 'admin@cspc.edu.ph',
            'role'  => 'Admin',
        ]);

        $response = $this->post('/login', [
            'email'    => 'admin@cspc.edu.ph',
            'password' => 'student123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_registration_routes_are_gone(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
    }

    public function test_forgot_password_help_page_loads(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertOk();
        $response->assertSee('library administrator', false);
    }
}
