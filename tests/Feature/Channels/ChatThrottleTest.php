<?php

declare(strict_types=1);

use App\Channels\HandleInboundMessage;
use App\Conversation\ChatThrottle;
use App\Models\ChannelIdentity;
use App\Models\User;
use Tests\Fakes\FakeSmartLogParser;

/*
 * One person forwarding dozens of messages must not use up the AI for
 * everyone (audit 2026-11-14 C3).
 */

beforeEach(function (): void {
    config(['conversation.ai_messages_per_minute' => 2]);
});

it('answers politely and asks no AI once someone sends too much in a minute', function (): void {
    linkedUser();
    $parser = fakeSmartLogParser(FakeSmartLogParser::workout());
    $chat = app(HandleInboundMessage::class);
    $chat->handle(telegramText('benched 100 3x5'));
    $chat->handle(telegramText('squat 100 3x5'));

    expect($chat->handle(telegramText('deadlift 120 1x5')))->toBe(ChatThrottle::REPLY)
        ->and($parser->calls)->toHaveCount(2);
});

it('counts per person', function (): void {
    linkedUser();
    $other = User::factory()->create();
    ChannelIdentity::create(['user_id' => $other->id, 'provider' => 'telegram', 'external_id' => '5555']);
    fakeSmartLogParser(FakeSmartLogParser::workout());
    $chat = app(HandleInboundMessage::class);
    $chat->handle(telegramText('benched 100 3x5'));
    $chat->handle(telegramText('squat 100 3x5'));

    expect($chat->handle(telegramText('benched 100 3x5', '5555')))->not->toBe(ChatThrottle::REPLY);
});

it('lets messages through again a minute later', function (): void {
    linkedUser();
    fakeSmartLogParser(FakeSmartLogParser::workout());
    $chat = app(HandleInboundMessage::class);
    $chat->handle(telegramText('benched 100 3x5'));
    $chat->handle(telegramText('squat 100 3x5'));
    $this->travel(61)->seconds();

    expect($chat->handle(telegramText('deadlift 120 1x5')))->not->toBe(ChatThrottle::REPLY);
});

it('never throttles commands', function (): void {
    linkedUser();
    fakeSmartLogParser(FakeSmartLogParser::workout());
    $chat = app(HandleInboundMessage::class);
    $chat->handle(telegramText('benched 100 3x5'));
    $chat->handle(telegramText('squat 100 3x5'));

    expect($chat->handle(telegramText('/help')))->not->toBe(ChatThrottle::REPLY);
});
