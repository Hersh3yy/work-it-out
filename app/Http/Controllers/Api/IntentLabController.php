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
        $data = $this->validated($request);

        try {
            return response()->json($classifier->classify($data['text'], $data['driver'] ?? 'laya', $data['criteria'] ?? null));
        } catch (AiUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }
    }

    public function explain(Request $request, IntentClassifier $classifier): JsonResponse
    {
        $data = $this->validated($request, maxText: 300);

        try {
            return response()->json($classifier->explain($data['text'], $data['driver'] ?? 'laya', $data['criteria'] ?? null));
        } catch (AiUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }
    }

    /**
     * criteria: optional other option wording, {"option": "what it means"}, 2 to 8 options.
     *
     * @return array{text: string, driver?: string, criteria?: array<string, string>}
     */
    private function validated(Request $request, int $maxText = 2000): array
    {
        return $request->validate([
            'text' => ['required', 'string', "max:{$maxText}"],
            'driver' => ['nullable', Rule::in(['laya', 'jev'])],
            'criteria' => ['nullable', 'array', 'min:2', 'max:8'],
            'criteria.*' => ['required', 'string', 'max:300'],
        ]);
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
