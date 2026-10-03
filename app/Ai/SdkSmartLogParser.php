<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Agents\SmartLogAgent;
use App\Contracts\Ai\SmartLogParser;
use App\Contracts\Stats\PersonalRecords;
use App\Exceptions\AiUnavailable;
use App\Models\User;

/**
 * Laravel AI SDK adapter for the SmartLogParser port.
 *
 * SmartLogAgent is not Conversational, so there is no forUser(); the
 * structured response is read with toArray(). The payload is validated and
 * capped here, at the boundary, so nothing downstream trusts the model.
 */
final readonly class SdkSmartLogParser implements SmartLogParser
{
    private const LOG_TYPES = ['workout', 'meal', 'biometrics', 'general'];

    private const RPG_CATEGORIES = ['strength', 'stamina', 'vitality'];

    public function __construct(
        private PersonalRecords $records,
    ) {}

    public function parse(User $user, string $message): array
    {
        $agent = new SmartLogAgent($user, $this->records->for($user));

        $parsed = $agent->prompt($message)->toArray();

        return $this->validate($parsed);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     *
     * @throws AiUnavailable
     */
    private function validate(array $parsed): array
    {
        $logType = $parsed['log_type'] ?? null;
        $summary = $parsed['summary'] ?? null;

        if (! is_string($logType) || ! in_array($logType, self::LOG_TYPES, true)) {
            throw AiUnavailable::because(SmartLogAgent::class, 'payload missing a valid log_type');
        }
        if (! is_string($summary) || trim($summary) === '') {
            throw AiUnavailable::because(SmartLogAgent::class, 'payload missing summary');
        }

        $cap = static fn (mixed $value, int $max): ?string => is_string($value)
            ? mb_substr($value, 0, $max)
            : null;

        $parsed['summary'] = $cap($summary, 255);
        $parsed['lt_surge_feedback'] = $cap($parsed['lt_surge_feedback'] ?? null, 600);
        $parsed['shen_feedback'] = $cap($parsed['shen_feedback'] ?? null, 600);
        $parsed['latika_feedback'] = $cap($parsed['latika_feedback'] ?? null, 600);
        $parsed['diary_text'] = $cap($parsed['diary_text'] ?? null, 1000);
        $parsed['rpg_stat_name'] = $cap($parsed['rpg_stat_name'] ?? null, 60);
        $parsed['rpg_stat_reason'] = $cap($parsed['rpg_stat_reason'] ?? null, 255);

        $category = $parsed['rpg_stat_category'] ?? null;
        $parsed['rpg_stat_category'] = in_array($category, self::RPG_CATEGORIES, true) ? $category : null;

        return $parsed;
    }
}
