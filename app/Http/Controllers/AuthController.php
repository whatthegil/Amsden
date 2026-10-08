<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ExpireIdleSession;
use App\Models\User;
use App\Services\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    private function isAllowedEmail(string $email): bool
    {
        // A well-formed address first, so "a@b@cspc.edu.ph" and the like fail.
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            && (str_ends_with($email, '@cspc.edu.ph') || str_ends_with($email, '@my.cspc.edu.ph'));
    }

    private function startSession(User $user): void
    {
        // A new session id for the signed-in user. Keeping the one from before
        // sign-in let anyone who had planted that id in the browser - on a
        // shared library computer, say - ride in on the login that followed
        // (session fixation). Both password and Google sign-in come through here.
        session()->regenerate();

        session(['user' => [
            'id'        => $user->id,
            'name'      => $user->name,
            'email'     => $user->email,
            'role'      => $user->role,
            'canUpload' => User::mayUpload($user->role, (bool) $user->can_upload),
            'avatar'    => $user->avatar,
            'createdAt' => $user->created_at ? $user->created_at->format('Y-m-d') : now()->format('Y-m-d'),
        ], 'last_activity' => time()]);
    }

    /**
     * A sign-in that did not get through. Only successful ones used to be
     * recorded, so someone working through passwords for an account left no
     * trace an administrator could see. The reason goes in the log, never the
     * password tried.
     */
    private function logFailedLogin(string $email, ?User $user, string $reason): void
    {
        Store::addLog([
            'userName' => $user?->name ?? 'Unknown',
            'email'    => mb_substr($email !== '' ? $email : '—', 0, 190),
            'action'   => 'Failed Login',
            'document' => $reason . ' · ' . request()->ip(),
            'status'   => 'Denied',
        ]);
    }

    private function redirectToDashboard(User $user)
    {
        if (User::isStaff($user->role)) {
            return redirect()->route('admin.dashboard');
        }
        // First-ever login for this account -> show the Acceptable Use Policy
        // once; every login after they accept it skips straight to the dashboard.
        return $user->policy_accepted_at
            ? redirect()->route('student.dashboard')
            : redirect()->route('student.policy');
    }

    // ─── Email / Password Auth ─────────────────────────────────────────────────

    // Accounts first created through Google sign-in were given a random
    // password nobody knows, so the one message also points those owners to
    // the Google button.
    private const BAD_LOGIN = 'Invalid email or password. If you first signed in with Google, use the CSPC Mail button below, then set a password on your Profile page.';

    private static function dummyHash(): string
    {
        static $hash;
        return $hash ??= Hash::make(\Illuminate\Support\Str::random(40));
    }

    public function loginForm()
    {
        if (session('user')) {
            $u = session('user');
            return User::isStaff($u['role'])
                ? redirect()->route('admin.dashboard')
                : redirect()->route('student.dashboard');
        }
        return view('pages.login', ['error' => null, 'success' => null, 'email' => null]);
    }

    public function login(Request $request)
    {
        // Phone keyboards capitalise the first letter and autocomplete often
        // leaves a trailing space; neither should make a valid account fail.
        // Only plain strings: email[]=x would otherwise be cast to "Array" with
        // a warning, which Laravel turns into a 500. Lengths are capped so a
        // megabyte "password" is not hashed; no real address or password is
        // anywhere near them.
        $rawEmail    = $request->input('email');
        $rawPassword = $request->input('password');
        $email       = is_string($rawEmail) ? strtolower(trim($rawEmail)) : '';
        $password    = is_string($rawPassword) ? $rawPassword : '';

        if (mb_strlen($email) > 254 || strlen($password) > 1024) {
            $this->logFailedLogin(mb_substr($email, 0, 190), null, 'Oversized input');
            return view('pages.login', ['error' => self::BAD_LOGIN, 'success' => null, 'email' => null]);
        }

        if (!$this->isAllowedEmail($email)) {
            $this->logFailedLogin($email, null, 'Not a CSPC address');
            return view('pages.login', ['error' => 'Only @cspc.edu.ph or @my.cspc.edu.ph email addresses are allowed.', 'success' => null, 'email' => $email]);
        }

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        // A password is always checked, against a throwaway hash when there is
        // no account, so a missing account does not answer measurably faster
        // than a wrong password. Every failure gets the same message: telling
        // "no such account" or "Google-only account" apart from "wrong password"
        // would let anyone find out which CSPC addresses have accounts here.
        // The log keeps the real reason for the administrator.
        $valid = Hash::check($password, $user?->password ?? self::dummyHash());

        if (!$user || !$valid || !$user->hasKnownPassword()) {
            $reason = !$user ? 'No such account' : (!$user->hasKnownPassword() ? 'No password set yet' : 'Wrong password');
            $this->logFailedLogin($email, $user, $reason);
            return view('pages.login', ['error' => self::BAD_LOGIN, 'success' => null, 'email' => $email]);
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

    /** ?idle=1 is the page signing itself out when no one answered the idle prompt. */
    public function logout(Request $request)
    {
        $idle = $request->boolean('idle');
        $user = session('user');
        if ($user) {
            Store::addLog(['userName' => $user['name'], 'email' => $user['email'], 'action' => $idle ? 'Logout (Idle)' : 'Logout', 'document' => '—']);
        }
        // The whole session goes, not just who was in it, and the CSRF token
        // with it: on a shared computer the next person must not inherit the
        // last one's session or a token that still works for it.
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $redirect = redirect()->route('login');
        return $idle ? $redirect->with('error', ExpireIdleSession::message()) : $redirect;
    }

    /**
     * Called by the page while its user is active and when they choose to stay
     * signed in; ExpireIdleSession has already recorded the activity by now.
     */
    public function keepAlive()
    {
        return session('user')
            ? response()->noContent()
            : response()->json(['message' => 'Not signed in.'], 401);
    }

    // ─── Google OAuth ──────────────────────────────────────────────────────────

    public function redirectToGoogle()
    {
        return $this->google()
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    /**
     * Google sends the user back to whichever domain they started from. A
     * configured redirect URI outlives a domain rename - the old one kept
     * being sent after the site moved to cspcbams.laravel.cloud, and Google
     * refused it with redirect_uri_mismatch. Both legs must send the same one.
     */
    private function google()
    {
        return Socialite::driver('google')->redirectUrl(route('auth.google.callback'));
    }

    // Google sends these back on the callback URL when it refuses the sign-in
    // *before* issuing an auth code. Without translating them the user only ever
    // saw the generic "Google sign-in failed", which hid the real cause.
    private const GOOGLE_ERRORS = [
        'access_denied'         => 'Google sign-in was cancelled, or this Google project is still in "Testing" mode and your account is not on its test-user list. Ask the administrator to publish the OAuth consent screen or add you as a test user.',
        'admin_policy_enforced' => 'Your CSPC Google Workspace administrator has blocked this app. It has to be allow-listed in the Google Admin console before you can sign in.',
        'org_internal'          => 'This Google project only accepts accounts from the organisation that owns it. Ask the administrator to check the OAuth consent screen User Type.',
        'disallowed_useragent'  => 'Google refused this browser. Please open the site in Chrome, Edge, or Firefox rather than an in-app browser.',
    ];

    public function handleGoogleCallback(Request $request)
    {
        // Google redirects here with ?error=... (and no ?code=) when it declines
        // the sign-in. Socialite would just choke on the missing code and raise
        // an opaque exception, so handle that case first.
        if ($request->has('error')) {
            // Anyone can put anything in ?error=, so it is never shown back:
            // only the codes above get their own message, and the log keeps a
            // trimmed copy of the rest.
            $error = $request->query('error');
            $error = is_string($error) ? $error : '';
            $desc  = $request->query('error_description');
            Log::warning('Google sign-in refused by Google', [
                'error'       => mb_substr($error, 0, 100),
                'description' => is_string($desc) ? mb_substr($desc, 0, 300) : null,
            ]);

            return redirect()->route('login')->with(
                'error',
                self::GOOGLE_ERRORS[$error] ?? 'Google sign-in failed. Please try again.'
            );
        }

        try {
            // Not stateless: Socialite checks the state it put in the session
            // on the way out, so a callback URL started by someone else (login
            // CSRF - signing the victim into the attacker's account) fails.
            $googleUser = $this->google()->user();
        } catch (\Laravel\Socialite\Two\InvalidStateException $e) {
            Log::warning('Google sign-in callback with a missing or wrong state');

            return redirect()->route('login')->with('error', 'Your Google sign-in expired or was started elsewhere. Please try again.');
        } catch (\Exception $e) {
            // Previously swallowed silently, which made every Google failure
            // undiagnosable. Record it so storage/logs/laravel.log says why.
            Log::error('Google sign-in failed during token exchange', [
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
            ]);

            return redirect()->route('login')->with('error', 'Google sign-in failed. Please try again.');
        }

        $email = $googleUser->getEmail();

        if (!$email) {
            Log::error('Google sign-in returned no email address', ['googleId' => $googleUser->getId()]);

            return redirect()->route('login')->with('error', 'Google did not share an email address with us. Please grant the email permission and try again.');
        }

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
            }
            // Refreshed every sign-in: the picture changes when the owner
            // changes it on Google, and an old link stops working.
            $user->avatar = $googleUser->getAvatar() ?: $user->avatar;
            $user->save();
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
