<?php

declare(strict_types=1);

namespace App\Ai;

use Closure;
use Laravel\Ai\Exceptions\FailoverableException;
use LogicException;

/**
 * The models a job may use, in order: its own, the log job's (for jobs that
 * have their own), then the fallbacks in config ai.fallbacks. When a model
 * is overloaded, rate limited or unreachable, the next one gets the same
 * prompt. The AI library's own failover takes one model per provider, so a
 * second Gemini model needs this.
 *
 * @see https://refactoring.guru/design-patterns/chain-of-responsibility
 */
final class ModelChain
{
    /**
     * @return list<array{provider: string|null, model: string|null}>
     */
    public static function for(string $job): array
    {
        $log = self::link(config('ai.jobs.log.provider'), config('ai.jobs.log.model'));
        $own = self::link(config("ai.jobs.{$job}.provider") ?: $log['provider'], config("ai.jobs.{$job}.model") ?: $log['model']);
        $chain = [$own, $log];

        foreach (config('ai.fallbacks', []) as $entry) {
            [$provider, $model] = array_pad(explode(':', trim((string) $entry), 2), 2, null);
            $chain[] = self::link($provider, $model);
        }

        return array_values(array_unique($chain, SORT_REGULAR));
    }

    /**
     * Asks each model in the job's chain until one answers.
     *
     * @template T
     *
     * @param  Closure(string|null, string|null): T  $ask  receives the provider and the model
     * @return T
     *
     * @throws FailoverableException when every model in the chain fails that way
     */
    public static function ask(string $job, Closure $ask): mixed
    {
        $chain = self::for($job);

        foreach ($chain as $i => $link) {
            try {
                return $ask($link['provider'], $link['model']);
            } catch (FailoverableException $e) {
                if ($i === array_key_last($chain)) {
                    throw $e;
                }
            }
        }

        throw new LogicException("No models configured for the {$job} job.");
    }

    /**
     * @return array{provider: string|null, model: string|null}
     */
    private static function link(mixed $provider, mixed $model): array
    {
        return ['provider' => is_string($provider) && $provider !== '' ? $provider : null, 'model' => is_string($model) && $model !== '' ? $model : null];
    }
}
