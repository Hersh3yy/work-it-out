<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Exceptions\AiUnavailable;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Context;
use Laravel\Ai\Exceptions\FailoverableException;
use Throwable;

/**
 * The one seam every AI call goes through.
 *
 * Reports failures with the user id, provider and agent class (never the
 * prompt text or any secret). Only an outage (the provider overloaded, rate
 * limited, unreachable or out of credits, or an adapter's AiUnavailable)
 * becomes AiUnavailable, so callers keep the message for later; a bug is
 * rethrown as itself and never reads as "the AI is busy". Before the call it puts the user, the
 * agent and the start time in the hidden request context (hidden so log
 * lines stay clean), which is how RecordAiCall knows whose call the SDK's
 * event belongs to.
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
        Context::addHidden([
            self::CONTEXT_USER_ID => $user->id,
            self::CONTEXT_AGENT => $agent,
            self::CONTEXT_STARTED_AT => hrtime(true),
        ]);

        try {
            return $call();
        } catch (AiUnavailable|FailoverableException $outage) {
            $this->logFailure($user, $agent, $outage);

            throw $outage instanceof AiUnavailable ? $outage : AiUnavailable::because($agent, $outage::class);
        } catch (Throwable $bug) {
            $this->logFailure($user, $agent, $bug);

            throw $bug;
        }
    }

    private function logFailure(User $user, string $agent, Throwable $e): void
    {
        report($e);
        logger()->error('AI call failed', [
            'user_id' => $user->id,
            'provider' => config('ai.default'),
            'agent' => $agent,
            'error' => $e instanceof AiUnavailable ? $e->getMessage() : $e::class,
        ]);
    }
}
