<?php

declare(strict_types=1);

use App\Ai\IntentClassifier;
use App\Enums\Intent;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function layaIntent(string $choice, float $p): void
{
    Http::fake(['*' => Http::response(['answers' => ['kind' => [
        'choice' => $choice,
        'answer_confidence' => $p,
        'probabilities' => [$choice => $p, 'nonsense' => round(1 - $p, 3)],
    ]]])]);
}

it('answers with the intent, how sure, and every option', function (): void {
    layaIntent('feeling', 0.76);

    $this->postJson('/api/lab/intent', ['text' => 'so tired, no energy at all'])
        ->assertOk()
        ->assertJsonPath('intent', 'feeling')
        ->assertJsonPath('confidence', 0.76)
        ->assertJsonPath('driver', 'laya')
        ->assertJsonStructure(['probabilities', 'ms']);

    Http::assertSent(fn (Request $request): bool => array_keys($request['questions']['kind']['criteria']) === Intent::values()
        && $request['model'] === 'laya-typed-decisions');
});

it('needs no login and lists the options it chooses from', function (): void {
    $this->getJson('/api/lab/intent')
        ->assertOk()
        ->assertJsonPath('options.workout_log', Intent::criteria()['workout_log']);
});

it('says 503 with a reason when the model is not running', function (): void {
    Http::fake(['*' => fn () => throw new ConnectionException('down')]);

    $this->postJson('/api/lab/intent', ['text' => 'bench 3x8 80'])
        ->assertStatus(503)
        ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'cannot reach'));
});

it('scores the English eval set', function (): void {
    layaIntent('workout_log', 0.6);

    $this->postJson('/api/lab/intent/eval')
        ->assertOk()
        ->assertJsonPath('total', count(IntentClassifier::labelled(base_path('tests/Evals/intent.txt'))))
        ->assertJsonPath('correct', 12);
});

it('allows a browser on another port (CORS)', function (): void {
    layaIntent('question', 0.5);

    $this->postJson('/api/lab/intent', ['text' => 'what now?'], ['Origin' => 'http://localhost:4321'])
        ->assertOk()
        ->assertHeader('Access-Control-Allow-Origin');
});

it('does not exist in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    Http::fake();

    $this->postJson('/api/lab/intent', ['text' => 'x'])->assertNotFound();
    Http::assertNothingSent();
});

it('prints the answer and bars from artisan', function (): void {
    layaIntent('feeling', 0.7);

    $this->artisan('intent:try', ['text' => 'my knee hurts'])
        ->expectsOutputToContain('feeling')
        ->assertExitCode(0);
});

it('accepts other option wording to try', function (): void {
    layaIntent('gym', 0.8);

    $this->postJson('/api/lab/intent', ['text' => 'bench 3x8', 'criteria' => ['gym' => 'a gym session', 'other' => 'anything else']])
        ->assertOk()
        ->assertJsonPath('intent', 'gym');

    Http::assertSent(fn (Request $request): bool => array_keys($request['questions']['kind']['criteria']) === ['gym', 'other']);
});

it('explains an answer by leaving each word out', function (): void {
    Http::fake(['*' => function (Request $request) {
        $knee = str_contains($request['state']['message'], 'knee');

        return Http::response(['answers' => ['kind' => [
            'choice' => $knee ? 'feeling' : 'nonsense',
            'answer_confidence' => $knee ? 0.7 : 0.5,
            'probabilities' => ['feeling' => $knee ? 0.7 : 0.2, 'nonsense' => $knee ? 0.1 : 0.5],
        ]]]);
    }]);

    $response = $this->postJson('/api/lab/intent/explain', ['text' => 'my knee hurts'])->assertOk();

    expect($response->json('base.intent'))->toBe('feeling')
        ->and($response->json('words'))->toHaveCount(3)
        ->and($response->json('words.1.word'))->toBe('knee')
        ->and($response->json('words.1.influence'))->toBe(0.5)
        ->and((float) $response->json('words.0.influence'))->toBe(0.0);
});

it('refuses option lists that are too small', function (): void {
    $this->postJson('/api/lab/intent', ['text' => 'x', 'criteria' => ['only' => 'one']])->assertUnprocessable();
});
