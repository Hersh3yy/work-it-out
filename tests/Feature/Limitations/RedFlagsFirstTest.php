<?php

declare(strict_types=1);

use App\Channels\HandleInboundMessage;
use App\Enums\MessageKind;
use App\Limitations\RedFlags;
use App\Models\ExerciseEntry;
use App\Models\Limitation;
use Tests\Fakes\FakeLimitationParser;
use Tests\Fakes\FakeSmartLogParser;

/*
 * Danger words get the see-a-doctor reply even while an open question is
 * waiting for its answer (audit 2026-11-14, item 2): "1 hour ago my leg went
 * numb" is not option 1, and "2 days ago" is not two training days.
 */

it('answers danger words before the injury area question', function (): void {
    linkedUser();
    classifierSays(MessageKind::GoalOrInfo);
    fakeLimitationParser(FakeLimitationParser::backUnclear());
    $chat = app(HandleInboundMessage::class);
    $chat->handle(telegramText('i hurt my back and need to go slow for 2 weeks'));

    expect($chat->handle(telegramText('1 hour ago my leg went numb')))->toBe(RedFlags::REPLY)
        ->and(Limitation::sole()->body_area)->toBeNull();
});

it('answers danger words before the red-flag screen question', function (): void {
    linkedUser();
    classifierSays(MessageKind::GoalOrInfo);
    fakeLimitationParser(FakeLimitationParser::knee());
    $chat = app(HandleInboundMessage::class);
    $chat->handle(telegramText('my left knee hurts a bit when I squat'));

    expect($chat->handle(telegramText('no, but now my leg went numb')))->toBe(RedFlags::REPLY);
});

it('answers danger words before a missing exercise value', function (): void {
    linkedUser();
    fakeSmartLogParser(FakeSmartLogParser::missingWeight());
    $chat = app(HandleInboundMessage::class);
    $chat->handle(telegramText('incline db press 3x10'));

    expect($chat->handle(telegramText('4 minutes ago I got chest pain')))->toBe(RedFlags::REPLY)
        ->and(ExerciseEntry::sole()->weight_kg)->toBeNull();
});

it('answers danger words before a profile question', function (): void {
    $user = linkedUser();
    $user->update(['training_days_per_week' => 0, 'primary_goal' => null]);
    fakeSmartLogParser(FakeSmartLogParser::workout());
    $chat = app(HandleInboundMessage::class);
    $chat->handle(telegramText('benched 100 3x5'));

    expect($chat->handle(telegramText('2 days ago my arm went numb on bench')))->toBe(RedFlags::REPLY)
        ->and($user->refresh()->training_days_per_week)->toBe(0);
});
