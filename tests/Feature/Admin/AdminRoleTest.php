<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('makes someone an admin by email and takes it away with --revoke', function (): void {
    $user = User::factory()->create(['email' => 'hiren@example.com']);

    $this->artisan('user:admin', ['email' => 'hiren@example.com'])->assertSuccessful();
    expect($user->refresh()->is_admin)->toBeTrue()
        ->and(Gate::forUser($user)->allows('admin'))->toBeTrue();

    $this->artisan('user:admin', ['email' => 'hiren@example.com', '--revoke' => true])->assertSuccessful();
    expect($user->refresh()->is_admin)->toBeFalse()
        ->and(Gate::forUser($user)->allows('admin'))->toBeFalse();
});

it('starts everyone as a normal user', function (): void {
    expect(User::factory()->create()->refresh()->is_admin)->toBeFalse();
});

it('fails on an unknown email', function (): void {
    $this->artisan('user:admin', ['email' => 'nobody@example.com'])->assertFailed();
});
