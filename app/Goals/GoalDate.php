<?php

declare(strict_types=1);

namespace App\Goals;

use Carbon\CarbonImmutable;

/**
 * The day a goal names, worked out in plain code from today: "13 December",
 * "in 2 weeks", "by March", "next Saturday". A date without a year that has
 * passed this year means next year. Null when the text names no day.
 */
final class GoalDate
{
    private const string MONTH = '(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*';

    private const array MONTHS = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12];

    private const array NUMBERS = ['a' => 1, 'an' => 1, 'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10, 'eleven' => 11, 'twelve' => 12];

    private const array WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public static function in(string $text): ?CarbonImmutable
    {
        $text = mb_strtolower($text);
        $today = CarbonImmutable::today();

        return self::isoDate($text)
            ?? self::dayAndMonth($text, $today)
            ?? self::fromNow($text, $today)
            ?? self::weekday($text, $today)
            ?? self::startOfMonth($text, $today)
            ?? (preg_match('/\btomorrow\b/', $text) === 1 ? $today->addDay() : null);
    }

    private static function isoDate(string $text): ?CarbonImmutable
    {
        return preg_match('/\b(\d{4})-(\d{2})-(\d{2})\b/', $text, $m) === 1 ? self::date((int) $m[1], (int) $m[2], (int) $m[3]) : null;
    }

    private static function dayAndMonth(string $text, CarbonImmutable $today): ?CarbonImmutable
    {
        if (preg_match('/\b(\d{1,2})(?:st|nd|rd|th)?(?: of)? '.self::MONTH.'\b/', $text, $m) === 1) {
            return self::nextOccurrence($today, self::MONTHS[$m[2]], (int) $m[1]);
        }

        if (preg_match('/\b'.self::MONTH.' (\d{1,2})(?:st|nd|rd|th)?\b/', $text, $m) === 1) {
            return self::nextOccurrence($today, self::MONTHS[$m[1]], (int) $m[2]);
        }

        if (preg_match('/\b(\d{1,2})\/(\d{1,2})\b/', $text, $m) === 1) {
            return self::nextOccurrence($today, (int) $m[2], (int) $m[1]);
        }

        return null;
    }

    private static function fromNow(string $text, CarbonImmutable $today): ?CarbonImmutable
    {
        $count = '(\d{1,3}|'.implode('|', array_keys(self::NUMBERS)).')';

        if (preg_match('/\bin '.$count.' (day|week|month)s?\b/', $text, $m) === 1) {
            $amount = is_numeric($m[1]) ? (int) $m[1] : self::NUMBERS[$m[1]];

            return match ($m[2]) {
                'day' => $today->addDays($amount),
                'week' => $today->addWeeks($amount),
                default => $today->addMonthsNoOverflow($amount),
            };
        }

        if (preg_match('/\bnext (week|month)\b/', $text, $m) === 1) {
            return $m[1] === 'week' ? $today->addWeek() : $today->addMonthNoOverflow();
        }

        return null;
    }

    private static function weekday(string $text, CarbonImmutable $today): ?CarbonImmutable
    {
        if (preg_match('/\bthis weekend\b/', $text) === 1) {
            return $today->isSaturday() ? $today : $today->next('saturday');
        }

        if (preg_match('/\b(?:on|next|this) ('.implode('|', self::WEEKDAYS).')\b/', $text, $m) === 1) {
            return $today->next($m[1]);
        }

        return null;
    }

    private static function startOfMonth(string $text, CarbonImmutable $today): ?CarbonImmutable
    {
        return preg_match('/\b(?:in|by|before|end of) '.self::MONTH.'\b/', $text, $m) === 1
            ? self::nextOccurrence($today, self::MONTHS[$m[1]], 1)
            : null;
    }

    private static function nextOccurrence(CarbonImmutable $today, int $month, int $day): ?CarbonImmutable
    {
        $date = self::date($today->year, $month, $day);

        return $date !== null && $date->isBefore($today) ? self::date($today->year + 1, $month, $day) : $date;
    }

    private static function date(int $year, int $month, int $day): ?CarbonImmutable
    {
        return checkdate($month, $day, $year) ? CarbonImmutable::create($year, $month, $day)?->startOfDay() : null;
    }
}
