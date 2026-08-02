<?php

declare(strict_types=1);

use App\Contracts\Profile\ProfileIntake;
use App\Models\User;

it('reports every intake question for a blank profile', function (): void {
    $user = User::factory()->withoutProfile()->create();

    $report = app(ProfileIntake::class)->report($user);

    expect($report->isComplete())->toBeFalse()
        ->and($report->percentComplete)->toBe(0)
        ->and($report->missing)->toHaveKeys([
            'primary_goal', 'experience_level', 'training_days_per_week',
            'current_weight_kg', 'goal_description', 'target_weight_kg', 'goal_deadline',
        ])
        ->and($report->nextQuestion())->toContain('main goal');
});

it('reports a complete profile as complete', function (): void {
    $user = User::factory()->withCompleteProfile()->create();

    $report = app(ProfileIntake::class)->report($user);

    expect($report->isComplete())->toBeTrue()
        ->and($report->percentComplete)->toBe(100)
        ->and($report->nextQuestion())->toBeNull();
});

it('exposes the intake report over the API', function (): void {
    $user = User::factory()->withoutProfile()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/profile/intake')
        ->assertOk()
        ->assertJsonPath('is_complete', false)
        ->assertJsonPath('percent_complete', 0)
        ->assertJsonStructure([
            'percent_complete', 'is_complete', 'missing_fields', 'questions', 'next_question',
        ]);
});

it('requires authentication for the intake endpoint', function (): void {
    $this->getJson('/api/profile/intake')->assertUnauthorized();
});
