<?php

namespace Tests\Feature;

use App\Services\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The access log is shown a page at a time, newest first. */
class AdminLogsPagingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): array
    {
        return ['id' => 1, 'name' => 'Adm', 'email' => 'adm@cspc.edu.ph', 'role' => 'Admin', 'canUpload' => true];
    }

    private function seedLogs(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Store::addLog(['userName' => 'User', 'email' => 'u@cspc.edu.ph', 'action' => 'Login', 'document' => "Entry $i"]);
        }
    }

    public function test_first_page_shows_the_newest_fifty(): void
    {
        $this->seedLogs(120);

        $res = $this->withSession(['user' => $this->admin()])->get('/admin/logs');

        $res->assertOk();
        $res->assertSee('120 entries');
        $res->assertSee('Page 1 of 3');
        $res->assertSee('Entry 120');
        $res->assertSee('Entry 71');
        $res->assertDontSee('Entry 70<', false);
        $res->assertSee('Older');
        $res->assertDontSee('Newer');
    }

    public function test_later_pages_continue_the_numbering(): void
    {
        $this->seedLogs(120);

        $res = $this->withSession(['user' => $this->admin()])->get('/admin/logs?page=3');

        $res->assertOk();
        $res->assertSee('Showing 101&ndash;120 of 120', false);
        $res->assertSee('Entry 1<', false);
        $res->assertSee('Newer');
        $res->assertDontSee('Older');
    }

    public function test_a_short_log_has_no_pager(): void
    {
        $this->seedLogs(3);

        $this->withSession(['user' => $this->admin()])
            ->get('/admin/logs')
            ->assertOk()
            ->assertDontSee('Page 1 of');
    }
}
