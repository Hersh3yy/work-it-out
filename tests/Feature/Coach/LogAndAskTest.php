<?php

declare(strict_types=1);

use App\Channels\HandleInboundMessage;
use App\Enums\MessageKind;
use Tests\Fakes\FakeCoachAdvisor;
use Tests\Fakes\FakeSmartLogParser;

/*
 * One message can log and ask: the receipt first, then the coach answers
 * the same message ("bench 3x5 70, what next?", PROJECT.md diary 2026-11-12).
 */

it('logs the workout and then answers the question in the same message', function (): void {
    linkedUser();
    classifierSays(MessageKind::Workout);
    fakeSmartLogParser();
    $coach = fakeCoach(new FakeCoachAdvisor('Next: cable row 3x12 at 40 kg.'));

    $reply = app(HandleInboundMessage::class)->handle(telegramText('bench 3x5 70, what next?'));

    expect($reply)->toStartWith('Logged: Bench Press')
        ->toEndWith("\n\nNext: cable row 3x12 at 40 kg.")
        ->and($coach->calls)->toHaveCount(1)
        ->and($coach->calls[0]['question'])->toBe('bench 3x5 70, what next?')
        ->and($coach->calls[0]['context']['recent_logs'][0]['message'])->toBe('bench 3x5 70, what next?');
    $this->assertDatabaseCount('activity_logs', 1);
});

it('answers the question inside a goal message after noting it', function (): void {
    linkedUser();
    classifierSays(MessageKind::GoalOrInfo);
    fakeLimitationParser();
    fakeSmartLogParser(new FakeSmartLogParser(['log_type' => 'general', 'summary' => 'Goal: squats in 1 week', 'exercises' => [], 'questions' => []]));
    $coach = fakeCoach(new FakeCoachAdvisor('Yes: bodyweight box squats first, goblet next week.'));

    $reply = app(HandleInboundMessage::class)->handle(telegramText('I want to be able to do squats in 1 week, does your advice change?'));

    expect($reply)->toStartWith('Noted:')
        ->toEndWith('Yes: bodyweight box squats first, goblet next week.')
        ->and($coach->calls)->toHaveCount(1);
});

it('leaves a plain log without a coach answer', function (): void {
    linkedUser();
    classifierSays(MessageKind::Workout);
    fakeSmartLogParser();
    $coach = fakeCoach();

    expect(app(HandleInboundMessage::class)->handle(telegramText('bench 3x8 80')))->not->toContain('Chest-supported')
        ->and($coach->calls)->toBe([]);
});
