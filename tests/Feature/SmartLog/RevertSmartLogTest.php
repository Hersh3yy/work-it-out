<?php

declare(strict_types=1);

use App\Actions\SmartLog\RecordSmartLog;
use App\Actions\SmartLog\RevertSmartLog;
use App\Models\User;

it('undoing a merged log keeps the rest of the session', function (): void {
    $this->travelTo(today()->setTime(18, 0));
    $user = User::factory()->create();
    $record = app(RecordSmartLog::class);

    $record->handle($user, 'bench', workoutFacts([lift('Bench Press')]));
    $this->travel(30)->minutes();
    $second = $record->handle($user, 'incline', workoutFacts([lift('Incline DB Press', 30.0)]));

    app(RevertSmartLog::class)->handle($second->log);

    expect($user->workoutSessions()->count())->toBe(1)
        ->and($user->workoutSessions()->first()->exerciseEntries()->pluck('exercise_name')->all())->toBe(['Bench Press']);
    $this->assertDatabaseCount('activity_logs', 1);
    $this->assertDatabaseCount('diary_entries', 1);
});

it('undoing a weight log restores the previous current weight', function (): void {
    $user = User::factory()->create(['current_weight_kg' => 105]);
    $record = app(RecordSmartLog::class);

    $this->travelTo(today()->subDay()->setTime(8, 0));
    $record->handle($user, '105', ['log_type' => 'biometrics', 'summary' => 'Weight', 'weight_kg_stat' => 105.0]);
    $this->travelBack();
    $today = $record->handle($user, '104.5', ['log_type' => 'biometrics', 'summary' => 'Weight', 'weight_kg_stat' => 104.5]);

    app(RevertSmartLog::class)->handle($today->log);

    expect((float) $user->refresh()->current_weight_kg)->toBe(105.0);
    $this->assertDatabaseCount('body_weight_logs', 1);
});

it('deletes a log over HTTP and 404s for another user\'s log', function (): void {
    $owner = User::factory()->create();
    $result = app(RecordSmartLog::class)->handle($owner, 'bench', workoutFacts([lift('Bench Press')]));

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->deleteJson("/api/log/{$result->log->id}")
        ->assertNotFound();

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/log/{$result->log->id}")
        ->assertNoContent();

    $this->assertDatabaseCount('activity_logs', 0);
    expect($owner->workoutSessions()->count())->toBe(0);
});
