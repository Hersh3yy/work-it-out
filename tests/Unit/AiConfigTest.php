<?php

declare(strict_types=1);

test('the configured text model reaches every provider we use', function (): void {
    expect(config('ai.model'))->toBeNull()
        ->and(config('ai.providers.openai.models.text.default'))->toBe(env('AI_TEXT_MODEL'))
        ->and(config('ai.providers.gemini.models.text.default'))->toBe(env('AI_TEXT_MODEL', 'gemini-3.5-flash'))
        ->and(config('ai.conversations.generate_title'))->toBeFalse();
});

it('has one provider and model slot per AI job, empty by default', function (): void {
    foreach (['log', 'plan', 'chat'] as $job) {
        expect(config("ai.jobs.{$job}"))->toHaveKeys(['provider', 'model']);
    }
});
