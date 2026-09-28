<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Five literature review searches and five similarity checks a day each. */
class DailyUsageLimitTest extends TestCase
{
    use RefreshDatabase;

    private array $student;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openai.api_key' => null]);

        $user = User::create(['name' => 'Gil Realubit', 'email' => 'student@my.cspc.edu.ph', 'password' => Hash::make('x'), 'role' => 'Student']);
        $this->student = ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => 'Student'];
    }

    public function test_the_default_limit_is_five_a_day(): void
    {
        $this->assertSame(5, config('limits.daily.literature_review'));
        $this->assertSame(5, config('limits.daily.similarity_check'));
    }

    public function test_the_sixth_literature_search_of_the_day_is_refused(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->withSession(['user' => $this->student])
                ->post('/student/literature-review', ['topic' => "topic {$i}"])
                ->assertOk()
                ->assertDontSee('You have used all');
        }

        $this->withSession(['user' => $this->student])
            ->post('/student/literature-review', ['topic' => 'one more'])
            ->assertOk()
            ->assertSee("You have used all 5 of today&#039;s literature review searches", false)
            ->assertSee('No searches left today');

        $this->assertDatabaseCount('logs', 5);
    }

    public function test_the_page_counts_down_what_is_left(): void
    {
        $this->withSession(['user' => $this->student])->get('/student/similarity-check')->assertSee('5 of 5 checks left today');

        $this->withSession(['user' => $this->student])->post('/student/similarity-check', ['title' => 'RFID attendance']);

        $this->withSession(['user' => $this->student])->get('/student/similarity-check')->assertSee('4 of 5 checks left today');
    }

    public function test_a_similarity_check_with_no_title_is_not_counted(): void
    {
        $this->withSession(['user' => $this->student])->post('/student/similarity-check', ['title' => '']);

        $this->withSession(['user' => $this->student])->get('/student/similarity-check')->assertSee('5 of 5 checks left today');
    }

    public function test_the_sixth_similarity_check_is_refused(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->withSession(['user' => $this->student])->post('/student/similarity-check', ['title' => "title {$i}"]);
        }

        $this->withSession(['user' => $this->student])
            ->post('/student/similarity-check', ['title' => 'one more'])
            ->assertSee("You have used all 5 of today&#039;s similarity checks", false);
    }

    public function test_the_limit_resets_at_midnight_philippine_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 15:30', 'UTC')); // 23:30 in Manila
        for ($i = 1; $i <= 5; $i++) {
            $this->withSession(['user' => $this->student])->post('/student/literature-review', ['topic' => "topic {$i}"]);
        }
        $this->withSession(['user' => $this->student])->get('/student/literature-review')->assertSee('No searches left today');

        Carbon::setTestNow(Carbon::parse('2026-09-28 16:05', 'UTC')); // 00:05 the next day in Manila
        $this->withSession(['user' => $this->student])->get('/student/literature-review')->assertSee('5 of 5 searches left today');

        Carbon::setTestNow();
    }
}
