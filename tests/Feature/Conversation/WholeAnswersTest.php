<?php

declare(strict_types=1);

use App\Channels\HandleInboundMessage;
use App\Enums\BodyArea;
use App\Enums\MessageKind;
use App\Enums\PrimaryGoal;
use App\Limitations\RedFlagScreen;
use App\Models\ActivityLog;
use App\Models\ExerciseEntry;
use App\Models\Limitation;
use Tests\Fakes\FakeLimitationParser;
use Tests\Fakes\FakeSmartLogParser;

/*
 * An open question only takes a message that is wholly its answer; anything
 * else is a new message and goes the usual way (audit 2026-11-08, tier 1
 * items 1 to 4). Each case below is a reproduction from that audit.
 */

it('logs "run 5k in 25 min" while the goal question is open', function (): void {
    $user = linkedUser();
    $user->update(['primary_goal' => null]);
    $parser = fakeSmartLogParser(FakeSmartLogParser::workout());
    $chat = app(HandleInboundMessage::class);
    $chat->handle(telegramText('benched 100 3x5'));

    $chat->handle(telegramText('run 5k in 25 min'));

    expect($parser->calls)->toHaveCount(2)
        ->and(ActivityLog::count())->toBe(2)
        ->and($user->refresh()->primary_goal)->toBeNull();
});

it('logs "3 sets of squats at 100" while the training-days question is open', function (): void {
    $user = linkedUser();
    $user->update(['training_days_per_week' => 0]);
    $parser = fakeSmartLogParser(FakeSmartLogParser::workout());
    $chat = app(HandleInboundMessage::class);
    $chat->handle(telegramText('benched 100 3x5'));

    $chat->handle(telegramText('3 sets of squats at 100'));

    expect($parser->calls)->toHaveCount(2)
        ->and($user->refresh()->training_days_per_week)->toBe(0);
});

it('logs "No pain today, ran 2 km" right after the goal was answered', function (): void {
    $user = linkedUser();
    $user->update(['primary_goal' => null]);
    $parser = fakeSmartLogParser(FakeSmartLogParser::workout());
    $chat = app(HandleInboundMessage::class);
    $chat->handle(telegramText('benched 100 3x5'));
    $chat->handle(telegramText('2'));

    $chat->handle(telegramText('No pain today, ran 2 km'));

    expect($parser->calls)->toHaveCount(2)
        ->and($user->refresh()->primary_goal)->toBe(PrimaryGoal::LoseFat);
});

it('still takes a short goal answer and its correction', function (string $answer, PrimaryGoal $goal): void {
    $user = linkedUser();
    $user->update(['primary_goal' => null]);
    fakeSmartLogParser(FakeSmartLogParser::workout());
    $chat = app(HandleInboundMessage::class);
    $chat->handle(telegramText('benched 100 3x5'));

    $chat->handle(telegramText($answer));

    expect($user->refresh()->primary_goal)->toBe($goal);
})->with([
    'a number' => ['3', PrimaryGoal::ImproveEndurance],
    'the label' => ['lose fat', PrimaryGoal::LoseFat],
    'a word' => ['Muscle!', PrimaryGoal::BuildMuscle],
]);

it('logs "shoulder press 3x10 30kg" while the area question is open', function (): void {
    linkedUser();
    $classifier = classifierSays(MessageKind::GoalOrInfo);
    fakeLimitationParser(FakeLimitationParser::backUnclear());
    $parser = fakeSmartLogParser(FakeSmartLogParser::workout());
    $chat = app(HandleInboundMessage::class);
    $chat->handle(telegramText('i hurt my back and need to go slow for 2 weeks'));
    $classifier->says(MessageKind::Workout);

    $chat->handle(telegramText('shoulder press 3x10 30kg'));

    expect($parser->calls)->toHaveCount(1)
        ->and(Limitation::sole()->body_area)->toBeNull();
});

it('logs "2 sets of rows at 50" while the area question is open, not option 2', function (): void {
    linkedUser();
    $classifier = classifierSays(MessageKind::GoalOrInfo);
    fakeLimitationParser(FakeLimitationParser::backUnclear());
    $parser = fakeSmartLogParser(FakeSmartLogParser::workout());
    $chat = app(HandleInboundMessage::class);
    $chat->handle(telegramText('i hurt my back and need to go slow for 2 weeks'));
    $classifier->says(MessageKind::Workout);

    $chat->handle(telegramText('2 sets of rows at 50'));

    expect($parser->calls)->toHaveCount(1)
        ->and(Limitation::sole()->body_area)->toBeNull();
});

it('still takes the area by number or name', function (string $answer, BodyArea $area): void {
    linkedUser();
    classifierSays(MessageKind::GoalOrInfo);
    fakeLimitationParser(FakeLimitationParser::backUnclear());
    $chat = app(HandleInboundMessage::class);
    $chat->handle(telegramText('i hurt my back and need to go slow for 2 weeks'));

    expect($chat->handle(telegramText($answer)))->toEndWith(RedFlagScreen::QUESTION)
        ->and(Limitation::sole()->body_area)->toBe($area);
})->with([
    'a number' => ['1', BodyArea::LowerBack],
    'the name' => ['Lower back', BodyArea::LowerBack],
    'the name in a phrase' => ['my upper back.', BodyArea::UpperBack],
]);

it('does not flag the injury when "yes" answers the coach', function (): void {
    linkedUser();
    $classifier = classifierSays(MessageKind::GoalOrInfo);
    fakeLimitationParser(FakeLimitationParser::knee());
    fakeCoach();
    $chat = app(HandleInboundMessage::class);
    $chat->handle(telegramText('my left knee hurts a bit when I squat'));
    $this->travel(1)->minutes();
    $classifier->says(MessageKind::CoachQuestion);
    $chat->handle(telegramText('what can I train today? anything for the knee on stairs?'));
    $this->travel(1)->minutes();

    $reply = $chat->handle(telegramText('yes'));

    expect($reply)->not->toBe(RedFlagScreen::FLAGGED_REPLY)
        ->and(Limitation::sole()->red_flags)->toBeNull();
});

it('records "pulled my hamstring" as a new injury next to the back', function (): void {
    $user = linkedUser();
    $back = $user->limitations()->create(['body_area' => BodyArea::LowerBack, 'note' => 'strained deadlifting', 'started_on' => today()->subDays(6)]);
    classifierSays(MessageKind::GoalOrInfo);
    fakeLimitationParser(new FakeLimitationParser([
        'is_limitation' => true, 'body_area' => 'other', 'candidates' => [], 'severity' => 'moderate',
        'duration_days' => 21, 'started_days_ago' => 0, 'note' => 'pulled hamstring',
    ]));

    app(HandleInboundMessage::class)->handle(telegramText('pulled my hamstring'));

    expect(Limitation::count())->toBe(2)
        ->and($back->refresh()->updates()->count())->toBe(0)
        ->and($back->expected_until)->toBeNull();
});

it('answers the open question of an older log after a newer log without one', function (): void {
    linkedUser();
    fakeSmartLogParser(FakeSmartLogParser::missingWeight());
    app(HandleInboundMessage::class)->handle(telegramText('incline db press 3x10'));
    $this->travel(1)->minutes();
    fakeSmartLogParser(FakeSmartLogParser::workout());
    app(HandleInboundMessage::class)->handle(telegramText('benched 100 3x5'));

    app(HandleInboundMessage::class)->handle(telegramText('30'));

    expect((float) ExerciseEntry::query()->where('exercise_name', 'Incline DB Press')->sole()->weight_kg)->toBe(30.0);
});
