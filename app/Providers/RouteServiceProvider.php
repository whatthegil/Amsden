<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */
    // protected $namespace = 'App\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Brute-force protection. This app doesn't use Laravel's auth guards
        // (see AuthController) so there's no failed-login lockout otherwise.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(6)->by($request->ip());
        });

        // Cost protection for the AI-powered Literature Review search — each
        // POST can trigger a paid Claude API call (see LiteratureReviewService).
        // Keyed by the session user's email when logged in (the normal case,
        // since the route already requires role:Student,Faculty), falling
        // back to IP so an unauthenticated hit still gets throttled.
        RateLimiter::for('literature-review-ai', function (Request $request) {
            $email = $request->session()->get('user')['email'] ?? null;
            return Limit::perHour(15)->by($email ?? $request->ip());
        });

        // The archive is meant to be read a document at a time, and every
        // control around it - the watermark, the capture log, the wrapper app's
        // FLAG_SECURE - assumes a person reading. None of it slows down a loop
        // over the id range, which until now could pull the whole archive as
        // fast as storage would serve it. A reader opens a handful of documents
        // in an hour; a scraper wants hundreds.
        // A request per pause in typing, so it allows more than reading does.
        RateLimiter::for('bluebook-search', function (Request $request) {
            $email = $request->session()->get('user')['email'] ?? null;
            return Limit::perMinute(90)->by($email ?? $request->ip());
        });

        RateLimiter::for('bluebook-read', function (Request $request) {
            $email = $request->session()->get('user')['email'] ?? null;

            // The viewer reads a document in byte ranges - dozens of small
            // requests for one paper - so ranges get their own, larger budget.
            // Opening a page or a whole file is what bulk collection needs, and
            // that keeps the tight one.
            if ($request->headers->has('Range')) {
                return Limit::perMinute(600)->by('range:' . ($email ?? $request->ip()));
            }

            return Limit::perMinute(30)->by($email ?? $request->ip());
        });

        // The capture log is evidence, so it must not be floodable into
        // uselessness by a page that has been told to post in a loop. Generous
        // enough for the real thing: the viewer reports a focus loss, a tab
        // switch and a keypress, which a restless reader can genuinely trip a
        // few times a minute.
        RateLimiter::for('capture-flag', function (Request $request) {
            $email = $request->session()->get('user')['email'] ?? null;
            return Limit::perMinute(20)->by($email ?? $request->ip());
        });
    }
}
