<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Tests\Fakes\FakeSmartLogParser;

it('smart-log outage is logged, not swallowed', function (): void {
    fakeSmartLogParser(FakeSmartLogParser::workout()->failing());
    Log::spy();

    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/log', ['message' => 'Benched 100kg today'])
        ->assertStatus(503);

    $this->assertDatabaseCount('workout_sessions', 0);

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message, array $context = []): bool => $message === 'AI call failed'
            && ! str_contains(json_encode($context), 'Benched 100kg today'))
        ->once();
});
