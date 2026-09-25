<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Store;
use Closure;
use Illuminate\Http\Request;

/**
 * Restricts a route to specific roles, based on the session-stored user
 * (this app doesn't use Laravel's auth guards — see AuthController).
 *
 * Not logged in -> sent to login. Logged in but wrong role (e.g. an Admin
 * hitting a /student/* route, or a Student hitting /admin/*) -> sent to
 * their own dashboard rather than the one they tried to reach. Either kind
 * of denial is recorded in the access logs for audit purposes.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = session('user');

        if (!$user) {
            Store::addLog([
                'userName' => 'Unknown',
                'email'    => '—',
                'action'   => 'Unauthorized Access Attempt',
                'document' => '/' . $request->path(),
                'status'   => 'Denied',
            ]);
            return redirect()->route('login');
        }

        // The session holds a copy of the account taken at login, so a role
        // or upload-permission change made by an admin would otherwise not
        // apply until the user logged out and back in. Re-read it each time.
        $account = User::find($user['id'] ?? null);
        if (!$account) {
            session()->forget('user');
            return redirect()->route('login');
        }
        $user = array_merge($user, [
            'name'      => $account->name,
            'email'     => $account->email,
            'role'      => $account->role,
            'canUpload' => (bool) $account->can_upload,
        ]);
        session(['user' => $user]);

        if (!in_array($user['role'], $roles, true)) {
            Store::addLog([
                'userName' => $user['name'],
                'email'    => $user['email'],
                'action'   => 'Unauthorized Access Attempt',
                'document' => '/' . $request->path(),
                'status'   => 'Denied',
            ]);
            return redirect()->route($user['role'] === 'Admin' ? 'admin.dashboard' : 'student.dashboard');
        }

        return $next($request);
    }
}
