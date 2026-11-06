<?php

declare(strict_types=1);

use App\Ai\Agents\ClassifierAgent;
use App\Ai\Agents\CoachAgent;
use App\Ai\Usage\UsageReport;
use App\Channels\HandleInboundMessage;
use App\Enums\MessageKind;
use App\Exceptions\AiUnavailable;
use App\Listeners\RecordAiCall;
use App\Models\AiCallRecord;
use App\Models\User;
use App\Services\Ai\AiCall;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Events\AgentFailed;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;

function sdkPrompt(): AgentPrompt
{
    return new AgentPrompt(new ClassifierAgent, 'bench 3x8 80', [], Mockery::mock(TextProvider::class), 'gemini-3.5-flash-lite');
}

function sdkAnswer(int $in, int $out, string $model = 'gemini-3.5-flash-lite'): AgentResponse
{
    return new AgentResponse('inv-1', 'ok', new TextUsage($in, $out, reasoningTokens: 7), new Meta('gemini', $model));
}

it('listens to the SDK for every answered and failed call', function (): void {
    Event::fake();

    Event::assertListening(AgentPrompted::class, [RecordAiCall::class, 'handlePrompted']);
    Event::assertListening(AgentFailed::class, [RecordAiCall::class, 'handleFailed']);
});

it('records a call with the user and job that AiCall tagged', function (): void {
    $user = User::factory()->create();

    app(AiCall::class)->run($user, CoachAgent::class, function () {
        event(new AgentPrompted('inv-1', sdkPrompt(), sdkAnswer(1200, 300)));

        return 'ok';
    });

    $call = AiCallRecord::sole();
    expect($call->user_id)->toBe($user->id)
        ->and($call->agent)->toBe(CoachAgent::class)
        ->and($call->job())->toBe('CoachAgent')
        ->and($call->provider)->toBe('gemini')
        ->and($call->model)->toBe('gemini-3.5-flash-lite')
        ->and($call->input_tokens)->toBe(1200)
        ->and($call->output_tokens)->toBe(300)
        ->and($call->reasoning_tokens)->toBe(7)
        ->and($call->duration_ms)->not->toBeNull()
        ->and($call->failed)->toBeFalse();
});

it('records a failed call with the error name and no tokens', function (): void {
    $user = User::factory()->create();

    expect(fn () => app(AiCall::class)->run($user, CoachAgent::class, function (): never {
        event(new AgentFailed('inv-1', sdkPrompt(), new RuntimeException('boom')));

        throw new RuntimeException('boom');
    }))->toThrow(AiUnavailable::class);

    $call = AiCallRecord::sole();
    expect($call->failed)->toBeTrue()
        ->and($call->error)->toBe('RuntimeException')
        ->and($call->totalTokens())->toBe(0)
        ->and($call->user_id)->toBe($user->id);
});

it('reports calls, tokens and cost per job from the price table', function (): void {
    config(['ai.prices' => ['gemini-3.5-flash-lite' => ['input' => 0.10, 'output' => 0.40], 'mystery' => ['input' => null, 'output' => null]]]);
    $user = User::factory()->create(['email' => 'hiren@example.com']);
    AiCallRecord::create(['user_id' => $user->id, 'agent' => CoachAgent::class, 'model' => 'gemini-3.5-flash-lite', 'input_tokens' => 1_000_000, 'output_tokens' => 500_000, 'created_at' => now()]);
    AiCallRecord::create(['user_id' => $user->id, 'agent' => CoachAgent::class, 'model' => 'gemini-3.5-flash-lite', 'failed' => true, 'error' => 'ProviderOverloadedException', 'created_at' => now()]);
    AiCallRecord::create(['user_id' => $user->id, 'agent' => ClassifierAgent::class, 'model' => 'mystery', 'input_tokens' => 10, 'output_tokens' => 5, 'created_at' => now()]);
    AiCallRecord::create(['user_id' => $user->id, 'agent' => ClassifierAgent::class, 'model' => 'gemini-3.5-flash-lite', 'input_tokens' => 99, 'output_tokens' => 1, 'created_at' => now()->subDays(40)]);

    $usage = app(UsageReport::class)->since(now()->subDays(30), 'job');

    expect($usage['rows'])->toBe([
        ['group' => 'CoachAgent', 'calls' => 2, 'failed' => 1, 'input' => 1_000_000, 'output' => 500_000, 'tokens' => 1_500_000, 'cost' => 0.30],
        ['group' => 'ClassifierAgent', 'calls' => 1, 'failed' => 0, 'input' => 10, 'output' => 5, 'tokens' => 15, 'cost' => 0.0],
    ])
        ->and($usage['total']['calls'])->toBe(3)
        ->and($usage['total']['cost'])->toBe(0.30)
        ->and($usage['unpriced'])->toBe(['mystery'])
        ->and($usage['errors'])->toBe(['ProviderOverloadedException' => 1])
        ->and(app(UsageReport::class)->since(now()->subDays(30), 'user')['rows'][0]['group'])->toBe('hiren@example.com');
});

it('prints the usage table', function (): void {
    AiCallRecord::create(['agent' => CoachAgent::class, 'model' => 'gemini-3.5-flash-lite', 'input_tokens' => 10, 'output_tokens' => 5, 'created_at' => now()]);
    AiCallRecord::create(['agent' => CoachAgent::class, 'failed' => true, 'error' => 'ProviderOverloadedException', 'created_at' => now()]);

    expect(Artisan::call('ai:usage', ['--since' => '7d', '--by' => 'model']))->toBe(0)
        ->and(Artisan::output())->toContain('gemini-3.5-flash-lite')->toContain('No price in config/ai.php')->toContain('Failed calls: ProviderOverloadedException x1.')
        ->and(Artisan::call('ai:usage', ['--by' => 'colour']))->toBe(1);
});

it('pauses coach questions at the monthly token cap and keeps logging', function (): void {
    config(['ai.monthly_tokens_per_user' => 100]);
    $user = linkedUser();
    AiCallRecord::create(['user_id' => $user->id, 'agent' => CoachAgent::class, 'input_tokens' => 80, 'output_tokens' => 30, 'created_at' => now()]);
    $coach = fakeCoach();
    fakeSmartLogParser();

    classifierSays(MessageKind::CoachQuestion);
    expect(app(HandleInboundMessage::class)->handle(telegramText('what should I do tomorrow?')))->toContain("this month's AI budget")
        ->and($coach->calls)->toBe([]);

    classifierSays(MessageKind::Workout);
    app(HandleInboundMessage::class)->handle(telegramText('bench 3x8 80'));
    $this->assertDatabaseCount('activity_logs', 1);
});

it('schedules the weekly usage report', function (): void {
    $events = collect(app(Schedule::class)->events())->filter(fn ($e): bool => str_contains((string) $e->command, 'ai:usage --since=7d --by=job'));

    expect($events)->toHaveCount(1);
});
