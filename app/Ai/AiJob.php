<?php

declare(strict_types=1);

namespace App\Ai;

/**
 * One use of a model, each with its own provider, model and timeout in
 * config/ai.php `jobs`, so moving the coach to a bigger model leaves the
 * log reader where it is.
 */
enum AiJob: string
{
    case Classify = 'classify';
    case Log = 'log';
    case Limitation = 'limitation';
    case Coach = 'coach';
    case Profile = 'profile';

    public function provider(): ?string
    {
        return $this->setting('provider');
    }

    public function model(): ?string
    {
        return $this->setting('model');
    }

    /** Seconds before a call counts as an outage and the next model is asked. */
    public function timeout(): int
    {
        return (int) config("ai.jobs.{$this->value}.timeout");
    }

    private function setting(string $key): ?string
    {
        $value = config("ai.jobs.{$this->value}.{$key}");

        return is_string($value) && $value !== '' ? $value : null;
    }
}
