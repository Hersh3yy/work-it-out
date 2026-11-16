<?php

declare(strict_types=1);

use App\Actions\Coach\SendDailyCoaching;
use App\Channels\HandleInboundMessage;
use App\Channels\Telegram\TelegramChannel;
use App\Coach\CoachContext;
use App\Enums\MessageKind;
use App\Fitness\QualitySheet;
use App\Goals\GoalKind;
use App\Models\User;
use App\Profile\ProfileFacts;
use Carbon\CarbonImmutable;
use Tests\Fakes\FakeChatChannel;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
});

function deadliftYesterdayAndARave(): User
{
    $user = linkedUser();
    $user->update(['current_weight_kg' => 80]);
    trainedDaysAgo($user, 1, ['exercise_name' => 'Deadlift', 'set_list' => [['reps' => 5, 'weight_kg' => 120]]]);
    $user->goals()->create(['kind' => GoalKind::Event, 'name' => 'rave', 'description' => 'rave on 13 December', 'target_on' => '2026-12-13']);

    return $user;
}

it('puts every quality in the facts with its energy, level, power, readiness and last training day', function (): void {
    $user = deadliftYesterdayAndARave();

    $qualities = ProfileFacts::for($user)->toArray()['qualities'];

    expect($qualities)->toHaveCount(6)
        ->and($qualities[0])->toBe([
            'energy' => 'lift',
            'quality' => 'strength',
            'level' => 0,
            'power' => 1.17,
            'power_measures' => ['exercise' => 'Deadlift', 'e1rm_kg' => 140.0, 'body_weight_kg' => 80.0, 'e1rm_per_body_weight' => 1.75, 'standard' => 1.5],
            'readiness' => 40,
            'last_trained_on' => '2026-11-14',
            'limited_by' => [],
        ])
        ->and($qualities[4]['power'])->toBeNull();
});

it('puts goals in the facts with the time left and how ready each demanded quality is', function (): void {
    $user = deadliftYesterdayAndARave();

    expect(ProfileFacts::for($user)->toArray()['goals'])->toBe([[
        'name' => 'rave',
        'kind' => 'event',
        'in_their_words' => 'rave on 13 December',
        'date' => '2026-12-13',
        'readiness' => ['phase' => 'build', 'days_left' => 28, 'qualities' => ['endurance' => 'not_measured', 'recovery' => 'ready', 'mobility' => 'not_measured']],
    ]]);
});

it('names every reason a quality is still recovering', function (): void {
    $user = deadliftYesterdayAndARave();
    $user->limitations()->create(['body_area' => 'lower_back', 'severity' => 'mild', 'note' => 'stiff', 'started_on' => today()]);

    expect(QualitySheet::for($user)->todayLine())->toContain('strength (40, trained Sat 14 Nov, lower back)');
});

it('gives the coach the qualities and the goals', function (): void {
    $facts = CoachContext::for(deadliftYesterdayAndARave())->toArray()['facts'];

    expect($facts)->toHaveKeys(['qualities', 'goals']);
});

it('names the qualities a workout trained on its receipt', function (): void {
    linkedUser();
    classifierSays(MessageKind::Workout);
    fakeSmartLogParser();

    expect(app(HandleInboundMessage::class)->handle(telegramText('bench 3x5 100')))->toContain("\nTrained: strength");
});

it('names today\'s qualities and the nearest goal in the morning brief', function (): void {
    $user = deadliftYesterdayAndARave();
    $coach = fakeCoach();
    app()->instance(TelegramChannel::class, new FakeChatChannel);

    app(SendDailyCoaching::class)->handle($user);

    expect($coach->calls[0]['question'])
        ->toContain('Fresh today: power, endurance, core, mobility, recovery. Still recovering: strength (40, trained Sat 14 Nov).')
        ->toContain('Nearest goal: rave on Sun 13 Dec, 28 days to go: build. Endurance not measured yet, recovery ready, mobility not measured yet.');
});

it('gives the app the qualities and the goals on the overview', function (): void {
    $user = deadliftYesterdayAndARave();

    $this->actingAs($user, 'sanctum')->getJson('/api/me/overview')
        ->assertOk()
        ->assertJsonCount(6, 'qualities')
        ->assertJsonPath('qualities.0.quality', 'strength')
        ->assertJsonPath('qualities.0.readiness', 40)
        ->assertJsonPath('goals.0.name', 'rave')
        ->assertJsonPath('goals.0.readiness.phase', 'build')
        ->assertJsonMissingPath('facts.qualities');
});
