<?php

declare(strict_types=1);

namespace App\Fitness;

use App\Enums\BodyArea;
use App\Services\Exercises\ExerciseAliases;
use App\Services\Stats\PersonalRecordService;

/**
 * Which qualities an exercise trains, by keywords in its name. A stopgap
 * built on the existing keyword lists until the exercise catalogue exists.
 */
final class ExerciseQualities
{
    /** @var array<string, list<Quality>> keyword in the normalized name => qualities */
    private const array KEYWORDS = [
        'deadlift' => [Quality::Strength], 'rdl' => [Quality::Strength], 'romanian' => [Quality::Strength],
        'squat' => [Quality::Strength], 'lunge' => [Quality::Strength], 'legpress' => [Quality::Strength],
        'goodmorning' => [Quality::Strength], 'row' => [Quality::Strength], 'bench' => [Quality::Strength],
        'press' => [Quality::Strength], 'dip' => [Quality::Strength], 'pullup' => [Quality::Strength],
        'chinup' => [Quality::Strength], 'pulldown' => [Quality::Strength], 'curl' => [Quality::Strength],
        'clean' => [Quality::Strength, Quality::Power], 'snatch' => [Quality::Strength, Quality::Power],
        'jump' => [Quality::Power], 'box' => [Quality::Power], 'sprint' => [Quality::Power, Quality::Endurance],
        'swing' => [Quality::Power], 'throw' => [Quality::Power], 'slam' => [Quality::Power],
        'walk' => [Quality::Endurance, Quality::Recovery], 'cycl' => [Quality::Endurance], 'bike' => [Quality::Endurance],
        'swim' => [Quality::Endurance], 'rowing' => [Quality::Endurance], 'rower' => [Quality::Endurance],
        'hike' => [Quality::Endurance], 'elliptical' => [Quality::Endurance],
        'plank' => [Quality::Core], 'carry' => [Quality::Core], 'farmer' => [Quality::Core], 'hollow' => [Quality::Core],
        'deadbug' => [Quality::Core], 'pallof' => [Quality::Core], 'situp' => [Quality::Core], 'crunch' => [Quality::Core],
        'rollout' => [Quality::Core], 'hold' => [Quality::Core],
        'stretch' => [Quality::Mobility, Quality::Recovery], 'yoga' => [Quality::Mobility, Quality::Recovery],
        'mobility' => [Quality::Mobility], 'foamroll' => [Quality::Recovery],
    ];

    /** @var array<string, list<BodyArea>> quality value => the areas its exercises load */
    private const array AREAS = [
        'strength' => [BodyArea::LowerBack, BodyArea::Hip, BodyArea::Knee, BodyArea::Shoulder, BodyArea::Chest, BodyArea::UpperBack, BodyArea::Elbow, BodyArea::Wrist],
        'power' => [BodyArea::LowerBack, BodyArea::Hip, BodyArea::Knee, BodyArea::Ankle, BodyArea::Shoulder, BodyArea::Wrist],
        'endurance' => [BodyArea::Hip, BodyArea::Knee, BodyArea::Ankle, BodyArea::Foot],
        'core' => [BodyArea::LowerBack, BodyArea::Hip, BodyArea::Shoulder],
        'mobility' => [],
        'recovery' => [],
    ];

    /** Shorter keywords only match the start of a word, so "crunch" is not a run. */
    private const int JOINED_KEYWORD_LENGTH = 6;

    /**
     * @return list<Quality>
     */
    public static function of(string $exerciseName): array
    {
        $qualities = [];

        foreach ([...self::KEYWORDS, ...self::cardioKeywords()] as $keyword => $trained) {
            if (self::nameHas($exerciseName, (string) $keyword)) {
                $qualities = [...$qualities, ...$trained];
            }
        }

        return array_values(array_unique($qualities, SORT_REGULAR));
    }

    /**
     * @return list<BodyArea>
     */
    public static function areasLoadedBy(Quality $quality): array
    {
        return self::AREAS[$quality->value];
    }

    public static function trains(string $exerciseName, Quality $quality): bool
    {
        return in_array($quality, self::of($exerciseName), true);
    }

    private static function nameHas(string $exerciseName, string $keyword): bool
    {
        if (mb_strlen($keyword) >= self::JOINED_KEYWORD_LENGTH && str_contains(ExerciseAliases::key($exerciseName), $keyword)) {
            return true;
        }

        $words = preg_split('/[^a-z0-9]+/', mb_strtolower($exerciseName), flags: PREG_SPLIT_NO_EMPTY) ?: [];

        return array_any($words, static fn (string $word): bool => str_starts_with($word, $keyword));
    }

    /**
     * @return array<string, list<Quality>>
     */
    private static function cardioKeywords(): array
    {
        $words = [...PersonalRecordService::RUN_KEYWORDS, ...PersonalRecordService::SPORT_KEYWORDS];

        return array_fill_keys(array_map(ExerciseAliases::key(...), $words), [Quality::Endurance]);
    }
}
