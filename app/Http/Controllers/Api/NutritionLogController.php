<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNutritionLogRequest;
use App\Http\Resources\NutritionLogResource;
use App\Models\NutritionLog;
use App\Services\NutritionParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class NutritionLogController extends Controller
{
    public function __construct(
        private readonly NutritionParserService $nutritionParser
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $todayLogs = $user->nutritionLogs()
            ->today()
            ->orderBy('logged_at')
            ->get();

        $todayByMeal = $todayLogs->groupBy(fn (NutritionLog $log): string => $log->meal_type->value ?? $log->meal_type);

        $todayTotals = [
            'calories'  => (int) $todayLogs->sum('calories'),
            'protein_g' => round((float) $todayLogs->sum('protein_g'), 1),
            'carbs_g'   => round((float) $todayLogs->sum('carbs_g'), 1),
            'fat_g'     => round((float) $todayLogs->sum('fat_g'), 1),
        ];

        $weekSummary = $user->nutritionLogs()
            ->thisWeek()
            ->get()
            ->groupBy(fn (NutritionLog $log): string => $log->logged_at->toDateString())
            ->map(fn ($dayLogs): array => [
                'date'      => $dayLogs->first()->logged_at->toDateString(),
                'calories'  => (int) $dayLogs->sum('calories'),
                'protein_g' => round((float) $dayLogs->sum('protein_g'), 1),
                'carbs_g'   => round((float) $dayLogs->sum('carbs_g'), 1),
                'fat_g'     => round((float) $dayLogs->sum('fat_g'), 1),
            ])
            ->values();

        return response()->json([
            'today' => [
                'by_meal' => $todayByMeal->map(fn ($logs): array => NutritionLogResource::collection($logs)->resolve()),
                'totals'  => $todayTotals,
            ],
            'week_summary' => $weekSummary,
        ]);
    }

    public function store(StoreNutritionLogRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (! empty($data['raw_text'])) {
            $parsed = $this->nutritionParser->parse($data['raw_text']);
            $data   = array_merge($data, $parsed);
        }

        $log = $request->user()->nutritionLogs()->create([
            'logged_at' => $data['logged_at'] ?? now(),
            'meal_type' => $data['meal_type'] ?? 'snack',
            'food_name' => $data['food_name'] ?? $data['raw_text'],
            'calories'  => $data['calories'] ?? null,
            'protein_g' => $data['protein_g'] ?? null,
            'carbs_g'   => $data['carbs_g'] ?? null,
            'fat_g'     => $data['fat_g'] ?? null,
            'notes'     => $data['notes'] ?? null,
        ]);

        return (new NutritionLogResource($log))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Request $request, string $nutritionLog): JsonResponse
    {
        $log = $request->user()
            ->nutritionLogs()
            ->findOrFail($nutritionLog);

        $log->delete();

        return response()->json(['message' => 'Nutrition log deleted.']);
    }
}
