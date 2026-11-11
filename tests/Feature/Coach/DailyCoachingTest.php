<?php

declare(strict_types=1);

use App\Actions\Coach\SendDailyCoaching;
use App\Actions\SmartLog\RecordSmartLog;
use App\Channels\HandleInboundMessage;
use App\Coach\AnswerDepth;
use App\Contracts\Channels\ChatChannel;
use App\Enums\MessageKind;
use App\Enums\TrainerPersona;
use App\Jobs\SendDailyCoachingTo;
use App\Models\AiCallRecord;
use App\Models\ChannelIdentity;
use App\Models\CoachTurn;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\FakeChatChannel;
use Tests\Fakes\FakeCoachAdvisor;

function fakeChannel(): FakeChatChannel
{
    $fake = new FakeChatChannel;
    app()->instance(ChatChannel::class, $fake);

    return $fake;
}

function activeLinkedUser(?int $hour = 8, string $chatId = '4242', bool $loggedRecently = true): User
{
    $user = User::factory()->create(['daily_coaching_hour' => $hour]);
    ChannelIdentity::create(['user_id' => $user->id, 'provider' => 'telegram', 'external_id' => $chatId]);

    if ($loggedRecently) {
        app(RecordSmartLog::class)->handle($user, 'bench', workoutFacts([lift('Bench Press')]));
    }

    return $user;
}

it('runs every hour from the scheduler', function (): void {
    $events = collect(app(Schedule::class)->events())->filter(fn ($e): bool => str_contains((string) $e->command, 'coach:daily'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 * * * *');
});

it('queues the morning message for the people whose hour it is', function (): void {
    Queue::fake();
    $this->travelTo(now()->setTimezone('Europe/Amsterdam')->setTime(8, 2));
    $due = activeLinkedUser(8, '1');
    $later = activeLinkedUser(9, '2');
    $unlinked = User::factory()->create(['daily_coaching_hour' => 8]);
    $silent = activeLinkedUser(8, '3', loggedRecently: false);
    $off = activeLinkedUser(null, '4');
    $alreadyToday = activeLinkedUser(8, '5');
    $alreadyToday->coachTurns()->create(['question' => 'Morning message', 'answer' => 'Rest day.', 'kind' => CoachTurn::DAILY]);

    $this->artisan('coach:daily')->expectsOutputToContain('Queued 1 morning message')->assertSuccessful();

    Queue::assertPushed(SendDailyCoachingTo::class, 1);
    Queue::assertPushed(SendDailyCoachingTo::class, fn (SendDailyCoachingTo $job): bool => $job->user->is($due));
    expect(User::dueForMorningMessage()->pluck('id')->all())->toBe([$due->id])
        ->and([$later->id, $unlinked->id, $silent->id, $off->id, $alreadyToday->id])->not->toContain($due->id);
});

it('sends the coach\'s morning message on every linked chat and keeps it as a daily turn', function (): void {
    $this->travelTo(now()->setTimezone('Europe/Amsterdam')->setTime(8, 0));
    $user = activeLinkedUser();
    $user->update(['trainer_persona' => TrainerPersona::Shen]);
    $coach = fakeCoach(new FakeCoachAdvisor("Wednesday: legs without the spine.\n1. Leg extension 3x12"));
    $channel = fakeChannel();

    $message = app(SendDailyCoaching::class)->handle($user);

    expect($message)->toStartWith('Wednesday: legs')
        ->and($channel->sent)->toBe([['chat_id' => '4242', 'text' => $message]])
        ->and($coach->calls[0]['persona'])->toBe(TrainerPersona::Shen)
        ->and($coach->calls[0]['depth'])->toBe(AnswerDepth::Plan)
        ->and($coach->calls[0]['question'])->toContain('Morning message for '.now()->timezone('Europe/Amsterdam')->format('l'))
        ->and($coach->calls[0]['context']['recent_logs'][0]['message'])->toBe('bench');
    $this->assertDatabaseHas('coach_turns', ['user_id' => $user->id, 'kind' => CoachTurn::DAILY, 'answer' => $message, 'depth' => 'plan']);
});

it('sends nothing to a person over their monthly budget', function (): void {
    config(['ai.monthly_tokens_per_user' => 100]);
    $user = activeLinkedUser();
    AiCallRecord::create(['user_id' => $user->id, 'agent' => 'x', 'input_tokens' => 150, 'created_at' => now()]);
    $coach = fakeCoach();
    $channel = fakeChannel();

    expect(app(SendDailyCoaching::class)->handle($user))->toBeNull()
        ->and($coach->calls)->toBe([])
        ->and($channel->sent)->toBe([]);
});

it('sends to one person now from the console', function (): void {
    $user = activeLinkedUser();
    $user->update(['email' => 'hiren@example.com']);
    fakeCoach(new FakeCoachAdvisor('Tuesday: rest, walk 30 min.'));
    $channel = fakeChannel();

    $this->artisan('coach:daily', ['--user' => 'hiren@example.com'])->expectsOutputToContain('Tuesday: rest')->assertSuccessful();
    $this->artisan('coach:daily', ['--user' => 'nobody@example.com'])->assertFailed();

    expect($channel->sent)->toHaveCount(1);
});

it('lets the person move or stop the morning message with /daily', function (): void {
    $user = linkedUser();
    $chat = app(HandleInboundMessage::class);

    expect($chat->handle(telegramText('/daily')))->toContain('08:00')
        ->and($chat->handle(telegramText('/daily 7')))->toContain('07:00')
        ->and($user->fresh()->daily_coaching_hour)->toBe(7)
        ->and($chat->handle(telegramText('/daily@FeetnessBot 21:00')))->toContain('21:00')
        ->and($chat->handle(telegramText('/daily off')))->toContain('no more morning messages')
        ->and($user->fresh()->daily_coaching_hour)->toBeNull()
        ->and($chat->handle(telegramText('/daily')))->toContain('is off')
        ->and($chat->handle(telegramText('/daily on')))->toContain('08:00')
        ->and($chat->handle(telegramText('/daily 25')))->toContain('Say /daily 7');
});

it('treats a reply right after the morning message as talk with the coach', function (): void {
    $user = activeLinkedUser();
    $user->coachTurns()->create(['question' => 'Morning message', 'answer' => 'Leg extension 3x12 today.', 'kind' => CoachTurn::DAILY]);
    classifierSays(MessageKind::Unknown);
    $coach = fakeCoach(new FakeCoachAdvisor('Then leg press with the pad, 3x12.'));

    expect(app(HandleInboundMessage::class)->handle(telegramText('no leg extension machine here')))->toBe('Then leg press with the pad, 3x12.')
        ->and($coach->calls[0]['context']['recent_exchanges'][0]['you_answered'])->toBe('Leg extension 3x12 today.');
});
