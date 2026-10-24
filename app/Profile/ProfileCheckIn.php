<?php

declare(strict_types=1);

namespace App\Profile;

use App\Models\ProfilePrompt;
use App\Models\User;
use App\Profile\Questions\ProfileQuestion;

/**
 * Decides when to ask a profile question and records every prompt.
 *
 * At most one question per `hours_between_questions`; the next one is the
 * first in `config/profile.php` the user has not answered and has not skipped
 * in the last `skip_days`. An asked question stays answerable for
 * `answer_window_hours`. Every door (chat now, the app later) uses this class.
 */
final readonly class ProfileCheckIn
{
    /**
     * The question to add to a reply, or null when none is due.
     */
    public function next(User $user): ?string
    {
        $recent = ProfilePrompt::query()
            ->where('user_id', $user->id)
            ->where('asked_at', '>', now()->subHours((int) config('profile.hours_between_questions')))
            ->exists();

        if ($recent) {
            return null;
        }

        $question = $this->firstOpen($user);

        if ($question === null) {
            return null;
        }

        ProfilePrompt::query()->create(['user_id' => $user->id, 'question_key' => $question->key(), 'asked_at' => now()]);

        return $question->ask($user);
    }

    /**
     * The confirmation when the text answers (or skips) the open question,
     * else null so the message is handled as usual.
     */
    public function answer(User $user, string $text): ?string
    {
        $prompt = ProfilePrompt::query()
            ->where('user_id', $user->id)
            ->whereNull('answered_at')
            ->whereNull('skipped_at')
            ->where('asked_at', '>', now()->subHours((int) config('profile.answer_window_hours')))
            ->latest('asked_at')
            ->first();

        $question = $prompt === null ? null : $this->question($prompt->question_key);

        if ($question === null) {
            return null;
        }

        if (in_array(mb_strtolower(trim($text)), ['skip', '/skip'], true)) {
            $prompt->update(['skipped_at' => now()]);

            return "OK, I won't ask that for a while.";
        }

        $confirmation = $question->answer($user, $text);

        if ($confirmation !== null) {
            $prompt->update(['answered_at' => now()]);
        }

        return $confirmation;
    }

    private function firstOpen(User $user): ?ProfileQuestion
    {
        $skipped = ProfilePrompt::query()
            ->where('user_id', $user->id)
            ->where('skipped_at', '>', now()->subDays((int) config('profile.skip_days')))
            ->pluck('question_key')
            ->all();

        foreach ($this->questions() as $question) {
            if (! $question->isAnswered($user) && ! in_array($question->key(), $skipped, true)) {
                return $question;
            }
        }

        return null;
    }

    private function question(string $key): ?ProfileQuestion
    {
        foreach ($this->questions() as $question) {
            if ($question->key() === $key) {
                return $question;
            }
        }

        return null;
    }

    /**
     * @return list<ProfileQuestion>
     */
    private function questions(): array
    {
        return array_map(static fn (string $class): ProfileQuestion => app($class), config('profile.questions', []));
    }
}
