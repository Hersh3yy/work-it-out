<?php

declare(strict_types=1);

use App\Actions\SmartLog\RecordSmartLog;
use App\Actions\SmartLog\RevertSmartLog;
use App\Enums\LogType;
use App\Events\LogRecorded;
use App\Events\LogReverted;
use App\Jobs\UpdateUserStats;
use App\Listeners\RefreshTrainingStats;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

it('wires the stats listener to both log events', function (): void {
    Event::fake();

    Event::assertListening(LogRecorded::class, [RefreshTrainingStats::class, 'handleRecorded']);
    Event::assertListening(LogReverted::class, [RefreshTrainingStats::class, 'handleReverted']);
});

it('announces a recorded log and an undo', function (): void {
    Event::fake([LogRecorded::class, LogReverted::class]);
    $user = User::factory()->create();

    $result = app(RecordSmartLog::class)->handle($user, 'bench', workoutFacts([lift('Bench Press')]));
    app(RevertSmartLog::class)->handle($result->log);

    Event::assertDispatched(LogRecorded::class, fn (LogRecorded $e): bool => $e->log->is($result->log));
    Event::assertDispatched(LogReverted::class, fn (LogReverted $e): bool => $e->user->is($user) && $e->type === LogType::Workout);
});

it('recomputes stats only when training data changed', function (): void {
    Queue::fake();
    $user = User::factory()->create();
    $record = app(RecordSmartLog::class);

    $record->handle($user, 'walked the dog', ['log_type' => 'general', 'summary' => 'Walked the dog']);
    Queue::assertNotPushed(UpdateUserStats::class);

    $record->handle($user, 'bench', workoutFacts([lift('Bench Press')]));
    Queue::assertPushed(UpdateUserStats::class, 1);
});
