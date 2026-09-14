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

    private function makeBluebook(array $overrides = []): Bluebook
    {
        return Bluebook::create(array_merge([
            'title' => 'A Studied Paper', 'authors' => ['Dela Cruz, Maria'], 'year' => 2025,
            'department' => 'CCS', 'program' => 'Bachelor of Science in Information Technology',
            'keywords' => ['sample'], 'abstract' => 'An abstract.', 'adviser' => '',
            'status' => 'Approved', 'uploaded_by' => 'tester@my.cspc.edu.ph',
            'uploaded_by_name' => 'Test Student', 'date_added' => '2026-01-01',
        ], $overrides));
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
    /**
     * The document is rendered to canvas rather than handed to the browser in
     * an iframe. Android WebView has no PDF viewer, so an iframe is blank
     * inside the wrapper app, and the watermark cannot be drawn over a frame.
     */
    public function test_the_document_is_rendered_by_pdfjs_not_an_iframe(): void
    {
        $b = $this->makeBluebook(['file_path' => 'bluebooks/paper.pdf']);

        $response = $this->withSession(['user' => $this->student()])
            ->get("/student/bluebooks/{$b->id}");

        $response->assertOk();
        $response->assertSee('id="pdf-view"', false);
        $response->assertSee('/vendor/pdfjs/pdf.min.js', false);
        $response->assertDontSee('<iframe', false);
    }

    /**
     * .pdf-view also carries .watermark-overlay, which is a centred column flex
     * container. While the viewer inherited that, the page list was a flex item
     * sized to its own content - and a page that reserves its height with
     * padding-top and holds its canvas out of flow contributes no width at all.
     * The list collapsed to its padding, every page measured 0px wide and each
     * canvas was drawn 0x0: the document loaded and nothing appeared.
     */
    public function test_the_viewer_is_not_laid_out_as_a_flex_item(): void
    {
        $css = file_get_contents(public_path('css/style.css'));

        $this->assertMatchesRegularExpression(
            '/\.pdf-view\s*\{[^}]*display:\s*block/',
            $css,
            '.pdf-view must override the flex display it inherits from .watermark-overlay.'
        );

        $this->assertMatchesRegularExpression(
            '/\.pdf-pages\s*\{[^}]*width:\s*100%/',
            $css,
            '.pdf-pages must fill the viewer rather than shrink to its contents.'
        );
    }

    /**
     * .pdf-pages is its own scroll container, so the observers that decide when
     * a page is drawn and when it is let go have to take it as their root. With
     * the default root their margins are measured from the window, and a margin
     * on the window does not widen an intervening scroller's clip - so a page
     * was only ever drawn once it was already on screen, and dropped the moment
     * it left. Measured on a 200-page document, two pages held a canvas at any
     * time and scrolling showed grey boxes that filled in late.
     */
    public function test_the_page_observers_watch_the_scroll_container(): void
    {
        $js = file_get_contents(public_path('js/pdf-viewer.js'));

        $this->assertSame(
            2,
            preg_match_all('/new IntersectionObserver\(.*?\{\s*root:\s*pagesEl/s', $js),
            'Both page observers must use .pdf-pages as their root, not the window.'
        );
    }

    /**
     * A render that measures a zero width draws nothing and returns, and the
     * page observers do not fire again afterwards because nothing about the
     * intersection changed. If the viewer is laid out late, or starts hidden,
     * the width arriving is the only signal that those pages can now be drawn -
     * and it reaches the list, not the window, so a window resize listener
     * never hears it.
     */
    public function test_a_late_layout_is_still_drawn(): void
    {
        $js = file_get_contents(public_path('js/pdf-viewer.js'));

        $this->assertMatchesRegularExpression(
            '/new ResizeObserver\(\s*\w+\s*\)\.observe\(pagesEl\)/',
            $js,
            'The viewer must watch the page list for a width it can finally draw into.'
        );
    }

    /**
     * The two observers report separately, each callback followed by its own
     * microtask checkpoint. A page already in the worker cache resolves inside
     * that gap and, finding a keep set the wider observer has not filled in
     * yet, discards its finished canvas. Page 1 is always in that cache -
     * the layout pass reads it for the document proportions - so every document
     * opened on a blank first page. The render observer therefore records the
     * page itself, synchronously, before any of the async work starts.
     */
    public function test_the_first_page_is_kept_before_it_is_drawn(): void
    {
        $js = file_get_contents(public_path('js/pdf-viewer.js'));

        $this->assertMatchesRegularExpression(
            '/keep\.add\(num\);\s*\n\s*render\(/',
            $js,
            'A page must join the keep set before render() starts, or its canvas is thrown away.'
        );
    }

    /**
     * Scrolling past a page that is mid-render cancels it. That is the ordinary
     * path on a long document, not a failure, so it must not leave "This page
     * could not be displayed." sitting over a page the reader can scroll back
     * to perfectly well.
     */
    public function test_a_cancelled_render_is_not_reported_as_an_error(): void
    {
        $js = file_get_contents(public_path('js/pdf-viewer.js'));

        $this->assertStringContainsString(
            "RenderingCancelledException",
            $js,
            'A cancelled render must be distinguished from a page that genuinely failed.'
        );
    }
}