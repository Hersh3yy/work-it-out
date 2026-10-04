<?php

declare(strict_types=1);

use App\Actions\SmartLog\RecordSmartLog;
use App\Models\User;

function logWithOpenWeight(User $user)
{
    return app(RecordSmartLog::class)->handle($user, 'incline 3x10', workoutFacts(
        [lift('Incline DB Press', null, 3, 10)],
        ['questions' => [['field' => 'exercises.0.weight_kg', 'question' => 'What weight for the incline press?']]],
    ))->log;
}

it('fills the asked value over HTTP without AI', function (): void {
    $parser = fakeSmartLogParser();
    $user = User::factory()->create();
    $log = logWithOpenWeight($user);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/log/{$log->id}/answer", ['value' => '32,5'])
        ->assertOk()
        ->assertJsonPath('entry.weight_kg', 32.5)
        ->assertJsonPath('questions', []);

    expect($parser->calls)->toBe([])
        ->and($log->fresh()->questions)->toBeNull();
});

it('rejects text that is not a value and keeps the question open', function (): void {
    $user = User::factory()->create();
    $log = logWithOpenWeight($user);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/log/{$log->id}/answer", ['value' => 'dunno'])
        ->assertUnprocessable();

    expect($log->fresh()->questions)->not->toBeNull();
});

it('is 422 when the log has no open question', function (): void {
    $user = User::factory()->create();
    $log = app(RecordSmartLog::class)->handle($user, 'bench', workoutFacts([lift('Bench Press')]))->log;

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/log/{$log->id}/answer", ['value' => '80'])
        ->assertUnprocessable();
});

it('skips the question and 404s for another user\'s log', function (): void {
    $user = User::factory()->create();
    $log = logWithOpenWeight($user);

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson("/api/log/{$log->id}/skip")
        ->assertNotFound();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/log/{$log->id}/skip")
        ->assertNoContent();

    expect($log->fresh()->questions)->toBeNull();
});

it('converts units for time and distance answers', function (string $field, string $value, string $column, int $expected): void {
    $user = User::factory()->create();
    $log = app(RecordSmartLog::class)->handle($user, 'run', workoutFacts(
        [['exercise_name' => 'Run']],
        ['questions' => [['field' => $field, 'question' => '?']]],
    ))->log;

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/log/{$log->id}/answer", ['value' => $value])
        ->assertOk();

    expect($log->exerciseEntries()->first()->{$column})->toBe($expected);
})->with([
    'bare minutes' => ['exercises.0.duration_seconds', '28', 'duration_seconds', 1680],
    'seconds' => ['exercises.0.duration_seconds', '90 sec', 'duration_seconds', 90],
    'kilometers' => ['exercises.0.distance_meters', '5k', 'distance_meters', 5000],
    'bare meters' => ['exercises.0.distance_meters', '400', 'distance_meters', 400],
]);
