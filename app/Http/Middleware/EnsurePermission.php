<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Store;
use Closure;
use Illuminate\Http\Request;

/**
 * Restricts an admin route to the staff who hold one of the named privileges
 * (User::PERMISSIONS). An Admin holds them all; a Sub-Admin only those an Admin
 * ticked on their account. Runs after EnsureRole, which has already refreshed
 * the session copy of the account.
 *
 *   ->middleware('permission:review_bluebooks,manage_bluebooks')   // either
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions)
    {
        $user = session('user');

        foreach ($permissions as $permission) {
            if (User::allows($user, $permission)) {
                return $next($request);
            }
        }

        Store::addLog([
            'userName' => $user['name'] ?? 'Unknown',
            'email'    => $user['email'] ?? '—',
            'action'   => 'Unauthorized Access Attempt',
            'document' => '/' . $request->path(),
            'status'   => 'Denied',
        ]);

        return redirect()->route('admin.dashboard')
            ->with('error', 'Your account does not have permission to do that.');
    }
}
