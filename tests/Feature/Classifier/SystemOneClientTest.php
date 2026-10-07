<?php

declare(strict_types=1);

use App\Ai\SystemOne\SystemOneClient;
use App\Enums\MessageKind;
use App\Exceptions\AiUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function layaAnswer(string $choice = 'workout', array $extra = []): array
{
    return ['answers' => ['kind' => array_merge([
        'type' => 'choice',
        'choice' => $choice,
        'probabilities' => ['workout' => 0.46, 'unknown' => 0.26, 'coach_question' => 0.11, 'body_weight' => 0.09, 'goal_or_info' => 0.08],
        'confidence' => 0.14,
        'answer_confidence' => 0.46,
    ], $extra)], 'usage' => ['input_tokens' => 137, 'output_tokens' => 0]];
}

it('sends one typed choice question and reads the answer', function (): void {
    Http::fake(['laya.test/*' => Http::response(layaAnswer())]);

    $answer = (new SystemOneClient('laya', 'http://laya.test', '/v1/systemone', model: 'laya-multilingual'))
        ->choice('squat 90 90 95', 'What kind?', MessageKind::criteria());

    expect($answer->choice)->toBe('workout')
        ->and($answer->confidence)->toBe(0.46)
        ->and(array_key_first($answer->probabilities))->toBe('workout');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://laya.test/v1/systemone'
        && $request['state'] === ['message' => 'squat 90 90 95']
        && $request['questions']['kind']['type'] === 'choice'
        && array_keys($request['questions']['kind']['criteria']) === array_keys(MessageKind::criteria())
        && $request['model'] === 'laya-multilingual'
        && ! $request->hasHeader('Authorization'));
});

it('uses answer_confidence, not the act signal, as the confidence', function (): void {
    Http::fake(['*' => Http::response(layaAnswer(extra: ['answer_confidence' => 0.91, 'confidence' => 0.05]))]);

    $answer = (new SystemOneClient('laya', 'http://laya.test', '/v1/systemone'))->choice('x', 'q', MessageKind::criteria());

    expect($answer->confidence)->toBe(0.91);
});

it('reads the TypeSafe shape and sends the key as a bearer token', function (): void {
    Http::fake(['*' => Http::response(['choices' => ['kind' => ['choice' => 'coach_question', 'confidence' => 0.88, 'probabilities' => ['coach_question' => 0.88]]]])]);

    $answer = (new SystemOneClient('jev', 'https://api.typesafe.test', '/v1/systemone', apiKey: 'secret', model: 'jev'))
        ->choice('what should I train?', 'q', MessageKind::criteria());

    expect($answer->choice)->toBe('coach_question')->and($answer->confidence)->toBe(0.88);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer secret'));
});

it('turns an unreachable server, an HTTP error or a junk answer into AiUnavailable', function (callable $fake): void {
    Http::fake(['*' => $fake]);

    (new SystemOneClient('laya', 'http://laya.test', '/v1/systemone'))->choice('x', 'q', MessageKind::criteria());
})->with([
    'unreachable' => [fn () => fn () => throw new ConnectionException('down')],
    'http 500' => [fn () => Http::response('boom', 500)],
    'choice not offered' => [fn () => Http::response(layaAnswer('pizza'))],
])->throws(AiUnavailable::class);
