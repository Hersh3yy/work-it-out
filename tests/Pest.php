<?php

declare(strict_types=1);

use App\Channels\InboundMessage;
use App\Contracts\Ai\CoachAdvisor;
use App\Contracts\Ai\LimitationParser;
use App\Contracts\Ai\MessageClassifier;
use App\Contracts\Ai\ProfileBuilder;
use App\Contracts\Ai\SmartLogParser;
use App\Enums\ChatProvider;
use App\Enums\MessageKind;
use App\Models\ChannelIdentity;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeCoachAdvisor;
use Tests\Fakes\FakeLimitationParser;
use Tests\Fakes\FakeMessageClassifier;
use Tests\Fakes\FakeProfileBuilder;
use Tests\Fakes\FakeSmartLogParser;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Unit tests get the Laravel container (config(), app()) but no database by default.
pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', fn () => $this->toBe(1));

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/*
| One-line helpers to swap any AI port for its in-memory fake. Each returns
| the fake instance so tests can queue replies and assert recorded calls.
*/

function fakeSmartLogParser(?FakeSmartLogParser $fake = null): FakeSmartLogParser
{
    $fake ??= FakeSmartLogParser::workout();
    app()->instance(SmartLogParser::class, $fake);

    return $fake;
}

function fakeProfileBuilder(?FakeProfileBuilder $fake = null): FakeProfileBuilder
{
    $fake ??= new FakeProfileBuilder;
    app()->instance(ProfileBuilder::class, $fake);

    return $fake;
}

function fakeCoach(?FakeCoachAdvisor $fake = null): FakeCoachAdvisor
{
    $fake ??= new FakeCoachAdvisor;
    app()->instance(CoachAdvisor::class, $fake);

    return $fake;
}

function fakeLimitationParser(?FakeLimitationParser $fake = null): FakeLimitationParser
{
    $fake ??= new FakeLimitationParser;
    app()->instance(LimitationParser::class, $fake);

    return $fake;
}

/*
| Facts payloads for RecordSmartLog, shaped like a normalized SmartLogParser result.
*/

function workoutFacts(array $exercises, array $overrides = []): array
{
    return array_merge([
        'log_type' => 'workout',
        'summary' => 'Workout',
        'exercises' => $exercises,
        'questions' => [],
    ], $overrides);
}

/*
 * Hiren's superset of 2026-11-15: "10 pushups, 12 lying leg raises, 40 second
 * plank, 4 of those". Saved before this as Plank "?×?" four times.
 */
function supersetFacts(): array
{
    return workoutFacts([
        ['exercise_name' => 'Push-ups', 'sets' => 4, 'reps' => 10, 'bodyweight' => true, 'group' => 'Superset'],
        ['exercise_name' => 'Lying Leg Raise', 'sets' => 4, 'reps' => 12, 'bodyweight' => true, 'group' => 'Superset'],
        ['exercise_name' => 'Plank', 'sets' => 4, 'duration_seconds' => 40, 'bodyweight' => true, 'group' => 'Superset'],
    ]);
}

function lift(string $name, ?float $kg = 80.0, int $sets = 3, int $reps = 8): array
{
    return ['exercise_name' => $name, 'sets' => $sets, 'reps' => $reps, 'weight_kg' => $kg];
}

/*
| A chat message from the linked test sender, and that sender's user.
*/

function telegramText(string $text, string $sender = '4242'): InboundMessage
{
    return new InboundMessage(ChatProvider::Telegram, $sender, $sender, $text, 'Hiren');
}

function linkedUser(): User
{
    $user = User::factory()->create(['training_days_per_week' => 4]);
    ChannelIdentity::create(['user_id' => $user->id, 'provider' => 'telegram', 'external_id' => '4242']);

    return $user;
}

/*
| A classifier that answers a fixed kind for anything the plain rules do not catch.
*/

function classifierSays(MessageKind $kind): FakeMessageClassifier
{
    $fake = new FakeMessageClassifier($kind);
    app()->instance(MessageClassifier::class, $fake);

    return $fake;
}

/*
| Every AI port is bound to its fake in TestCase. A test that exercises a
| real adapter (with Ai::fakeAgent or Http::fake underneath) asks for it here.
*/

function useRealAdapter(string $port): void
{
    app()->forgetInstance($port);
}

/**
 * A session some days back at 18:00 with these exercise entries; an entry's
 * `set_list` becomes its set rows.
 *
 * @param  array<string, mixed>  ...$entries
 */
function trainedDaysAgo(User $user, int $daysAgo, array ...$entries): WorkoutSession
{
    $session = WorkoutSession::factory()->for($user)->create([
        'logged_at' => today()->subDays($daysAgo)->setTime(18, 0),
        'duration_minutes' => null,
        'completed_planned' => true,
    ]);

    foreach (array_values($entries) as $order => $attributes) {
        $sets = $attributes['set_list'] ?? [];
        unset($attributes['set_list']);
        $entry = $session->exerciseEntries()->create(['sets' => null, 'reps' => null, 'weight_kg' => null, ...$attributes, 'sort_order' => $order]);

        if ($sets !== []) {
            $entry->replaceSets($sets);
        }
    }

    return $session;
}
