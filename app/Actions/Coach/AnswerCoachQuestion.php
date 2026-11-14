<?php

declare(strict_types=1);

namespace App\Actions\Coach;

use App\Coach\AnswerDepth;
use App\Coach\CoachBudget;
use App\Enums\CoachTurnKind;
use App\Exceptions\AiUnavailable;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * A question for the coach ("30 min, 3 things, go", "foam roller for my
 * back?"): one model call over the person's living context, the answer
 * stored as a coach turn (conversation, never a fact). AnswerDepth sizes the
 * answer: a quick tip, a session plan, or an explanation gets more room. Capped per day and
 * per month (config) so a chatty week cannot drain the AI budget; logging is never capped.
 */
final readonly class AnswerCoachQuestion
{
    public function __construct(
        private TakeCoachTurn $coach,
        private CoachBudget $budget,
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

        if ($this->budget->exhaustedFor($user)) {
            return "That's this month's AI budget used. Logging still works as usual; the coach is back on the 1st.";
        }

        $answer = $this->coach->handle($user, $question, AnswerDepth::for($question), CoachTurnKind::Asked)->answer;
        RateLimiter::hit(self::todaysQuestions($user), 86400);

        return $answer;
    }

    /** The coach answered within the follow-up window (config coach.follow_up_minutes). */
    public function isTalkingWith(User $user): bool
    {
        return $user->coachTurns()->where('created_at', '>=', now()->subMinutes((int) config('coach.follow_up_minutes')))->exists();
    }

    private static function todaysQuestions(User $user): string
    {
        return 'coach-questions:'.$user->id.':'.today()->toDateString();
    }
}
