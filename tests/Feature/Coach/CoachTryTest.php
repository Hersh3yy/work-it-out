<?php

declare(strict_types=1);

use App\Actions\SmartLog\RecordSmartLog;
use App\Ai\Agents\CoachAgent;
use App\Models\ActivityLog;
use App\Models\AiCallRecord;
use App\Models\CoachTurn;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Ai;
use Laravel\Ai\Prompts\AgentPrompt;

/*
 * coach:try (roadmap n41): the same question on two models side by side, so a
 * model switch is judged on real answers. Nothing is saved.
 */

it('asks the same question on each model and shows both answers', function (): void {
    Ai::fakeAgent(CoachAgent::class, ['Squat 3x5 at 60 kg.', 'Rest today, walk 30 minutes.']);

    $this->artisan('coach:try', ['question' => 'what should I train today?', '--model' => ['gemini:gemini-3.5-flash-lite', 'mistral:mistral-large-4-0']])
        ->expectsOutputToContain('gemini:gemini-3.5-flash-lite')
        ->expectsOutputToContain('Squat 3x5 at 60 kg.')
        ->expectsOutputToContain('mistral:mistral-large-4-0')
        ->expectsOutputToContain('Rest today, walk 30 minutes.')
        ->assertSuccessful();

    CoachAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->model === 'gemini-3.5-flash-lite' && $prompt->prompt === 'what should I train today?');
    CoachAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->model === 'mistral-large-4-0');
});

it('saves nothing: no turn, no log, no usage row', function (): void {
    Ai::fakeAgent(CoachAgent::class, ['A.', 'B.']);
    $user = User::factory()->create();

    $this->artisan('coach:try', ['question' => 'what now?', '--model' => ['gemini:a', 'gemini:b'], '--user' => $user->email])->assertSuccessful();

    expect(CoachTurn::count())->toBe(0)
        ->and(ActivityLog::count())->toBe(0)
        ->and(AiCallRecord::count())->toBe(0);
});

it('gives the coach the person\'s own history with --user', function (): void {
    Queue::fake();
    Ai::fakeAgent(CoachAgent::class, ['A.']);
    $user = User::factory()->create();
    app(RecordSmartLog::class)->handle($user, 'bench 3x5 80', workoutFacts([lift('Bench Press', 80.0, 3, 5)]));

    $this->artisan('coach:try', ['question' => 'what now?', '--model' => ['gemini:a'], '--user' => $user->email])->assertSuccessful();

    CoachAgent::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->agent->instructions(), 'Bench Press'));
});

it('shows a failing model\'s error and still answers on the other', function (): void {
    Ai::fakeAgent(CoachAgent::class, [fn (string $prompt, $attachments, $provider, string $model): string => $model === 'nope' ? throw new RuntimeException('model not found') : 'B answers.']);

    $this->artisan('coach:try', ['question' => 'what now?', '--model' => ['mistral:nope', 'gemini:b']])
        ->expectsOutputToContain('model not found')
        ->expectsOutputToContain('B answers.')
        ->assertSuccessful();
});

it('needs at least one model', function (): void {
    $this->artisan('coach:try', ['question' => 'what now?'])->assertFailed();
});
