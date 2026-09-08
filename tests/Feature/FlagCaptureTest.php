<?php

namespace Tests\Feature;

use App\Models\Bluebook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The capture-attempt endpoint the bluebook viewer posts to.
 *
 * A web page cannot stop an operating-system screenshot, so this is an audit
 * trail rather than a protection. What it records therefore has to be accurate:
 * a deliberate PrintScreen press and an incidental window switch are both
 * flagged, and a reviewer needs to be able to tell them apart.
 */
class FlagCaptureTest extends TestCase
{
    use RefreshDatabase;

    private function student(): array
    {
        return [
            'id' => 1, 'name' => 'Test Student', 'email' => 'tester@my.cspc.edu.ph',
            'role' => 'Student', 'canUpload' => false,
        ];
    }

    private function makeBluebook(): Bluebook
    {
        return Bluebook::create([
            'title' => 'A Studied Paper', 'authors' => ['Dela Cruz, Maria'], 'year' => 2025,
            'department' => 'CCS', 'program' => 'Bachelor of Science in Information Technology',
            'keywords' => ['sample'], 'abstract' => 'An abstract.', 'adviser' => '',
            'status' => 'Approved', 'uploaded_by' => 'tester@my.cspc.edu.ph',
            'uploaded_by_name' => 'Test Student', 'date_added' => '2026-01-01',
        ]);
    }

    public function test_it_records_the_reason_the_page_reported(): void
    {
        $b = $this->makeBluebook();

        $this->withSession(['user' => $this->student()])
            ->postJson("/student/bluebooks/{$b->id}/flag-capture", ['reason' => 'Snipping shortcut (Win+Shift+S)'])
            ->assertOk();

        $this->assertDatabaseHas('logs', [
            'action'   => 'Screenshot/Recording Attempt',
            'status'   => 'Flagged',
            'document' => 'A Studied Paper — Snipping shortcut (Win+Shift+S)',
        ]);
    }

    public function test_it_distinguishes_an_incidental_focus_loss(): void
    {
        $b = $this->makeBluebook();

        $this->withSession(['user' => $this->student()])
            ->postJson("/student/bluebooks/{$b->id}/flag-capture", ['reason' => 'Window lost focus'])
            ->assertOk();

        $this->assertDatabaseHas('logs', ['document' => 'A Studied Paper — Window lost focus']);
    }

    /** The reason comes from the page, so it must not be written in unchecked. */
    public function test_an_unrecognised_reason_is_not_written_into_the_log(): void
    {
        $b = $this->makeBluebook();

        $this->withSession(['user' => $this->student()])
            ->postJson("/student/bluebooks/{$b->id}/flag-capture", ['reason' => '<script>alert(1)</script>'])
            ->assertOk();

        $this->assertDatabaseHas('logs', ['document' => 'A Studied Paper — Unknown']);
        $this->assertDatabaseMissing('logs', ['document' => 'A Studied Paper — <script>alert(1)</script>']);
    }

    /**
     * On a phone nothing at all fires when a screenshot is taken, so the
     * watermark is the only control that reaches the captured image. It can
     * only name the viewer if the page gives it their identity.
     */
    public function test_the_viewer_page_carries_the_viewer_identity_for_the_watermark(): void
    {
        $b = $this->makeBluebook();

        $this->withSession(['user' => $this->student()])
            ->get("/student/bluebooks/{$b->id}")
            ->assertOk()
            ->assertSee('data-viewer="tester@my.cspc.edu.ph"', false);
    }

    public function test_watermark_tampering_is_recorded(): void
    {
        $b = $this->makeBluebook();

        $this->withSession(['user' => $this->student()])
            ->postJson("/student/bluebooks/{$b->id}/flag-capture", ['reason' => 'Watermark tampering'])
            ->assertOk();

        $this->assertDatabaseHas('logs', ['document' => 'A Studied Paper — Watermark tampering']);
    }

    public function test_a_missing_reason_falls_back_to_unknown(): void
    {
        $b = $this->makeBluebook();

        $this->withSession(['user' => $this->student()])
            ->postJson("/student/bluebooks/{$b->id}/flag-capture", [])
            ->assertOk();

        $this->assertDatabaseHas('logs', ['document' => 'A Studied Paper — Unknown']);
    }
}
