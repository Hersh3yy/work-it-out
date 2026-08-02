<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Ai\PlanGenerator;
use App\Enums\PlanType;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class PlanController extends Controller
{
    public function __construct(
        private readonly PlanGenerator $planner,
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
            $plan = $this->planner->generate($user, $type);

            return response()->json([
                'type' => $type->value,
                'plan' => $plan,
            ]);
        } catch (Throwable) {
            return response()->json(
                ['message' => 'Plan generation is temporarily unavailable. Please try again.'],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }
    }
}
