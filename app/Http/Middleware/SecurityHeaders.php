<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\Store;

/**
 * Response headers for every page.
 *
 * The controls around a document - the watermark, the capture log, the wrapper
 * app's FLAG_SECURE - all assume the page is the one this app served, rendered
 * on its own. None of them survive the page being framed by somebody else's
 * site, or a script arriving from somewhere else and taking the overlay off
 * before a capture. These headers are what make those assumptions true.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Streamed responses are the document itself, which sets its own
        // headers and must not be given a policy meant for a page.
        if ($response->headers->has('Content-Disposition')) {
            return $response;
        }

        $headers = [
            // A document URL must not travel to another site in a Referer.
            'Referrer-Policy'        => 'same-origin',
            'X-Content-Type-Options' => 'nosniff',
            // Superseded by frame-ancestors below, kept for older browsers -
            // the wrapper app's WebView among them.
            'X-Frame-Options'        => 'SAMEORIGIN',
            // Nothing here uses any of them, and a page that cannot reach the
            // camera cannot be talked into photographing anything either.
            'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        ];

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        $response->headers->set('Content-Security-Policy', $this->policy());

        return $response;
    }

    /**
     * The app serves every asset itself, so 'self' covers almost all of it.
     *
     * Inline script and style stay allowed because the views are written with
     * both - style attributes throughout the Blade, and a script block in the
     * head. Tightening those means nonces and a pass over the views; it is the
     * next thing worth doing here, not something to switch on underneath them.
     * What this does buy, today, is that a script injected from anywhere else
     * cannot load, nothing can frame the viewer, and no form can post away.
     */
    private function policy(): string
    {
        $directives = [
            "default-src 'self'",
            // blob: is PDF.js - it builds its worker from one.
            "script-src 'self' 'unsafe-inline' blob:",
            "worker-src 'self' blob:",
            // style.css opens with an @import of Google Fonts, so the
            // stylesheet origin has to be allowed here and the file origin
            // under font-src, or the app loses its typeface entirely.
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            // data: is the watermark tile, which is a canvas exported to a URL.
            "img-src 'self' data: blob:",
            "font-src 'self' data: https://fonts.gstatic.com",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ];

        $directives[] = 'connect-src ' . implode(' ', $this->fetchOrigins());

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
