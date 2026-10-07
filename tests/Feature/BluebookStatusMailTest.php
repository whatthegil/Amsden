<?php

namespace Tests\Feature;

use App\Mail\BluebookStatusMail;
use App\Models\Bluebook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The uploader hears by email whenever their bluebook changes status.
 */
class BluebookStatusMailTest extends TestCase
{
    use RefreshDatabase;

    private const AUTHOR = 's@my.cspc.edu.ph';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function admin(): array
    {
        $u = User::firstOrCreate(['email' => 'adm@cspc.edu.ph'], ['name' => 'Adm', 'password' => Hash::make('x'), 'role' => 'Admin']);

        return ['user' => ['id' => $u->id, 'name' => 'Adm', 'email' => 'adm@cspc.edu.ph', 'role' => 'Admin', 'canUpload' => true]];
    }

    private function makeBluebook(string $status, string $uploadedBy = self::AUTHOR, array $extra = []): Bluebook
    {
        return Bluebook::create($extra + [
            'title'            => 'Mail Flow Paper',
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
            'uploaded_by_name' => 'Maria',
            'date_added'       => '2026-01-01',
        ]);
    }

    private function assertMailed(string $event): void
    {
        Mail::assertQueued(BluebookStatusMail::class, fn(BluebookStatusMail $m) =>
            $m->event === $event && $m->hasTo(self::AUTHOR) && $m->title === 'Mail Flow Paper');
    }

    public function test_approving_tells_the_author_to_bring_the_waiver(): void
    {
        $b = $this->makeBluebook('Pending');
        $this->withSession($this->admin())->post("/admin/bluebooks/{$b->id}/approve");

        $this->assertMailed(BluebookStatusMail::APPROVED);
    }

    public function test_approving_several_tells_each_author(): void
    {
        $b = $this->makeBluebook('Pending');
        $this->withSession($this->admin())->post('/admin/bluebooks/approve-selected', ['ids' => [$b->id]]);

        $this->assertMailed(BluebookStatusMail::APPROVED);
    }

    public function test_posting_tells_the_author(): void
    {
        $b = $this->makeBluebook(Bluebook::STATUS_AWAITING_WAIVER, self::AUTHOR, ['waiver_recorded_at' => now(), 'access_level' => 'public']);
        $this->withSession($this->admin())->post("/admin/bluebooks/{$b->id}/waiver-received");

        $this->assertSame('Approved', $b->fresh()->status);
        $this->assertMailed(BluebookStatusMail::POSTED);
    }

    public function test_rejecting_sends_the_reason(): void
    {
        $b = $this->makeBluebook('Pending');
        $this->withSession($this->admin())->post("/admin/bluebooks/{$b->id}/reject", ['reason' => 'Missing the approval sheet.']);

        Mail::assertQueued(BluebookStatusMail::class, fn(BluebookStatusMail $m) =>
            $m->event === BluebookStatusMail::REJECTED && $m->note === 'Missing the approval sheet.');
    }

    public function test_recalling_tells_the_author(): void
    {
        $b = $this->makeBluebook('Approved');
        $this->withSession($this->admin())->post("/admin/bluebooks/{$b->id}/recall");

        $this->assertMailed(BluebookStatusMail::RECALLED);
    }

    public function test_an_evaluation_with_issues_is_mailed_and_a_clean_one_is_not(): void
    {
        $b = $this->makeBluebook('Pending');

        $this->withSession($this->admin())->post("/admin/bluebooks/{$b->id}/evaluate", ['status' => ['toc' => 'ok']]);
        Mail::assertNothingQueued();

        $this->withSession($this->admin())->post("/admin/bluebooks/{$b->id}/evaluate", ['status' => ['toc' => 'issue']]);
        Mail::assertQueued(BluebookStatusMail::class, fn(BluebookStatusMail $m) =>
            $m->event === BluebookStatusMail::EVALUATED && $m->issues === 1);
    }

    public function test_resubmitting_confirms_receipt(): void
    {
        \Illuminate\Support\Facades\Storage::fake(\App\Services\Store::bluebookDisk());
        \Illuminate\Support\Facades\Queue::fake();
        $b = $this->makeBluebook('Rejected', self::AUTHOR, ['rejection_reason' => 'Fix it.']);

        $pdf = \Illuminate\Http\UploadedFile::fake()->createWithContent('fixed.pdf',
            "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]>>endobj\ntrailer<</Size 4/Root 1 0 R>>\n%%EOF");

        $this->withSession(['user' => ['email' => self::AUTHOR, 'name' => 'Maria', 'role' => 'Student', 'canUpload' => true]])
            ->post("/student/my-uploads/{$b->id}/reupload", ['file' => $pdf])
            ->assertSessionHas('success');

        $this->assertMailed(BluebookStatusMail::RECEIVED);
    }

    public function test_nothing_is_sent_for_a_bluebook_the_library_added(): void
    {
        User::create(['name' => 'Sub', 'email' => 'sub@cspc.edu.ph', 'password' => Hash::make('x'), 'role' => User::ROLE_SUB_ADMIN]);
        $b = $this->makeBluebook('Pending', 'sub@cspc.edu.ph', ['waiver_recorded_at' => now(), 'access_level' => 'public']);

        $this->withSession($this->admin())->post("/admin/bluebooks/{$b->id}/approve");

        Mail::assertNothingQueued();
    }

    public function test_every_message_renders(): void
    {
        foreach ([
            BluebookStatusMail::RECEIVED, BluebookStatusMail::APPROVED, BluebookStatusMail::POSTED,
            BluebookStatusMail::REJECTED, BluebookStatusMail::RECALLED, BluebookStatusMail::EVALUATED,
        ] as $event) {
            $html = (new BluebookStatusMail($event, 'A <b>Title</b>', 'Maria', 'Fix <i>this</i>', 2))->render();

            $this->assertStringContainsString('Hello, Maria', $html);
            $this->assertStringContainsString('A &lt;b&gt;Title&lt;/b&gt;', $html, "{$event}: the title must be escaped.");
            $this->assertStringContainsString(route('student.my-uploads'), $html);
        }

        $rejected = (new BluebookStatusMail(BluebookStatusMail::REJECTED, 'T', 'M', 'Fix <i>this</i>'))->render();
        $this->assertStringContainsString('Fix &lt;i&gt;this&lt;/i&gt;', $rejected);
    }
}
