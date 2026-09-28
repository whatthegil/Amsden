<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Email-and-password sign-in, for CSPC addresses only, including accounts made through Google. */
class ManualLoginTest extends TestCase
{
    use RefreshDatabase;

    private function googleAccount(): User
    {
        $user = User::create([
            'name' => 'Gil Realubit', 'email' => 'girealubit@my.cspc.edu.ph',
            'password' => Hash::make(Str::random(32)), 'role' => 'Student', 'google_id' => '1085920448857',
        ]);
        $user->forceFill(['policy_accepted_at' => now()])->save();

        return $user;
    }

    private function signedIn(User $user): array
    {
        return ['user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role]];
    }

    public function test_only_cspc_addresses_may_log_in(): void
    {
        foreach (['someone@gmail.com', 'a@b@cspc.edu.ph', 'x@notcspc.edu.ph', 'x@cspc.edu.ph.evil.com'] as $email) {
            $this->post('/login', ['email' => $email, 'password' => 'whatever1'])
                ->assertSee('Only @cspc.edu.ph or @my.cspc.edu.ph email addresses are allowed.');
        }
        $this->assertNull(session('user'));
    }

    public function test_a_google_account_without_a_password_is_told_how_to_get_one(): void
    {
        $this->googleAccount();

        $this->post('/login', ['email' => 'girealubit@my.cspc.edu.ph', 'password' => 'guessing1'])
            ->assertSee('set a password on your Profile page');
        $this->assertNull(session('user'));
    }

    public function test_a_google_account_sets_a_password_without_a_current_one_then_logs_in_manually(): void
    {
        $user = $this->googleAccount();

        $this->withSession($this->signedIn($user))->get('/profile')
            ->assertSee('Set a Password')
            ->assertDontSee('Current Password');

        $this->withSession($this->signedIn($user))->post('/profile/password', [
            'new_password' => 'cspc-secret-1', 'new_password_confirmation' => 'cspc-secret-1',
        ])->assertSessionHas('success');

        $this->assertTrue($user->fresh()->hasKnownPassword());
        $this->assertDatabaseHas('logs', ['action' => 'Set Password']);

        session()->flush();
        $this->post('/login', ['email' => 'GiRealubit@my.cspc.edu.ph ', 'password' => 'cspc-secret-1'])
            ->assertRedirect(route('student.dashboard'));
        $this->assertSame($user->id, session('user')['id']);
    }

    public function test_once_set_changing_it_needs_the_current_password(): void
    {
        $user = $this->googleAccount();
        $user->setKnownPassword('cspc-secret-1');
        $user->save();

        $this->withSession($this->signedIn($user))->get('/profile')->assertSee('Current Password');

        $this->withSession($this->signedIn($user))->post('/profile/password', [
            'new_password' => 'another-pass-2', 'new_password_confirmation' => 'another-pass-2',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('cspc-secret-1', $user->fresh()->password));
    }

    public function test_an_account_an_admin_made_keeps_its_password_after_linking_google(): void
    {
        \App\Services\Store::addUser(['name' => 'Ana Cruz', 'email' => 'ancruz@cspc.edu.ph', 'password' => 'faculty-pass', 'role' => 'Faculty']);
        $user = User::where('email', 'ancruz@cspc.edu.ph')->first();
        $user->forceFill(['google_id' => '99'])->save();

        $this->post('/login', ['email' => 'ancruz@cspc.edu.ph', 'password' => 'faculty-pass']);

        $this->assertSame($user->id, session('user')['id'] ?? null);
    }
}
