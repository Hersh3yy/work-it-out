<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Agents\SmartLogAgent;
use App\Contracts\Ai\SmartLogParser;
use App\Contracts\Stats\PersonalRecords;
use App\Models\User;

/**
 * Laravel AI SDK adapter for the SmartLogParser port.
 */
final readonly class SdkSmartLogParser implements SmartLogParser
{
    public function __construct(
        private PersonalRecords $records,
    ) {}

    public function parse(User $user, string $message): array
    {
        $agent = new SmartLogAgent($user, $this->records->for($user));

        /** @var array<string, mixed> $parsed */
        $parsed = $agent->forUser($user)->prompt($message)->structured();

        return $parsed;
    }
}
