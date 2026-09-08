<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

/**
 * Seeds only the two starting accounts.
 *
 * This used to also insert four sample bluebooks and six backdated audit log
 * entries describing activity that never happened. That was useful while the
 * archive was being built locally, but on a real deployment it puts fictional
 * research papers into the archive and fictional entries into an audit log
 * that is meant to be a truthful record. Both are now left out: the archive
 * starts empty and fills up through actual uploads.
 *
 * Accounts are seeded with placeholder passwords. Change them immediately
 * after seeding any environment that is reachable from the internet.
 */
class DatabaseSeeder extends Seeder
{
    public function run()
    {
        User::create([
            'name'       => 'Jeliard D. Almonte',
            'email'      => 'jealmonte@cspc.edu.ph',
            'password'   => Hash::make('admin123'),
            'role'       => 'Admin',
            'can_upload' => true,
        ]);

        User::create([
            'name'       => 'Gil Rexis E. Realubit',
            'email'      => 'bhokrealubit@my.cspc.edu.ph',
            'password'   => Hash::make('student123'),
            'role'       => 'Student',
            'can_upload' => false,
        ]);
    }
}
