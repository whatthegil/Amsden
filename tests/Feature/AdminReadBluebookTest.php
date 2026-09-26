<?php

namespace Tests\Feature;

use App\Models\Bluebook;
use App\Services\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The admin reads any bluebook, at any stage, and all of it. */
class AdminReadBluebookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Store::bluebookDisk());
        config(['watermark.per_viewer' => false]);
    }

    private function admin(): array
    {
        return ['id' => 1, 'name' => 'Adm', 'email' => 'adm@cspc.edu.ph', 'role' => 'Admin', 'canUpload' => true];
    }

    private function student(): array
    {
        return ['id' => 2, 'name' => 'S T', 'email' => 's@my.cspc.edu.ph', 'role' => 'Student', 'canUpload' => true];
    }

    private function makeBluebook(array $overrides = []): Bluebook
    {
        Storage::disk(Store::bluebookDisk())->put('bluebooks/p.pdf', '%PDF-1.4 whole document');

        return Bluebook::create(array_merge([
            'title' => 'A Pending Paper', 'authors' => ['Dela Cruz, Maria'], 'year' => 2025,
            'department' => 'CCS', 'program' => 'Bachelor of Science in Information Technology',
            'keywords' => ['sample'], 'abstract' => 'An abstract.', 'adviser' => 'Dr. Adviser',
            'status' => 'Pending', 'pages' => 10, 'uploaded_by' => 's@my.cspc.edu.ph',
            'uploaded_by_name' => 'S T', 'date_added' => '2026-01-01',
            'file_path' => 'bluebooks/p.pdf', 'file_original_name' => 'p.pdf',
        ], $overrides));
    }

    public function test_admin_opens_a_pending_bluebook_from_the_admin_side(): void
    {
        $b = $this->makeBluebook();

        $res = $this->withSession(['user' => $this->admin()])->get("/admin/bluebooks/{$b->id}");

        $res->assertOk();
        $res->assertSee('A Pending Paper');
        $res->assertSee('Admin Portal');
        $res->assertSee("/admin/bluebooks/{$b->id}/file", false);
        $res->assertDontSee('Bookmark');
        $this->assertSame(0, (int) $b->fresh()->views, 'An admin reading is not a view.');
    }

    public function test_admin_gets_the_whole_file_whatever_the_waiver(): void
    {
        $b = $this->makeBluebook(['status' => 'Approved', 'access_level' => Bluebook::ACCESS_CONSULTATION]);

        $res = $this->withSession(['user' => $this->admin()])->get("/admin/bluebooks/{$b->id}/file");

        $res->assertOk();
        $this->assertStringContainsString('whole document', $res->streamedContent());
    }

    public function test_readers_cannot_use_the_admin_routes(): void
    {
        $b = $this->makeBluebook();

        $this->withSession(['user' => $this->student()])
            ->get("/admin/bluebooks/{$b->id}/file")
            ->assertStatus(302);
    }

    public function test_the_admin_list_links_each_title_to_its_page(): void
    {
        $b = $this->makeBluebook();

        $this->withSession(['user' => $this->admin()])
            ->get('/admin/bluebooks')
            ->assertSee("/admin/bluebooks/{$b->id}\"", false);
    }
}
