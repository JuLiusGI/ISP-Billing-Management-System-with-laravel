<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Creates the initial staff accounts, one per role.
 *
 * These are development credentials and must be changed before any real
 * deployment. Nothing here is hard-coded into application logic: the address
 * and password come from the environment, with documented defaults.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SEED_ADMIN_PASSWORD', 'password');

        $accounts = [
            [
                'email' => env('SEED_ADMIN_EMAIL', 'admin@example.com'),
                'first_name' => 'System',
                'last_name' => 'Administrator',
                'role' => Role::SUPER_ADMIN,
            ],
            [
                'email' => 'billing@example.com',
                'first_name' => 'Bea',
                'last_name' => 'Santos',
                'role' => Role::BILLING_STAFF,
            ],
            [
                'email' => 'technician@example.com',
                'first_name' => 'Tomas',
                'last_name' => 'Cruz',
                'role' => Role::TECHNICIAN,
            ],
            [
                'email' => 'accountant@example.com',
                'first_name' => 'Ana',
                'last_name' => 'Lim',
                'role' => Role::ACCOUNTANT,
            ],
        ];

        foreach ($accounts as $account) {
            $user = User::withTrashed()->firstOrNew(['email' => $account['email']]);

            // Only fill a brand new account. Re-seeding must never reset the
            // password or name of an account someone is already using.
            if (! $user->exists) {
                $user->fill([
                    'first_name' => $account['first_name'],
                    'last_name' => $account['last_name'],
                    'password' => $password,
                    'status' => UserStatus::Active,
                    'email_verified_at' => now(),
                ])->save();
            }

            $role = Role::where('name', $account['role'])->first();

            if ($role) {
                $user->roles()->syncWithoutDetaching([$role->id]);
            }
        }
    }
}
