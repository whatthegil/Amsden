<?php

namespace Tests\Feature;

use App\Models\Bluebook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Approval is two steps: the admin approves the paper, the author hands the
 * printed waiver in to the library, and only then is the bluebook posted.
 */
class WaiverApprovalTest extends TestCase
{
    use RefreshDatabase;

    private ?string $savedForm = null;

    protected function setUp(): void
    {
        parent::setUp();
        // Tests must not depend on, or clobber, the real form in the repo.
        $form = Bluebook::waiverFormPath();
        if (is_file($form)) {
            $this->savedForm = File::get($form);
        }
    }

    protected function tearDown(): void
    {
        $form = Bluebook::waiverFormPath();
        if ($this->savedForm !== null) {
            File::put($form, $this->savedForm);
        } elseif (is_file($form)) {
            File::delete($form);
        }
        parent::tearDown();
    }

    private function installForm(): void
    {
        File::ensureDirectoryExists(dirname(Bluebook::waiverFormPath()));
        File::put(Bluebook::waiverFormPath(), "%PDF-1.4\n% test waiver\n%%EOF\n");
    }

    private function removeForm(): void
    {
        if (is_file(Bluebook::waiverFormPath())) {
            File::delete(Bluebook::waiverFormPath());
        }
    }

    private function admin(): array
    {
        return ['id' => 1, 'name' => 'Adm', 'email' => 'adm@cspc.edu.ph', 'role' => 'Admin', 'canUpload' => true];
    }

    private function author(): array
    {
        return ['id' => 2, 'name' => 'S T', 'email' => 's@my.cspc.edu.ph', 'role' => 'Student', 'canUpload' => true];
    }

    private function makeBluebook(string $status, string $uploadedBy = 's@my.cspc.edu.ph'): Bluebook
    {
        return Bluebook::create([
            'title'            => 'Waiver Flow Paper',
            'authors'          => ['Dela Cruz, Maria'],
            'year'             => 2024,
            'department'       => 'CCS',
            'program'          => 'Bachelor of Science in Information Technology',
            'keywords'         => ['sample'],
            'abstract'         => 'A sample abstract.',
            'adviser'          => 'Dr. Adviser',
            'status'           => $status,
            'pages'            => 10,
            'uploaded_by'      => $uploadedBy,
            'uploaded_by_name' => 'S T',
            'date_added'       => '2026-01-01',
        ]);
    }

    public function test_approving_holds_the_bluebook_for_the_waiver(): void
    {
        $bluebook = $this->makeBluebook('Pending');

        $this->withSession(['user' => $this->admin()])
            ->post("/admin/bluebooks/{$bluebook->id}/approve")
            ->assertRedirect(route('admin.bluebooks'));

        $this->assertSame(Bluebook::STATUS_AWAITING_WAIVER, $bluebook->fresh()->status);
    }

    public function test_bluebook_awaiting_waiver_is_not_in_browse(): void
    {
        $this->makeBluebook(Bluebook::STATUS_AWAITING_WAIVER);

        $this->withSession(['user' => $this->author()])
            ->get('/student/bluebooks')
            ->assertOk()
            ->assertDontSee('Waiver Flow Paper');
    }

    public function test_waiver_received_posts_the_bluebook(): void
    {
        $bluebook = $this->makeBluebook(Bluebook::STATUS_AWAITING_WAIVER);

        $this->withSession(['user' => $this->admin()])
            ->post("/admin/bluebooks/{$bluebook->id}/waiver-received")
            ->assertRedirect(route('admin.bluebooks'));

        $this->assertSame('Approved', $bluebook->fresh()->status);
        $this->assertDatabaseHas('logs', ['action' => 'Received Waiver', 'document' => 'Waiver Flow Paper']);

        $this->withSession(['user' => $this->author()])
            ->get('/student/bluebooks')
            ->assertSee('Waiver Flow Paper');
    }

    public function test_waiver_received_does_nothing_to_a_pending_bluebook(): void
    {
        $bluebook = $this->makeBluebook('Pending');

        $this->withSession(['user' => $this->admin()])
            ->post("/admin/bluebooks/{$bluebook->id}/waiver-received");

        $this->assertSame('Pending', $bluebook->fresh()->status);
    }

    public function test_admin_list_offers_waiver_received(): void
    {
        $this->makeBluebook(Bluebook::STATUS_AWAITING_WAIVER);

        $this->withSession(['user' => $this->admin()])
            ->get('/admin/bluebooks')
            ->assertOk()
            ->assertSee('Awaiting Waiver')
            ->assertSee('Waiver Received');
    }

    public function test_my_uploads_shows_download_button_only_while_awaiting_waiver(): void
    {
        $waiting = $this->makeBluebook(Bluebook::STATUS_AWAITING_WAIVER);
        $this->makeBluebook('Pending');

        $res = $this->withSession(['user' => $this->author()])->get('/student/my-uploads');

        $res->assertOk();
        $res->assertSee('Download Waiver');
        $res->assertSee(route('student.my-uploads.waiver', $waiting->id), false);
        $this->assertSame(1, substr_count($res->getContent(), 'Download Waiver'));
    }

    public function test_author_downloads_the_waiver_form(): void
    {
        $this->installForm();
        $bluebook = $this->makeBluebook(Bluebook::STATUS_AWAITING_WAIVER);

        $res = $this->withSession(['user' => $this->author()])
            ->get("/student/my-uploads/{$bluebook->id}/waiver");

        $res->assertOk();
        $res->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment', $res->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Access Permission Waiver', $res->headers->get('Content-Disposition'));
    }

    public function test_other_students_cannot_download_someone_elses_waiver(): void
    {
        $this->installForm();
        $bluebook = $this->makeBluebook(Bluebook::STATUS_AWAITING_WAIVER, 'someone.else@my.cspc.edu.ph');

        $this->withSession(['user' => $this->author()])
            ->get("/student/my-uploads/{$bluebook->id}/waiver")
            ->assertRedirect(route('student.my-uploads'));
    }

    public function test_missing_form_explains_instead_of_failing(): void
    {
        $this->removeForm();
        $bluebook = $this->makeBluebook(Bluebook::STATUS_AWAITING_WAIVER);

        $this->withSession(['user' => $this->author()])
            ->get("/student/my-uploads/{$bluebook->id}/waiver")
            ->assertRedirect(route('student.my-uploads'))
            ->assertSessionHas('error');
    }
}
