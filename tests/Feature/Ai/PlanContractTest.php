<?php

declare(strict_types=1);

use App\Enums\PlanType;
use App\Models\User;
use Tests\Fakes\FakePlanGenerator;

it('generates workout and meal plans through the port', function (string $endpoint, PlanType $expected): void {
    $fake = fakePlanGenerator(new FakePlanGenerator('# Custom Fake Plan'));

    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson($endpoint)
        ->assertOk()
        ->assertJsonPath('type', $expected->value)
        ->assertJsonPath('plan', '# Custom Fake Plan');

    expect($fake->calls[0]['type'])->toBe($expected);
})->with([
    'workout' => ['/api/plans/workout', PlanType::Workout],
    'meal' => ['/api/plans/meal', PlanType::Meal],
]);

it('returns 503 when the plan port fails', function (): void {
    fakePlanGenerator((new FakePlanGenerator)->failing());

    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/plans/workout')
        ->assertServiceUnavailable();
});
