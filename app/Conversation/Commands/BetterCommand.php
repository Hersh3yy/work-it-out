<?php

declare(strict_types=1);

namespace App\Conversation\Commands;

use App\Actions\Limitations\ResolveLimitation;
use App\Models\User;

/**
 * Only the person decides an injury has healed; this closes the newest one.
 */
final readonly class BetterCommand implements ChatCommand
{
    public function __construct(
        private ResolveLimitation $resolve,
    ) {}

    public function names(): array
    {
        return ['better', 'healed'];
    }

    public function menuDescription(): string
    {
        return 'An injury has healed';
    }

    public function reply(User $user): string
    {
        $limitation = $user->limitations()->active()->latest('started_on')->first();

        if ($limitation === null) {
            return 'Nothing to resolve: no active injury or limitation on file.';
        }

        $this->resolve->handle($limitation);

        return 'Glad to hear it. '.ucfirst($limitation->areaLabel()).' marked as healed; suggestions go back to normal.';
    }
}
