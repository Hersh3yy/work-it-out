<?php

declare(strict_types=1);

use App\Ai\Agents\ClassifierAgent;
use App\Ai\SdkMessageClassifier;
use App\Channels\HandleInboundMessage;
use App\Contracts\Ai\MessageClassifier;
use App\Enums\MessageKind;
use App\Interpretation\Interpreter;
use App\Interpretation\RuleClassifier;
use App\Interpretation\Rules\BareNumberIsNotBodyWeight;
use App\Interpretation\Rules\RepsTimesSetsHasTwoReadings;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Ai\Ai;
use Tests\Fakes\FakeMessageClassifier;
use Tests\Fakes\FakeSmartLogParser;

function classifierSays(MessageKind $kind): FakeMessageClassifier
{
    $fake = new FakeMessageClassifier($kind);
    app()->instance(MessageClassifier::class, $fake);

    return $fake;
}

it('sorts the obvious kinds with plain rules', function (string $text, ?MessageKind $kind): void {
    expect((new RuleClassifier)->classify($text)?->kind)->toBe($kind);
})->with([
    'command' => ['/undo', MessageKind::Command],
    'weight with unit' => ['104.5kg', MessageKind::BodyWeight],
    'weight with words' => ['weighed 103,2 kg', MessageKind::BodyWeight],
    'bare numbers' => ["90\n90\n95", null],
    'nonsense' => ['rainbow butterfly', null],
]);

it('replies politely to nonsense and saves nothing', function (): void {
    linkedUser();
    $parser = fakeSmartLogParser();
    classifierSays(MessageKind::Unknown);

    expect(app(HandleInboundMessage::class)->handle(telegramText('rainbow butterfly')))->toBe(Interpreter::NOT_UNDERSTOOD)
        ->and($parser->calls)->toBe([]);
    $this->assertDatabaseCount('activity_logs', 0);
});

it('answers a coach question honestly until coach chat exists', function (): void {
    linkedUser();
    classifierSays(MessageKind::CoachQuestion);

    expect(app(HandleInboundMessage::class)->handle(telegramText('what should I train today?')))->toBe(Interpreter::COACH_LATER);
    $this->assertDatabaseCount('activity_logs', 0);
});

it('logs a weight with a unit without any AI call', function (): void {
    $user = linkedUser();
    $parser = fakeSmartLogParser();
    $classifier = classifierSays(MessageKind::Unknown);

    expect(app(HandleInboundMessage::class)->handle(telegramText('104.5kg')))->toBe('Logged weight: 104.5 kg')
        ->and($parser->calls)->toBe([])
        ->and($classifier->modelCalls)->toBe([])
        ->and((float) $user->refresh()->current_weight_kg)->toBe(104.5);
});

it('never stores bare numbers as a body weight', function (): void {
    $user = linkedUser();
    fakeSmartLogParser(new FakeSmartLogParser(['log_type' => 'biometrics', 'summary' => 'Weight 90 kg', 'weight_kg_stat' => 90.0]));

    $reply = app(HandleInboundMessage::class)->handle(telegramText("90\n90\n95\n85\n85"));

    expect($reply)->toStartWith('Is 90 your body weight, or the weights of your sets?')
        ->and($user->refresh()->current_weight_kg)->toBeNull();
    $this->assertDatabaseCount('body_weight_logs', 0);
    $this->assertDatabaseCount('activity_logs', 0);
});

it('fills sets and reps from one answer to the two-readings question', function (): void {
    linkedUser();
    fakeSmartLogParser(new FakeSmartLogParser([
        'log_type' => 'workout',
        'summary' => 'Deadlift 3x5 @ 90 kg',
        'exercises' => [['exercise_name' => 'Deadlift', 'sets' => 3, 'reps' => 5, 'weight_kg' => 90]],
    ]));
    $chat = app(HandleInboundMessage::class);

    expect($chat->handle(telegramText('I did deadlift: 90 x3 x 5')))
        ->toBe("Logged: Deadlift @ 90 kg\nWas that 3 sets of 5 reps, or 5 sets of 3 reps? Reply with the number of sets.")
        ->and($chat->handle(telegramText('5')))->toBe('Updated: Deadlift 5x3 @ 90 kg');
});

it('leaves "3x5" alone when both counts are the same', function (): void {
    $parsed = ['exercises' => [['exercise_name' => 'Squat', 'sets' => 5, 'reps' => 5]], 'questions' => []];

    expect((new RepsTimesSetsHasTwoReadings)->apply('squat 100 x5 x5', $parsed)->parsed)->toBe($parsed);
});

it('keeps a weight the message names as a weight', function (): void {
    $parsed = ['log_type' => 'biometrics', 'weight_kg_stat' => 104.5];

    expect((new BareNumberIsNotBodyWeight)->apply('I weigh 104.5 today', $parsed)->stopReply)->toBeNull();
});

it('returns the help text to the app door as a 422', function (): void {
    $user = User::factory()->create();
    classifierSays(MessageKind::Unknown);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/log', ['message' => 'rainbow butterfly'])
        ->assertUnprocessable()
        ->assertJsonPath('kind', 'unknown')
        ->assertJsonPath('message', Interpreter::NOT_UNDERSTOOD);
});

it('classifies through the real adapter: rules first, then the model', function (): void {
    Ai::fakeAgent(ClassifierAgent::class, [['kind' => 'goal_or_info', 'confidence' => 0.8]]);
    $classifier = app(SdkMessageClassifier::class);

    expect($classifier->classify('/help')->decidedBy)->toBe('rules')
        ->and($classifier->classify('I want to squat 100 by December')->toArray())
        ->toBe(['kind' => 'goal_or_info', 'confidence' => 0.8, 'decided_by' => 'model']);
});

it('prints what the interpreter made of a message and saves nothing', function (): void {
    fakeSmartLogParser(FakeSmartLogParser::workout());
    User::factory()->create();

    expect(Artisan::call('log:parse', ['text' => 'benched 100 3x5']))->toBe(0)
        ->and(Artisan::output())->toContain('"kind": "workout"')->toContain('"would_record": true');

    $this->artisan('log:parse "104.5kg" --rules-only')
        ->expectsOutputToContain('"kind": "body_weight"')
        ->assertSuccessful();

    $this->assertDatabaseCount('activity_logs', 0);
});
