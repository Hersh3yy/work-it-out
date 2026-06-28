<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkoutSessionRequest;
use App\Http\Resources\WorkoutSessionResource;
use App\Jobs\UpdateUserStats;
use App\Models\WorkoutSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class WorkoutSessionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $sessions = $request->user()
            ->workoutSessions()
            ->with('exerciseEntries')
            ->latest('logged_at')
            ->paginate(15);

        return WorkoutSessionResource::collection($sessions);
    }

    public function store(StoreWorkoutSessionRequest $request): JsonResponse
    {
        $session = DB::transaction(function () use ($request): WorkoutSession {
            /** @var WorkoutSession $session */
            $session = $request->user()->workoutSessions()->create(
                $request->only([
                    'logged_at',
                    'duration_minutes',
                    'perceived_exertion',
                    'energy_level',
                    'notes',
                    'completed_planned',
                ])
            );

            foreach ($request->validated('exercises') as $index => $exerciseData) {
                $session->exerciseEntries()->create([
                    ...$exerciseData,
                    'sort_order' => $index,
                ]);
            }

            return $session;
        });

        UpdateUserStats::dispatch($request->user());

        return (new WorkoutSessionResource($session->load('exerciseEntries')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, string $workout): WorkoutSessionResource
    {
        $session = $request->user()
            ->workoutSessions()
            ->with('exerciseEntries')
            ->findOrFail($workout);

        return new WorkoutSessionResource($session);
    }

    public function destroy(Request $request, string $workout): JsonResponse
    {
        $session = $request->user()
            ->workoutSessions()
            ->findOrFail($workout);

        $session->delete();

        return response()->json(['message' => 'Workout session deleted.']);
    }
}
