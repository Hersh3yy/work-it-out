<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Ai\Data\TrainerReply;
use App\Contracts\Ai\TrainerChat;
use App\Enums\TrainerPersona;
use App\Models\User;
use RuntimeException;

/**
 * In-memory TrainerChat for tests: queue canned replies, record every call,
 * or simulate an AI outage.
 */
final class FakeTrainerChat implements TrainerChat
{
    /** @var list<array{user_id: int, message: string, conversation_id: string|null, coach: TrainerPersona|null}> */
    public array $calls = [];

    /** @var list<string> */
    private array $replies;

    private bool $shouldFail = false;

    public function __construct(string ...$replies)
    {
        $this->replies = $replies === [] ? ['Fake trainer reply.'] : array_values($replies);
    }

    public function failing(): self
    {
        $this->shouldFail = true;

        return $this;
    }

    public function send(
        User $user,
        string $message,
        ?string $conversationId = null,
        ?TrainerPersona $coach = null,
    ): TrainerReply {
        $this->calls[] = [
            'user_id' => $user->id,
            'message' => $message,
            'conversation_id' => $conversationId,
            'coach' => $coach,
        ];

        if ($this->shouldFail) {
            throw new RuntimeException('Fake AI outage');
        }

        $reply = count($this->replies) > 1 ? array_shift($this->replies) : $this->replies[0];

        return new TrainerReply(
            reply: $reply,
            conversationId: $conversationId ?? 'fake-conversation-id',
            coach: $coach ?? $user->trainer_persona,
        );
    }
}
