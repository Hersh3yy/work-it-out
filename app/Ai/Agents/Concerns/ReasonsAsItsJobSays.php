<?php

declare(strict_types=1);

namespace App\Ai\Agents\Concerns;

use App\Ai\AiJob;
use Laravel\Ai\Enums\Lab;

/**
 * Sends the job's reasoning effort to Mistral, the provider whose API takes
 * it; any other provider gets nothing extra, so a fallback model never sees
 * a field it does not know.
 */
trait ReasonsAsItsJobSays
{
    abstract protected function job(): AiJob;

    /**
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        $effort = $this->job()->reasoning();
        $isMistral = ($provider instanceof Lab ? $provider->value : $provider) === 'mistral';

        return $effort !== null && $isMistral ? ['reasoning_effort' => $effort] : [];
    }
}
