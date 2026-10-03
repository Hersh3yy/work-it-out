<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Exceptions\AiUnavailable;
use App\Models\User;
use Closure;
use Throwable;

/**
 * The one seam every AI call goes through.
 *
 * Reports failures with the user id, provider and agent class (never the
 * prompt text or any secret) and rethrows AiUnavailable so HTTP and chat
 * callers handle outages identically.
 */
final class AiCall
{
    /**
     * @template T
     *
     * @param  Closure(): T  $call
     * @return T
     *
     * @throws AiUnavailable
     */
    public function run(User $user, string $agent, Closure $call): mixed
    {
        try {
            return $call();
        } catch (Throwable $e) {
            report($e);

            logger()->error('AI call failed', [
                'user_id' => $user->id,
                'provider' => config('ai.default'),
                'agent' => $agent,
                'error' => $e instanceof AiUnavailable ? $e->getMessage() : $e::class,
            ]);

            throw $e instanceof AiUnavailable
                ? $e
                : AiUnavailable::because($agent, $e::class);
        }
    }
}
