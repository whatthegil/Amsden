<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A session idle for 10 minutes is asked to extend, and signed out 2 minutes
 * later if no one answers; the server allows a further minute for the page's
 * once-a-minute activity reports.
 */
class IdleSessionTest extends TestCase
{
    use RefreshDatabase;

    private function student(): array
    {
        $user = User::create([
            'name' => 'Gil Realubit', 'email' => 'student@my.cspc.edu.ph',
            'password' => Hash::make('student123'), 'role' => 'Student',
        ]);
        $user->forceFill(['policy_accepted_at' => now()])->save();

        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => 'Student', 'canUpload' => false];
    }

    public function test_a_recently_active_session_stays_signed_in(): void
    {
        $this->withSession(['user' => $this->student(), 'last_activity' => time() - 12 * 60])
            ->get('/student/dashboard')
            ->assertOk();
    }

    public function test_a_session_idle_past_the_limit_is_signed_out(): void
    {
        $this->withSession(['user' => $this->student(), 'last_activity' => time() - 14 * 60])
            ->get('/student/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionMissing('user')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('logs', ['action' => 'Logout (Idle)']);
    }

    public function test_keep_alive_refreshes_the_activity_time(): void
    {
        $this->withSession(['user' => $this->student(), 'last_activity' => time() - 11 * 60])
            ->postJson('/session/keep-alive')
            ->assertNoContent();

        $this->assertGreaterThanOrEqual(time() - 5, session('last_activity'));
    }

    public function test_keep_alive_after_the_limit_reports_signed_out(): void
    {
        $this->withSession(['user' => $this->student(), 'last_activity' => time() - 20 * 60])
            ->postJson('/session/keep-alive')
            ->assertUnauthorized();
    }

    public function test_the_page_signing_out_idle_says_why(): void
    {
        $this->withSession(['user' => $this->student(), 'last_activity' => time()])
            ->get('/logout?idle=1')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('logs', ['action' => 'Logout (Idle)']);
    }

    public function test_signing_in_again_does_not_inherit_an_old_idle_time(): void
    {
        User::create([
            'name' => 'Gil Realubit', 'email' => 'student@my.cspc.edu.ph',
            'password' => Hash::make('student123'), 'role' => 'Student',
        ]);

        $this->withSession(['last_activity' => time() - 60 * 60])
            ->post('/login', ['email' => 'student@my.cspc.edu.ph', 'password' => 'student123']);

        $this->assertGreaterThanOrEqual(time() - 5, session('last_activity'));
    }

    public function test_signed_in_pages_carry_the_idle_prompt(): void
    {
        $this->withSession(['user' => $this->student()])
            ->get('/student/dashboard')
            ->assertSee('id="idle-modal"', false);
    }
}
