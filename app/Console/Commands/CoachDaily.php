<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Coach\SendDailyCoaching;
use App\Jobs\SendDailyCoachingTo;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Runs every hour from the scheduler: queues the morning message for every
 * person whose hour it is. With --user it sends to that one person now and
 * prints the message, whatever the hour.
 */
final class CoachDaily extends Command
{
    protected $signature = 'coach:daily {--user= : Send to this email now and print the message}';

    protected $description = 'Queue the morning coaching message for everyone whose hour it is';

    public function handle(SendDailyCoaching $send): int
    {
        if ($this->option('user') !== null) {
            return $this->sendNow((string) $this->option('user'), $send);
        }

        if (! config('coach.daily.enabled')) {
            return self::SUCCESS;
        }

        $due = User::dueForMorningMessage()->get();
        $due->each(static fn (User $user) => SendDailyCoachingTo::dispatch($user));
        $this->line(sprintf('Queued %d morning message(s).', $due->count()));

        return self::SUCCESS;
    }

    private function sendNow(string $email, SendDailyCoaching $send): int
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error('No user with that email.');

            return self::FAILURE;
        }

        $message = $send->handle($user);
        $this->line($message ?? 'Not sent: this person is over their monthly AI budget.');

        return self::SUCCESS;
    }
}
