<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Contracts\Ai\ProfileBuilder;
use App\Profile\ProfileFacts;
use RuntimeException;

/**
 * Profile writer for tests: records every call and returns fixed notes, by
 * default one note citing the newest log it was shown and one citing facts.
 */
final class FakeProfileBuilder implements ProfileBuilder
{
    /** @var list<array{facts: array<string, mixed>, previous: array<int, mixed>, logs: array<int, mixed>}> */
    public array $calls = [];

    /** @var list<array{text: string, cites: list<string>}>|null */
    private ?array $notes = null;

    private bool $shouldFail = false;

    /**
     * @param  list<array{text: string, cites: list<string>}>  $notes
     */
    public function returning(array $notes): self
    {
        $this->notes = $notes;

        return $this;
    }

    public function failing(): self
    {
        $this->shouldFail = true;

        return $this;
    }

    public function build(ProfileFacts $facts, array $previousNotes, array $recentLogs): array
    {
        $this->calls[] = ['facts' => $facts->toArray(), 'previous' => $previousNotes, 'logs' => $recentLogs];

        if ($this->shouldFail) {
            throw new RuntimeException('Fake profile outage');
        }

        if ($this->notes !== null) {
            return $this->notes;
        }

        $newest = $recentLogs[0]['id'] ?? null;

        return array_values(array_filter([
            $newest === null ? null : ['text' => 'Logged training recently.', 'cites' => [$newest]],
            ['text' => 'No body weight logged yet.', 'cites' => ['facts']],
        ]));
    }

    public function modelName(): ?string
    {
        return 'fake';
    }
}
