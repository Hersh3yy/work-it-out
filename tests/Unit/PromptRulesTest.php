<?php

declare(strict_types=1);

use App\Ai\Agents\ClassifierAgent;
use App\Ai\Agents\CoachAgent;
use App\Ai\Agents\LimitationAgent;
use App\Ai\Agents\ProfileAgent;
use App\Ai\Agents\SmartLogAgent;
use App\Enums\TrainerPersona;
use Laravel\Ai\Attributes\TopP;

it('keeps an illness that is over and a word about a load out of the injury reader', function (): void {
    expect((new LimitationAgent)->instructions())
        ->toContain('an illness that is already over')
        ->toContain('The lower the better right?" -> false');
});

it('tells the log reader what is not an exercise and what assistance is', function (): void {
    expect((new SmartLogAgent('2026-11-15'))->instructions())
        ->toContain('is not an exercise: put it in workout_notes')
        ->toContain('weight_kg null and "45 kg assistance" in notes')
        ->toContain('"bench press barbell" are both "Bench Press"')
        ->toContain('Dumbbells are logged per hand, never added up');
});

it('makes the coach answer first, keep goals in words and never claim a save', function (): void {
    expect((new CoachAgent(TrainerPersona::LtSurge, '{}'))->instructions())
        ->toContain('never with "Understood."')
        ->toContain('Goals are not only facts.main_goal')
        ->toContain('Never say it was saved when CONTEXT does not show it')
        ->toContain('never send it again unchanged');
});

it('keeps stated goals and the real start of a limitation in the profile', function (): void {
    expect((new ProfileAgent)->instructions())
        ->toContain('Two things are never dropped');
});

it('sends top_p 1 with temperature 0, which Mistral requires for greedy sampling', function (string $agent): void {
    $attributes = (new ReflectionClass($agent))->getAttributes(TopP::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->value)->toEqual(1.0);
})->with([ClassifierAgent::class, LimitationAgent::class]);
