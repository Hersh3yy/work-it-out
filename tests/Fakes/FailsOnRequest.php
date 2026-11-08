<?php

declare(strict_types=1);

namespace Tests\Fakes;

use Laravel\Ai\Exceptions\ProviderOverloadedException;
use RuntimeException;
use Throwable;

/**
 * The two ways a fake AI port can fail, matching the two ways a real one does:
 * the provider is unavailable (overloaded, rate limited, unreachable), or our
 * own code has a bug. Callers must treat only the first as an outage.
 */
trait FailsOnRequest
{
    private ?Throwable $failure = null;

    public function failing(): static
    {
        $this->failure = ProviderOverloadedException::forProvider('fake');

        return $this;
    }

    public function broken(): static
    {
        $this->failure = new RuntimeException('Fake bug');

        return $this;
    }

    private function failIfAsked(): void
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }
    }
}
