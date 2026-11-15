<?php

declare(strict_types=1);

namespace App\Conversation\Answers;

use App\Enums\BodyArea;
use App\Events\LogRecorded;
use App\Limitations\RedFlagScreen;
use App\Models\User;

/**
 * The answer to "Where exactly? 1) lower back 2) upper back ...": the
 * option's number or the area's name, and nothing else. Fills the
 * limitation's body area; "skip" leaves it unspecified. Either way the
 * red-flag screen comes next.
 */
final readonly class LimitationChoiceAnswer implements AnswerHandler
{
    /** Words around an area's name that keep it a whole answer: "my upper back", "the left knee". */
    private const string AROUND_THE_NAME = '/^(?:(?:it\'?s|in|on)\s+)?(?:(?:my|the)\s+)?(?:(?:left|right)\s+)?/';

    public function __construct(
        private OpenQuestions $openQuestions,
    ) {}

    public function handle(User $user, string $text): ?Answer
    {
        $open = $this->openQuestions->for($user);
        $limitation = $open?->log->limitation;

        if ($open === null || ! $open->is(OpenQuestionKind::LimitationArea) || $limitation === null) {
            return null;
        }

        $text = mb_strtolower(trim($text, " \t\n\r.!"));

        if (in_array($text, ['skip', '/skip'], true)) {
            $open->log->update(['questions' => [RedFlagScreen::question()]]);

            return new Answer("OK, noted without the exact area.\n\n".RedFlagScreen::QUESTION);
        }

        $picked = $this->picked($text, $open);

        if ($picked === null) {
            return null;
        }

        $limitation->update(['body_area' => $picked]);
        $open->log->update([
            'questions' => [RedFlagScreen::question()],
            'summary' => 'Limitation: '.$picked->label().', '.$limitation->note,
        ]);
        $open->log->diaryEntry()->update(['content' => $open->log->summary]);
        LogRecorded::dispatch($open->log);

        return new Answer('Got it: '.$limitation->describe().".\n\n".RedFlagScreen::QUESTION);
    }

    /**
     * "shoulder press 3x10 30kg" names an exercise, and "2 sets of rows"
     * starts with a count: neither picks an area.
     */
    private function picked(string $text, OpenQuestion $open): ?BodyArea
    {
        if (preg_match('/^[1-9]$/', $text)) {
            return BodyArea::tryFrom($open->options()[(int) $text - 1] ?? '');
        }

        $name = (string) preg_replace(self::AROUND_THE_NAME, '', $text);

        return collect(BodyArea::cases())->first(static fn (BodyArea $area): bool => $name === $area->label() || $name === $area->value);
    }
}
