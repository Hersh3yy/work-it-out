<?php

namespace App\Http\Controllers;

use App\Models\Workout;
use App\Services\WorkoutParserService;
use Illuminate\Http\Request;

class WorkoutController extends Controller
{
    public function index()
    {
        return response()->json([
            'workouts' => Workout::with(['exerciseSets.exercise'])->latest()->get()
        ]);
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
