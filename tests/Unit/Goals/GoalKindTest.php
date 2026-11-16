<?php

declare(strict_types=1);

use App\Fitness\Quality;
use App\Goals\GoalKind;

it('reads the kind of goal from its words', function (string $text, GoalKind $kind): void {
    expect(GoalKind::in($text)?->kind)->toBe($kind);
})->with([
    ['rave on 13 December', GoalKind::Event],
    ['my sister\'s wedding in May', GoalKind::Event],
    ['5k in 8 weeks', GoalKind::Distance],
    ['half marathon in April', GoalKind::Distance],
    ['carry my mum\'s fridge next month', GoalKind::Carry],
    ['deadlift 150 by March', GoalKind::Lift],
    ['I want to squat 100 by December', GoalKind::Lift],
    ['lose 5 kg by summer', GoalKind::Bodyweight],
]);

it('finds no goal in a remark without a goal word', function (): void {
    expect(GoalKind::in('I sleep badly lately'))->toBeNull();
});

it('makes a dated event demand endurance, recovery and mobility', function (): void {
    expect(GoalKind::Event->demands())->toBe([Quality::Endurance, Quality::Recovery, Quality::Mobility]);
});

it('makes every kind demand at least one quality', function (GoalKind $kind): void {
    expect($kind->demands())->not->toBeEmpty();
})->with(GoalKind::cases());
