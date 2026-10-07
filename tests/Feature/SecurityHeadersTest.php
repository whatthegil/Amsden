<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The headers a scanner such as OWASP ZAP looks for, on every kind of response
 * it will reach: a page, a 404 that matched no route, and the HTTPS variants.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    private function scriptSrc(string $csp): string
    {
        preg_match('/script-src ([^;]*)/', $csp, $m);

        return $m[1] ?? '';
    }

    public function test_inline_script_runs_only_with_the_nonce(): void
    {
        $response = $this->get('/');
        $response->assertOk();

        $scriptSrc = $this->scriptSrc($response->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString("'unsafe-inline'", $scriptSrc);
        $this->assertMatchesRegularExpression("/'nonce-[A-Za-z0-9+\/=]{20,}'/", $scriptSrc);
        $this->assertStringContainsString("script-src-attr 'none'", $response->headers->get('Content-Security-Policy'));

        preg_match("/'nonce-([^']+)'/", $scriptSrc, $m);
        $html = $response->getContent();
        preg_match_all('/<script(?![^>]*\ssrc=)[^>]*>/', $html, $inline);
        $this->assertNotEmpty($inline[0], 'The login page has an inline script to check.');
        foreach ($inline[0] as $tag) {
            $this->assertStringContainsString('nonce="' . $m[1] . '"', $tag);
        }
    }

    public function test_each_response_gets_its_own_nonce(): void
    {
        $a = $this->scriptSrc($this->get('/')->headers->get('Content-Security-Policy'));
        $b = $this->scriptSrc($this->get('/')->headers->get('Content-Security-Policy'));

        $this->assertNotSame($a, $b);
    }

    /** The policy forbids them, so one left in a view would silently stop working. */
    public function test_no_view_uses_inline_handlers_or_unmarked_scripts(): void
    {
        foreach (File::allFiles(resource_path('views')) as $file) {
            $blade = $file->getContents();

            $this->assertDoesNotMatchRegularExpression(
                '/<[a-z][^>]*\son[a-z]+\s*=/i', $blade,
                "{$file->getRelativePathname()} has an on* attribute; use addEventListener."
            );

            preg_match_all('/<script(?![^>]*\ssrc=)[^>]*>/', $blade, $tags);
            foreach ($tags[0] as $tag) {
                $this->assertStringContainsString('$cspNonce', $tag, "{$file->getRelativePathname()} has a script without the nonce.");
            }
        }
    }

    public function test_a_page_is_not_kept_in_any_cache(): void
    {
        $cache = $this->get('/')->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $cache);
    }

    public function test_a_route_that_does_not_exist_gets_the_headers_too(): void
    {
        $response = $this->get('/no-such-page-' . uniqid());

        $response->assertNotFound();
        $this->assertNotNull($response->headers->get('Content-Security-Policy'));
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_isolation_headers_are_sent(): void
    {
        $response = $this->get('/');

        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
        $response->assertHeader('Permissions-Policy');
    }

    public function test_hsts_only_over_https(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');

        $secure = $this->get('https://localhost/');
        $this->assertStringContainsString('max-age=31536000', $secure->headers->get('Strict-Transport-Security'));
        $this->assertStringContainsString('upgrade-insecure-requests', $secure->headers->get('Content-Security-Policy'));
    }

    /** The scripts send the token from the meta tag, so the readable cookie is not set at all. */
    public function test_no_script_readable_csrf_cookie(): void
    {
        $response = $this->get('/');

        $response->assertCookieMissing('XSRF-TOKEN');
        $this->assertStringContainsString('name="csrf-token"', $response->getContent());
    }

    public function test_no_cross_origin_access_is_granted(): void
    {
        $response = $this->withHeaders(['Origin' => 'https://evil.example'])->get('/');

        $response->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
