<?php

declare(strict_types=1);

namespace App\Profile;

/**
 * The sections of the written profile. The writer picks one per note in
 * its structured output, so a note cannot come back without its section,
 * and each case says what a coach needs from that section.
 */
enum NoteLabel: string
{
    case Rhythm = 'rhythm';
    case Strength = 'strength';
    case Balance = 'balance';
    case Conditioning = 'conditioning';
    case Body = 'body';
    case Limitation = 'limitation';
    case Goal = 'goal';
    case Responds = 'responds';
    case Gaps = 'gaps';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function title(): string
    {
        return ucfirst($this->value);
    }

    public function prefix(string $text): string
    {
        return $this->title().': '.$text;
    }

    public function describe(): string
    {
        return match ($this) {
            self::Rhythm => 'how often they actually train against their plan, the gaps and when they fell, and habits like logging "yesterday" or late at night',
            self::Strength => 'the lifts on record with their last top sets and the loads per set when they differ, and the trend across dates for a lift logged more than once',
            self::Balance => 'which areas got work in the last weeks and which got none (push, pull, legs, trunk, shoulders), from the saved exercises',
            self::Conditioning => 'distances, times and paces logged, and whether they are getting faster or slower',
            self::Body => 'body weight entries and trend, or that none exist',
            self::Limitation => 'each active limitation: what happened and when in their own words, its course since, and anything in the entries that puts the start earlier than FACTS says',
            self::Goal => 'goals and intentions in their own words, from the profile fields and from what they asked the coach ("wants squats back next week", "scared of squats", "weight loss")',
            self::Responds => 'how they act on advice: whether a suggested session was logged afterwards, whether they pick their own loads, whether they correct the coach',
            self::Gaps => 'the one or two facts a coach would need next (a body weight, how the limitation feels today, a load never logged); always written',
        };
    }
}
