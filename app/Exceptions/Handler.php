<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Rate-limited routes (login, register, the AI-powered literature
        // review search — see RouteServiceProvider::configureRateLimiting)
        // otherwise render Laravel's bare 429 page, which doesn't match the
        // rest of the app. Send the user back with a flash message instead.
        $this->renderable(function (ThrottleRequestsException $e, $request) {
            if (!$request->expectsJson()) {
                return redirect()->back()
                    ->withInput($request->except(['password', 'confirmPassword']))
                    ->with('error', 'Too many attempts. Please wait a moment and try again.');
            }
        });
    }
}
