<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Ai\Agents\PlanAgent;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class PlanController extends Controller
{
    /**
     * Generate a personalised weekly workout plan.
     * Rate-limited alongside trainer chat (20/hr per user).
     */
    public function workout(Request $request): JsonResponse
    {
        return $this->generate($request, 'workout');
    }

    /**
     * Generate a personalised weekly meal plan.
     * Rate-limited alongside trainer chat (20/hr per user).
     */
    public function meal(Request $request): JsonResponse
    {
        return $this->generate($request, 'meal');
    }

    private function generate(Request $request, string $type): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $agent    = new PlanAgent($user, $type);
            $response = $agent->forUser($user)->prompt(
                "Generate my {$type} plan for this week."
            );

            return response()->json([
                'type' => $type,
                'plan' => (string) $response,
            ]);
        } catch (Throwable) {
            return response()->json(
                ['message' => 'Plan generation is temporarily unavailable. Please try again.'],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }
    }
}
