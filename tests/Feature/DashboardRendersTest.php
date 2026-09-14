<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Both dashboards render, and neither carries the stat cards any more.
 *
 * These used to assert on the cards' icons, which is what this file was for:
 * the icons had been outlines that all but vanished at small sizes, and the
 * test pinned them as solid. The cards themselves have since been taken off
 * both dashboards, so what is worth holding now is that each page still comes
 * up, still shows the panels underneath, and does not quietly grow the cards
 * back - the counts they displayed are still handed to both views.
 */
class DashboardRendersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_renders_without_stat_cards(): void
    {
        $admin = ['id' => 1, 'name' => 'A B', 'email' => 'a@cspc.edu.ph', 'role' => 'Admin', 'canUpload' => true];

        $res = $this->withSession(['user' => $admin])->get('/admin/dashboard');

        $res->assertOk();
        $res->assertSee('Recent Bluebooks', false);
        $res->assertSee('Recent Activity', false);

        $res->assertDontSee('stats-grid', false);
        $res->assertDontSee('stat-card', false);
    }

    public function test_student_dashboard_renders_without_stat_cards(): void
    {
        User::create(['name' => 'S T', 'email' => 's@my.cspc.edu.ph', 'password' => Hash::make('x'), 'role' => 'Student']);
        $stu = ['id' => 1, 'name' => 'S T', 'email' => 's@my.cspc.edu.ph', 'role' => 'Student', 'canUpload' => false];

        $res = $this->withSession(['user' => $stu])->get('/student/dashboard');

        $res->assertOk();
        $res->assertSee('Literature Review Search', false);
        $res->assertSee('Recently Added', false);

        $res->assertDontSee('stats-grid', false);
        $res->assertDontSee('stat-card', false);
        // The four labels the cards carried.
        $res->assertDontSee('Approved in Archive', false);
        $res->assertDontSee('My Pending Review', false);
    }
}
