<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** No password is ever stored as typed. */
class PasswordHashingTest extends TestCase
{
    use RefreshDatabase;

    private function stored(string $email): string
    {
        return DB::table('users')->where('email', $email)->value('password');
    }

    public function test_a_plain_password_given_to_the_model_is_hashed(): void
    {
        User::create(['name' => 'A', 'email' => 'a@cspc.edu.ph', 'password' => 'plain-text-1', 'role' => 'Student']);

        $this->assertNotSame('plain-text-1', $this->stored('a@cspc.edu.ph'));
        $this->assertSame('bcrypt', password_get_info($this->stored('a@cspc.edu.ph'))['algoName']);
        $this->assertTrue(Hash::check('plain-text-1', $this->stored('a@cspc.edu.ph')));
    }

    public function test_an_already_hashed_password_is_not_hashed_twice(): void
    {
        User::create(['name' => 'B', 'email' => 'b@cspc.edu.ph', 'password' => Hash::make('secret-22'), 'role' => 'Student']);

        $this->assertTrue(Hash::check('secret-22', $this->stored('b@cspc.edu.ph')));
    }

    public function test_every_way_of_setting_a_password_stores_a_hash(): void
    {
        Store::addUser(['name' => 'C', 'email' => 'c@cspc.edu.ph', 'password' => 'admin-made-3', 'role' => 'Faculty']);
        $user = User::where('email', 'c@cspc.edu.ph')->first();

        Store::updateUser($user->id, ['password' => 'admin-reset-4']);
        $this->assertTrue(Hash::check('admin-reset-4', $this->stored('c@cspc.edu.ph')));

        $user->refresh()->setKnownPassword('owner-chosen-5');
        $user->save();
        $this->assertTrue(Hash::check('owner-chosen-5', $this->stored('c@cspc.edu.ph')));

        $this->assertSame(0, DB::table('users')->whereIn('password', ['admin-made-3', 'admin-reset-4', 'owner-chosen-5'])->count());
    }

    public function test_the_session_never_holds_the_password(): void
    {
        User::create(['name' => 'D', 'email' => 'd@cspc.edu.ph', 'password' => 'login-pass-6', 'role' => 'Student']);

        $this->post('/login', ['email' => 'd@cspc.edu.ph', 'password' => 'login-pass-6']);

        $this->assertArrayNotHasKey('password', session('user'));
        $this->assertStringNotContainsString('login-pass-6', json_encode(session()->all()));
    }
}
