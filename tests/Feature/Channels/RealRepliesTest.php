<?php

declare(strict_types=1);

use App\Actions\SmartLog\RecordSmartLog;
use App\Channels\HandleInboundMessage;
use App\Enums\MessageKind;
use App\Enums\PrimaryGoal;
use App\Models\ProfilePrompt;
use App\Models\User;
use Tests\Fakes\FakeCoachAdvisor;

/*
 * Replies as Hiren actually typed them on 4 and 7 November: words around the
 * number, an answer and a question in one message, a correction.
 */

function benchWithOpenQuestion(User $user, string $field, array $sets = []): void
{
    $lift = ['exercise_name' => 'Bench Press', 'sets' => $sets === [] ? 3 : count($sets), 'reps' => null, 'weight_kg' => null, 'set_list' => $sets];

    app(RecordSmartLog::class)->handle($user, 'bench', workoutFacts([$lift], ['questions' => [['field' => $field, 'question' => '?']]]));
}

it('takes "5 reps each set" as the reps', function (): void {
    $user = linkedUser();
    benchWithOpenQuestion($user, 'exercises.0.reps');

    $reply = app(HandleInboundMessage::class)->handle(telegramText('5 reps each set'));

    expect($reply)->toStartWith('Updated: Bench Press')
        ->and($user->activityLogs()->count())->toBe(1)
        ->and($user->activityLogs()->first()->exerciseEntries()->first()->reps)->toBe(5);
});

it('fills one set and sends the question after it to the coach', function (): void {
    $user = linkedUser();
    benchWithOpenQuestion($user, 'exercises.0.set_list.5.weight_kg', [
        ['reps' => 5, 'weight_kg' => 60.0], ['reps' => 5, 'weight_kg' => 80.0], ['reps' => 5, 'weight_kg' => 70.0],
        ['reps' => 5, 'weight_kg' => 70.0], ['reps' => 5, 'weight_kg' => 70.0], ['reps' => 5, 'weight_kg' => null],
    ]);
    classifierSays(MessageKind::CoachQuestion);
    $coach = fakeCoach(new FakeCoachAdvisor('1. Pallof press 3x12'));

    $reply = app(HandleInboundMessage::class)->handle(telegramText('70kg....now what next ?'));

    $sixth = $user->activityLogs()->first()->exerciseEntries()->first()->setRows()->where('position', 6)->first();
    expect($sixth->weight_kg)->toEqual(70.0)
        ->and($reply)->toStartWith('Updated: Bench Press')
        ->and($reply)->toEndWith('1. Pallof press 3x12')
        ->and($coach->calls[0]['question'])->toBe('now what next ?')
        ->and($user->activityLogs()->first()->questions)->toBeNull();
});

it('does not take a weight as the reps', function (): void {
    $user = linkedUser();
    benchWithOpenQuestion($user, 'exercises.0.reps');

    $reply = app(HandleInboundMessage::class)->handle(telegramText('12 kg'));

    expect($user->activityLogs()->where('raw_message', 'bench')->first()->exerciseEntries()->first()->reps)->toBeNull()
        ->and($reply)->not->toStartWith('Updated');
});

it('treats a message with a second number as a new log, not an answer', function (): void {
    $user = linkedUser();
    benchWithOpenQuestion($user, 'exercises.0.weight_kg');
    $parser = fakeSmartLogParser();

    app(HandleInboundMessage::class)->handle(telegramText('3x5 squat 100'));

    expect($parser->calls)->toHaveCount(1)
        ->and($user->activityLogs()->where('raw_message', 'bench')->first()->exerciseEntries()->first()->weight_kg)->toBeNull();
});

it('lets "No, general fitness" correct a goal answered a moment ago', function (): void {
    $user = linkedUser();
    $user->update(['primary_goal' => PrimaryGoal::LoseFat]);
    ProfilePrompt::create(['user_id' => $user->id, 'question_key' => 'primary_goal', 'asked_at' => now()->subMinute(), 'answered_at' => now()->subSeconds(30)]);
    $parser = fakeSmartLogParser();

    $reply = app(HandleInboundMessage::class)->handle(telegramText('No, general fitness'));

    expect($reply)->toBe('Saved: your main goal is general fitness.')
        ->and($user->fresh()->primary_goal)->toBe(PrimaryGoal::GeneralFitness)
        ->and($parser->calls)->toBe([]);
});

it('does not reopen a goal answered long ago', function (): void {
    $user = linkedUser();
    $user->update(['primary_goal' => PrimaryGoal::LoseFat]);
    ProfilePrompt::create(['user_id' => $user->id, 'question_key' => 'primary_goal', 'asked_at' => now()->subDay(), 'answered_at' => now()->subDay()]);
    fakeSmartLogParser();

    app(HandleInboundMessage::class)->handle(telegramText('No, general fitness'));

    expect($user->fresh()->primary_goal)->toBe(PrimaryGoal::LoseFat);
});
