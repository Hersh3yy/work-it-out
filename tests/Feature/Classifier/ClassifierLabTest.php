<?php

declare(strict_types=1);

use App\Interpretation\Lab\ClassifierLab;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::fake(['*' => Http::response(['answers' => ['kind' => [
        'choice' => 'workout', 'answer_confidence' => 0.9, 'probabilities' => ['workout' => 0.9, 'unknown' => 0.1],
    ]]])]);
});

it('runs each driver on its own and reports failures instead of throwing', function (): void {
    config(['ai.classifier.jev.url' => 'http://unreachable.invalid']);
    Http::fake(['unreachable.invalid/*' => fn () => throw new ConnectionException('no'), '*' => Http::response(['answers' => ['kind' => ['choice' => 'workout', 'answer_confidence' => 0.9]]])]);

    $results = collect(app(ClassifierLab::class)->run('squat 5x5 100', ['rules', 'laya', 'jev']))->keyBy('driver');

    expect($results['rules']->kind)->toBeNull()
        ->and($results['laya']->kind)->toBe('workout')
        ->and($results['jev']->error)->toContain('cannot reach');
});

it('scores a labelled file per driver', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'cls');
    file_put_contents($file, "# comment\nworkout | squat 5x5 100\nbody_weight | 104.5kg\ncoach_question | what now?\nnot a line\n");

    $report = app(ClassifierLab::class)->evaluate($file, ['rules', 'laya']);

    expect($report['cases'])->toHaveCount(3)
        ->and($report['summary']['rules']['correct'])->toBe(1)
        ->and($report['summary']['laya']['correct'])->toBe(1)
        ->and($report['summary']['laya']['total'])->toBe(3);
});

it('prints a side by side table from artisan', function (): void {
    $this->artisan('classify:try', ['text' => 'squat 5x5 100', '--driver' => ['rules', 'laya']])
        ->expectsOutputToContain('workout')
        ->assertExitCode(0);
});

it('scores the shipped eval file from artisan', function (): void {
    $this->artisan('classify:try', ['--file' => base_path('tests/Evals/classify.txt'), '--driver' => ['rules']])
        ->expectsOutputToContain('accuracy')
        ->assertExitCode(0);
});

it('serves the lab page and answers JSON locally', function (): void {
    $this->get('/lab/classify')->assertOk()->assertSee('Classifier lab');

    $this->postJson('/lab/classify', ['text' => 'squat 5x5 100', 'drivers' => ['laya']])
        ->assertOk()
        ->assertJsonPath('results.0.driver', 'laya')
        ->assertJsonPath('results.0.kind', 'workout');
});

it('does not exist in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $this->get('/lab/classify')->assertNotFound();
    // CSRF answers 419 before LocalOnly answers 404; either way the lab never runs.
    expect($this->postJson('/lab/classify', ['text' => 'x'])->status())->toBeIn([404, 419]);
    Http::assertNothingSent();
});
