<?php

declare(strict_types=1);

use App\Channels\HandleInboundMessage;
use App\Enums\PrimaryGoal;
use App\Models\ProfilePrompt;
use App\Models\User;
use App\Profile\ProfileCheckIn;
use App\Profile\Questions\MainGoal;
use App\Profile\Questions\TrainingDaysPerWeek;
use Tests\Fakes\FakeSmartLogParser;

function blankProfileUser(): User
{
    $user = linkedUser();
    $user->update(['training_days_per_week' => 0, 'primary_goal' => null]);

    return $user->refresh();
}

it('adds one profile question to a log receipt and records the prompt', function (): void {
    $user = blankProfileUser();
    fakeSmartLogParser(FakeSmartLogParser::workout());

    $reply = app(HandleInboundMessage::class)->handle(telegramText('benched 100 3x5'));

    expect($reply)->toBe("Logged: Bench Press 3x5 @ 100 kg\n\n".app(TrainingDaysPerWeek::class)->ask($user));
    $this->assertDatabaseHas('profile_prompts', ['user_id' => $user->id, 'question_key' => 'training_days_per_week', 'answered_at' => null]);
});

it('saves the answer without AI and fixes the weekly score', function (): void {
    $user = blankProfileUser();
    $parser = fakeSmartLogParser(FakeSmartLogParser::workout());
    $chat = app(HandleInboundMessage::class);

    $chat->handle(telegramText('benched 100 3x5'));

    expect($chat->handle(telegramText('4 days')))->toBe('Saved: you plan to train 4 days a week.')
        ->and($parser->calls)->toHaveCount(1)
        ->and($user->refresh()->training_days_per_week)->toBe(4)
        ->and((float) $user->weekly_adherence_rate)->toBe(25.0);
});

it('asks at most one question a day, then the next one', function (): void {
    $user = blankProfileUser();
    $checkIn = app(ProfileCheckIn::class);

    expect($checkIn->next($user))->not->toBeNull()
        ->and($checkIn->next($user))->toBeNull();

    $checkIn->answer($user, 'three');
    $this->travel(21)->hours();

    expect($checkIn->next($user->refresh()))->toBe(app(MainGoal::class)->ask($user));
});

it('reads a goal from a number or a word', function (string $text, PrimaryGoal $goal): void {
    $user = User::factory()->create(['primary_goal' => null]);

    expect(app(MainGoal::class)->answer($user, $text))->toStartWith('Saved: your main goal is')
        ->and($user->refresh()->primary_goal)->toBe($goal);
})->with([
    ['2', PrimaryGoal::LoseFat],
    ['lose fat', PrimaryGoal::LoseFat],
    ['build muscle mostly', PrimaryGoal::BuildMuscle],
    ['endurance', PrimaryGoal::ImproveEndurance],
]);

it('skips a question and leaves it alone for a week', function (): void {
    $user = blankProfileUser();
    $checkIn = app(ProfileCheckIn::class);
    $checkIn->next($user);

    expect($checkIn->answer($user, 'skip'))->toBe("OK, I won't ask that for a while.");

    $this->travel(21)->hours();
    expect($checkIn->next($user))->toBe(app(MainGoal::class)->ask($user));
    expect(ProfilePrompt::query()->where('question_key', 'training_days_per_week')->first()->skipped_at)->not->toBeNull();
});

it('lets a normal message through when it is not an answer', function (): void {
    $user = blankProfileUser();
    $parser = fakeSmartLogParser(FakeSmartLogParser::workout());
    app(ProfileCheckIn::class)->next($user);

    expect(app(HandleInboundMessage::class)->handle(telegramText('benched 100 3x5')))->toStartWith('Logged: Bench Press')
        ->and($parser->calls)->toHaveCount(1);
});

it('lets the open log question win over the profile question', function (): void {
    $user = blankProfileUser();
    fakeSmartLogParser(FakeSmartLogParser::missingWeight());
    $chat = app(HandleInboundMessage::class);

    $chat->handle(telegramText('incline db press 3x10'));

    expect($chat->handle(telegramText('4')))->toBe('Updated: Incline DB Press 3x10 @ 4 kg')
        ->and($user->refresh()->training_days_per_week)->toBe(0);
});

it('asks nothing once the profile is complete', function (): void {
    $user = User::factory()->create(['training_days_per_week' => 4, 'primary_goal' => PrimaryGoal::LoseFat]);

    expect(app(ProfileCheckIn::class)->next($user))->toBeNull();
});

it('gives the app door the same question with the log receipt', function (): void {
    $user = User::factory()->create(['training_days_per_week' => 0, 'primary_goal' => null]);
    fakeSmartLogParser(FakeSmartLogParser::workout());

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/log', ['message' => 'benched 100 3x5'])
        ->assertCreated()
        ->assertJsonPath('profile_question', app(TrainingDaysPerWeek::class)->ask($user));
});
