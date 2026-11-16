<?php

declare(strict_types=1);

use App\Goals\GoalDate;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
});

it('reads the date a goal names', function (string $text, ?string $date): void {
    expect(GoalDate::in($text)?->toDateString())->toBe($date);
})->with([
    'day and month' => ['rave on 13 December', '2026-12-13'],
    'month and ordinal' => ['5k on December 5th', '2026-12-05'],
    'short month, next year when passed' => ['wedding on 3 Jan', '2027-01-03'],
    'weeks from now' => ['festival in 2 weeks', '2026-11-29'],
    'number words' => ['ski trip in three days', '2026-11-18'],
    'a month from now' => ['move the fridge in a month', '2026-12-15'],
    'by a month' => ['squat 100 by March', '2027-03-01'],
    'next weekday' => ['party next Saturday', '2026-11-21'],
    'tomorrow' => ['concert tomorrow', '2026-11-16'],
    'iso date' => ['concert on 2026-12-01', '2026-12-01'],
    'day/month' => ['hike on 20/12', '2026-12-20'],
    'no date' => ['I want to run a 5k', null],
    'a date in the past this year is next year' => ['rave on 1 November', '2027-11-01'],
]);
