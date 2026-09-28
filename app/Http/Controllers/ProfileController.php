<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * The session only carries a snapshot of the user; always load the live
     * record so the profile reflects edits made elsewhere (e.g. by an admin).
     */
    private function currentUser(): ?User
    {
        $sessionUser = session('user');
        return $sessionUser ? User::where('email', $sessionUser['email'])->first() : null;
    }

    public function show()
    {
        $user = $this->currentUser();
        if (!$user) {
            session()->forget('user');
            return redirect()->route('login');
        }

        return view('pages.profile', [
            'user'    => session('user'),
            'active'  => 'profile',
            'profile' => [
                'name'         => $user->name,
                'email'        => $user->email,
                'role'         => $user->role,
                'canUpload'    => (bool) $user->can_upload,
                'avatar'       => $user->avatar,
                'googleLinked' => (bool) $user->google_id,
                'hasPassword'  => $user->hasKnownPassword(),
                'createdAt'    => $user->created_at ? $user->created_at->format('F j, Y') : null,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $user = $this->currentUser();
        if (!$user) {
            session()->forget('user');
            return redirect()->route('login');
        }

        $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $user->name = trim($request->input('name'));
        $user->save();

        // Keep the session snapshot in sync so the sidebar/topbar update immediately
        session(['user' => array_merge(session('user'), ['name' => $user->name])]);

        Store::addLog(['userName' => $user->name, 'email' => $user->email, 'action' => 'Updated Profile', 'document' => '—']);

        return redirect()->route('profile')->with('success', 'Profile updated successfully.');
    }

    public function password(Request $request)
    {
        $user = $this->currentUser();
        if (!$user) {
            session()->forget('user');
            return redirect()->route('login');
        }

        // An account made by Google sign-in has only a random password, so it
        // sets its first one without one; signing in through Google already
        // proved the owner holds the CSPC email.
        $first = !$user->hasKnownPassword();

        $request->validate([
            'current_password' => $first ? ['nullable'] : ['required'],
            'new_password'     => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (!$first && !Hash::check($request->input('current_password'), $user->password)) {
            return redirect()->route('profile')->with('password_error', 'Your current password is incorrect.');
        }

        $user->setKnownPassword($request->input('new_password'));
        $user->save();

        Store::addLog(['userName' => $user->name, 'email' => $user->email, 'action' => $first ? 'Set Password' : 'Changed Password', 'document' => '—']);

        return redirect()->route('profile')->with('success', $first
            ? 'Password set. You can now log in with your CSPC email and this password, or keep using Google.'
            : 'Password changed successfully.');
    }
}
