<?php

declare(strict_types=1);

namespace App\Actions\Coach;

use App\Ai\Agents\CoachAgent;
use App\Coach\AnswerDepth;
use App\Coach\CoachContext;
use App\Contracts\Ai\CoachAdvisor;
use App\Enums\TrainerPersona;
use App\Exceptions\AiUnavailable;
use App\Models\User;
use App\Services\Ai\AiCall;
use Illuminate\Support\Facades\RateLimiter;

/**
 * A question for the coach ("30 min, 3 things, go", "foam roller for my
 * back?"): one model call over the person's living context, the answer
 * stored as a coach turn (conversation, never a fact). AnswerDepth sizes the
 * answer: a quick tip, a session plan, or an explanation gets more room. Capped per day so a
 * chatty week cannot drain the AI budget; logging is never capped.
 */
final readonly class AnswerCoachQuestion
{
    public function __construct(
        private CoachAdvisor $advisor,
        private AiCall $ai,
    ) {}

    /**
     * @throws AiUnavailable
     */
    public function handle(User $user, string $question): string
    {
        $dailyLimit = (int) config('coach.daily_limit');

        if (RateLimiter::tooManyAttempts(self::todaysQuestions($user), $dailyLimit)) {
            return "That's today's {$dailyLimit} coach questions used. Logging still works as usual; ask me again tomorrow.";
        }

        $context = CoachContext::for($user);
        $persona = $user->trainer_persona ?? TrainerPersona::LtSurge;
        $depth = AnswerDepth::for($question);
        $answer = $this->ai->run($user, CoachAgent::class, fn (): string => $this->advisor->answer($context, $persona, $question, $depth));

        RateLimiter::hit(self::todaysQuestions($user), 86400);
        $user->coachTurns()->create([
            'question' => mb_substr($question, 0, 2000),
            'answer' => $answer,
            'depth' => $depth->value,
            'model' => $this->advisor->modelName(),
        ]);

        return $answer;
    }

    private static function todaysQuestions(User $user): string
    {
        return 'coach-questions:'.$user->id.':'.today()->toDateString();
    }
}
