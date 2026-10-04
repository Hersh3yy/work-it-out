<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\SmartLog\RecordSmartLog;
use App\Actions\SmartLog\RevertSmartLog;
use App\Ai\Agents\SmartLogAgent;
use App\Contracts\Ai\SmartLogParser;
use App\Exceptions\AiUnavailable;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Ai\AiCall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP door for a free-text log: validate, parse into facts, record.
 *
 * The reply is a receipt (what was logged, at most one question for a value
 * the user did not give), never coach feedback. Everything after parsing
 * lives in RecordSmartLog so the chat door shares it.
 */
final class SmartLogController extends Controller
{
    public function __construct(
        private readonly SmartLogParser $parser,
        private readonly AiCall $ai,
        private readonly RecordSmartLog $record,
        private readonly RevertSmartLog $revert,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $message = $request->string('message')->value();

        try {
            $parsed = $this->ai->run($user, SmartLogAgent::class, fn (): array => $this->parser->parse($user, $message));
        } catch (AiUnavailable) {
            return response()->json(
                ['message' => 'The AI log processor is temporarily unavailable. Please try again.'],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        $result = $this->record->handle($user, $message, $parsed);

        return response()->json($result->toArray(), Response::HTTP_CREATED);
    }

    public function destroy(Request $request, string $log): Response
    {
        /** @var User $user */
        $user = $request->user();

        $this->revert->handle($user->activityLogs()->findOrFail($log));

        return response()->noContent();
    }
}
