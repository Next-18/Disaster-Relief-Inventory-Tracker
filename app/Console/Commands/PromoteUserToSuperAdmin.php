<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PromoteUserToSuperAdmin extends Command
{
    protected $signature = 'admin:promote-super-admin {email : Email address of the Admin to promote}';

    protected $description = 'Promote one explicitly selected barangay Admin to Super Admin';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->components->error('No user with that email address was found.');

            return self::FAILURE;
        }

        if (! in_array($user->role, ['admin', 'super_admin'], true)) {
            $this->components->error('Only an existing Admin account can be promoted.');

            return self::FAILURE;
        }

        if ($user->role === 'super_admin') {
            $this->components->info('That account is already a Super Admin.');

            return self::SUCCESS;
        }

        $user->forceFill(['role' => 'super_admin'])->save();
        $user->tokens()->delete();

        $this->components->info("{$user->email} is now a Super Admin. Existing mobile API tokens were revoked.");

        return self::SUCCESS;
    }
}
