<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Contracts\Ai\PlanGenerator;
use App\Enums\PlanType;
use App\Models\User;

/**
 * In-memory PlanGenerator for tests.
 */
final class FakePlanGenerator implements PlanGenerator
{
    use FailsOnRequest;

    /** @var list<array{user_id: int, type: PlanType}> */
    public array $calls = [];

    public function __construct(
        private readonly string $plan = "# Fake Weekly Plan\n\nDay 1: Rest.",
    ) {}

    public function generate(User $user, PlanType $type): string
    {
        $this->calls[] = ['user_id' => $user->id, 'type' => $type];

        $this->failIfAsked();

        return $this->plan;
    }
}
