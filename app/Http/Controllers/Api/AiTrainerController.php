<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Ai\Agents\TrainerAgent;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class AiTrainerController extends Controller
{
    /**
     * List the authenticated user's AI trainer conversations (paginated).
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $conversations = $user->conversations()
            ->latest('updated_at')
            ->paginate(20);

        return response()->json($conversations);
    }

    /**
     * Send a message to the AI trainer.
     * Rate limited to 20 requests per user per hour (defined in AppServiceProvider).
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message'         => ['required', 'string', 'max:1000'],
            'conversation_id' => ['nullable', 'string'],
        ]);

        /** @var User $user */
        $user    = $request->user();
        $message = $request->string('message')->value();

        try {
            $agent = new TrainerAgent($user);

            $conversationId = $request->string('conversation_id')->value();

            if ($conversationId !== '') {
                $response = $agent->continue($conversationId, as: $user)->prompt($message);
            } else {
                $response = $agent->forUser($user)->prompt($message);
            }

            return response()->json([
                'reply'           => (string) $response,
                'conversation_id' => $response->conversationId,
            ]);
        } catch (Throwable) {
            $errorMessage = $user->trainer_persona->downMessage();

            return response()->json(
                ['reply' => $errorMessage, 'conversation_id' => null],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }
    }
}
