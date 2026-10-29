<?php

declare(strict_types=1);

namespace App\Conversation\Answers;

use App\Actions\SmartLog\AnswerOpenQuestion;
use App\Enums\BodyArea;
use App\Events\LogRecorded;
use App\Models\User;

/**
 * The answer to "Where exactly? 1) lower back 2) upper back ...": a number
 * or the area's name. Fills the limitation's body area; "skip" leaves it
 * unspecified.
 */
final readonly class LimitationChoiceAnswer implements AnswerHandler
{
    public function __construct(
        private AnswerOpenQuestion $answers,
    ) {}

    public function handle(User $user, string $text): ?string
    {
        $open = $this->answers->openFor($user);
        $question = $open?->questions[0] ?? null;

        if ($open === null || ($question['field'] ?? null) !== 'limitation.body_area') {
            return null;
        }

        $limitation = $open->limitation;
        $options = array_values(array_filter(array_map(static fn (mixed $v): ?BodyArea => BodyArea::tryFrom((string) $v), $question['options'] ?? [])));
        $text = mb_strtolower(trim($text));

        if (in_array($text, ['skip', '/skip'], true)) {
            $open->update(['questions' => null]);

            return 'OK, noted without the exact area.';
        }

        $picked = match (true) {
            (bool) preg_match('/^([1-9])\b/', $text, $m) => $options[(int) $m[1] - 1] ?? null,
            default => collect(BodyArea::cases())->first(fn (BodyArea $a): bool => str_contains($text, $a->label()) || str_contains($text, str_replace('_', ' ', $a->value))),
        };

        if ($picked === null || $limitation === null) {
            return null;
        }

        $limitation->update(['body_area' => $picked]);
        $open->update([
            'questions' => null,
            'summary' => 'Limitation: '.$picked->label().', '.$limitation->note,
        ]);
        $open->diaryEntry()->update(['content' => $open->summary]);
        LogRecorded::dispatch($open);

        return 'Got it: '.$limitation->describe().". I'll check with you when a logged exercise loads it. Say /better when it has healed.";
    }
}
