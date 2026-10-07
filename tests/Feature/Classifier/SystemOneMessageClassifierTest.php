<?php

declare(strict_types=1);

use App\Ai\SystemOne\SystemOneClient;
use App\Ai\SystemOneMessageClassifier;
use App\Contracts\Ai\MessageClassifier;
use App\Enums\MessageKind;
use App\Interpretation\RuleClassifier;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\FakeMessageClassifier;

function systemOne(?MessageClassifier $fallback = null, float $min = 0.6): SystemOneMessageClassifier
{
    return new SystemOneMessageClassifier(
        new RuleClassifier,
        new SystemOneClient('laya', 'http://laya.test', '/v1/systemone'),
        $min,
        $fallback,
    );
}

function layaSays(string $choice, float $p): void
{
    Http::fake(['*' => Http::response(['answers' => ['kind' => [
        'choice' => $choice, 'answer_confidence' => $p, 'probabilities' => [$choice => $p],
    ]]])]);
}

it('lets plain rules answer without calling the model', function (): void {
    Http::fake();

    $classification = systemOne()->classify('104.5kg');

    expect($classification->kind)->toBe(MessageKind::BodyWeight)->and($classification->decidedBy)->toBe('rules');
    Http::assertNothingSent();
});

it('returns the model choice when it is sure enough', function (): void {
    layaSays('coach_question', 0.83);

    $classification = systemOne()->classify('what should I train today?');

    expect($classification->kind)->toBe(MessageKind::CoachQuestion)
        ->and($classification->confidence)->toBe(0.83)
        ->and($classification->decidedBy)->toBe('laya');
});

it('falls back to the llm classifier when the model is unsure', function (): void {
    layaSays('unknown', 0.31);
    $fallback = new FakeMessageClassifier(MessageKind::Workout);

    $classification = systemOne($fallback)->classify('My squat just now was 90 90 95 85 85');

    expect($classification->kind)->toBe(MessageKind::Workout)
        ->and($fallback->modelCalls)->toBe(['My squat just now was 90 90 95 85 85']);
});

it('keeps an unsure answer when there is no fallback', function (): void {
    layaSays('unknown', 0.31);

    expect(systemOne(null)->classify('hmm')->kind)->toBe(MessageKind::Unknown);
});

it('is the bound classifier when the driver is laya', function (): void {
    config(['ai.classifier.driver' => 'laya']);
    $this->app->forgetInstance(MessageClassifier::class);
    $this->app->offsetUnset(MessageClassifier::class);
    (new AppServiceProvider($this->app))->register();

    expect(app(MessageClassifier::class))->toBeInstanceOf(SystemOneMessageClassifier::class);
});
