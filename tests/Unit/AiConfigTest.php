<?php

declare(strict_types=1);

use App\Ai\AiJob;

test('the configured text model reaches every provider we use', function (): void {
    expect(config('ai.model'))->toBeNull()
        ->and(config('ai.providers.openai.models.text.default'))->toBe(env('AI_TEXT_MODEL'))
        ->and(config('ai.providers.gemini.models.text.default'))->toBe(env('AI_TEXT_MODEL', 'gemini-3.5-flash'))
        ->and(config('ai.conversations.generate_title'))->toBeFalse();
});

it('has a provider, a model and a timeout for every AI job', function (AiJob $job): void {
    expect(config("ai.jobs.{$job->value}"))->toHaveKeys(['provider', 'model', 'timeout'])
        ->and($job->timeout())->toBeGreaterThan(0);
})->with(AiJob::cases());

it('prices the model every job runs on in production, at the EU rate', function (): void {
    expect(config('ai.prices.mistral-medium-3-5'))->toBe(['input' => 1.65, 'output' => 8.25]);
});
