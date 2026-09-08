<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardRendersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_renders_with_solid_stat_icons(): void
    {
        $admin = ['id' => 1, 'name' => 'A B', 'email' => 'a@cspc.edu.ph', 'role' => 'Admin', 'canUpload' => true];
        $res = $this->withSession(['user' => $admin])->get('/admin/dashboard');
        $res->assertOk();
        $res->assertSee('class="stat-icon green"', false);
        $res->assertSee('fill="currentColor"', false);
    }

    public function test_student_dashboard_renders_with_solid_stat_icons(): void
    {
        User::create(['name' => 'S T', 'email' => 's@my.cspc.edu.ph', 'password' => Hash::make('x'), 'role' => 'Student']);
        $stu = ['id' => 1, 'name' => 'S T', 'email' => 's@my.cspc.edu.ph', 'role' => 'Student', 'canUpload' => false];
        $res = $this->withSession(['user' => $stu])->get('/student/dashboard');
        $res->assertOk();
        $res->assertSee('Approved in Archive', false);
        $res->assertSee('fill="currentColor"', false);
    }
}
