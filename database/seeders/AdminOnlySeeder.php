<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminOnlySeeder extends Seeder
{
    public function run(): void
    {
        $email = 'admin@barangay.gov.ph';
        $password = config('app.admin_seed_password');

        if (! is_string($password) || strlen($password) < 12) {
            throw new \RuntimeException(
                'Set ADMIN_SEED_PASSWORD to a unique password of at least 12 characters before seeding.'
            );
        }

        $admin = User::query()->where('email', $email)->first();

        if ($admin) {
            if (! in_array($admin->role, ['admin', 'super_admin'], true)) {
                throw new \RuntimeException(
                    'The configured administrator email is already assigned to a non-admin account.'
                );
            }

            // Existing administrator: do not modify the account.
            return;
        }

        User::create([
            'name' => 'Barangay Administrator',
            'email' => $email,
            'password' => $password,
            'role' => 'super_admin',
            'password_changed' => true,
        ]);
    }
}
