<?php

declare(strict_types=1);

use App\Actions\SmartLog\RevertSmartLog;
use App\Channels\HandleInboundMessage;
use App\Enums\MessageKind;
use App\Events\GoalRecorded;
use App\Fitness\Quality;
use App\Goals\GoalKind;
use App\Models\Goal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Tests\Fakes\FakeSmartLogParser;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
});

function goalSays(string $summary): void
{
    classifierSays(MessageKind::GoalOrInfo);
    fakeLimitationParser();
    fakeSmartLogParser(new FakeSmartLogParser(['log_type' => 'general', 'summary' => $summary, 'exercises' => [], 'questions' => []]));
}

it('stores "rave on 13 December" as an event goal demanding endurance, recovery and mobility', function (): void {
    $user = linkedUser();
    goalSays('Goal: rave on 13 December');
    Event::fake([GoalRecorded::class]);

    $reply = app(HandleInboundMessage::class)->handle(telegramText('I have a rave on 13 December'));

    $goal = $user->goals()->sole();
    expect($goal->kind)->toBe(GoalKind::Event)
        ->and($goal->name)->toBe('rave')
        ->and($goal->target_on?->toDateString())->toBe('2026-12-13')
        ->and($goal->kind->demands())->toBe([Quality::Endurance, Quality::Recovery, Quality::Mobility])
        ->and($reply)->toContain('Goal saved: rave on Sun 13 Dec, 28 days to go.');
    Event::assertDispatched(GoalRecorded::class);
});

it('reads "in 2 weeks" as two weeks from today', function (): void {
    $user = linkedUser();
    goalSays('Goal: festival in 2 weeks');

    app(HandleInboundMessage::class)->handle(telegramText('festival in 2 weeks, want to be ready'));

    expect($user->goals()->sole()->target_on?->toDateString())->toBe('2026-11-29');
});

it('asks for the date when a goal has none, and takes the answer', function (): void {
    $user = linkedUser();
    goalSays('Goal: be ready for a rave');

    $asked = app(HandleInboundMessage::class)->handle(telegramText('I want to be ready for a rave'));
    $answered = app(HandleInboundMessage::class)->handle(telegramText('13 December'));

    expect($asked)->toContain('When is it?')
        ->and($user->goals()->sole()->target_on?->toDateString())->toBe('2026-12-13')
        ->and($answered)->toBe('Goal saved: rave on Sun 13 Dec, 28 days to go.')
        ->and($user->activityLogs()->sole()->questions)->toBeNull();
});

it('leaves a remark without a goal word as a plain note', function (): void {
    $user = linkedUser();
    goalSays('Sleeps badly lately');

    app(HandleInboundMessage::class)->handle(telegramText('I sleep badly lately'));

    expect($user->goals()->count())->toBe(0);
});

it('drops the goal when its message is undone', function (): void {
    $user = linkedUser();
    goalSays('Goal: rave on 13 December');
    app(HandleInboundMessage::class)->handle(telegramText('rave on 13 December'));

    app(RevertSmartLog::class)->handle($user->activityLogs()->sole());

    expect(Goal::query()->count())->toBe(0);
});

it('lists goals with the time left, the phase and how ready each demanded quality is', function (): void {
    $user = linkedUser();
    $user->goals()->create(['kind' => GoalKind::Event, 'name' => 'rave', 'description' => 'rave on 13 December', 'target_on' => '2026-12-13']);
    $user->goals()->create(['kind' => GoalKind::Lift, 'name' => 'squat', 'description' => 'squat 100', 'target_on' => null]);
    $user->goals()->create(['kind' => GoalKind::Event, 'name' => 'party', 'description' => 'old party', 'target_on' => '2026-11-01']);

    $reply = app(HandleInboundMessage::class)->handle(telegramText('/goals'));

    expect($reply)->toContain('Rave on Sun 13 Dec, 28 days to go: build. Endurance not measured yet, recovery not measured yet, mobility not measured yet.')
        ->toContain('Squat: no date yet.')
        ->not->toContain('party');
});
