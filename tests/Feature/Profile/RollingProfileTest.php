<?php

declare(strict_types=1);

use App\Actions\SmartLog\RecordSmartLog;
use App\Actions\SmartLog\RevertSmartLog;
use App\Channels\HandleInboundMessage;
use App\Events\LogRecorded;
use App\Events\LogReverted;
use App\Exceptions\AiUnavailable;
use App\Jobs\BuildProfile;
use App\Listeners\RefreshProfile;
use App\Models\BodyWeightLog;
use App\Models\ProfileVersion;
use App\Models\User;
use App\Profile\ProfileFacts;
use App\Profile\UpdateProfile;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

function logFor(User $user, string $text = 'bench 3x8 80'): string
{
    return app(RecordSmartLog::class)->handle($user, $text, workoutFacts([lift('Bench Press')]))->log->id;
}

it('wires the profile listener to both log events', function (): void {
    Event::fake();

    Event::assertListening(LogRecorded::class, [RefreshProfile::class, 'handleRecorded']);
    Event::assertListening(LogReverted::class, [RefreshProfile::class, 'handleReverted']);
});

it('queues one delayed update per user however many logs arrive', function (): void {
    Queue::fake();
    $user = User::factory()->create();

    logFor($user);
    logFor($user, 'curls 3x10 12');
    logFor($user, 'ran 5k');

    Queue::assertPushed(BuildProfile::class, 1);
    Queue::assertPushed(BuildProfile::class, fn (BuildProfile $job): bool => $job->user->is($user) && $job->delay !== null);
});

it('can be switched off', function (): void {
    Queue::fake();
    config(['profile.rolling.enabled' => false]);

    logFor(User::factory()->create());

    Queue::assertNotPushed(BuildProfile::class);
});

it('writes a version after a log, citing that log and the facts', function (): void {
    $user = User::factory()->create();
    $id = logFor($user);

    $version = $user->profileVersions()->first();

    expect($version->kind)->toBe(ProfileVersion::ROLLING)
        ->and($version->based_on_log_id)->toBe($id)
        ->and($version->model)->toBe('fake')
        ->and($version->notes)->toBe([
            ['text' => 'Logged training recently.', 'cites' => [$id]],
            ['text' => 'No body weight logged yet.', 'cites' => ['facts']],
        ]);
});

it('shows the writer the previous notes and only the last 10 logs, newest first', function (): void {
    Queue::fake();
    $user = User::factory()->create();
    $ids = [];

    foreach (range(1, 12) as $i) {
        $this->travel(1)->minutes();
        $ids[] = logFor($user, "log {$i}");
    }

    $fake = fakeProfileBuilder();
    app(UpdateProfile::class)->handle($user);
    app(UpdateProfile::class)->handle($user);

    expect($fake->calls[0]['logs'])->toHaveCount(10)
        ->and($fake->calls[0]['logs'][0]['id'])->toBe($ids[11])
        ->and($fake->calls[0]['logs'][0]['message'])->toBe('log 12')
        ->and($fake->calls[0]['previous'])->toBe([])
        ->and($fake->calls[1]['previous'])->toBe($user->profileVersions()->skip(1)->first()->notes);
});

it('a full rebuild ignores the previous notes and reads more logs', function (): void {
    Queue::fake();
    $user = User::factory()->create();

    foreach (range(1, 12) as $i) {
        $this->travel(1)->minutes();
        logFor($user, "log {$i}");
    }

    $fake = fakeProfileBuilder();
    app(UpdateProfile::class)->handle($user);
    $version = app(UpdateProfile::class)->handle($user, ProfileVersion::FULL);

    expect($fake->calls[1]['previous'])->toBe([])
        ->and($fake->calls[1]['logs'])->toHaveCount(12)
        ->and($version->kind)->toBe(ProfileVersion::FULL);
});

it('drops citations to logs the writer was not shown, and notes left with none', function (): void {
    Queue::fake();
    $user = User::factory()->create();
    $mine = logFor($user);
    $someoneElses = logFor(User::factory()->create());

    fakeProfileBuilder()->returning([
        ['text' => 'Real.', 'cites' => [$mine, 'made-up-id']],
        ['text' => 'Borrowed.', 'cites' => [$someoneElses]],
        ['text' => 'Uncited.', 'cites' => []],
        ['text' => 'From numbers.', 'cites' => ['facts']],
    ]);

    $version = app(UpdateProfile::class)->handle($user);

    expect($version->notes)->toBe([
        ['text' => 'Real.', 'cites' => [$mine]],
        ['text' => 'From numbers.', 'cites' => ['facts']],
    ]);
});

it('keeps a note citing an older log that still exists, and drops it once that log is undone', function (): void {
    Queue::fake();
    $user = User::factory()->create();
    $old = logFor($user, 'squat 5x5 90');

    fakeProfileBuilder()->returning([['text' => 'Squats heavy.', 'cites' => [$old]]]);
    app(UpdateProfile::class)->handle($user);

    config(['profile.rolling.recent_logs' => 1]);
    $this->travel(1)->minutes();
    logFor($user);

    expect(app(UpdateProfile::class)->handle($user)->notes)->toBe([['text' => 'Squats heavy.', 'cites' => [$old]]]);

    app(RevertSmartLog::class)->handle($user->activityLogs()->find($old));

    expect(app(UpdateProfile::class)->handle($user))->toBeNull();
});

it('caps the number of notes', function (): void {
    Queue::fake();
    $user = User::factory()->create();
    logFor($user);

    fakeProfileBuilder()->returning(array_map(fn (int $i): array => ['text' => "Note {$i}", 'cites' => ['facts']], range(1, 12)));

    expect(app(UpdateProfile::class)->handle($user)->notes)->toHaveCount(8);
});

it('writes nothing for a user with no logs and no notes', function (): void {
    $fake = fakeProfileBuilder();

    expect(app(UpdateProfile::class)->handle(User::factory()->create()))->toBeNull()
        ->and($fake->calls)->toBe([]);
});

it('saves nothing when the AI is down, and the job swallows the outage', function (): void {
    Queue::fake();
    $user = User::factory()->create();
    logFor($user);
    fakeProfileBuilder()->failing();

    expect(fn () => app(UpdateProfile::class)->handle($user))->toThrow(AiUnavailable::class);

    (new BuildProfile($user))->handle(app(UpdateProfile::class));

    expect($user->profileVersions()->count())->toBe(0);
});

it('gives the writer numbers computed by plain code', function (): void {
    $user = User::factory()->create(['training_days_per_week' => 3]);
    BodyWeightLog::create(['user_id' => $user->id, 'logged_at' => today()->subWeek(), 'weight_kg' => 105]);
    BodyWeightLog::create(['user_id' => $user->id, 'logged_at' => today(), 'weight_kg' => 103.5]);

    $facts = ProfileFacts::for($user)->toArray();

    expect($facts['entered_by_user']['training_days_per_week'])->toBe(3)
        ->and($facts['body_weight']['entries'])->toBe(2)
        ->and($facts['body_weight']['change_kg'])->toBe(-1.5)
        ->and($facts['totals']['days_since_last_session'])->toBeNull();
});

it('answers /me with the numbers and the latest notes', function (): void {
    $user = linkedUser();
    logFor($user);

    $reply = app(HandleInboundMessage::class)->handle(telegramText('/me'));

    expect($reply)->toContain('Plan: 4 training days a week')
        ->toContain('Body weight: nothing logged yet')
        ->toContain('- Logged training recently.')
        ->not->toContain('facts');
});

it('says when there are no notes yet', function (): void {
    linkedUser();

    expect(app(HandleInboundMessage::class)->handle(telegramText('/me')))->toContain('No written notes yet');
});

it('prints the profile with sources on the console', function (): void {
    $user = User::factory()->create(['email' => 'hiren@example.com']);
    $id = logFor($user);

    $this->artisan('profile:show', ['email' => 'hiren@example.com'])
        ->expectsOutputToContain('Logged training recently.  ['.$id.']')
        ->assertSuccessful();
});

it('rebuilds on demand', function (): void {
    Queue::fake();
    $user = User::factory()->create(['email' => 'hiren@example.com']);
    logFor($user);

    $this->artisan('profile:rebuild', ['email' => 'hiren@example.com', '--full' => true])
        ->expectsOutputToContain('version')
        ->assertSuccessful();

    expect($user->profileVersions()->first()->kind)->toBe(ProfileVersion::FULL);
    $this->artisan('profile:rebuild')->assertFailed();
});

it('schedules a weekly full rebuild', function (): void {
    $events = collect(app(Schedule::class)->events())->filter(fn ($e): bool => str_contains((string) $e->command, 'profile:rebuild --all --full'));

    expect($events)->toHaveCount(1);
});
