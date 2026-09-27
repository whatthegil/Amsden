<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Faculty browse and read the archive - the dashboard, Browse, a bluebook,
 * their bookmarks and history. Submitting papers and the research tools are
 * for Students, even if a Faculty account has uploads switched on.
 */
class FacultyAccessTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role, bool $canUpload = false): User
    {
        return User::create([
            'name' => $role . ' Person', 'email' => strtolower($role) . '@cspc.edu.ph',
            'password' => Hash::make('secret'), 'role' => $role, 'can_upload' => $canUpload,
        ]);
    }

    private function as(User $u): array
    {
        return ['user' => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role, 'canUpload' => (bool) $u->can_upload]];
    }

    public function test_faculty_can_browse(): void
    {
        $faculty = $this->account('Faculty');

        foreach (['/student/dashboard', '/student/bluebooks', '/student/bookmarks', '/student/history'] as $url) {
            $this->withSession($this->as($faculty))->get($url)->assertOk();
        }
    }

    public function test_faculty_cannot_upload_or_use_the_student_tools(): void
    {
        // Uploads switched on before this rule: still not allowed.
        $faculty = $this->account('Faculty', true);

        foreach (['/student/upload', '/student/my-uploads', '/student/similarity-check', '/student/literature-review'] as $url) {
            $this->withSession($this->as($faculty))->get($url)->assertRedirect(route('student.dashboard'));
        }

        $this->withSession($this->as($faculty))->post('/student/upload', ['title' => 'Sneaked In'])
            ->assertRedirect(route('student.dashboard'));
        $this->assertDatabaseMissing('bluebooks', ['title' => 'Sneaked In']);
    }

    public function test_the_faculty_sidebar_and_dashboard_offer_only_browsing(): void
    {
        $faculty = $this->account('Faculty', true);

        $res = $this->withSession($this->as($faculty))->get('/student/dashboard');
        $res->assertOk();
        $res->assertSee(route('student.bluebooks'), false);
        $res->assertSee(route('student.bookmarks'), false);
        $res->assertSee(route('student.history'), false);
        $res->assertDontSee(route('student.upload'), false);
        $res->assertDontSee(route('student.my-uploads'), false);
        $res->assertDontSee(route('student.similarity-check'), false);
        $res->assertDontSee(route('student.literature-review'), false);
    }

    public function test_students_keep_every_page(): void
    {
        $student = $this->account('Student', true);

        foreach (['/student/upload', '/student/my-uploads', '/student/similarity-check', '/student/literature-review'] as $url) {
            $this->withSession($this->as($student))->get($url)->assertOk();
        }
    }

    public function test_an_admin_cannot_switch_on_uploads_for_faculty(): void
    {
        $admin   = $this->account('Admin');
        $faculty = $this->account('Faculty');

        $this->withSession($this->as($admin))->post("/admin/users/{$faculty->id}/enable-upload");
        $this->assertFalse((bool) $faculty->fresh()->can_upload);

        $this->withSession($this->as($admin))->get('/admin/users')
            ->assertDontSee(route('admin.users.enableUpload', $faculty->id), false);
    }
}
