<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Agents\CoachAgent;
use App\Coach\AnswerDepth;
use App\Coach\CoachContext;
use App\Contracts\Ai\CoachAdvisor;
use App\Enums\TrainerPersona;
use App\Exceptions\AiUnavailable;

/**
 * Laravel AI SDK adapter for the CoachAdvisor port. Runs on the chat job's
 * model chain (config ai.jobs.chat, then the log job's, then ai.fallbacks)
 * and remembers which model answered.
 */
final class SdkCoachAdvisor implements CoachAdvisor
{
    private ?string $usedModel = null;

    /** Telegram's limit is 4096 characters; leave room. */
    private const int MAX_CHARS = 3500;

    public function answer(CoachContext $context, TrainerPersona $persona, string $question, AnswerDepth $depth): string
    {
        $agent = new CoachAgent($persona, $context->toJson(), $depth);
        $answer = trim(ModelChain::ask('chat', function (?string $provider, ?string $model) use ($agent, $question): string {
            $this->usedModel = $model;

            return (string) $agent->prompt($question, provider: $provider, model: $model);
        }));

        if ($answer === '') {
            throw AiUnavailable::because(CoachAgent::class, 'empty answer');
        }

        return mb_strimwidth($answer, 0, self::MAX_CHARS, '…');
    }

    public function modelName(): ?string
    {
        return $this->usedModel ?? ModelChain::for('chat')[0]['model'];
    }
}
