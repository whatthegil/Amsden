<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use App\Services\Store;

/**
 * Response headers for every response.
 *
 * The controls around a document - the watermark, the capture log, the wrapper
 * app's FLAG_SECURE - all assume the page is the one this app served, rendered
 * on its own. None of them survive the page being framed by somebody else's
 * site, or a script arriving from somewhere else and taking the overlay off
 * before a capture. These headers are what make those assumptions true.
 *
 * It runs globally rather than in the web group so a 404 or other error that
 * never matched a route is sent with the same headers as a page that did.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        // A fresh nonce per response: the only inline scripts that run are the
        // ones the views mark with it, so an injected <script> or on* attribute
        // does not.
        $nonce = base64_encode(random_bytes(16));
        View::share('cspNonce', $nonce);

        $response = $next($request);

        // Headers that are right for any response, the document included.
        // A document URL must not travel to another site in a Referer.
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // Nothing here is meant to be pulled into another site's page.
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        // Only over HTTPS - over plain HTTP browsers ignore it, and a local
        // http:// setup has nothing to pin.
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Streamed responses are the document itself, which sets its own
        // caching and must not be given a policy meant for a page.
        if ($response->headers->has('Content-Disposition')) {
            return $response;
        }

        // Pages carry a CSRF token and whoever is signed in, so no browser or
        // proxy keeps a copy - Back after sign-out must not show one again.
        // A response that chose its own caching (the page images) keeps it.
        $cache = (string) $response->headers->get('Cache-Control');
        if (!str_contains($cache, 'max-age') && !str_contains($cache, 'no-store')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        $headers = [
            // Superseded by frame-ancestors below, kept for older browsers -
            // the wrapper app's WebView among them.
            'X-Frame-Options'            => 'SAMEORIGIN',
            // Nothing here uses any of them, and a page that cannot reach the
            // camera cannot be talked into photographing anything either.
            'Permissions-Policy'         => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            // A window this app opens, or that opens it, gets no handle on the
            // other. Google sign-in is a full-page redirect, not a popup, so it
            // is unaffected.
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        $response->headers->set('Content-Security-Policy', $this->policy($nonce, $request->isSecure()));

        return $response;
    }

    /**
     * The app serves every asset itself, so 'self' covers almost all of it.
     *
     * Inline script runs only with the nonce: every <script> block in the views
     * carries it, and no view uses on* attributes. Inline style stays allowed
     * because style attributes run throughout the Blade; style cannot run code,
     * so it is the lesser gap and the one left for a later pass over the views.
     */
    private function policy(string $nonce, bool $secure): string
    {
        $directives = [
            "default-src 'self'",
            // blob: is PDF.js - it builds its worker from one.
            "script-src 'self' 'nonce-{$nonce}' blob:",
            "script-src-attr 'none'",
            "worker-src 'self' blob:",
            // style.css opens with an @import of Google Fonts, so the
            // stylesheet origin has to be allowed here and the file origin
            // under font-src, or the app loses its typeface entirely.
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            // data: is the watermark tile, which is a canvas exported to a URL.
            // googleusercontent.com serves the Google profile pictures shown
            // on the profile page.
            "img-src 'self' data: blob: https://*.googleusercontent.com",
            "font-src 'self' data: https://fonts.gstatic.com",
            "media-src 'self'",
            "manifest-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ];

        $directives[] = 'connect-src ' . implode(' ', $this->fetchOrigins());

        // Over HTTPS only: on a local http:// setup it would send every asset
        // request to an https:// that is not listening.
        if ($secure) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }

    /**
     * Where the viewer is allowed to fetch a document from.
     *
     * Only ever 'self' unless documents are served on signed links, in which
     * case the bucket is added - and only the bucket, by origin, rather than
     * opening connect-src to https: at large.
     */
    private function fetchOrigins(): array
    {
        $origins = ["'self'"];

        if (!config('filesystems.bluebook_direct_fetch', false)) {
            return $origins;
        }

        try {
            $disk = Storage::disk(Store::bluebookDisk());
            $url  = $disk->url('probe.pdf');
            $host = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);

            if (filter_var($host, FILTER_VALIDATE_URL)) {
                $origins[] = $host;
            }
        } catch (\Throwable $e) {
            // A disk that cannot name a URL is one that cannot sign one either,
            // so there is nothing to allow and 'self' is already correct.
        }

        return $origins;
    }
}
