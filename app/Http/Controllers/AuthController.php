<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    private function isAllowedEmail(string $email): bool
    {
        return str_ends_with($email, '@cspc.edu.ph') || str_ends_with($email, '@my.cspc.edu.ph');
    }

    private function startSession(User $user): void
    {
        session(['user' => [
            'id'        => $user->id,
            'name'      => $user->name,
            'email'     => $user->email,
            'role'      => $user->role,
            'canUpload' => (bool) $user->can_upload,
            'avatar'    => $user->avatar,
            'createdAt' => $user->created_at ? $user->created_at->format('Y-m-d') : now()->format('Y-m-d'),
        ]]);
    }

    private function redirectToDashboard(User $user)
    {
        if ($user->role === 'Admin') {
            return redirect()->route('admin.dashboard');
        }
        // First-ever login for this account -> show the Acceptable Use Policy
        // once; every login after they accept it skips straight to the dashboard.
        return $user->policy_accepted_at
            ? redirect()->route('student.dashboard')
            : redirect()->route('student.policy');
    }

    // ─── Email / Password Auth ─────────────────────────────────────────────────

    public function loginForm()
    {
        if (session('user')) {
            $u = session('user');
            return $u['role'] === 'Admin'
                ? redirect()->route('admin.dashboard')
                : redirect()->route('student.dashboard');
        }
        return view('pages.login', ['error' => null, 'success' => null, 'email' => null]);
    }

    public function login(Request $request)
    {
        $email    = $request->input('email');
        $password = $request->input('password');

        if (!$this->isAllowedEmail($email)) {
            return view('pages.login', ['error' => 'Only @cspc.edu.ph or @my.cspc.edu.ph email addresses are allowed.', 'success' => null, 'email' => $email]);
        }

        $user = User::where('email', $email)->first();
        if (!$user || !Hash::check($password, $user->password)) {
            return view('pages.login', ['error' => 'Invalid email or password. Please try again.', 'success' => null, 'email' => $email]);
        }

        Store::addLog(['userName' => $user->name, 'email' => $user->email, 'action' => 'Login', 'document' => '—']);
        $this->startSession($user);

        return $this->redirectToDashboard($user);
    }

    // Self-service email/password registration was removed: accounts are
    // created by the library administrator (admin → Users → New), or
    // provisioned automatically on first CSPC Google sign-in. This page just
    // tells the user who to contact for access or a password reset.
    public function forgotPassword()
    {
        return view('pages.forgot-password');
    }

    public function logout()
    {
        $user = session('user');
        if ($user) {
            Store::addLog(['userName' => $user['name'], 'email' => $user['email'], 'action' => 'Logout', 'document' => '—']);
        }
        session()->forget('user');
        return redirect()->route('login');
    }

    // ─── Google OAuth ──────────────────────────────────────────────────────────

    public function redirectToGoogle()
    {
        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Exception $e) {
            return redirect()->route('login')->with('error', 'Google sign-in failed. Please try again.');
        }

        $email = $googleUser->getEmail();

        if (!$this->isAllowedEmail($email)) {
            return redirect()->route('login')->with('error', 'Only @cspc.edu.ph or @my.cspc.edu.ph Google accounts are allowed. Please use your institutional email.');
        }

        // Find by google_id first, then fallback to email
        $user = User::where('google_id', $googleUser->getId())
                    ->orWhere('email', $email)
                    ->first();

        if ($user) {
            if (!$user->google_id) {
                $user->google_id = $googleUser->getId();
                $user->avatar    = $googleUser->getAvatar();
                $user->save();
            }
            Store::addLog(['userName' => $user->name, 'email' => $user->email, 'action' => 'Login via Google', 'document' => '—']);
        } else {
            $role = str_ends_with($email, '@my.cspc.edu.ph') ? 'Student' : 'Faculty';
            $user = User::create([
                'name'       => $googleUser->getName(),
                'email'      => $email,
                'password'   => Hash::make(\Illuminate\Support\Str::random(32)),
                'role'       => $role,
                'can_upload' => false,
                'google_id'  => $googleUser->getId(),
                'avatar'     => $googleUser->getAvatar(),
            ]);
            Store::addLog(['userName' => $user->name, 'email' => $user->email, 'action' => 'Registered via Google', 'document' => '—']);
        }

        $this->startSession($user);
        return $this->redirectToDashboard($user);
    }
}
