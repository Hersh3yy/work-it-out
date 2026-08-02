<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Agents\TrainerAgent;
use App\Ai\Data\TrainerReply;
use App\Contracts\Ai\TrainerChat;
use App\Contracts\Profile\ProfileIntake;
use App\Contracts\Stats\PersonalRecords;
use App\Enums\TrainerPersona;
use App\Models\User;

/**
 * Laravel AI SDK adapter for the TrainerChat port.
 *
 * Enriches every prompt with the user's real personal records and the
 * profile-intake report so the coach can quote true numbers and ask the
 * right onboarding questions.
 */
final readonly class SdkTrainerChat implements TrainerChat
{
    public function __construct(
        private PersonalRecords $records,
        private ProfileIntake $intake,
    ) {}

    public function send(
        User $user,
        string $message,
        ?string $conversationId = null,
        ?TrainerPersona $coach = null,
    ): TrainerReply {
        $agent = new TrainerAgent(
            user: $user,
            coachOverride: $coach,
            personalRecords: $this->records->for($user),
            intake: $this->intake->report($user),
        );

        $response = $conversationId !== null && $conversationId !== ''
            ? $agent->continue($conversationId, as: $user)->prompt($message)
            : $agent->forUser($user)->prompt($message);

        return new TrainerReply(
            reply: (string) $response,
            conversationId: $response->conversationId,
            coach: $coach ?? $user->trainer_persona,
        );
    }
}
