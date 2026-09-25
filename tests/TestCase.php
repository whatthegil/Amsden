<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * EnsureRole re-reads the logged-in account from the database on every
     * request, so a session user that exists only in the session would be
     * logged out. Tests describe the user they want in the session, so make
     * sure a matching account row exists before the request runs.
     */
    public function withSession(array $data)
    {
        if (isset($data['user'])) {
            $u       = $data['user'];
            $account = (isset($u['email']) ? User::where('email', $u['email'])->first() : null)
                ?? (isset($u['id']) ? User::find($u['id']) : null)
                ?? new User(['password' => bcrypt('secret')]);

            $account->forceFill([
                'name'       => $u['name'] ?? 'Test User',
                'email'      => $u['email'] ?? 'user' . uniqid() . '@cspc.edu.ph',
                'role'       => $u['role'] ?? 'Student',
                'can_upload' => $u['canUpload'] ?? false,
            ]);
            if (!$account->exists && isset($u['id'])) $account->id = $u['id'];
            $account->save();

            $data['user']['id'] = $account->id;
        }

        return parent::withSession($data);
    }
}
