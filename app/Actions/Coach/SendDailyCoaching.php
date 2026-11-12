<?php

declare(strict_types=1);

namespace App\Actions\Coach;

use App\Ai\Agents\CoachAgent;
use App\Coach\AnswerDepth;
use App\Coach\CoachBudget;
use App\Coach\CoachContext;
use App\Contracts\Ai\CoachAdvisor;
use App\Contracts\Channels\ChatChannel;
use App\Enums\TrainerPersona;
use App\Exceptions\AiUnavailable;
use App\Models\ChannelIdentity;
use App\Models\CoachTurn;
use App\Models\User;
use App\Services\Ai\AiCall;

/**
 * The morning message: the coach writes first, from the living context,
 * what today is for. Stored as a coach turn of kind "daily" (so a reply
 * within the follow-up window goes to the coach) and sent on every chat
 * the person has linked.
 */
final readonly class SendDailyCoaching
{
    private const string BRIEF = 'Morning message for %s, %s. Nobody asked; you write first. Say what today is for, given their plan (training days a week), what they trained in the last 7 days, their goal and their limitation: either one session they can walk in with, or a rest or walk instruction with its reason. A session has a warm-up line, then numbered exercises with sets x reps and a load in kg: a lift they did before moves one small step up (2.5 kg, or two reps) when its last sets were all completed, a new lift gets a starting load from a lift on record, and a lift they said they want back gets its next step and the sign that unlocks the step after. Up to 10 short lines. Start with the day, not with a greeting or their name. End with exactly one question back, the first that applies: how the limitation feels today when one is active and how_it_is_going has nothing from the last 2 days; their body weight when none was logged in the last 7 days; otherwise none.';

    public function __construct(
        private CoachAdvisor $advisor,
        private AiCall $ai,
        private CoachBudget $budget,
        private ChatChannel $channel,
    ) {}

    /**
     * The message sent, or null when this person is over budget this month.
     *
     * @throws AiUnavailable
     */
    public function handle(User $user): ?string
    {
        if ($this->budget->exhaustedFor($user)) {
            return null;
        }

        $brief = sprintf(self::BRIEF, now()->timezone((string) config('coach.daily.timezone'))->format('l'), today()->toDateString());
        $context = CoachContext::for($user);
        $persona = $user->trainer_persona ?? TrainerPersona::LtSurge;
        $message = $this->ai->run($user, CoachAgent::class, fn (): string => $this->advisor->answer($context, $persona, $brief, AnswerDepth::Plan));

        $user->coachTurns()->create([
            'question' => $brief,
            'answer' => $message,
            'depth' => AnswerDepth::Plan->value,
            'kind' => CoachTurn::DAILY,
            'model' => $this->advisor->modelName(),
        ]);

        $user->channelIdentities->each(fn (ChannelIdentity $identity) => $this->channel->send($identity->external_id, $message));

        return $message;
    }
}
