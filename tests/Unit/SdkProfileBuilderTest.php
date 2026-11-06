<?php

declare(strict_types=1);

use App\Ai\SdkProfileBuilder;

it('tries the profile model first and falls back to the log model', function (): void {
    config(['ai.jobs.profile.model' => 'gemini-3.5-flash', 'ai.jobs.log.model' => 'gemini-3.5-flash-lite']);
    expect(SdkProfileBuilder::modelsToTry())->toBe(['gemini-3.5-flash', 'gemini-3.5-flash-lite'])
        ->and((new SdkProfileBuilder)->modelName())->toBe('gemini-3.5-flash');

    config(['ai.jobs.profile.model' => null]);
    expect(SdkProfileBuilder::modelsToTry())->toBe(['gemini-3.5-flash-lite']);

    config(['ai.jobs.profile.model' => 'gemini-3.5-flash-lite']);
    expect(SdkProfileBuilder::modelsToTry())->toBe(['gemini-3.5-flash-lite']);
});
