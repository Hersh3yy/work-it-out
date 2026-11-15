<?php

declare(strict_types=1);

use App\Channels\HandleInboundMessage;
use App\Coach\CoachContext;
use App\Enums\MessageKind;
use App\Profile\ProfileFacts;
use App\Profile\UpdateProfile;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\FakeSmartLogParser;

/*
 * Hiren's superset of 2026-11-15 worded the same way in the receipt, the
 * coach's context and the profile writer's input: one line, with the rounds.
 */
const SUPERSET_LINE = 'Superset x4: Push-ups 10, Lying Leg Raise 12, Plank 40s';

function logHirensSuperset(): void
{
    classifierSays(MessageKind::Workout);
    fakeSmartLogParser(new FakeSmartLogParser(array_merge(supersetFacts(), ['summary' => 'Superset: Pushups 4x10, Lying Leg Raises 4x12, Plank 4x40s'])));
}

it('words the superset as one line with its rounds in the receipt', function (): void {
    linkedUser();
    logHirensSuperset();

    expect(app(HandleInboundMessage::class)->handle(telegramText("Did a super set\n10 pushups\n12 lying leg raises for abs\n40 second plank\n\n4 of those")))
        ->toBe('Logged: '.SUPERSET_LINE);
});

it('shows the coach and the profile writer the same line', function (): void {
    Queue::fake();
    $user = linkedUser();
    logHirensSuperset();
    app(HandleInboundMessage::class)->handle(telegramText('superset'));
    $writer = fakeProfileBuilder();

    app(UpdateProfile::class)->handle($user);

    expect(CoachContext::for($user)->toArray()['recent_logs'][0]['exercises'])->toBe([SUPERSET_LINE])
        ->and($writer->calls[0]['logs'][0]['saved_as'])->toBe('Superset: Pushups 4x10, Lying Leg Raises 4x12, Plank 4x40s ('.SUPERSET_LINE.')');
});

it('says the same line when the superset is undone', function (): void {
    linkedUser();
    logHirensSuperset();
    $chat = app(HandleInboundMessage::class);
    $chat->handle(telegramText('superset'));

    expect($chat->handle(telegramText('/undo')))->toBe('Removed: '.SUPERSET_LINE);
});

it('keeps a weighted lift after a superset on its own line', function (): void {
    linkedUser();
    classifierSays(MessageKind::Workout);
    fakeSmartLogParser(new FakeSmartLogParser(workoutFacts([
        ['exercise_name' => 'Kettlebell Swing', 'sets' => 4, 'reps' => 8, 'weight_kg' => 24.0, 'group' => 'Circuit'],
        ['exercise_name' => 'Box Jump', 'sets' => 4, 'reps' => 10, 'bodyweight' => true, 'group' => 'Circuit'],
        ['exercise_name' => 'Bench Press', 'sets' => 3, 'reps' => 8, 'weight_kg' => 80.0],
    ])));

    expect(app(HandleInboundMessage::class)->handle(telegramText('4 rounds of 8 kb swings 24 and 10 box jumps, then bench 3x8 80')))
        ->toBe('Logged: Circuit x4: Kettlebell Swing 8 @ 24 kg, Box Jump 10, Bench Press 3x8 @ 80 kg');
});

it('counts no volume this week from bodyweight, timed or assisted sets', function (): void {
    $user = linkedUser();
    logHirensSuperset();
    app(HandleInboundMessage::class)->handle(telegramText('superset'));
    fakeSmartLogParser(new FakeSmartLogParser(workoutFacts([
        ['exercise_name' => 'Assisted Pull-up', 'sets' => 5, 'reps' => 5, 'weight_kg' => 45.0, 'assisted' => true],
    ])));
    app(HandleInboundMessage::class)->handle(telegramText('assisted pull up 5x5 45'));

    $facts = ProfileFacts::for($user)->toArray();

    expect($facts['this_week']['volume_kg'])->toBe(0.0)
        ->and($facts['records']['strength'])->toBe([]);
});
