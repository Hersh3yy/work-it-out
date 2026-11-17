<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Accounts\DeleteUser;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Delete a person and everything they logged (on their request; cannot be undone)')]
#[Signature('user:delete {email} {--force : Skip the question}')]
final class UserDelete extends Command
{
    public function handle(DeleteUser $delete): int
    {
        $user = User::query()->where('email', mb_strtolower(trim((string) $this->argument('email'))))->first();

        if ($user === null) {
            $this->error('No user with that email.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Delete {$user->name} and everything they logged? This cannot be undone.")) {
            return self::FAILURE;
        }

        $delete->handle($user);
        $this->info("{$user->name} and everything they logged are deleted.");

        return self::SUCCESS;
    }
}
