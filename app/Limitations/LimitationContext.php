<?php

declare(strict_types=1);

namespace App\Limitations;

use App\Models\Limitation;
use App\Models\User;

/**
 * What the limitation reader is told besides the message: the limitations
 * the person already has and the question the coach last asked. "98%" or
 * "I don't feel it at all" is an update on the back only when the reader
 * knows there is a back to update.
 */
final readonly class LimitationContext
{
    private const int COACH_QUESTION_DAYS = 3;

    private const string FEELING_WORDS = '/\b(pain|hurt|hurts|sore|stiff|rehab|recover|better|worse|feel|feels|feeling|healed|ok|okay|fine)\b|%/iu';

    /**
     * @param  list<array{area: string, since: string, note: string}>  $active
     */
    private function __construct(
        public array $active,
        public ?string $coachAsked,
    ) {}

    public static function for(User $user): self
    {
        $active = $user->limitations()->active()->latest('started_on')->get()
            ->map(static fn (Limitation $l): array => ['area' => $l->areaLabel(), 'since' => $l->started_on->toDateString(), 'note' => $l->note])
            ->values()->all();

        return new self($active, $active === [] ? null : self::lastCoachQuestion($user));
    }

    public function hasActive(): bool
    {
        return $this->active !== [];
    }

    /** A workout message that also says how the limitation is going. */
    public function isMentionedIn(string $text): bool
    {
        if (! $this->hasActive()) {
            return false;
        }

        foreach ($this->active as $limitation) {
            if (str_contains(mb_strtolower($text), mb_strtolower($limitation['area']))) {
                return true;
            }
        }

        return (bool) preg_match(self::FEELING_WORDS, $text);
    }

    /**
     * @return array{active: list<array{area: string, since: string, note: string}>, coach_asked: string|null}
     */
    public function toArray(): array
    {
        return ['active' => $this->active, 'coach_asked' => $this->coachAsked];
    }

    /** The question the coach's last answer ended with, when recent. */
    private static function lastCoachQuestion(User $user): ?string
    {
        $turn = $user->coachTurns()->where('created_at', '>=', now()->subDays(self::COACH_QUESTION_DAYS))->first();
        $lastLine = $turn === null ? '' : trim((string) last(array_filter(preg_split('/\n+/', $turn->answer) ?: [])));

        return str_ends_with($lastLine, '?') ? $lastLine : null;
    }
}
