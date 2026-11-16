<?php

declare(strict_types=1);

namespace App\Goals;

use App\Fitness\Quality;

/**
 * What a dated goal is, read from its words, and which qualities it demands
 * (method card 4: the needs analysis before any plan).
 *
 * @see https://refactoring.guru/design-patterns/strategy
 */
enum GoalKind: string
{
    case Event = 'event';
    case Distance = 'distance';
    case Carry = 'carry';
    case Lift = 'lift';
    case Bodyweight = 'bodyweight';

    /**
     * The words that name this kind of goal, most specific first.
     *
     * @return list<string>
     */
    public function words(): array
    {
        return match ($this) {
            self::Event => ['rave', 'festival', 'party', 'wedding', 'concert', 'gig', 'trip', 'holiday', 'vacation', 'hike', 'ski', 'dance', 'event'],
            self::Distance => ['half marathon', 'marathon', 'triathlon', '5k', '10k', 'race', 'km', 'mile'],
            self::Carry => ['fridge', 'couch', 'sofa', 'furniture', 'boxes', 'move house', 'moving', 'carry'],
            self::Lift => ['deadlift', 'squat', 'bench', 'overhead press', 'pull-up', 'pullup', 'chin-up', 'clean'],
            self::Bodyweight => ['body weight', 'bodyweight', 'weigh', 'lose'],
        };
    }

    /**
     * @return list<Quality>
     */
    public function demands(): array
    {
        return match ($this) {
            self::Event => [Quality::Endurance, Quality::Recovery, Quality::Mobility],
            self::Distance => [Quality::Endurance, Quality::Recovery],
            self::Carry => [Quality::Strength, Quality::Core],
            self::Lift => [Quality::Strength, Quality::Core],
            self::Bodyweight => [Quality::Endurance, Quality::Strength],
        };
    }

    /** What the goal is called in replies: the word that named it, or the kind itself. */
    public function nameFor(string $word): string
    {
        return $this === self::Bodyweight ? 'body weight' : $word;
    }

    public static function in(string $text): ?GoalWord
    {
        foreach (self::cases() as $kind) {
            foreach ($kind->words() as $word) {
                if (preg_match('/\b'.preg_quote($word, '/').'(s|es)?\b/i', $text) === 1) {
                    return new GoalWord($kind, $kind->nameFor($word));
                }
            }
        }

        return null;
    }
}
