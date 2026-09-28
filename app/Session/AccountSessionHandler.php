<?php

namespace App\Session;

use App\Models\User;
use Illuminate\Session\DatabaseSessionHandler;

/**
 * Laravel's database session store, taught where this app keeps the signed-in
 * user. It fills sessions.user_id from Laravel's auth guard, which C-BAMS does
 * not use (see AuthController), so every row would have stayed anonymous;
 * this reads the account from the session's own 'user' entry instead.
 */
class AccountSessionHandler extends DatabaseSessionHandler
{
    protected function addUserInformation(&$payload)
    {
        $payload['user_id'] = $this->userId();

        return $this;
    }

    protected function userId()
    {
        $id = $this->container->make('session')->get('user.id');

        // sessions.user_id references users.id: an account removed while its
        // session was open leaves the row anonymous instead of failing the save.
        return $id && User::whereKey($id)->exists() ? $id : null;
    }
}
