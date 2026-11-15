<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\LogType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A free-text log was undone. The log row is gone, so the event carries
 * what listeners need: whose log it was, what kind and for which day.
 */
final readonly class LogReverted
{
    use Dispatchable;

    public function __construct(
        public User $user,
        public LogType $type,
        public CarbonImmutable $loggedOn,
    ) {}
}
