<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Exceptions\AiUnavailable;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Context;
use Throwable;

/**
 * The one seam every AI call goes through.
 *
 * Reports failures with the user id, provider and agent class (never the
 * prompt text or any secret) and rethrows AiUnavailable so HTTP and chat
 * callers handle outages identically. Before the call it puts the user, the
 * agent and the start time in the request context, which is how
 * RecordAiCall knows whose call the SDK's event belongs to.
 */
final class AiCall
{
    public const string CONTEXT_USER_ID = 'ai.user_id';

    public const string CONTEXT_AGENT = 'ai.agent';

    public const string CONTEXT_STARTED_AT = 'ai.started_at';

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
        Context::add([
            self::CONTEXT_USER_ID => $user->id,
            self::CONTEXT_AGENT => $agent,
            self::CONTEXT_STARTED_AT => hrtime(true),
        ]);

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
