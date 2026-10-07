<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];

    /**
     * No XSRF-TOKEN cookie. The scripts send the token from the csrf-token meta
     * tag, so the cookie was never read - and being script-readable by design,
     * it could not be HttpOnly, which is all it ever added.
     *
     * @var bool
     */
    protected $addHttpCookie = false;
}
