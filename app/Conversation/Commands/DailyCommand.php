<?php

declare(strict_types=1);

namespace App\Conversation\Commands;

use App\Models\User;

/**
 * "/daily" shows when the morning message comes, "/daily 7" moves it,
 * "/daily off" stops it, "/daily on" brings it back at the default hour.
 */
final readonly class DailyCommand implements ChatCommand
{
    public function names(): array
    {
        return ['daily', 'morning'];
    }

    public function menuDescription(): string
    {
        return 'Your morning message: /daily 7, /daily off';
    }

    public function reply(User $user, string $argument = ''): string
    {
        $argument = mb_strtolower(trim($argument));

        return match (true) {
            $argument === '' => $this->status($user),
            $argument === 'off', $argument === 'stop' => $this->setHour($user, null),
            $argument === 'on' => $this->setHour($user, (int) config('coach.daily.default_hour')),
            (bool) preg_match('/^(\d{1,2})(?::00)?$/', $argument, $m) && (int) $m[1] <= 23 => $this->setHour($user, (int) $m[1]),
            default => 'Say /daily 7 for 07:00, /daily off to stop, or /daily to see the current setting.',
        };
    }

    private function status(User $user): string
    {
        return $user->daily_coaching_hour === null
            ? 'Your morning message is off. /daily on brings it back, /daily 7 picks the hour.'
            : sprintf('Your morning message comes at %02d:00 (%s). /daily 7 moves it, /daily off stops it.', $user->daily_coaching_hour, config('coach.daily.timezone'));
    }

    private function setHour(User $user, ?int $hour): string
    {
        $user->update(['daily_coaching_hour' => $hour]);

        return $hour === null
            ? 'OK, no more morning messages. /daily on brings them back.'
            : sprintf('OK, your morning message comes at %02d:00 (%s).', $hour, config('coach.daily.timezone'));
    }
}
