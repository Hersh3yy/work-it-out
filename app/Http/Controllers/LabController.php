<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Interpretation\Lab\ClassifierLab;
use App\Interpretation\Lab\LabResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Classifier lab: a throwaway page to try the classify step per model in a
 * browser. Local only (LocalOnly middleware). Saves nothing.
 */
final class LabController extends Controller
{
    public function page(): View
    {
        return view('lab.classify', [
            'drivers' => ClassifierLab::DRIVERS,
            'active' => config('ai.classifier.driver'),
            'examples' => array_column(ClassifierLab::labelled(base_path('tests/Evals/classify.txt')), 1),
        ]);
    }

    public function classify(Request $request, ClassifierLab $lab): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
            'drivers' => ['array'],
            'drivers.*' => [Rule::in(ClassifierLab::DRIVERS)],
        ]);

        return response()->json([
            'results' => array_map(
                static fn (LabResult $r): array => $r->toArray(),
                $lab->run($data['text'], $data['drivers'] ?? ClassifierLab::DRIVERS),
            ),
        ]);
    }

    public function evaluate(Request $request, ClassifierLab $lab): JsonResponse
    {
        $data = $request->validate([
            'drivers' => ['array'],
            'drivers.*' => [Rule::in(ClassifierLab::DRIVERS)],
        ]);

        $report = $lab->evaluate(base_path('tests/Evals/classify.txt'), $data['drivers'] ?? ['rules', 'laya']);

        return response()->json([
            'summary' => $report['summary'],
            'cases' => array_map(static fn (array $case): array => [
                'text' => $case['text'],
                'expected' => $case['expected'],
                'results' => array_map(static fn (LabResult $r): array => $r->toArray(), $case['results']),
            ], $report['cases']),
        ]);
    }
}
