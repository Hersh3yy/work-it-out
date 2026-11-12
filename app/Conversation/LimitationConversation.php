<?php

declare(strict_types=1);

namespace App\Conversation;

use App\Actions\Limitations\RecordLimitation;
use App\Actions\Limitations\RecordLimitationUpdate;
use App\Enums\LogSource;
use App\Limitations\ExistingLimitation;
use App\Limitations\RedFlagScreen;
use App\Models\ActivityLog;
use App\Models\Limitation;
use App\Models\User;

/**
 * What the bot says when someone tells it about an injury: a new limitation
 * with its follow-up question, or an update on one it already knows.
 */
final readonly class LimitationConversation
{
    public function __construct(
        private RecordLimitation $recordLimitation,
        private RecordLimitationUpdate $recordUpdate,
    ) {}

    /**
     * @param  array<string, mixed>  $facts  from the LimitationParser
     */
    public function reply(User $user, string $text, array $facts, LogSource $source): string
    {
        $existing = ExistingLimitation::for($user, $facts);

        return $existing === null
            ? $this->noteNew($user, $text, $facts, $source)
            : $this->noteUpdate($existing, $text, $facts, $source);
    }

    /**
     * A workout message that also says how a known limitation is going: the
     * update hangs off the workout's log. A new injury inside a workout
     * message is not started from here; they can tell it on its own.
     *
     * @param  array<string, mixed>  $facts  from the LimitationParser
     */
    public function noteOnLog(User $user, string $text, array $facts, LogSource $source, ActivityLog $log): ?string
    {
        $existing = ExistingLimitation::for($user, $facts);

        if ($existing === null) {
            return null;
        }

        $this->recordUpdate->handle($existing, $text, $facts, $source, $log);

        return 'Noted for your '.$existing->areaLabel().' too, day '.$existing->day().'.';
    }

    /**
     * @param  array<string, mixed>  $facts
     */
    private function noteNew(User $user, string $text, array $facts, LogSource $source): string
    {
        $limitation = $this->recordLimitation->handle($user, $text, $facts, $source);
        $question = $limitation->activityLog->questions[0];

        return $question['field'] === RedFlagScreen::FIELD
            ? 'Noted: '.$limitation->describe().".\n\n".$question['question']
            : $question['question'];
    }

    /**
     * @param  array<string, mixed>  $facts
     */
    private function noteUpdate(Limitation $limitation, string $text, array $facts, LogSource $source): string
    {
        $this->recordUpdate->handle($limitation, $text, $facts, $source);
        $until = $limitation->expected_until === null ? '' : ', until '.$limitation->expected_until->format('j M');

        return 'Noted for your '.$limitation->areaLabel().', day '.$limitation->day().$until.'. Say /better when it has healed.';
    }
}
