<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\AiCallRecord;
use App\Services\Ai\AiCall;
use Illuminate\Support\Facades\Context;
use Laravel\Ai\Events\AgentFailed;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Responses\Data\TextUsage;

/**
 * Writes one ai_calls row per model call, answered or failed, from the
 * SDK's own events. The user and the job come from the context AiCall set
 * before the call, so nothing here depends on which adapter made it.
 */
final class RecordAiCall
{
    public function handlePrompted(AgentPrompted $event): void
    {
        $usage = $event->response->usage;

        AiCallRecord::create([
            ...$this->fromContext($event->prompt->agent::class),
            'provider' => $event->response->meta->provider,
            'model' => $event->response->meta->model,
            'input_tokens' => $usage->inputTokens,
            'output_tokens' => $usage->outputTokens,
            'cached_input_tokens' => $usage instanceof TextUsage ? $usage->cacheReadInputTokens : null,
            'reasoning_tokens' => $usage instanceof TextUsage ? $usage->reasoningTokens : null,
        ]);
    }

    public function handleFailed(AgentFailed $event): void
    {
        AiCallRecord::create([
            ...$this->fromContext($event->prompt->agent::class),
            'failed' => true,
            'error' => mb_substr(class_basename($event->exception), 0, 120),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fromContext(string $agentClass): array
    {
        $startedAt = Context::get(AiCall::CONTEXT_STARTED_AT);

        return [
            'user_id' => Context::get(AiCall::CONTEXT_USER_ID),
            'agent' => Context::get(AiCall::CONTEXT_AGENT) ?? $agentClass,
            'duration_ms' => $startedAt === null ? null : (int) round((hrtime(true) - $startedAt) / 1_000_000),
            'created_at' => now(),
        ];
    }
}
