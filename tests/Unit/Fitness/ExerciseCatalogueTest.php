<?php

declare(strict_types=1);

use App\Enums\BodyArea;
use App\Fitness\Exercise;
use App\Fitness\ExerciseCatalogue;
use App\Fitness\MovementPattern;
use App\Fitness\Quality;
use App\Limitations\ExerciseLoads;
use App\Services\Exercises\ExerciseAliases;
use App\Services\Stats\PersonalRecordService;

it('makes every catalogue entry train at least one quality', function (): void {
    foreach (ExerciseCatalogue::all() as $exercise) {
        expect($exercise->qualities)->not->toBeEmpty();
    }

    expect(ExerciseCatalogue::all())->not->toBeEmpty();
});

it('gives every alias to one exercise only, and every name once', function (): void {
    $aliases = array_merge(...array_map(static fn (Exercise $e): array => array_map(ExerciseAliases::key(...), $e->aliases), ExerciseCatalogue::all()));
    $names = array_map(static fn (Exercise $e): string => ExerciseAliases::key($e->name), ExerciseCatalogue::all());

    expect(array_unique($aliases))->toHaveCount(count($aliases))
        ->and(array_unique($names))->toHaveCount(count($names));
});

it('renames only what was renamed before, so no history splits over two spellings', function (): void {
    expect(ExerciseAliases::canonical('Push-ups'))->toBe('Push-ups')
        ->and(ExerciseAliases::canonical('yoga'))->toBe('yoga')
        ->and(ExerciseAliases::canonical('jogging'))->toBe('Run');
});

it('has replaced the three old lists', function (): void {
    expect(defined(ExerciseAliases::class.'::ALIASES'))->toBeFalse()
        ->and(defined(ExerciseLoads::class.'::LOADS'))->toBeFalse()
        ->and(defined(ExerciseLoads::class.'::NAMES'))->toBeFalse()
        ->and(defined(PersonalRecordService::class.'::RUN_KEYWORDS'))->toBeFalse()
        ->and(defined(PersonalRecordService::class.'::SPORT_KEYWORDS'))->toBeFalse()
        ->and(class_exists('App\Fitness\ExerciseQualities'))->toBeFalse();
});

it('reads a variant by its words without renaming it', function (string $written, string $exercise): void {
    expect(array_map(static fn (Exercise $e): string => $e->name, ExerciseCatalogue::matching($written)))->toContain($exercise)
        ->and(ExerciseAliases::canonical($written))->toBe($written);
})->with([
    ['Walking lunges', 'Lunge'],
    ['chest supported row', 'Barbell Row'],
    ['Incline Bench Press', 'Bench Press'],
    ['Treadmill', 'Run'],
    ['goblet squat', 'Squat'],
]);

it('matches whole words, so a crunch is not a run and rowing is not a barbell row', function (): void {
    expect(ExerciseCatalogue::qualitiesOf('cable crunch'))->toBe([Quality::Core])
        ->and(ExerciseCatalogue::qualitiesOf('rowing'))->toBe([Quality::Endurance]);
});

it('knows each exercise\'s movement pattern', function (): void {
    expect(ExerciseCatalogue::hasPattern('Treadmill', MovementPattern::Run))->toBeTrue()
        ->and(ExerciseCatalogue::hasPattern('Padel', MovementPattern::Sport))->toBeTrue()
        ->and(ExerciseCatalogue::hasPattern("Farmer's carry", MovementPattern::Carry))->toBeTrue()
        ->and(ExerciseCatalogue::hasPattern('Deadlift', MovementPattern::Hinge))->toBeTrue();
});

it('lists the exercises that load an area for the coach', function (): void {
    expect(ExerciseLoads::exercisesLoading(BodyArea::LowerBack))->toContain('deadlift', 'squat', 'romanian deadlift')
        ->not->toContain('bench press');
});
