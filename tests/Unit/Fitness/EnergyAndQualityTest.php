<?php

declare(strict_types=1);

use App\Enums\TrainerPersona;
use App\Fitness\Energy;
use App\Fitness\Quality;

it('gives every quality exactly one energy', function (Quality $quality): void {
    $owners = array_filter(Energy::cases(), static fn (Energy $energy): bool => in_array($quality, $energy->qualities(), true));

    expect($owners)->toHaveCount(1)
        ->and(array_values($owners)[0])->toBe($quality->energy());
})->with(Quality::cases());

it('gives every energy two qualities', function (Energy $energy): void {
    expect($energy->qualities())->toHaveCount(2);
})->with(Energy::cases());

it('gives every energy exactly one coach', function (Energy $energy): void {
    $coaches = array_filter(TrainerPersona::cases(), static fn (TrainerPersona $coach): bool => $coach->energy() === $energy);

    expect($coaches)->toHaveCount(1)
        ->and(array_values($coaches)[0])->toBe($energy->coach());
})->with(Energy::cases());

it('pairs the coaches with the energies decided on 15 November', function (): void {
    expect(TrainerPersona::LtSurge->energy()->qualities())->toBe([Quality::Strength, Quality::Power])
        ->and(TrainerPersona::Shen->energy()->qualities())->toBe([Quality::Endurance, Quality::Core])
        ->and(TrainerPersona::Latika->energy()->qualities())->toBe([Quality::Mobility, Quality::Recovery]);
});
