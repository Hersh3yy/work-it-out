<?php

declare(strict_types=1);

use App\Actions\SmartLog\RevertSmartLog;
use App\Channels\HandleInboundMessage;
use App\Enums\BodyArea;
use App\Enums\MessageKind;
use App\Limitations\RedFlagScreen;
use App\Models\Limitation;
use App\Profile\ProfileSummary;
use Tests\Fakes\FakeLimitationParser;

/*
 * The red-flag screen asked once, and how a limitation is going over the
 * days, from Hiren's own back strain (PLAN.md section 4, 2026-11-01).
 */

function backUpdate(?int $durationDays = null): FakeLimitationParser
{
    return new FakeLimitationParser([
        'is_limitation' => true, 'body_area' => 'lower_back', 'candidates' => [], 'severity' => null,
        'duration_days' => $durationDays, 'started_days_ago' => null, 'note' => 'back pain 70% of yesterday',
    ]);
}

it('asks the red-flag screen after the area is chosen and stores a no as clear', function (): void {
    $user = linkedUser();
    classifierSays(MessageKind::GoalOrInfo);
    fakeLimitationParser(FakeLimitationParser::backUnclear());
    $chat = app(HandleInboundMessage::class);

    $chat->handle(telegramText('i hurt my back and need to go slow for 2 weeks'));

    expect($chat->handle(telegramText('1')))->toEndWith(RedFlagScreen::QUESTION)
        ->and($chat->handle(telegramText('No')))->toBe(RedFlagScreen::CLEAR_REPLY)
        ->and($user->limitations()->first()->red_flags)->toBe(Limitation::RED_FLAGS_CLEAR)
        ->and($user->activityLogs()->first()->questions)->toBeNull();
});

it('flags a yes and says to get it checked', function (): void {
    $user = linkedUser();
    classifierSays(MessageKind::GoalOrInfo);
    fakeLimitationParser(FakeLimitationParser::knee());
    $chat = app(HandleInboundMessage::class);

    $chat->handle(telegramText('my left knee hurts a bit when I squat'));

    expect($chat->handle(telegramText('ja')))->toBe(RedFlagScreen::FLAGGED_REPLY)
        ->and($user->limitations()->first()->red_flags)->toBe(Limitation::RED_FLAGS_FLAGGED);
});

it('lets a workout through while the screen is open', function (): void {
    linkedUser();
    classifierSays(MessageKind::GoalOrInfo);
    fakeLimitationParser(FakeLimitationParser::knee());
    $logParser = fakeSmartLogParser();

    app(HandleInboundMessage::class)->handle(telegramText('my left knee hurts a bit when I squat'));
    classifierSays(MessageKind::Workout);
    app(HandleInboundMessage::class)->handle(telegramText('bench 3x5 100'));

    expect($logParser->calls)->toHaveCount(1);
});

it('records how an active limitation is going instead of a second injury', function (): void {
    $user = linkedUser();
    $back = $user->limitations()->create(['body_area' => BodyArea::LowerBack, 'note' => 'go slow', 'started_on' => today()->subDays(8)]);
    classifierSays(MessageKind::GoalOrInfo);
    fakeLimitationParser(backUpdate());

    $reply = app(HandleInboundMessage::class)->handle(telegramText('back pain is still at least 70% of yesterday'));

    expect($reply)->toBe('Noted for your lower back, day 9. Say /better when it has healed.')
        ->and($user->limitations()->count())->toBe(1)
        ->and($back->updates()->first()->note)->toBe('back pain is still at least 70% of yesterday');
    $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'summary' => 'Update on lower back: back pain is still at least 70% of yesterday']);
});

it('moves the expected end when an update gives a new duration', function (): void {
    $user = linkedUser();
    $back = $user->limitations()->create(['body_area' => BodyArea::LowerBack, 'note' => 'go slow', 'started_on' => today()]);
    classifierSays(MessageKind::GoalOrInfo);
    fakeLimitationParser(backUpdate(7));

    expect(app(HandleInboundMessage::class)->handle(telegramText('physio says one more week')))
        ->toContain('until '.today()->addDays(7)->format('j M'))
        ->and($back->fresh()->expected_until->toDateString())->toBe(today()->addDays(7)->toDateString());
});

it('treats a different area as a new limitation', function (): void {
    $user = linkedUser();
    $user->limitations()->create(['body_area' => BodyArea::LowerBack, 'note' => 'go slow', 'started_on' => today()]);
    classifierSays(MessageKind::GoalOrInfo);
    fakeLimitationParser(FakeLimitationParser::knee());

    app(HandleInboundMessage::class)->handle(telegramText('my left knee hurts a bit when I squat'));

    expect($user->limitations()->count())->toBe(2);
});

it('shows the latest update in /me and removes it on undo', function (): void {
    $user = linkedUser();
    $user->limitations()->create(['body_area' => BodyArea::LowerBack, 'note' => 'go slow', 'started_on' => today()]);
    classifierSays(MessageKind::GoalOrInfo);
    fakeLimitationParser(backUpdate());
    $chat = app(HandleInboundMessage::class);

    $chat->handle(telegramText('sitting hurts after 20 min'));

    expect(app(ProfileSummary::class)->for($user))->toContain('Latest, '.today()->format('j M').': sitting hurts after 20 min');

    app(RevertSmartLog::class)->handle($user->activityLogs()->first());

    $this->assertDatabaseCount('limitation_updates', 0);
});

it('includes updates and the screen in the app overview', function (): void {
    $user = linkedUser();
    $back = $user->limitations()->create(['body_area' => BodyArea::LowerBack, 'note' => 'go slow', 'started_on' => today(), 'red_flags' => 'clear']);
    $back->updates()->create(['note' => 'better today', 'logged_on' => today()]);

    $this->actingAs($user)->getJson('/api/me/overview')
        ->assertOk()
        ->assertJsonPath('limitations.0.red_flags', 'clear')
        ->assertJsonPath('limitations.0.day', 1)
        ->assertJsonPath('limitations.0.updates.0.note', 'better today');
});
