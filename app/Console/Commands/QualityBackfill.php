<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\WriteQualitySnapshots;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Write the quality rows for every day someone trained, and today, so their level counts their whole history (safe to run again)')]
#[Signature('quality:backfill {email? : One user; leave out with --all} {--all : Every user with a workout}')]
final class QualityBackfill extends Command
{
    public function handle(): int
    {
        $users = match (true) {
            $this->argument('email') !== null => User::query()->where('email', $this->argument('email'))->get(),
            (bool) $this->option('all') => User::query()->whereHas('workoutSessions')->get(),
            default => null,
        };

        if ($users === null || $users->isEmpty()) {
            $this->error($users === null ? 'Give an email or --all.' : 'No matching user.');

            return self::FAILURE;
        }

        foreach ($users as $user) {
            $days = $this->trainingDays($user);

            foreach ($days as $day) {
                WriteQualitySnapshots::dispatchSync($user, $day);
            }

            $this->line("{$user->email}: ".count($days).' days written');
        }

        return self::SUCCESS;
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function trainingDays(User $user): array
    {
        return $user->workoutSessions()->pluck('logged_at')
            ->map(static fn (CarbonInterface $at): string => $at->toDateString())
            ->push(today()->toDateString())
            ->unique()->sort()->values()
            ->map(static fn (string $day): CarbonImmutable => CarbonImmutable::parse($day))
            ->all();
    }
}
