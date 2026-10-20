<?php

namespace Tests\Feature;

use App\Models\Bluebook;
use App\Models\BluebookVersion;
use App\Models\User;
use App\Services\Store;
use App\Services\VersionHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Checklist;
use Tests\TestCase;

/**
 * Every file submitted for a bluebook is kept as a version, with who sent it,
 * what changed, and who decided what on it and when.
 */
class VersionHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Store::bluebookDisk());
        Queue::fake();
    }

    private function admin(): array
    {
        User::firstOrCreate(['email' => 'adm@cspc.edu.ph'], ['name' => 'Ada Admin', 'password' => bcrypt('x'), 'role' => 'Admin']);

        return ['id' => 1, 'name' => 'Ada Admin', 'email' => 'adm@cspc.edu.ph', 'role' => 'Admin', 'canUpload' => true];
    }

    private function author(): array
    {
        return ['id' => 2, 'name' => 'Maria Dela Cruz', 'email' => 's@my.cspc.edu.ph', 'role' => 'Student', 'canUpload' => true];
    }

    private function fakePdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            . "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]>>endobj\n"
            . "trailer<</Size 4/Root 1 0 R>>\n%%EOF");
    }

    /** A pending submission with its first version, as an upload leaves it. */
    private function submission(): Bluebook
    {
        Storage::disk(Store::bluebookDisk())->put('bluebooks/first.pdf', '%PDF-1.4 first');
        $b = Bluebook::create([
            'title' => 'Versioned Paper', 'authors' => ['Dela Cruz, Maria'], 'year' => 2025, 'department' => 'CCS',
            'program' => 'Bachelor of Science in Information Technology', 'keywords' => ['k'], 'abstract' => 'An abstract.',
            'adviser' => 'Dr. A', 'status' => 'Pending', 'pages' => 10, 'uploaded_by' => 's@my.cspc.edu.ph',
            'uploaded_by_name' => 'Maria Dela Cruz', 'date_added' => '2026-01-01',
            'file_path' => 'bluebooks/first.pdf', 'file_original_name' => 'first.pdf', 'file_size' => 14,
        ]);
        VersionHistory::submitted($b->id, $this->author());

        return $b;
    }

    public function test_decline_resubmit_and_approve_are_each_recorded(): void
    {
        $b = $this->submission();

        $this->withSession(['user' => $this->admin()])
            ->post("/admin/bluebooks/{$b->id}/reject", Checklist::rejecting('Add chapter 3.'));
        $this->withSession(['user' => $this->author()])
            ->post("/student/my-uploads/{$b->id}/reupload", ['file' => $this->fakePdf('second.pdf'), 'notes' => 'Added chapter 3.'])
            ->assertSessionHas('success');
        $this->withSession(['user' => $this->admin()])
            ->post("/admin/bluebooks/{$b->id}/approve");

        $versions = BluebookVersion::where('bluebook_id', $b->id)->orderBy('version')->get();
        $this->assertCount(2, $versions);

        [$first, $second] = $versions;
        $this->assertSame('original', $first->kind);
        $this->assertSame('bluebooks/first.pdf', $first->file_path);
        $this->assertSame('Declined', $first->outcome);
        $this->assertSame('adm@cspc.edu.ph', $first->reviewed_by);
        $this->assertSame('Add chapter 3.', $first->reason);
        $this->assertSame(1, $first->issues_count);
        $this->assertSame('issue', $first->evaluation['complete']['status']);
        $this->assertNotNull($first->reviewed_at);

        $this->assertSame('resubmission', $second->kind);
        $this->assertSame('second.pdf', $second->file_original_name);
        $this->assertSame('Added chapter 3.', $second->notes);
        $this->assertSame('s@my.cspc.edu.ph', $second->submitted_by);
        $this->assertSame('Approved', $second->outcome);
        $this->assertSame('Ada Admin', $second->reviewed_by_name);

        $history = VersionHistory::forBluebook($b->id);
        $this->assertSame(1, $history['revisionsRequired']);
        $this->assertSame(1, $history['revisionsMade']);

        // The declined file is kept for reference.
        Storage::disk(Store::bluebookDisk())->assertExists('bluebooks/first.pdf');
    }

    public function test_a_recall_reopens_the_version_but_keeps_the_approval_in_its_steps(): void
    {
        $b = $this->submission();
        $this->withSession(['user' => $this->admin()])->post("/admin/bluebooks/{$b->id}/approve");
        $this->withSession(['user' => $this->admin()])->post("/admin/bluebooks/{$b->id}/recall");

        $v = BluebookVersion::where('bluebook_id', $b->id)->sole();
        $this->assertNull($v->outcome);
        $this->assertSame(['Submitted', 'Approved', 'Recalled'], array_column($v->events, 'action'));
    }

    public function test_a_bluebook_from_before_the_history_starts_one_when_reviewed(): void
    {
        $b = Bluebook::create([
            'title' => 'Old Paper', 'authors' => ['A'], 'year' => 2020, 'department' => 'CCS', 'program' => 'BSIT',
            'keywords' => ['k'], 'abstract' => 'x', 'adviser' => 'Dr. A', 'status' => 'Pending', 'pages' => 3,
            'uploaded_by' => 's@my.cspc.edu.ph', 'uploaded_by_name' => 'Maria Dela Cruz', 'date_added' => '2020-01-01',
        ]);

        $this->withSession(['user' => $this->admin()])->post("/admin/bluebooks/{$b->id}/approve");

        $v = BluebookVersion::where('bluebook_id', $b->id)->sole();
        $this->assertSame(1, $v->version);
        $this->assertSame('Approved', $v->outcome);
    }

    public function test_the_admin_sees_the_history_and_can_open_an_earlier_file(): void
    {
        $b = $this->submission();
        $this->withSession(['user' => $this->admin()])
            ->post("/admin/bluebooks/{$b->id}/reject", Checklist::rejecting('Add chapter 3.'));
        $this->withSession(['user' => $this->author()])
            ->post("/student/my-uploads/{$b->id}/reupload", ['file' => $this->fakePdf('second.pdf'), 'notes' => 'Added chapter 3.']);

        $this->withSession(['user' => $this->admin()])->get("/admin/bluebooks/{$b->id}")
            ->assertOk()
            ->assertSee('Version history')
            ->assertSee('1 revision required')
            ->assertSee('Added chapter 3.')
            ->assertSee('Ada Admin (adm@cspc.edu.ph)', false)
            ->assertSee(route('admin.bluebooks.versionFile', [$b->id, 1]), false);

        $this->withSession(['user' => $this->admin()])->get("/admin/bluebooks/{$b->id}/versions/1/file")->assertOk();
        $this->withSession(['user' => $this->admin()])->get("/admin/bluebooks/{$b->id}/versions/9/file")->assertNotFound();
        // Not the author's to fetch.
        $this->withSession(['user' => $this->author()])->get("/admin/bluebooks/{$b->id}/versions/1/file")->assertRedirect();
    }

    public function test_the_author_sees_their_history_without_the_reviewers_identity(): void
    {
        $b = $this->submission();
        $this->withSession(['user' => $this->admin()])
            ->post("/admin/bluebooks/{$b->id}/reject", Checklist::rejecting('Add chapter 3.'));

        $this->withSession(['user' => $this->author()])->get('/student/my-uploads')
            ->assertOk()
            ->assertSee('Version history')
            ->assertSee('Declined')
            ->assertDontSee('adm@cspc.edu.ph');
    }

    public function test_the_migration_starts_each_existing_bluebook_at_version_1(): void
    {
        $b = $this->submission();
        $b->forceFill(['status' => 'Rejected', 'rejection_reason' => 'Fix it.', 'evaluation' => ['complete' => ['status' => 'issue', 'comment' => 'x']], 'evaluated_at' => now()])->save();
        Store::addLog(['userName' => 'Ada Admin', 'email' => 'adm@cspc.edu.ph', 'action' => 'Rejected Bluebook', 'document' => $b->title, 'bluebookId' => $b->id]);

        $migration = require database_path('migrations/2026_10_21_000001_create_bluebook_versions_table.php');
        $migration->down();
        $migration->up();

        $v = BluebookVersion::where('bluebook_id', $b->id)->sole();
        $this->assertSame(1, $v->version);
        $this->assertSame('Declined', $v->outcome);
        $this->assertSame('adm@cspc.edu.ph', $v->reviewed_by);
        $this->assertSame('Fix it.', $v->reason);
        $this->assertSame(1, $v->issues_count);
        $this->assertSame('bluebooks/first.pdf', $v->file_path);
    }

    public function test_deleting_a_bluebook_removes_every_versions_file(): void
    {
        $b = $this->submission();
        $this->withSession(['user' => $this->admin()])
            ->post("/admin/bluebooks/{$b->id}/reject", Checklist::rejecting('Add chapter 3.'));
        $this->withSession(['user' => $this->author()])
            ->post("/student/my-uploads/{$b->id}/reupload", ['file' => $this->fakePdf('second.pdf')]);
        $second = $b->fresh()->file_path;

        $this->withSession(['user' => $this->admin()])->post("/admin/bluebooks/{$b->id}/delete");

        Storage::disk(Store::bluebookDisk())->assertMissing('bluebooks/first.pdf');
        Storage::disk(Store::bluebookDisk())->assertMissing($second);
        $this->assertSame(0, BluebookVersion::count());
    }
}
