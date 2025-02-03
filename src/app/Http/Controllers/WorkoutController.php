<?php

namespace App\Http\Controllers;

use App\Models\Workout;
use App\Services\WorkoutParserService;
use Illuminate\Http\Request;

class WorkoutController extends Controller
{
    public function index(Request $request)
    {
        $query = Workout::with(['exerciseSets.exercise'])->latest();

        // Validate pagination params
        $request->validate([
            'offset' => 'integer|min:0',
            'limit' => 'integer|min:1|max:100',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100'
        ]);

        // If offset is provided, use offset/limit pagination
        if ($request->has('offset')) {
            $limit = $request->input('limit', 15);

            $workouts = $query->offset($request->offset)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'data' => $workouts,
                'meta' => [
                    'offset' => (int) $request->offset,
                    'limit' => (int) $limit,
                    'total' => $query->count()
                ]
            ]);
        }

        // Otherwise use standard page-based pagination
        $perPage = $request->input('per_page', 15);
        return $query->paginate($perPage);
    }

    protected $workoutParser;

    public function __construct(WorkoutParserService $workoutParser)
    {
        $this->workoutParser = $workoutParser;
    }

    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string|min:3'
        ]);

        try {
            // Parse the workout description
            $parsedData = $this->workoutParser->parse($request->description);

            // Create the workout
            $workout = Workout::create([
                'raw_input' => $request->description,
                'parsed_data' => $parsedData,
                'performed_at' => now(),
            ]);

            return response()->json([
                'message' => 'Workout logged successfully',
                'workout' => $workout
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error processing workout',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function show(Workout $workout)
    {
        return response()->json([
            'workout' => $workout->load(['exerciseSets.exercise'])
        ]);
    }

    public function update(Request $request, Workout $workout)
    {
        $request->validate([
            'description' => 'sometimes|required|string|min:3'
        ]);

        $workout->update([
            'raw_input' => $request->description ?? $workout->raw_input
        ]);

        return response()->json([
            'message' => 'Workout updated successfully',
            'workout' => $workout
        ]);
    }

    public function destroy(Workout $workout)
    {
        $workout->delete();

        return response()->json([
            'message' => 'Workout deleted successfully'
        ]);
    }
}
