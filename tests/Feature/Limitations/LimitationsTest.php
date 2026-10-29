<?php

declare(strict_types=1);

use App\Actions\SmartLog\RevertSmartLog;
use App\Channels\HandleInboundMessage;
use App\Enums\BodyArea;
use App\Enums\MessageKind;
use App\Events\LogRecorded;
use App\Limitations\ExerciseLoads;
use App\Limitations\RedFlags;
use App\Models\User;
use App\Profile\ProfileFacts;
use Illuminate\Support\Facades\Event;
use Tests\Fakes\FakeLimitationParser;
use Tests\Fakes\FakeSmartLogParser;

/*
 * Hiren's example: "i hurt my back and need to go slow for 2 weeks".
 */

it('notes an injury, asks where exactly, and stores the answer', function (): void {
    $user = linkedUser();
    classifierSays(MessageKind::GoalOrInfo);
    $logParser = fakeSmartLogParser();
    fakeLimitationParser(FakeLimitationParser::backUnclear());
    $chat = app(HandleInboundMessage::class);

    $reply = $chat->handle(telegramText('i hurt my back and need to go slow for 2 weeks'));

    expect($reply)->toBe("Noted, I'll keep that in mind. Where exactly? 1) lower back 2) upper back 3) other")
        ->and($logParser->calls)->toBe([]);
    $limitation = $user->limitations()->first();
    expect($limitation->body_area)->toBeNull()
        ->and($limitation->note)->toBe('hurt back, go slow for 2 weeks')
        ->and($limitation->expected_until->toDateString())->toBe(today()->addDays(14)->toDateString());
    $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'log_type' => 'general', 'raw_message' => 'i hurt my back and need to go slow for 2 weeks']);

    expect($chat->handle(telegramText('1')))->toStartWith('Got it: lower back: hurt back, go slow for 2 weeks, until '.today()->addDays(14)->format('j M'));
    $this->assertDatabaseHas('limitations', ['user_id' => $user->id, 'body_area' => 'lower_back']);
});

it('accepts the area by name and refreshes the written profile', function (): void {
    Event::fake([LogRecorded::class]);
    linkedUser();
    classifierSays(MessageKind::GoalOrInfo);
    fakeLimitationParser(FakeLimitationParser::backUnclear());
    $chat = app(HandleInboundMessage::class);

    $chat->handle(telegramText('hurt my back'));
    $chat->handle(telegramText('upper back'));

    $this->assertDatabaseHas('limitations', ['body_area' => 'upper_back']);
    Event::assertDispatchedTimes(LogRecorded::class, 2);
});

it('notes a clear area straight away', function (): void {
    linkedUser();
    classifierSays(MessageKind::GoalOrInfo);
    fakeLimitationParser(FakeLimitationParser::knee());

    expect(app(HandleInboundMessage::class)->handle(telegramText('my left knee hurts a bit when I squat')))
        ->toStartWith("Noted: knee: left knee hurts when squatting. I'll check with you");
    $this->assertDatabaseHas('limitations', ['body_area' => 'knee', 'severity' => 'mild', 'expected_until' => null]);
});

it('adds a check line when a logged exercise loads the injured area', function (): void {
    $user = linkedUser();
    $user->limitations()->create(['body_area' => BodyArea::LowerBack, 'note' => 'go slow', 'started_on' => today()]);
    fakeSmartLogParser(new FakeSmartLogParser([
        'log_type' => 'workout', 'summary' => 'Deadlift 3x5 @ 90 kg',
        'exercises' => [['exercise_name' => 'Deadlift', 'sets' => 3, 'reps' => 5, 'weight_kg' => 90.0]],
    ]));

    expect(app(HandleInboundMessage::class)->handle(telegramText('deadlift 3x5 90')))
        ->toContain('Lower back OK with the Deadlift? Say /better when it has healed.');
});

it('adds no check for an exercise that does not load the area', function (): void {
    $user = linkedUser();
    $user->limitations()->create(['body_area' => BodyArea::Knee, 'note' => 'sore', 'started_on' => today()]);
    fakeSmartLogParser(FakeSmartLogParser::workout());

    expect(app(HandleInboundMessage::class)->handle(telegramText('bench 3x5 100')))->not->toContain('OK with');
});

it('lets the user, and only the user, mark it healed', function (): void {
    $user = linkedUser();
    $user->limitations()->create(['body_area' => BodyArea::LowerBack, 'note' => 'go slow', 'started_on' => today()]);
    $chat = app(HandleInboundMessage::class);

    expect($chat->handle(telegramText('/better')))->toBe('Glad to hear it. Lower back marked as healed; suggestions go back to normal.')
        ->and($user->limitations()->active()->count())->toBe(0)
        ->and($chat->handle(telegramText('/better')))->toBe('Nothing to resolve: no active injury or limitation on file.');
});

it('undoing the message removes the limitation with it', function (): void {
    $user = linkedUser();
    classifierSays(MessageKind::GoalOrInfo);
    fakeLimitationParser(FakeLimitationParser::knee());
    $chat = app(HandleInboundMessage::class);

    $chat->handle(telegramText('my knee hurts'));
    expect($chat->handle(telegramText('/undo')))->toStartWith('Removed: Limitation: knee');
    $this->assertDatabaseCount('limitations', 0);
});

it('answers red-flag words with see a doctor and saves nothing, before any AI', function (string $text): void {
    linkedUser();
    $classifier = classifierSays(MessageKind::GoalOrInfo);
    $parser = fakeLimitationParser(FakeLimitationParser::knee());

    expect(app(HandleInboundMessage::class)->handle(telegramText($text)))->toBe(RedFlags::REPLY)
        ->and($classifier->modelCalls)->toBe([])
        ->and($parser->calls)->toBe([]);
    $this->assertDatabaseCount('activity_logs', 0);
})->with([
    'my leg went numb after deadlifts',
    'pain shooting down my leg since yesterday',
    'chest pain during the run',
]);

it('still logs a goal as a note when it is not a limitation', function (): void {
    linkedUser();
    classifierSays(MessageKind::GoalOrInfo);
    fakeLimitationParser();
    fakeSmartLogParser(new FakeSmartLogParser(['log_type' => 'general', 'summary' => 'Wants to squat 100 by December']));

    expect(app(HandleInboundMessage::class)->handle(telegramText('I want to squat 100 by December')))->toStartWith('Noted: Wants to squat 100');
    $this->assertDatabaseCount('limitations', 0);
});

it('shows active limitations in /me and in the profile facts', function (): void {
    $user = linkedUser();
    $user->limitations()->create(['body_area' => BodyArea::LowerBack, 'note' => 'go slow for 2 weeks', 'started_on' => today(), 'expected_until' => today()->addDays(14)]);

    expect(app(HandleInboundMessage::class)->handle(telegramText('/me')))->toContain('Limitation: lower back, go slow for 2 weeks, until '.today()->addDays(14)->format('j M'))
        ->and(ProfileFacts::for($user)->toArray()['limitations'][0]['area'])->toBe('lower back');
});

it('maps exercises to the areas they load', function (): void {
    expect(ExerciseLoads::loads('Deadlift', BodyArea::LowerBack))->toBeTrue()
        ->and(ExerciseLoads::loads('Bench Press', BodyArea::LowerBack))->toBeFalse()
        ->and(ExerciseLoads::loads('Run', BodyArea::Knee))->toBeTrue()
        ->and(ExerciseLoads::areasFor('Walking lunges'))->toContain(BodyArea::Knee);
});

it('serves and resolves limitations over the API', function (): void {
    $user = User::factory()->create();
    $limitation = $user->limitations()->create(['body_area' => BodyArea::Knee, 'note' => 'sore', 'started_on' => today()]);

    $this->actingAs($user, 'sanctum')->getJson('/api/me/overview')
        ->assertOk()
        ->assertJsonPath('limitations.0.area_label', 'knee')
        ->assertJsonPath('limitations.0.active', true);

    $this->actingAs($user, 'sanctum')->postJson("/api/limitations/{$limitation->id}/resolve")->assertNoContent();
    $this->actingAs(User::factory()->create(), 'sanctum')->postJson("/api/limitations/{$limitation->id}/resolve")->assertNotFound();
    expect($limitation->fresh()->resolved_at)->not->toBeNull();
});

it('reverting a limitation log through the action removes the row too', function (): void {
    $user = User::factory()->create();
    $log = $user->activityLogs()->create(['source' => 'http', 'log_type' => 'general', 'raw_message' => 'knee', 'summary' => 'Limitation: knee', 'logged_on' => today()]);
    $user->limitations()->create(['activity_log_id' => $log->id, 'body_area' => BodyArea::Knee, 'note' => 'sore', 'started_on' => today()]);

    app(RevertSmartLog::class)->handle($log);

    $this->assertDatabaseCount('limitations', 0);
});
