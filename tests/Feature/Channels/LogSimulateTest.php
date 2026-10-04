<?php

declare(strict_types=1);

use App\Models\User;
use Tests\Fakes\FakeSmartLogParser;

it('runs one message through the chat path and prints the reply', function (): void {
    fakeSmartLogParser(FakeSmartLogParser::workout());
    $user = User::factory()->create();

    $this->artisan("log:simulate --user={$user->id} \"benched 100 3x5\"")
        ->expectsOutputToContain('Logged: Bench Press 3x5 @ 100 kg')
        ->assertSuccessful();

    $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'source' => 'simulate']);
    $this->assertDatabaseHas('channel_identities', ['user_id' => $user->id, 'provider' => 'simulate']);
});

it('replays a file, skipping comments and blank lines', function (): void {
    fakeSmartLogParser(FakeSmartLogParser::workout());
    $user = User::factory()->create();
    $file = tempnam(sys_get_temp_dir(), 'evals');
    file_put_contents($file, "# my session\n\nbench 3x5 100\n/undo\n");

    $this->artisan("log:simulate --user={$user->id} --file={$file}")
        ->expectsOutputToContain('Logged: Bench Press 3x5 @ 100 kg')
        ->expectsOutputToContain('Removed: Bench Press 3x5 @ 100 kg')
        ->assertSuccessful();

    $this->assertDatabaseCount('activity_logs', 0);
    unlink($file);
});

it('fails clearly for an unknown user or no message', function (): void {
    $this->artisan('log:simulate --user=999 "x"')->assertFailed();

    User::factory()->create(['id' => 1]);
    $this->artisan('log:simulate')->assertFailed();
});
