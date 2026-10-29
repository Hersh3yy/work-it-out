<?php

declare(strict_types=1);

namespace App\Conversation\Answers;

use App\Models\User;

/**
 * One kind of open question a plain message can answer (no AI). Handlers run
 * in the order listed in config/conversation.php; the first that recognises
 * the text replies, the rest never see it.
 *
 * @see https://refactoring.guru/design-patterns/chain-of-responsibility
 */
interface AnswerHandler
{
    /**
     * The reply when the text answers this handler's open question, else null.
     */
    public function handle(User $user, string $text): ?string;
}
