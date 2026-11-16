<?php

declare(strict_types=1);

namespace App\Conversation\Answers;

use App\Actions\Goals\SetGoalDate;
use App\Goals\GoalDate;
use App\Goals\GoalReply;
use App\Models\User;

/**
 * The answer to "When is it?" for a goal saved without a day: any day the
 * goal reader understands. Anything else goes on down the chain.
 */
final readonly class GoalDateAnswer implements AnswerHandler
{
    public function __construct(
        private OpenQuestions $openQuestions,
        private SetGoalDate $setDate,
    ) {}

    public function handle(User $user, string $text): ?Answer
    {
        $open = $this->openQuestions->for($user);
        $goal = $open?->log->goal;
        $day = GoalDate::in($text);

        if ($open === null || ! $open->is(OpenQuestionKind::GoalDate) || $goal === null || $day === null) {
            return null;
        }

        $this->setDate->handle($goal, $day);

        return new Answer(GoalReply::saved($goal));
    }
}
