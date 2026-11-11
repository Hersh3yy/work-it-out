<?php

declare(strict_types=1);

namespace App\Conversation\Commands;

use App\Actions\SmartLog\RevertSmartLog;
use App\Channels\ReplyComposer;
use App\Models\User;

/**
 * Removes the newest log from the last 24 hours, exactly the pieces it saved.
 */
final readonly class UndoCommand implements ChatCommand
{
    public function __construct(
        private RevertSmartLog $revert,
        private ReplyComposer $replies,
    ) {}

    public function names(): array
    {
        return ['undo'];
    }

    public function menuDescription(): string
    {
        return 'Remove your last log';
    }

    public function reply(User $user, string $argument = ''): string
    {
        $log = $user->activityLogs()->where('created_at', '>=', now()->subDay())->first();

        if ($log === null) {
            return 'Nothing to undo from the last 24 hours.';
        }

        $entries = $log->exerciseEntries()->orderBy('sort_order')->get();
        $removed = $entries->isEmpty()
            ? $log->summary
            : $entries->map($this->replies->entry(...))->implode(', ');

        $this->revert->handle($log);

        return "Removed: {$removed}";
    }
}
