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

    public function test_google_is_sent_back_to_the_domain_the_user_is_on(): void
    {
        config(['services.google.client_id' => 'test-client', 'services.google.redirect' => 'https://old-name.laravel.cloud/auth/google/callback']);

        $location = $this->get('https://cspcbams.laravel.cloud/auth/google')->headers->get('Location');

        $this->assertStringContainsString('redirect_uri=' . urlencode('https://cspcbams.laravel.cloud/auth/google/callback'), $location);
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

    public function test_email_case_and_surrounding_spaces_are_ignored(): void
    {
        $this->makeUser([
            'email' => 'admin@cspc.edu.ph',
            'role'  => 'Admin',
        ]);

        $response = $this->post('/login', [
            'email'    => ' Admin@CSPC.edu.ph ',
            'password' => 'student123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_google_created_account_is_pointed_to_google(): void
    {
        $user = $this->makeUser(['email' => 'g@my.cspc.edu.ph']);
        $user->forceFill(['google_id' => 'g-123', 'password' => Hash::make('unknowable')])->save();

        $response = $this->post('/login', [
            'email'    => 'g@my.cspc.edu.ph',
            'password' => 'student123',
        ]);

        $response->assertOk();
        $response->assertSee('If you first signed in with Google', false);
        $this->assertNull(session('user'));
    }

    public function test_failures_do_not_reveal_whether_an_account_exists(): void
    {
        $this->makeUser();
        $google = $this->makeUser(['email' => 'g@my.cspc.edu.ph']);
        $google->forceFill(['google_id' => 'g-123', 'password' => Hash::make('unknowable')])->save();

        $bodies = array_map(fn ($email) => $this->post('/login', ['email' => $email, 'password' => 'nope-nope'])
            ->assertOk()
            ->getContent(), ['student@my.cspc.edu.ph', 'nobody@my.cspc.edu.ph', 'g@my.cspc.edu.ph']);

        $strip = fn ($html) => preg_replace(['/value="[^"]*@my\.cspc\.edu\.ph"/', '/name="_token" value="[^"]*"/', '/nonce="[^"]*"/'], '', $html);
        $this->assertSame($strip($bodies[0]), $strip($bodies[1]));
        $this->assertSame($strip($bodies[0]), $strip($bodies[2]));
    }

    public function test_array_or_oversized_input_is_rejected_not_an_error(): void
    {
        $this->post('/login', ['email' => ['a@my.cspc.edu.ph'], 'password' => ['x']])->assertOk();
        $this->post('/login', ['email' => 'student@my.cspc.edu.ph', 'password' => str_repeat('a', 5000)])
            ->assertOk()
            ->assertSee('Invalid email or password', false);
        $this->assertNull(session('user'));
    }

    public function test_one_account_is_throttled_across_ips(): void
    {
        $this->makeUser();

        foreach (range(1, 5) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.$i"])
                ->post('/login', ['email' => 'student@my.cspc.edu.ph', 'password' => 'wrong'])
                ->assertOk();
        }

        // A sixth IP, the right password: the account is still on hold.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->post('/login', ['email' => 'student@my.cspc.edu.ph', 'password' => 'student123'])
            ->assertRedirect();
        $this->assertNull(session('user'));
    }

    public function test_google_error_text_is_not_reflected(): void
    {
        $this->get('/auth/google/callback?error=' . urlencode('Your account is locked. Call 0917-000-0000'))
            ->assertRedirect(route('login'));

        $this->assertSame('Google sign-in failed. Please try again.', session('error'));
    }

    public function test_google_callback_without_matching_state_is_refused(): void
    {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);

        $this->get('/auth/google/callback?code=attacker-code&state=forged')
            ->assertRedirect(route('login'));

        $this->assertNull(session('user'));
        $this->assertStringContainsString('expired or was started elsewhere', session('error'));
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
