<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminOnlySeeder extends Seeder
{
    public function run(): void
    {
        $email = 'admin@barangay.gov.ph';
        $password = env('ADMIN_SEED_PASSWORD');

        if (! is_string($password) || strlen($password) < 12) {
            throw new \RuntimeException('Set ADMIN_SEED_PASSWORD to a unique password of at least 12 characters before seeding.');
        }

        $admin = User::query()->where('email', $email)->first();

        if ($admin && $admin->role !== 'admin') {
            throw new \RuntimeException('The configured administrator email is already assigned to a non-admin account.');
        }

        if (! $admin) {
            User::create([
                'name' => 'Barangay Administrator',
                'email' => $email,
                'password' => $password,
                'role' => 'admin',
                'password_changed' => true,
            ]);

            return;
        }

        if (Hash::check('password', $admin->password)) {
            $admin->password = $password;
            $admin->password_changed = true;
            $admin->save();
        }
    }
}
