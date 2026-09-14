<?php

namespace Tests\Feature;

use App\Models\Bluebook;
use App\Services\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * How the document reaches the reader.
 *
 * Every view used to pull the whole object from storage through PHP and out
 * again. That response has to go out chunked with no Content-Length - declaring
 * one is what nginx rejected in d96a734 - and with no length there is nothing
 * for PDF.js to range-request against, so it had to hold all 29 MB before it
 * could draw page one.
 *
 * Where the disk can sign a link the fetch goes straight to the bucket, which
 * answers ranges. The streaming route stays: it serves a disk that cannot sign,
 * and it is what the viewer retries on when the direct fetch is refused.
 */
class BluebookDocumentDeliveryTest extends TestCase
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

    /** A disk that can sign one is used, and the streaming route is kept as the retry. */
    public function test_the_document_is_fetched_straight_from_storage_when_it_can_be_signed(): void
    {
        config(['filesystems.bluebook_direct_fetch' => true]);

        $disk = Mockery::mock();
        $disk->shouldReceive('providesTemporaryUrls')->andReturn(true);
        $disk->shouldReceive('temporaryUrl')->once()->andReturn('https://bucket.example/paper.pdf?sig=abc');
        Storage::shouldReceive('disk')->andReturn($disk);

        $b = $this->makeBluebook();

        $response = $this->withSession(['user' => $this->student()])
            ->get("/student/bluebooks/{$b->id}");

        $response->assertOk();
        $response->assertSee('data-pdf-url="https://bucket.example/paper.pdf?sig=abc"', false);
        $response->assertSee('data-direct="1"', false);
        $response->assertSee('data-fallback-url="' . route('student.bluebook.file', $b->id) . '"', false);
    }

    /**
     * The local driver cannot sign a URL, which is development and the test
     * suite. The viewer must fall back to the route rather than be handed
     * nothing, and must not be told it is talking to a bucket.
     */
    public function test_it_falls_back_to_the_stream_when_the_disk_cannot_sign(): void
    {
        $b = $this->makeBluebook();

        $response = $this->withSession(['user' => $this->student()])
            ->get("/student/bluebooks/{$b->id}");

        $response->assertOk();
        $response->assertSee('data-pdf-url="' . route('student.bluebook.file', $b->id) . '"', false);
        $response->assertDontSee('data-direct', false);
    }

    /** The helper reports honestly rather than throwing on a disk that cannot sign. */
    public function test_a_disk_that_cannot_sign_reports_no_url(): void
    {
        config(['filesystems.bluebook_direct_fetch' => true]);

        $this->assertNull(
            Store::bluebookFileUrl('bluebooks/paper.pdf'),
            'The local driver cannot sign a URL, so the caller must be told to stream instead.'
        );
    }

    /**
     * For its lifetime a signed link is a bearer token: it reads the stored PDF
     * with no session, no role check, and none of the watermarking the viewer
     * applies. That is a wider opening than the session route, so it is not
     * something an install should get without having asked for it.
     */
    public function test_the_direct_link_is_off_unless_it_has_been_asked_for(): void
    {
        $this->assertFalse(
            config('filesystems.bluebook_direct_fetch'),
            'Direct fetch must default to off - the secure posture is the default.'
        );

        $disk = Mockery::mock();
        $disk->shouldReceive('providesTemporaryUrls')->andReturn(true);
        $disk->shouldNotReceive('temporaryUrl');      // must not even be asked
        Storage::shouldReceive('disk')->andReturn($disk);

        $this->assertNull(Store::bluebookFileUrl('bluebooks/paper.pdf'));
    }

    /** Signing failure is a slow path, not a broken one. */
    public function test_a_signing_failure_degrades_to_the_stream(): void
    {
        config(['filesystems.bluebook_direct_fetch' => true]);

        $disk = Mockery::mock();
        $disk->shouldReceive('providesTemporaryUrls')->andReturn(true);
        $disk->shouldReceive('temporaryUrl')->andThrow(new \RuntimeException('no credentials'));
        Storage::shouldReceive('disk')->andReturn($disk);

        $this->assertNull(
            Store::bluebookFileUrl('bluebooks/paper.pdf'),
            'A disk that cannot be signed against must degrade to the stream, not throw.'
        );
    }

    /**
     * A signed link is cross-origin and is answered with a wildcard
     * Access-Control-Allow-Origin, which a browser refuses to pair with
     * credentials - and the link carries its own authorisation, so it does not
     * want them. The streaming route is same-origin and behind the session, so
     * it does. Sending them unconditionally fails every direct fetch.
     */
    public function test_credentials_are_sent_to_the_session_route_and_not_to_the_bucket(): void
    {
        $js = file_get_contents(public_path('js/pdf-viewer.js'));

        $this->assertStringContainsString(
            'withCredentials: !direct',
            $js,
            'Credentials must follow the session route only, never a signed storage link.'
        );
    }

    /**
     * A bucket with no CORS rule for this origin, a link that has outlived the
     * reading session, or storage that cannot be reached must not read to the
     * user as a document that is unavailable - it is still readable through
     * PHP. This is what makes the direct fetch safe to deploy before the bucket
     * is configured.
     */
    public function test_a_refused_direct_fetch_retries_the_stream(): void
    {
        $js = file_get_contents(public_path('js/pdf-viewer.js'));

        $this->assertMatchesRegularExpression(
            '/if \(!direct \|\| !fallback\) throw err;[\s\S]{0,400}?return load\(fallback, false\);/',
            $js,
            'A refused direct fetch must retry the streaming route before giving up.'
        );
    }

    /**
     * Progress does not stop when the document opens: over a signed link PDF.js
     * goes on pulling ranges while the reader reads. Without a latch the status
     * line reappears over pages that are already drawn, frozen at whatever
     * percentage it last saw.
     */
    public function test_the_loading_line_does_not_return_once_the_document_is_open(): void
    {
        $js = file_get_contents(public_path('js/pdf-viewer.js'));

        $this->assertMatchesRegularExpression(
            '/if \(opened \|\|/',
            $js,
            'Progress after the document has opened must not put the loading line back.'
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
