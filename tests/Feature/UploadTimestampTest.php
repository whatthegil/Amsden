<?php

namespace Tests\Feature;

use App\Models\Bluebook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** The Pending queue shows when each waiting upload came in, in Philippine time; nowhere else does. */
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

    public function test_the_archive_list_does_not_show_it(): void
    {
        $this->paper('Approved');

        $this->withSession(['user' => $this->admin])->get('/admin/bluebooks')
            ->assertDontSee('<th>Uploaded</th>', false)
            ->assertDontSee('Sep 28, 2026 · 5:46 PM');
    }

    public function test_the_rejected_list_does_not_show_it(): void
    {
        $this->paper('Rejected');

        $this->withSession(['user' => $this->admin])->get('/admin/rejected')->assertDontSee('Sep 28, 2026 · 5:46 PM');
    }

    public function test_the_admin_paper_view_does_not_show_it(): void
    {
        $book = $this->paper('Pending');

        $this->withSession(['user' => $this->admin])->get('/admin/bluebooks/' . $book->id)
            ->assertDontSee('Sep 28, 2026 · 5:46 PM');
    }
}
