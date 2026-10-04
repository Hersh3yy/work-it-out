<?php

declare(strict_types=1);

use App\Channels\HandleInboundMessage;
use Tests\Fakes\FakeSmartLogParser;

it('ignores a sender that is not linked and never calls the parser', function (): void {
    $parser = fakeSmartLogParser();

    expect(app(HandleInboundMessage::class)->handle(telegramText('bench 3x5 100', '999')))->toBeNull()
        ->and($parser->calls)->toBe([]);
    $this->assertDatabaseCount('activity_logs', 0);
});

it('logs a workout from a chat message and replies with a receipt', function (): void {
    $user = linkedUser();
    fakeSmartLogParser(FakeSmartLogParser::workout());

    $reply = app(HandleInboundMessage::class)->handle(telegramText('benched 100 3x5'));

    expect($reply)->toBe('Logged: Bench Press 3x5 @ 100 kg');
    $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'source' => 'telegram']);
    expect((float) $user->refresh()->weekly_adherence_rate)->toBe(25.0);
});

it('asks for a missing value and fills it in from a bare number without AI', function (): void {
    $user = linkedUser();
    $parser = fakeSmartLogParser(FakeSmartLogParser::missingWeight());
    $chat = app(HandleInboundMessage::class);

    expect($chat->handle(telegramText('incline db press 3x10')))
        ->toBe("Logged: Incline DB Press 3x10\nWhat weight for the incline press?")
        ->and($chat->handle(telegramText('30 kg')))->toBe('Updated: Incline DB Press 3x10 @ 30 kg')
        ->and($parser->calls)->toHaveCount(1)
        ->and($user->activityLogs()->first()->questions)->toBeNull();
});

it('leaves the value empty on skip', function (): void {
    linkedUser();
    fakeSmartLogParser(FakeSmartLogParser::missingWeight());
    $chat = app(HandleInboundMessage::class);

    $chat->handle(telegramText('incline db press 3x10'));

    expect($chat->handle(telegramText('skip')))->toBe('OK, left it empty.');
    $this->assertDatabaseHas('exercise_entries', ['exercise_name' => 'Incline DB Press', 'weight_kg' => null]);
});

it('undoes the last log', function (): void {
    $user = linkedUser();
    fakeSmartLogParser(FakeSmartLogParser::workout());
    $chat = app(HandleInboundMessage::class);

    $chat->handle(telegramText('benched 100 3x5'));

    expect($chat->handle(telegramText('/undo')))->toBe('Removed: Bench Press 3x5 @ 100 kg')
        ->and($user->workoutSessions()->count())->toBe(0)
        ->and((float) $user->refresh()->weekly_adherence_rate)->toBe(0.0)
        ->and($chat->handle(telegramText('/undo')))->toBe('Nothing to undo from the last 24 hours.');
});

it('replies and saves nothing when the AI is down', function (): void {
    linkedUser();
    fakeSmartLogParser(FakeSmartLogParser::workout()->failing());

    expect(app(HandleInboundMessage::class)->handle(telegramText('benched 100 3x5')))
        ->toStartWith("Couldn't reach the AI");
    $this->assertDatabaseCount('activity_logs', 0);
});

it('answers /start and /help', function (string $command): void {
    linkedUser();

    expect(app(HandleInboundMessage::class)->handle(telegramText($command)))->toBe(HandleInboundMessage::HELP);
})->with(['/start', '/help', '/help@FeetnessBot']);

it('describes an undone workout with the saved names, not the AI summary', function (): void {
    linkedUser();
    fakeSmartLogParser(new FakeSmartLogParser([
        'log_type' => 'workout',
        'summary' => 'bench press 3x8',
        'exercises' => [['exercise_name' => 'bench', 'sets' => 3, 'reps' => 8, 'weight_kg' => 80]],
    ]));
    $chat = app(HandleInboundMessage::class);

    $chat->handle(telegramText('bench 3x8 80'));

    expect($chat->handle(telegramText('/undo')))->toBe('Removed: Bench Press 3x8 @ 80 kg');
});
