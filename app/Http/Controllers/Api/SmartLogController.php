<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\SmartLog\AnswerOpenQuestion;
use App\Actions\SmartLog\RecordSmartLog;
use App\Actions\SmartLog\RevertSmartLog;
use App\Exceptions\AiUnavailable;
use App\Http\Controllers\Controller;
use App\Http\Resources\ExerciseEntryResource;
use App\Interpretation\Interpreter;
use App\Models\User;
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
        private readonly Interpreter $interpreter,
        private readonly RecordSmartLog $record,
        private readonly RevertSmartLog $revert,
        private readonly AnswerOpenQuestion $answers,
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
            $interpretation = $this->interpreter->interpret($user, $message);
        } catch (AiUnavailable) {
            return response()->json(
                ['message' => 'The AI log processor is temporarily unavailable. Please try again.'],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        if (! $interpretation->shouldRecord()) {
            return response()->json([
                'message' => $interpretation->reply,
                'kind' => $interpretation->classification->kind->value,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $this->record->handle($user, $message, $interpretation->parsed);

        return response()->json($result->toArray(), Response::HTTP_CREATED);
    }

    /**
     * Fill in the value a log's open question asked for. No AI involved.
     */
    public function answer(Request $request, string $log): JsonResponse
    {
        $request->validate(['value' => ['required', 'string', 'max:40']]);

        /** @var User $user */
        $user = $request->user();
        $activityLog = $user->activityLogs()->findOrFail($log);

        if (empty($activityLog->questions)) {
            return response()->json(['message' => 'This log has no open question.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $entry = $this->answers->answer($activityLog, $request->string('value')->value());

        if ($entry === null) {
            return response()->json(['message' => 'That is not a value for the open question. Send a number, or skip it.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['entry' => new ExerciseEntryResource($entry), 'questions' => []]);
    }

    public function skip(Request $request, string $log): Response
    {
        /** @var User $user */
        $user = $request->user();

        $this->answers->skip($user->activityLogs()->findOrFail($log));

        return response()->noContent();
    }

    public function destroy(Request $request, string $log): Response
    {
        /** @var User $user */
        $user = $request->user();

        $this->revert->handle($user->activityLogs()->findOrFail($log));

        return response()->noContent();
    }
}
