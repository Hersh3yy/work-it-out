<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Agents\CoachAgent;
use App\Coach\AnswerDepth;
use App\Coach\CoachContext;
use App\Enums\TrainerPersona;
use Throwable;

/**
 * Asks the coach on one named model, outside the model chain and outside
 * AiCall, for php artisan coach:try. A failing model is an answer with its
 * error, so the comparison still shows the others.
 */
final readonly class CoachTrial
{
    public function ask(CoachContext $context, TrainerPersona $persona, string $question, ?string $provider, string $model): CoachTrialAnswer
    {
        $label = ($provider ?? 'default').':'.$model;
        $started = hrtime(true);

        try {
            $response = (new CoachAgent($persona, $context->toJson(), AnswerDepth::for($question)))
                ->prompt($question, provider: $provider, model: $model);
        } catch (Throwable $e) {
            return CoachTrialAnswer::failed($label, $e::class.': '.$e->getMessage(), self::msSince($started));
        }

        return new CoachTrialAnswer(
            label: $label,
            answer: trim((string) $response),
            inputTokens: $response->usage->inputTokens,
            outputTokens: $response->usage->outputTokens,
            ms: self::msSince($started),
            model: $response->meta->model ?? $model,
        );
    }

    private static function msSince(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
