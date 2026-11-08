<?php

declare(strict_types=1);

use App\Ai\ModelChain;
use App\Ai\SdkProfileBuilder;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;

beforeEach(function (): void {
    config([
        'ai.jobs.log' => ['provider' => 'gemini', 'model' => 'gemini-3.5-flash-lite'],
        'ai.jobs.profile' => ['provider' => null, 'model' => 'gemini-3.5-flash'],
        'ai.jobs.chat' => ['provider' => null, 'model' => null],
        'ai.fallbacks' => [],
    ]);
});

it('orders a job\'s own model, then the log model, then the fallbacks, once each', function (): void {
    config(['ai.fallbacks' => ['gemini:gemini-3.5-flash', 'deepseek:deepseek-flash']]);

    expect(ModelChain::for('profile'))->toBe([
        ['provider' => 'gemini', 'model' => 'gemini-3.5-flash'],
        ['provider' => 'gemini', 'model' => 'gemini-3.5-flash-lite'],
        ['provider' => 'deepseek', 'model' => 'deepseek-flash'],
    ])->and(ModelChain::for('chat'))->toBe([
        ['provider' => 'gemini', 'model' => 'gemini-3.5-flash-lite'],
        ['provider' => 'gemini', 'model' => 'gemini-3.5-flash'],
        ['provider' => 'deepseek', 'model' => 'deepseek-flash'],
    ])->and((new SdkProfileBuilder)->modelName())->toBe('gemini-3.5-flash');
});

it('moves on when a model is overloaded or unreachable, and stops at the first answer', function (): void {
    config(['ai.fallbacks' => ['gemini:gemini-3.5-flash']]);
    $asked = [];

    $answer = ModelChain::ask('log', function (?string $provider, ?string $model) use (&$asked): string {
        $asked[] = $model;

        return match (count($asked)) {
            1 => throw new ProviderOverloadedException('503 high demand'),
            default => "answered by {$model}",
        };
    });

    expect($answer)->toBe('answered by gemini-3.5-flash')
        ->and($asked)->toBe(['gemini-3.5-flash-lite', 'gemini-3.5-flash']);
});

it('rethrows when every model fails', function (): void {
    ModelChain::ask('log', fn (): never => throw new ProviderConnectionException('timeout'));
})->throws(ProviderConnectionException::class);

it('does not swallow errors that are not about availability', function (): void {
    config(['ai.fallbacks' => ['gemini:gemini-3.5-flash']]);
    $asked = 0;

    $ask = function () use (&$asked): never {
        $asked++;
        throw new RuntimeException('bad payload');
    };

    expect(fn () => ModelChain::ask('log', $ask))->toThrow(RuntimeException::class)
        ->and($asked)->toBe(1);
});
