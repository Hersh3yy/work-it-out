<?php

declare(strict_types=1);

namespace App\Actions\Coach;

use App\Ai\Agents\CoachAgent;
use App\Coach\AnswerDepth;
use App\Coach\CoachContext;
use App\Contracts\Ai\CoachAdvisor;
use App\Enums\CoachTurnKind;
use App\Enums\TrainerPersona;
use App\Exceptions\AiUnavailable;
use App\Models\CoachTurn;
use App\Models\User;
use App\Services\Ai\AiCall;

/**
 * One coach message, in this person's coach's voice, over everything the
 * coach may know, kept as a coach turn. Shared by a question they asked and
 * the morning message the coach writes first.
 */
final readonly class TakeCoachTurn
{
    public function __construct(
        private CoachAdvisor $advisor,
        private AiCall $ai,
    ) {}

    /**
     * @throws AiUnavailable
     */
    public function handle(User $user, string $prompt, AnswerDepth $depth, CoachTurnKind $kind): CoachTurn
    {
        $context = CoachContext::for($user);
        $persona = $user->trainer_persona ?? TrainerPersona::LtSurge;
        $answer = $this->ai->run($user, CoachAgent::class, fn (): string => $this->advisor->answer($context, $persona, $prompt, $depth));

        return $user->coachTurns()->create([
            'question' => mb_substr($prompt, 0, 2000),
            'answer' => $answer,
            'depth' => $depth->value,
            'kind' => $kind,
            'model' => $this->advisor->modelName(),
        ]);
    }
}
