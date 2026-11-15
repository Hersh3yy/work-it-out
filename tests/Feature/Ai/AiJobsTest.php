<?php

declare(strict_types=1);

use App\Ai\Agents\ClassifierAgent;
use App\Ai\Agents\CoachAgent;
use App\Ai\Agents\LimitationAgent;
use App\Ai\Agents\ProfileAgent;
use App\Ai\AiJob;
use App\Ai\ModelChain;
use App\Ai\SdkCoachAdvisor;
use App\Ai\SdkLimitationParser;
use App\Ai\SdkMessageClassifier;
use App\Ai\SdkProfileBuilder;
use App\Coach\AnswerDepth;
use App\Coach\CoachContext;
use App\Enums\TrainerPersona;
use App\Models\User;
use App\Profile\ProfileFacts;
use Laravel\Ai\Ai;
use Laravel\Ai\Prompts\AgentPrompt;

/*
 * One job per use (audit 2026-11-14 item 7): the classifier and the injury
 * reader no longer ride the log job, and the coach has its own name.
 */

beforeEach(function (): void {
    config(['ai.jobs.log' => ['provider' => 'gemini', 'model' => 'log-model', 'timeout' => 30]]);
});

function onItsOwnModel(AiJob $job): void
{
    config(["ai.jobs.{$job->value}" => ['provider' => 'gemini', 'model' => "{$job->value}-model", 'timeout' => 7]]);
}

function askedOn(string $agent, AiJob $job): void
{
    $agent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->model === "{$job->value}-model" && $prompt->timeout === 7);
}

it('asks the classifier on the classify job', function (): void {
    onItsOwnModel(AiJob::Classify);
    Ai::fakeAgent(ClassifierAgent::class, [['kind' => 'workout', 'confidence' => 0.9]]);

    app(SdkMessageClassifier::class)->classify('benched 100 3x5');

    askedOn(ClassifierAgent::class, AiJob::Classify);
});

it('asks the injury reader on the limitation job', function (): void {
    onItsOwnModel(AiJob::Limitation);
    Ai::fakeAgent(LimitationAgent::class, [['is_limitation' => false, 'body_area' => null, 'candidates' => [], 'severity' => null, 'duration_days' => null, 'started_days_ago' => null, 'note' => '']]);

    (new SdkLimitationParser)->parse('my knee hurts');

    askedOn(LimitationAgent::class, AiJob::Limitation);
});

it('asks the coach on the coach job', function (): void {
    onItsOwnModel(AiJob::Coach);
    Ai::fakeAgent(CoachAgent::class, ['Squat 3x5 at 60 kg.']);

    (new SdkCoachAdvisor)->answer(CoachContext::for(User::factory()->create()), TrainerPersona::LtSurge, 'what now?', AnswerDepth::Quick);

    askedOn(CoachAgent::class, AiJob::Coach);
});

it('asks the profile writer on the profile job', function (): void {
    onItsOwnModel(AiJob::Profile);
    Ai::fakeAgent(ProfileAgent::class, [['notes' => [['label' => 'gaps', 'text' => 'No runs yet.', 'cites' => ['facts']]]]]);

    (new SdkProfileBuilder)->build(ProfileFacts::for(User::factory()->create()), [], []);

    askedOn(ProfileAgent::class, AiJob::Profile);
});

it('falls back to the log job when a job has no model of its own', function (AiJob $job): void {
    config(["ai.jobs.{$job->value}.provider" => null, "ai.jobs.{$job->value}.model" => null]);

    expect(ModelChain::for($job)[0])->toBe(['provider' => 'gemini', 'model' => 'log-model']);
})->with(array_filter(AiJob::cases(), static fn (AiJob $job): bool => $job !== AiJob::Log));
