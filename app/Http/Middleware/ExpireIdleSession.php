<?php

namespace App\Http\Middleware;

use App\Services\Store;
use Closure;
use Illuminate\Http\Request;

/**
 * Signs a user out once they have gone idle. The page asks after IDLE_WARN
 * seconds whether to stay signed in and signs out IDLE_GRACE seconds later if
 * no one answers (see main.js); this is the server's side of the same rule,
 * for a tab that was closed or slept through its countdown.
 *
 * The page only tells the server about activity once a minute, so the server
 * allows a minute more than the page before treating the session as idle.
 */
class ExpireIdleSession
{
    public const IDLE_WARN  = 10 * 60;
    public const IDLE_GRACE = 2 * 60;
    private const LIMIT     = self::IDLE_WARN + self::IDLE_GRACE + 60;

    public function handle(Request $request, Closure $next)
    {
        $user = session('user');

        if ($user) {
            $last = session('last_activity');

            if ($last && time() - $last > self::LIMIT) {
                Store::addLog(['userName' => $user['name'], 'email' => $user['email'], 'action' => 'Logout (Idle)', 'document' => '—']);
                session()->forget(['user', 'last_activity']);

                return $request->expectsJson()
                    ? response()->json(['message' => 'Signed out for inactivity.'], 401)
                    : redirect()->route('login')->with('error', self::message());
            }

            session(['last_activity' => time()]);
        }

        return $next($request);
    }

    public static function message(): string
    {
        return 'You were signed out after ' . (self::IDLE_WARN / 60) . ' minutes of inactivity. Please sign in again.';
    }
}
