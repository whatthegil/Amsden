<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Signing in starts a new session, signing out ends it, and a sign-in that
 * fails is on the record.
 */
class SessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $user = User::create([
            'name' => 'Gil Realubit', 'email' => 'student@my.cspc.edu.ph',
            'password' => Hash::make('student123'), 'role' => 'Student',
        ]);
        $user->forceFill(['password_set_at' => now(), 'policy_accepted_at' => now()])->save();

        return $user;
    }

    public function test_signing_in_starts_a_new_session(): void
    {
        $this->makeUser();
        $this->startSession();
        $before = session()->getId();

        $this->post('/login', ['email' => 'student@my.cspc.edu.ph', 'password' => 'student123'])
            ->assertRedirect(route('student.dashboard'));

        $this->assertNotSame($before, session()->getId(), 'A session id planted before sign-in must not survive it.');
    }

    public function test_signing_out_ends_the_session_and_its_token(): void
    {
        $user = $this->makeUser();
        $this->withSession(['user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => 'Student', 'canUpload' => false]]);
        $idBefore    = session()->getId();
        $tokenBefore = session()->token();

        $this->post('/logout')->assertRedirect(route('login'));

        $this->assertNull(session('user'));
        $this->assertNotSame($idBefore, session()->getId());
        $this->assertNotSame($tokenBefore, session()->token());
        $this->assertDatabaseHas('logs', ['action' => 'Logout', 'email' => 'student@my.cspc.edu.ph']);
    }

    public function test_a_link_to_logout_signs_no_one_out(): void
    {
        $user = $this->makeUser();
        $session = ['user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => 'Student', 'canUpload' => false]];

        $this->withSession($session)->get('/logout')->assertRedirect(route('login'));

        $this->assertNotNull(session('user'), 'Only a POST with the token signs out.');
        $this->assertDatabaseMissing('logs', ['action' => 'Logout']);
    }

    public function test_the_sign_out_buttons_post_with_the_token(): void
    {
        $user = $this->makeUser();

        $html = $this->withSession(['user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => 'Student', 'canUpload' => false]])
            ->get('/student/dashboard')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<form method="POST" action="' . preg_quote(route('logout'), '#') . '"[^>]*>\s*<input type="hidden" name="_token"#', $html);
        $this->assertStringNotContainsString('<a href="' . route('logout') . '"', $html);
    }

    public function test_a_wrong_password_is_logged_without_the_password(): void
    {
        $this->makeUser();

        $this->post('/login', ['email' => 'student@my.cspc.edu.ph', 'password' => 'guess-one'])->assertOk();

        $this->assertDatabaseHas('logs', [
            'action' => 'Failed Login', 'email' => 'student@my.cspc.edu.ph', 'status' => 'Denied', 'user_name' => 'Gil Realubit',
        ]);
        $this->assertDatabaseMissing('logs', ['document' => 'guess-one']);
        $this->assertStringStartsWith('Wrong password', \App\Models\Log::where('action', 'Failed Login')->value('document'));
    }

    public function test_an_unknown_account_is_logged_too(): void
    {
        $this->post('/login', ['email' => 'nobody@my.cspc.edu.ph', 'password' => 'x'])->assertOk();

        $this->assertDatabaseHas('logs', ['action' => 'Failed Login', 'email' => 'nobody@my.cspc.edu.ph', 'user_name' => 'Unknown']);
    }
}
