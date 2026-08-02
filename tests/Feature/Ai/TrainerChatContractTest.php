<?php

declare(strict_types=1);

use App\Enums\TrainerPersona;
use App\Models\User;
use Tests\Fakes\FakeTrainerChat;

it('returns the faked trainer reply through the port', function (): void {
    $fake = fakeTrainerChat(new FakeTrainerChat('Drop and give me twenty, Soldier!'));

    $user = User::factory()->asLtSurge()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/trainer/chat', ['message' => 'How did I do this week?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Drop and give me twenty, Soldier!')
        ->assertJsonPath('coach', 'lt_surge');

    expect($fake->calls)->toHaveCount(1)
        ->and($fake->calls[0]['message'])->toBe('How did I do this week?');
});

it('passes the coach override through the port', function (): void {
    $fake = fakeTrainerChat();

    $user = User::factory()->asLtSurge()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/trainer/chat', ['message' => 'Hello', 'coach' => 'latika'])
        ->assertOk()
        ->assertJsonPath('coach', 'latika');

    expect($fake->calls[0]['coach'])->toBe(TrainerPersona::Latika);
});

it('degrades to the persona down-message when the trainer port fails', function (): void {
    fakeTrainerChat((new FakeTrainerChat)->failing());

    $user = User::factory()->asLtSurge()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/trainer/chat', ['message' => 'Anyone there?'])
        ->assertServiceUnavailable()
        ->assertJsonPath('reply', TrainerPersona::LtSurge->downMessage());
});
