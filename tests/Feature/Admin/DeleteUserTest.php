<?php

declare(strict_types=1);

use App\Actions\SmartLog\RecordSmartLog;
use App\Enums\BodyArea;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function everyRowOf(User $user): array
{
    $tables = array_filter(Schema::getTableListing(), static fn (string $table): bool => Schema::hasColumn($table, 'user_id'));

    return collect($tables)->mapWithKeys(static fn (string $table): array => [$table => DB::table($table)->where('user_id', $user->id)->count()])
        ->put('personal_access_tokens', DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count())
        ->filter()
        ->all();
}

it('deletes a tester and every row that is theirs, and keeps everyone else', function (): void {
    $tester = linkedUser();
    $other = User::factory()->create();
    app(RecordSmartLog::class)->handle($tester, 'bench 3x5 60', workoutFacts([lift('Bench Press', 60.0, 3, 5)]));
    app(RecordSmartLog::class)->handle($other, 'squat 3x5 80', workoutFacts([lift('Squat', 80.0, 3, 5)]));
    $tester->limitations()->create(['body_area' => BodyArea::Knee, 'note' => 'sore', 'started_on' => today()]);
    $tester->createToken('app');
    DB::table('ai_calls')->insert(['user_id' => $tester->id, 'agent' => 'CoachAgent', 'provider' => 'mistral', 'model' => 'm', 'input_tokens' => 1, 'output_tokens' => 1, 'created_at' => now()]);

    $this->artisan('user:delete', ['email' => $tester->email, '--force' => true])->assertSuccessful();

    expect(User::query()->find($tester->id))->toBeNull()
        ->and(everyRowOf($tester))->toBe([])
        ->and(DB::table('exercise_entries')->count())->toBe(1)
        ->and(DB::table('ai_calls')->whereNull('user_id')->count())->toBe(1)
        ->and(everyRowOf($other))->not->toBe([]);
});

it('asks before deleting and does nothing on no', function (): void {
    $tester = User::factory()->create();

    $this->artisan('user:delete', ['email' => $tester->email])
        ->expectsConfirmation("Delete {$tester->name} and everything they logged? This cannot be undone.", 'no')
        ->assertFailed();

    expect(User::query()->find($tester->id))->not->toBeNull();
});
