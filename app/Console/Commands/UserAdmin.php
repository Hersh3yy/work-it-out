<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

final class UserAdmin extends Command
{
    protected $signature = 'user:admin {email} {--revoke : Take admin away instead}';

    protected $description = 'Make someone an admin: they create, invite and delete accounts in the app';

    public function handle(): int
    {
        $user = User::query()->where('email', mb_strtolower(trim((string) $this->argument('email'))))->first();

        if ($user === null) {
            $this->error('No user with that email.');

            return self::FAILURE;
        }

        $admin = ! $this->option('revoke');
        $user->forceFill(['is_admin' => $admin])->save();
        $this->info($admin ? "{$user->name} is an admin." : "{$user->name} is no longer an admin.");

        return self::SUCCESS;
    }
}
