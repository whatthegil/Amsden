<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

/** The Google profile picture on the profile page. */
class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    private const PICTURE = 'https://lh3.googleusercontent.com/a/ACg8ocK-new=s96-c';

    private function user(?string $avatar): User
    {
        $user = User::create([
            'name' => 'Gil Realubit', 'email' => 'girealubit@my.cspc.edu.ph',
            'password' => Hash::make('x'), 'role' => 'Student', 'google_id' => 'g-1', 'avatar' => $avatar,
        ]);
        $user->forceFill(['policy_accepted_at' => now()])->save();

        return $user;
    }

    private function profile(User $user)
    {
        return $this->withSession(['user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => 'Student']])
            ->get('/profile');
    }

    public function test_the_google_picture_is_shown_with_the_initial_ready_behind_it(): void
    {
        $page = $this->profile($this->user(self::PICTURE));

        $page->assertSee('src="' . self::PICTURE . '"', false);
        $this->assertMatchesRegularExpression('/profile-avatar-initial"[^>]*\shidden[\s>][^>]*>?G<\/div>/', $page->getContent());
    }

    public function test_the_initial_shows_when_there_is_no_picture(): void
    {
        $page = $this->profile($this->user(null));

        $page->assertDontSee('class="profile-avatar" referrerpolicy', false);
        $this->assertMatchesRegularExpression('/profile-avatar-initial"[^>]*>G<\/div>/', $page->getContent());
        $this->assertDoesNotMatchRegularExpression('/profile-avatar-initial"[^>]*\shidden[\s>]/', $page->getContent());
    }

    public function test_the_sidebar_shows_the_google_picture_on_every_page(): void
    {
        $user = $this->user(self::PICTURE);

        $this->withSession(['user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => 'Student']])
            ->get('/student/dashboard')
            ->assertSee('src="' . self::PICTURE . '" alt="" class="user-chip-avatar user-chip-photo"', false);
    }

    public function test_the_sidebar_shows_the_initial_without_a_picture(): void
    {
        $user = $this->user(null);

        $html = $this->withSession(['user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => 'Student']])
            ->get('/student/dashboard')->getContent();

        $this->assertStringNotContainsString('user-chip-photo', $html);
        $this->assertMatchesRegularExpression('/class="user-chip-avatar" aria-hidden="true"\s*>G<\/div>/', $html);
    }

    public function test_the_browser_is_allowed_to_load_the_picture(): void
    {
        $csp = $this->profile($this->user(self::PICTURE))->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression('/img-src[^;]*https:\/\/\*\.googleusercontent\.com/', $csp);
    }

    public function test_each_google_sign_in_refreshes_the_picture(): void
    {
        $user = $this->user('https://lh3.googleusercontent.com/a/old-link=s96-c');

        $google = (new GoogleUser())->map(['id' => 'g-1', 'email' => $user->email, 'name' => $user->name, 'avatar' => self::PICTURE]);
        $provider = Mockery::mock();
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($google);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/auth/google/callback?code=abc');

        $this->assertSame(self::PICTURE, $user->fresh()->avatar);
    }
}
