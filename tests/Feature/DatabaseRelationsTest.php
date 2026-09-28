<?php

namespace Tests\Feature;

use App\Models\Bluebook;
use App\Models\Bookmark;
use App\Models\Log;
use App\Models\User;
use App\Services\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Every table the app keeps is tied to the account behind it. */
class DatabaseRelationsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email = 'student@my.cspc.edu.ph'): User
    {
        return User::create(['name' => 'Gil Realubit', 'email' => $email, 'password' => Hash::make('x'), 'role' => 'Student']);
    }

    private function bluebook(User $user): Bluebook
    {
        return Bluebook::create([
            'title' => 'A Study', 'authors' => ['Realubit, Gil'], 'year' => 2026, 'department' => 'CCS',
            'program' => 'BSIT', 'keywords' => [], 'abstract' => 'x', 'adviser' => 'Cruz, Ana B.',
            'status' => 'Approved', 'uploaded_by' => $user->email, 'uploaded_by_name' => $user->name,
            'pages' => 1, 'views' => 0, 'date_added' => '2026-09-28',
        ]);
    }

    public function test_users_bluebooks_and_bookmarks_reach_each_other(): void
    {
        $user = $this->user();
        $book = $this->bluebook($user);
        Bookmark::create(['user_email' => $user->email, 'user_name' => $user->name, 'bluebook_id' => $book->id, 'added_at' => '2026-09-28']);

        $this->assertTrue($book->uploader->is($user));
        $this->assertSame([$book->id], $user->bluebooks->pluck('id')->all());
        $this->assertTrue($user->bookmarks->first()->bluebook->is($book));
        $this->assertTrue($book->bookmarks->first()->user->is($user));
    }

    public function test_a_log_names_the_account_that_acted(): void
    {
        $user = $this->user();

        Store::addLog(['userName' => $user->name, 'email' => $user->email, 'action' => 'Login']);
        Store::addLog(['userName' => 'Unknown', 'email' => '—', 'action' => 'Unauthorized Access Attempt', 'status' => 'Denied']);

        $this->assertTrue(Log::where('action', 'Login')->first()->user->is($user));
        $this->assertNull(Log::where('action', 'Unauthorized Access Attempt')->first()->user_id);
        $this->assertCount(1, $user->logs);
    }

    public function test_a_database_session_records_its_account(): void
    {
        $user = $this->user();
        config(['session.driver' => 'database']);
        $this->app['session']->setDefaultDriver('database');

        $session = $this->app['session']->driver();
        $session->put('user', ['id' => $user->id]);
        $session->save();

        $this->assertSame($user->id, (int) DB::table('sessions')->value('user_id'));
    }

    public function test_the_unused_starter_tables_are_gone(): void
    {
        $this->assertFalse(Schema::hasTable('password_resets'));
        $this->assertFalse(Schema::hasTable('personal_access_tokens'));
    }

    public function test_the_unused_permissions_column_is_gone(): void
    {
        // What an account may do follows users.role alone.
        $this->assertFalse(Schema::hasColumn('users', 'permissions'));
        $this->assertTrue(Schema::hasColumn('users', 'role'));
    }
}
