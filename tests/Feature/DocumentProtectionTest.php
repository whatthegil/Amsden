<?php

namespace Tests\Feature;

use App\Models\Bluebook;
use App\Models\Log;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What stands between a document and a copy of it.
 *
 * None of it stops a determined reader - a phone pointed at a screen defeats
 * every control here and always will. What these are for is making a casual
 * copy inconvenient, an deliberate one deliberate, and a leak attributable.
 * Each test below is anchored to a way that was measured to work.
 */
class DocumentProtectionTest extends TestCase
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
            'file_path' => 'bluebooks/paper.pdf',
        ]);
    }

    // ── The watermark ────────────────────────────────────────────────────────

    /**
     * The overlay is a div, and a div can be taken off in developer tools.
     * Measured against the guard that protected it, six ways out of ten got
     * past: z-index behind the content, a transform off screen, filter:
     * opacity(0), clip-path, scale, and position: static - none of which touch
     * the four properties it watched.
     *
     * Drawing the identity into the canvas puts it in the same pixels as the
     * document, where there is no property that removes it.
     */
    public function test_every_page_is_stamped_into_its_own_canvas(): void
    {
        $js = file_get_contents(public_path('js/pdf-viewer.js'));

        $this->assertMatchesRegularExpression(
            '/stamp\(canvas\);/',
            $js,
            'Each rendered page must be stamped, not merely covered by an overlay.'
        );

        // Before it is shown: a page appended first is a clean frame on screen.
        $this->assertMatchesRegularExpression(
            '/stamp\(canvas\);[\s\S]{0,400}?holder\.appendChild\(canvas\)/',
            $js,
            'The stamp must be applied before the page is put on screen.'
        );

        $this->assertStringContainsString(
            'detail.dataset.viewer',
            $js,
            'The stamp must carry who is viewing, which is the point of it.'
        );
    }

    /**
     * The guard used to decide whether to restore by recognising the attack,
     * which is why six of them walked past. It now writes the whole style back
     * on every pass without asking what changed, so a way of hiding the overlay
     * that nobody anticipated is undone by the same code as the rest.
     */
    public function test_the_overlay_is_reasserted_rather_than_inspected(): void
    {
        $js = file_get_contents(public_path('js/main.js'));

        $this->assertStringContainsString(
            'function applyWatermarkStyle',
            $js,
            'The overlay style must be re-assertable as a whole.'
        );

        $this->assertMatchesRegularExpression(
            "/setProperty\([\s\S]*?'important'\)/",
            $js,
            'Critical properties must be set !important so a stylesheet cannot win.'
        );

        foreach (['filter', 'clipPath', 'transform', 'zIndex', 'scale', 'mixBlendMode'] as $prop) {
            $this->assertMatchesRegularExpression(
                '/\b' . preg_quote($prop, '/') . '\s*:/',
                $js,
                "The re-asserted style must cover {$prop}, which was a measured bypass."
            );
        }
    }

    // ── Getting at the archive in bulk ───────────────────────────────────────

    /**
     * Every control around a document assumes a person reading one. None of
     * them slows a loop over the id range, which could pull the archive as fast
     * as storage would serve it.
     */
    public function test_reading_documents_in_bulk_is_throttled(): void
    {
        foreach (['student.bluebook', 'student.bluebook.file'] as $name) {
            $route = app('router')->getRoutes()->getByName($name);

            $this->assertContains(
                'throttle:bluebook-read',
                $route->gatherMiddleware(),
                "{$name} must be throttled - it is a route that hands out documents."
            );
        }
    }

    /** The capture log is evidence, so it must not be floodable into uselessness. */
    public function test_the_capture_log_cannot_be_flooded(): void
    {
        $route = app('router')->getRoutes()->getByName('student.bluebook.flag-capture');

        $this->assertContains('throttle:capture-flag', $route->gatherMiddleware());
    }

    /**
     * Only the page being opened was recorded, so a request straight to the
     * file route - a script with a session cookie, or a reader saving the file
     * - left nothing behind, and the log read as though the document had only
     * ever been looked at in the viewer.
     */
    public function test_taking_the_file_itself_is_recorded(): void
    {
        $b = $this->makeBluebook();

        // The file is not on disk in the test suite, so the request 404s after
        // the check - but the point is what the log holds, and a 404 must not
        // be the thing that decides whether an access was recorded.
        $this->withSession(['user' => $this->student()])
            ->get("/student/bluebooks/{$b->id}/file");

        $this->assertDatabaseMissing('logs', ['action' => 'Downloaded Bluebook File']);

        // With the file present the access is recorded.
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::disk('local')->put('bluebooks/paper.pdf', '%PDF-1.4 test');

        $this->withSession(['user' => $this->student()])
            ->get("/student/bluebooks/{$b->id}/file");

        $this->assertDatabaseHas('logs', [
            'email'  => 'tester@my.cspc.edu.ph',
            'action' => 'Downloaded Bluebook File',
        ]);
    }

    // ── Response headers ─────────────────────────────────────────────────────

    /**
     * The controls around a document assume the page is the one this app
     * served, rendered on its own. A script arriving from somewhere else could
     * take the overlay off before a capture; a frame on somebody else's site
     * could hide it behind their own chrome.
     */
    public function test_a_page_carries_the_protective_headers(): void
    {
        $b = $this->makeBluebook();

        $response = $this->withSession(['user' => $this->student()])
            ->get("/student/bluebooks/{$b->id}");

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        // A document URL must not travel to another site in a Referer.
        $response->assertHeader('Referrer-Policy', 'same-origin');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp, 'Pages must carry a content security policy.');

        foreach ([
            "frame-ancestors 'none'",   // nothing may frame the viewer
            "object-src 'none'",        // no plugins
            "base-uri 'self'",          // no base-tag hijack
            "form-action 'self'",       // nothing may post away
            "default-src 'self'",
        ] as $directive) {
            $this->assertStringContainsString($directive, $csp);
        }
    }

    /**
     * style.css opens with an @import of Google Fonts. A policy that forgets it
     * costs the app its typeface everywhere - which is how this was found.
     */
    public function test_the_policy_does_not_break_the_stylesheet(): void
    {
        $b = $this->makeBluebook();

        $csp = $this->withSession(['user' => $this->student()])
            ->get("/student/bluebooks/{$b->id}")
            ->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('https://fonts.googleapis.com', $csp);
        $this->assertStringContainsString('https://fonts.gstatic.com', $csp);
        // PDF.js builds its worker from a blob.
        $this->assertStringContainsString('blob:', $csp);
        // The watermark tile is a canvas exported to a data: URL.
        $this->assertStringContainsString('img-src', $csp);
        $this->assertMatchesRegularExpression('/img-src[^;]*data:/', $csp);
    }

    /** The document stream sets its own headers and must not be given a page policy. */
    public function test_the_document_stream_is_left_alone(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::disk('local')->put('bluebooks/paper.pdf', '%PDF-1.4 test');

        $b = $this->makeBluebook();

        $response = $this->withSession(['user' => $this->student()])
            ->get("/student/bluebooks/{$b->id}/file");

        $response->assertOk();
        $this->assertNull(
            $response->headers->get('Content-Security-Policy'),
            'The streamed document is not a page and takes no page policy.'
        );
        $response->assertHeader('Content-Type', 'application/pdf');
    }
}
