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
 * model (config ai.jobs.chat), falling back to the log job's.
 */
final readonly class SdkCoachAdvisor implements CoachAdvisor
{
    /** Telegram's limit is 4096 characters; leave room. */
    private const int MAX_CHARS = 3500;

    public function answer(CoachContext $context, TrainerPersona $persona, string $question, AnswerDepth $depth): string
    {
        $answer = trim((string) (new CoachAgent($persona, $context->toJson(), $depth))->prompt(
            $question,
            provider: config('ai.jobs.chat.provider') ?: config('ai.jobs.log.provider'),
            model: $this->modelName(),
        ));

        if ($answer === '') {
            throw AiUnavailable::because(CoachAgent::class, 'empty answer');
        }

        return mb_strimwidth($answer, 0, self::MAX_CHARS, '…');
    }

    public function modelName(): ?string
    {
        return config('ai.jobs.chat.model') ?: config('ai.jobs.log.model');
    }
}
