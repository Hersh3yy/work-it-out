<?php

declare(strict_types=1);

use App\Contracts\Ai\CoachAdvisor;
use App\Contracts\Ai\LimitationParser;
use App\Contracts\Ai\MessageClassifier;
use App\Contracts\Ai\NutritionParser;
use App\Contracts\Ai\PlanGenerator;
use App\Contracts\Ai\ProfileBuilder;
use App\Contracts\Ai\SmartLogParser;
use App\Contracts\Ai\TrainerChat;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Exceptions\FailoverableException;
use Tests\Fakes\FakeMessageClassifier;

/*
 * The suite must never reach a model, a provider or Telegram. These tests
 * fail if one of the guards in TestCase or phpunit.xml goes missing.
 */

it('binds every AI port to a fake', function (string $port): void {
    expect(app($port)::class)->toStartWith('Tests\\Fakes\\');
})->with([
    MessageClassifier::class,
    SmartLogParser::class,
    LimitationParser::class,
    CoachAdvisor::class,
    ProfileBuilder::class,
    TrainerChat::class,
    PlanGenerator::class,
    NutritionParser::class,
]);

it('gives the suite no real key or bot token', function (string $key): void {
    expect(config($key))->toBeEmpty();
})->with([
    'ai.providers.gemini.key',
    'ai.providers.anthropic.key',
    'ai.providers.deepseek.key',
    'ai.providers.openrouter.key',
    'ai.classifier.jev.key',
    'ai.classifier.laya.key',
    'ai.fallbacks',
    'services.telegram.bot_token',
]);

it('refuses any web request the test did not fake', function (): void {
    Http::get('https://generativelanguage.googleapis.com/v1beta/models');
})->throws(RuntimeException::class, 'Attempted request to');

it('does not pad failed logins', function (): void {
    expect(config('auth.timebox_duration'))->toBe(0);
});

it('fails a fake like a real outage, or like a bug', function (): void {
    $outage = rescue(fn () => (new FakeMessageClassifier)->failing()->classify('rainbow butterfly'), fn (Throwable $e) => $e, report: false);
    $bug = rescue(fn () => (new FakeMessageClassifier)->broken()->classify('rainbow butterfly'), fn (Throwable $e) => $e, report: false);

    expect($outage)->toBeInstanceOf(FailoverableException::class)
        ->and($bug)->toBeInstanceOf(RuntimeException::class)
        ->and($bug)->not->toBeInstanceOf(FailoverableException::class);
});
