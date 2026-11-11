<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminOnlySeeder extends Seeder
{
    public function run(): void
    {
        $email = 'superadmin@relieftracker.com';
        $password = config('app.admin_seed_password');

        if (! is_string($password) || strlen($password) < 12) {
            throw new \RuntimeException(
                'Set ADMIN_SEED_PASSWORD to a unique password of at least 12 characters before seeding.'
            );
        }

        $admin = User::query()->where('email', $email)->first();

        if ($admin) {
            if ($admin->role !== 'super_admin') {
                throw new \RuntimeException(
                    'The configured Super Admin email already belongs to a non-super-admin account.'
                );
            }

            // Existing Super Admin: do not modify the account.
            return;
        }

        User::create([
            'name' => 'ReliefTrack Super Administrator',
            'email' => $email,
            'password' => $password,
            'role' => 'super_admin',
            'password_changed' => true,
        ]);
    }
}
