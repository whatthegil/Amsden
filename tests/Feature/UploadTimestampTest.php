<?php

namespace Tests\Feature;

use App\Models\Bluebook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Admins see when each paper was uploaded, in Philippine time. */
class UploadTimestampTest extends TestCase
{
    use RefreshDatabase;

    private array $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::create(['name' => 'Admin', 'email' => 'admin@cspc.edu.ph', 'password' => Hash::make('x'), 'role' => 'Admin']);
        $this->admin = ['id' => $admin->id, 'name' => 'Admin', 'email' => $admin->email, 'role' => 'Admin'];
        User::create(['name' => 'Gil Realubit', 'email' => 's@my.cspc.edu.ph', 'password' => Hash::make('x'), 'role' => 'Student']);

        // 09:46 UTC is 5:46 PM in Manila.
        Carbon::setTestNow(Carbon::parse('2026-09-28 09:46:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function paper(string $status): Bluebook
    {
        return Bluebook::create([
            'title' => 'RFID Attendance', 'authors' => ['Realubit, Gil'], 'year' => 2026, 'department' => 'CCS',
            'program' => 'BSIT', 'keywords' => [], 'abstract' => 'x', 'adviser' => 'Cruz, Ana B.', 'status' => $status,
            'uploaded_by' => 's@my.cspc.edu.ph', 'uploaded_by_name' => 'Gil Realubit', 'pages' => 1, 'views' => 0, 'date_added' => '2026-09-28',
        ]);
    }

    public function test_the_pending_queue_shows_the_upload_time(): void
    {
        $this->paper('Pending');

        $this->withSession(['user' => $this->admin])->get('/admin/pending')->assertSee('Sep 28, 2026 · 5:46 PM');
    }

    public function test_the_archive_list_has_an_uploaded_column(): void
    {
        $this->paper('Approved');

        $this->withSession(['user' => $this->admin])->get('/admin/bluebooks')
            ->assertSee('<th>Uploaded</th>', false)
            ->assertSee('Sep 28, 2026 · 5:46 PM');
    }

    public function test_the_admin_paper_view_says_when_and_by_whom(): void
    {
        $book = $this->paper('Pending');

        $this->withSession(['user' => $this->admin])->get('/admin/bluebooks/' . $book->id)
            ->assertSee('Sep 28, 2026 · 5:46 PM by Gil Realubit');
    }
}
