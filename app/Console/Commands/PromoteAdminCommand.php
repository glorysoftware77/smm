<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PromoteAdminCommand extends Command
{
    protected $signature = 'user:promote-admin {email : Email address to mark as admin}';

    protected $description = 'Mark a user as the agency admin (Users module access)';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->error("No user found for {$email}");

            return self::FAILURE;
        }

        User::query()->where('is_admin', true)->update(['is_admin' => false]);

        $user->forceFill([
            'is_admin' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $this->info("{$user->email} is now the only admin.");

        return self::SUCCESS;
    }
}
