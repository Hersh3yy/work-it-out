<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BodyWeightLogResource;
use App\Http\Resources\WorkoutSessionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $recentSessions = $user->workoutSessions()
            ->with('exerciseEntries')
            ->latest('logged_at')
            ->limit(5)
            ->get();

        $todayNutrition = $user->nutritionLogs()->today()->get();

        $todayMacros = [
            'calories'  => (int) $todayNutrition->sum('calories'),
            'protein_g' => round((float) $todayNutrition->sum('protein_g'), 1),
            'carbs_g'   => round((float) $todayNutrition->sum('carbs_g'), 1),
            'fat_g'     => round((float) $todayNutrition->sum('fat_g'), 1),
        ];

        $currentWeight = $user->bodyWeightLogs()
            ->latest('logged_at')
            ->first();

        return response()->json([
            'week_stats'      => $user->weeklyStats(),
            'recent_sessions' => WorkoutSessionResource::collection($recentSessions),
            'today_macros'    => $todayMacros,
            'current_weight'  => $currentWeight ? new BodyWeightLogResource($currentWeight) : null,
        ]);
    }
}
