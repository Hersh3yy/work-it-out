<?php

declare(strict_types=1);

use App\Services\Exercises\ExerciseAliases;

it('maps known aliases to one canonical name', function (string $written, string $canonical): void {
    expect(ExerciseAliases::canonical($written))->toBe($canonical);
})->with([
    ['bench', 'Bench Press'],
    ['Bench Press', 'Bench Press'],
    ['benchpress', 'Bench Press'],
    ['BP', 'Bench Press'],
    ['squats', 'Squat'],
    ['RDLs', 'Romanian Deadlift'],
    ['pull-ups', 'Pull-up'],
    ['OHP', 'Overhead Press'],
]);

it('reuses a known spelling with the same key', function (): void {
    expect(ExerciseAliases::canonical('incline  db press ', ['Leg Press', 'Incline DB Press']))
        ->toBe('Incline DB Press');
});

it('keeps an unknown name as written, whitespace tidied', function (): void {
    expect(ExerciseAliases::canonical('  cable   crunch '))->toBe('cable crunch');
});

it('keys ignore case, spaces and punctuation', function (): void {
    expect(ExerciseAliases::key('Pull-Up'))->toBe(ExerciseAliases::key('pull up'));
});
