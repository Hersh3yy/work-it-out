<?php

declare(strict_types=1);

use App\Channels\HandleInboundMessage;
use App\Enums\BodyArea;
use App\Enums\MessageKind;
use App\Interpretation\Interpreter;
use Tests\Fakes\FakeLimitationParser;

/*
 * Replies about how the back is going ("98%", "don't feel it at all", a
 * workout that mentions it) become updates to the limitation on file, so
 * the coach stops asking (PROJECT.md diary 2026-11-12).
 */

function backSays(string $note): FakeLimitationParser
{
    return new FakeLimitationParser([
        'is_limitation' => true, 'body_area' => 'lower_back', 'candidates' => [], 'severity' => null,
        'duration_days' => null, 'started_days_ago' => null, 'note' => $note,
    ]);
}

it('reads a short reply after the coach asked as an update to the back', function (): void {
    $user = linkedUser();
    $back = $user->limitations()->create(['body_area' => BodyArea::LowerBack, 'note' => 'strained deadlifting', 'started_on' => today()->subDays(6)]);
    $user->coachTurns()->create(['question' => 'What should I do tomorrow?', 'answer' => "Rest or walk light.\n\nHow does the lower back feel today?"]);
    classifierSays(MessageKind::Unknown);
    $reader = fakeLimitationParser(backSays('98%'));

    $reply = app(HandleInboundMessage::class)->handle(telegramText('98%'));

    expect($reply)->toBe('Noted for your lower back, day 7. Say /better when it has healed.')
        ->and($back->updates()->count())->toBe(1)
        ->and($back->updates()->first()->note)->toBe('98%')
        ->and($reader->contexts[0]['active'][0]['area'])->toBe('lower back')
        ->and($reader->contexts[0]['coach_asked'])->toBe('How does the lower back feel today?');
});

it('does not consult the limitation reader for an unplaceable message when nothing is active', function (): void {
    linkedUser();
    classifierSays(MessageKind::Unknown);
    $reader = fakeLimitationParser(backSays('98%'));

    expect(app(HandleInboundMessage::class)->handle(telegramText('98%')))->toBe(Interpreter::NOT_UNDERSTOOD)
        ->and($reader->calls)->toBe([]);
});

it('records the back update inside a workout message and keeps the workout', function (): void {
    $user = linkedUser();
    $back = $user->limitations()->create(['body_area' => BodyArea::LowerBack, 'note' => 'strained deadlifting', 'started_on' => today()->subDays(13)]);
    classifierSays(MessageKind::Workout);
    fakeSmartLogParser();
    fakeLimitationParser(backSays('rehab, not ready for squats yet'));

    $reply = app(HandleInboundMessage::class)->handle(telegramText('overhead press 6x8 40, did some rehab for the back, not ready for squats yet'));

    expect($reply)->toStartWith('Logged: Bench Press')
        ->toContain('Noted for your lower back too, day 14.')
        ->not->toContain('OK with the')
        ->and($user->activityLogs()->count())->toBe(1)
        ->and($back->updates()->first()->activity_log_id)->toBe($user->activityLogs()->first()->id);
});

it('leaves a workout alone when it does not mention the limitation', function (): void {
    $user = linkedUser();
    $user->limitations()->create(['body_area' => BodyArea::LowerBack, 'note' => 'strained deadlifting', 'started_on' => today()->subDays(3)]);
    classifierSays(MessageKind::Workout);
    fakeSmartLogParser();
    $reader = fakeLimitationParser(backSays('x'));

    app(HandleInboundMessage::class)->handle(telegramText('bench 3x8 80'));

    expect($reader->calls)->toBe([])
        ->and($user->limitations()->first()->updates()->count())->toBe(0);
});
