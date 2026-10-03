<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Ai\Agents\PlanAgent;
use App\Contracts\Ai\PlanGenerator;
use App\Enums\PlanType;
use App\Exceptions\AiUnavailable;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Ai\AiCall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PlanController extends Controller
{
    public function __construct(
        private readonly PlanGenerator $planner,
        private readonly AiCall $ai,
    ) {}

    /**
     * Generate a personalised weekly workout plan.
     * Rate-limited alongside trainer chat (20/hr per user).
     */
    public function workout(Request $request): JsonResponse
    {
        return $this->generate($request, PlanType::Workout);
    }

    /**
     * Generate a personalised weekly meal plan.
     * Rate-limited alongside trainer chat (20/hr per user).
     */
    public function meal(Request $request): JsonResponse
    {
        return $this->generate($request, PlanType::Meal);
    }

    private function generate(Request $request, PlanType $type): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $plan = $this->ai->run($user, PlanAgent::class, fn (): string => $this->planner->generate($user, $type));

            return response()->json([
                'type' => $type->value,
                'plan' => $plan,
            ]);
        } catch (AiUnavailable) {
            return response()->json(
                ['message' => 'Plan generation is temporarily unavailable. Please try again.'],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }
    }
}
