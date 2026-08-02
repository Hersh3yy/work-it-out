<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Contracts\Ai\PlanGenerator;
use App\Enums\PlanType;
use App\Models\User;
use RuntimeException;

/**
 * In-memory PlanGenerator for tests.
 */
final class FakePlanGenerator implements PlanGenerator
{
    /** @var list<array{user_id: int, type: PlanType}> */
    public array $calls = [];

    private bool $shouldFail = false;

    public function __construct(
        private readonly string $plan = "# Fake Weekly Plan\n\nDay 1: Rest.",
    ) {}

    public function failing(): self
    {
        $this->shouldFail = true;

        return $this;
    }

    public function generate(User $user, PlanType $type): string
    {
        $this->calls[] = ['user_id' => $user->id, 'type' => $type];

        if ($this->shouldFail) {
            throw new RuntimeException('Fake AI outage');
        }

        return $this->plan;
    }
}
