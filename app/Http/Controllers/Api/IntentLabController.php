<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Ai\IntentClassifier;
use App\Enums\Intent;
use App\Exceptions\AiUnavailable;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The intent classifier on its own, as plain JSON for the Astro lab
 * (classifier-lab/). Local and testing only; saves nothing.
 */
final class IntentLabController extends Controller
{
    public function classify(Request $request, IntentClassifier $classifier): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
            'driver' => ['nullable', Rule::in(['laya', 'jev'])],
        ]);

        try {
            return response()->json($classifier->classify($data['text'], $data['driver'] ?? 'laya'));
        } catch (AiUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }
    }

    public function evaluate(Request $request, IntentClassifier $classifier): JsonResponse
    {
        $data = $request->validate(['driver' => ['nullable', Rule::in(['laya', 'jev'])]]);

        return response()->json($classifier->evaluate(base_path('tests/Evals/intent.txt'), $data['driver'] ?? 'laya'));
    }

    public function options(): JsonResponse
    {
        return response()->json(['question' => IntentClassifier::QUESTION, 'options' => Intent::criteria()]);
    }
}
