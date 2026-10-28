<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\AiUnavailable;
use App\Models\ProfileVersion;
use App\Models\User;
use App\Profile\UpdateProfile;
use Illuminate\Console\Command;

final class ProfileRebuild extends Command
{
    protected $signature = 'profile:rebuild {email? : One user; leave out with --all} {--all : Every user with at least one log} {--full : Start fresh from the last 30 logs instead of updating the current notes}';

    protected $description = 'Write a new version of the written profile now (one AI call per user)';

    public function handle(UpdateProfile $update): int
    {
        $kind = $this->option('full') ? ProfileVersion::FULL : ProfileVersion::ROLLING;

        $users = match (true) {
            $this->argument('email') !== null => User::query()->where('email', $this->argument('email'))->get(),
            (bool) $this->option('all') => User::query()->whereHas('activityLogs')->get(),
            default => null,
        };

        if ($users === null || $users->isEmpty()) {
            $this->error($users === null ? 'Give an email or --all.' : 'No matching user.');

            return self::FAILURE;
        }

        $failed = 0;

        foreach ($users as $user) {
            try {
                $version = $update->handle($user, $kind);
                $this->line($version === null
                    ? "{$user->email}: nothing to write about yet"
                    : sprintf('%s: version %d, %d notes', $user->email, $version->id, count($version->notes)));
            } catch (AiUnavailable) {
                $failed++;
                $this->warn("{$user->email}: AI unavailable, nothing saved");
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
