<?php

declare(strict_types=1);

namespace App\Conversation\Answers;

use App\Models\ActivityLog;

/**
 * The question a log is waiting on, with the log it belongs to.
 */
final readonly class OpenQuestion
{
    /**
     * @param  array{field: string, question: string, options?: list<string>, pair?: array{0: int, 1: int}}  $question
     */
    private function __construct(
        public OpenQuestionKind $kind,
        public ActivityLog $log,
        public array $question,
    ) {}

    public static function on(ActivityLog $log): ?self
    {
        $question = $log->questions[0] ?? null;
        $kind = OpenQuestionKind::forField((string) ($question['field'] ?? ''));

        return $kind === null ? null : new self($kind, $log, $question);
    }

    public function is(OpenQuestionKind $kind): bool
    {
        return $this->kind === $kind;
    }

    /**
     * @return list<string>
     */
    public function options(): array
    {
        return $this->question['options'] ?? [];
    }
}
