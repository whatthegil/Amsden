<?php

namespace Tests\Feature;

use App\Models\Bluebook;
use App\Models\Bookmark;
use App\Services\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Deleting a bluebook from the admin panel.
 *
 * The archive had no way to remove a record at all — no route, no controller
 * method, no UI — so a mistaken or seeded entry could only be taken out by
 * editing the database directly, which left no audit trail.
 */
class AdminDeleteBluebookTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): array
    {
        return [
            'id'        => 1,
            'name'      => 'Test Admin',
            'email'     => 'admin@cspc.edu.ph',
            'role'      => 'Admin',
            'canUpload' => true,
        ];
    }

    private function makeBluebook(array $overrides = []): Bluebook
    {
        return Bluebook::create(array_merge([
            'title'         => 'Seeded Sample Paper',
            'authors'       => ['Dela Cruz, Maria'],
            'year'          => 2024,
            'department'    => 'CCS',
            'program'       => 'Bachelor of Science in Information Technology',
            'keywords'      => ['sample'],
            'abstract'      => 'A sample abstract.',
            'adviser'       => 'Dr. Adviser',
            'status'        => 'Approved',
            'uploaded_by'   => 'tester@my.cspc.edu.ph',
            'uploaded_by_name' => 'Test Student',
            'date_added'    => '2026-01-01',
        ], $overrides));
    }

    public function test_admin_can_delete_a_bluebook(): void
    {
        $bluebook = $this->makeBluebook();

        $response = $this->withSession(['user' => $this->admin()])
            ->post("/admin/bluebooks/{$bluebook->id}/delete");

        $response->assertRedirect(route('admin.bluebooks'));
        $this->assertDatabaseMissing('bluebooks', ['id' => $bluebook->id]);
    }

    public function test_deleting_removes_the_stored_pdf(): void
    {
        Storage::fake(Store::bluebookDisk());
        Storage::disk(Store::bluebookDisk())->put('bluebooks/paper.pdf', 'pdf-bytes');

        $bluebook = $this->makeBluebook(['file_path' => 'bluebooks/paper.pdf']);

        $this->withSession(['user' => $this->admin()])
            ->post("/admin/bluebooks/{$bluebook->id}/delete");

        Storage::disk(Store::bluebookDisk())->assertMissing('bluebooks/paper.pdf');
    }

    public function test_deleting_removes_bookmarks_of_it(): void
    {
        $bluebook = $this->makeBluebook();
        Bookmark::create([
            'user_email'  => 'tester@my.cspc.edu.ph',
            'user_name'   => 'Test Student',
            'bluebook_id' => $bluebook->id,
            'added_at'    => '2026-01-01',
        ]);

        $this->withSession(['user' => $this->admin()])
            ->post("/admin/bluebooks/{$bluebook->id}/delete");

        $this->assertDatabaseMissing('bookmarks', ['bluebook_id' => $bluebook->id]);
    }

    public function test_deletion_is_written_to_the_access_log(): void
    {
        $bluebook = $this->makeBluebook(['title' => 'Paper To Remove']);

        $this->withSession(['user' => $this->admin()])
            ->post("/admin/bluebooks/{$bluebook->id}/delete");

        $this->assertDatabaseHas('logs', [
            'email'    => 'admin@cspc.edu.ph',
            'action'   => 'Deleted Bluebook',
            'document' => 'Paper To Remove',
        ]);
    }

    public function test_students_cannot_delete_a_bluebook(): void
    {
        $bluebook = $this->makeBluebook();

        $this->withSession(['user' => [
            'id' => 2, 'name' => 'Test Student', 'email' => 'tester@my.cspc.edu.ph',
            'role' => 'Student', 'canUpload' => true,
        ]])->post("/admin/bluebooks/{$bluebook->id}/delete");

        $this->assertDatabaseHas('bluebooks', ['id' => $bluebook->id]);
    }
}
