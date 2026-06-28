<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBodyWeightLogRequest;
use App\Http\Resources\BodyWeightLogResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class BodyWeightController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $logs = $user->bodyWeightLogs()
            ->orderBy('logged_at')
            ->paginate(50);

        // Chart-ready data: last 90 days of date/weight pairs for Flutter charts.
        $chartData = $user->bodyWeightLogs()
            ->where('logged_at', '>=', now()->subDays(90)->toDateString())
            ->orderBy('logged_at')
            ->get(['logged_at', 'weight_kg'])
            ->map(fn ($log): array => [
                'date'      => $log->logged_at->toDateString(),
                'weight_kg' => (float) $log->weight_kg,
            ]);

        return response()->json([
            'logs'       => BodyWeightLogResource::collection($logs),
            'chart_data' => $chartData,
        ]);
    }

    public function store(StoreBodyWeightLogRequest $request): JsonResponse
    {
        $log = $request->user()->bodyWeightLogs()->create($request->validated());

        // Keep current_weight_kg in sync on the user record.
        $request->user()->update(['current_weight_kg' => $request->validated('weight_kg')]);

        return (new BodyWeightLogResource($log))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
