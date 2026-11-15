<?php

declare(strict_types=1);

use App\Ai\Usage\UsageReport;
use App\Coach\AnswerDepth;
use App\Coach\CoachContext;
use App\Contracts\Ai\CoachAdvisor;
use App\Contracts\Ai\ProfileBuilder;
use App\Enums\TrainerPersona;
use App\Models\AiCallRecord;
use App\Models\User;
use App\Profile\ProfileFacts;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
 * The coach and the profile writer on Mistral's EU endpoint, through the real
 * adapters and the AI library's own Mistral driver, with the HTTP call faked.
 */

const MISTRAL_EU = 'https://api.eu.mistral.ai/v1';

beforeEach(function (): void {
    config([
        'ai.providers.mistral.key' => 'test-key',
        'ai.providers.mistral.url' => MISTRAL_EU,
        'ai.jobs.chat' => ['provider' => 'mistral', 'model' => 'mistral-large-4-0'],
        'ai.jobs.profile' => ['provider' => 'mistral', 'model' => 'mistral-large-4-0'],
    ]);
});

function mistralAnswer(string $content, int $in = 4000, int $out = 500): array
{
    return [
        'id' => 'cmpl-1',
        'object' => 'chat.completion',
        'model' => 'mistral-large-4-0',
        'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => $in, 'completion_tokens' => $out, 'total_tokens' => $in + $out],
    ];
}

it('asks the coach on the EU endpoint and prices the answer at the EU rate', function (): void {
    useRealAdapter(CoachAdvisor::class);
    Http::fake([MISTRAL_EU.'/chat/completions' => Http::response(mistralAnswer('Squat 3x5 at 62.5 kg.'))]);
    $user = User::factory()->create();

    $answer = app(CoachAdvisor::class)->answer(CoachContext::for($user), TrainerPersona::LtSurge, 'what should I train today?', AnswerDepth::Plan);

    expect($answer)->toBe('Squat 3x5 at 62.5 kg.')
        ->and(app(CoachAdvisor::class)->modelName())->toBe('mistral-large-4-0')
        ->and(AiCallRecord::sole()->only('provider', 'model'))->toBe(['provider' => 'mistral', 'model' => 'mistral-large-4-0'])
        ->and(app(UsageReport::class)->since(now()->subDay(), 'model')['total']['cost'])->toBe(0.004235);
    Http::assertSent(fn (Request $request): bool => $request->url() === MISTRAL_EU.'/chat/completions'
        && $request['model'] === 'mistral-large-4-0'
        && $request['max_tokens'] === AnswerDepth::Plan->maxTokens());
});

it('writes the profile on Mistral with a json schema answer', function (): void {
    useRealAdapter(ProfileBuilder::class);
    $notes = ['notes' => [['label' => 'gaps', 'text' => 'No runs logged yet.', 'cites' => ['facts']]]];
    Http::fake([MISTRAL_EU.'/chat/completions' => Http::response(mistralAnswer((string) json_encode($notes)))]);

    $written = app(ProfileBuilder::class)->build(ProfileFacts::for(User::factory()->create()), [], []);

    expect($written)->toHaveCount(1)
        ->and($written[0]['cites'])->toBe(['facts'])
        ->and($written[0]['text'])->toContain('No runs logged yet.');
    Http::assertSent(fn (Request $request): bool => $request['response_format']['type'] === 'json_schema');
});
