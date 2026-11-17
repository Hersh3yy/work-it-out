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
    expect(new SmartLogAgent('2026-11-15')->instructions())
        ->toContain('is not an exercise: put it in workout_notes')
        ->toContain('assisted true with weight_kg the help (45)')
        ->toContain('"bench press barbell" are both "Bench Press"')
        ->toContain('are logged per hand or per side, never added up');
});

it('tells the log reader every way a workout is texted, and to leave the sums to the app', function (): void {
    expect(new SmartLogAgent('2026-11-15')->instructions())
        ->toContain('Sets, reps and weight come in any order and any words')
        ->toContain('"5 keer 10 met 60 kg" is sets 5, reps 10, weight 60')
        ->toContain('bodyweight: true for an exercise done with the body alone')
        ->toContain('duration_seconds the time of ONE set')
        ->toContain('every exercise in it gets group "Superset"')
        ->toContain('"to failure", "till failure" is rir 0')
        ->toContain('per_side_kg 11.25 with weight_kg null; the app doubles it')
        ->toContain('"185 lb" is weight_lb 185 with weight_kg null')
        ->toContain('never work out the time from it')
        ->toContain('"3 more sets of bench press 70" is Bench Press sets 3 weight 70 with the reps left empty');
});

it('makes the coach answer first, keep goals in words and never claim a save', function (): void {
    expect(new CoachAgent(TrainerPersona::LtSurge, '{}')->instructions())
        ->toContain('never with "Understood."')
        ->toContain('Goals are not only facts.main_goal')
        ->toContain('Never say it was saved when CONTEXT does not show it')
        ->toContain('never send it again unchanged');
});

it('tells the coach what level, power, readiness and a goal\'s phase mean, and never to number an unmeasured quality', function (): void {
    expect(new CoachAgent(TrainerPersona::Shen, '{}')->instructions())
        ->toContain('level is training history and only grows')
        ->toContain('null means not measured yet, so never give it a number')
        ->toContain('lighten that quality today and train the fresh ones')
        ->toContain('taper inside two weeks');
});

it('keeps stated goals and the real start of a limitation in the profile', function (): void {
    expect((new ProfileAgent)->instructions())
        ->toContain('Two things are never dropped');
});

it('sends top_p 1 with temperature 0, which Mistral requires for greedy sampling', function (string $agent): void {
    $attributes = new ReflectionClass($agent)->getAttributes(TopP::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->value)->toEqual(1.0);
})->with([ClassifierAgent::class, LimitationAgent::class]);
