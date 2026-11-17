<?php

declare(strict_types=1);

use App\Actions\Coach\SendDailyCoaching;
use App\Actions\SmartLog\RecordSmartLog;
use App\Channels\HandleInboundMessage;
use App\Channels\Telegram\TelegramChannel;
use App\Coach\AnswerDepth;
use App\Coach\CoachContext;
use App\Enums\CoachTurnKind;
use App\Enums\MessageKind;
use App\Enums\TrainerPersona;
use App\Jobs\SendDailyCoachingTo;
use App\Models\AiCallRecord;
use App\Models\ChannelIdentity;
use App\Models\User;
use App\Profile\UpdateProfile;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\FakeChatChannel;
use Tests\Fakes\FakeCoachAdvisor;

function fakeChannel(): FakeChatChannel
{
    $fake = new FakeChatChannel;
    app()->instance(TelegramChannel::class, $fake);

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
    $alreadyToday->coachTurns()->create(['question' => 'Morning message', 'answer' => 'Rest day.', 'kind' => CoachTurnKind::Daily]);

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
    $this->assertDatabaseHas('coach_turns', ['user_id' => $user->id, 'kind' => CoachTurnKind::Daily->value, 'answer' => $message, 'depth' => 'plan']);
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
    $user->coachTurns()->create(['question' => 'Morning message', 'answer' => 'Leg extension 3x12 today.', 'kind' => CoachTurnKind::Daily]);
    classifierSays(MessageKind::Unknown);
    $coach = fakeCoach(new FakeCoachAdvisor('Then leg press with the pad, 3x12.'));

    expect(app(HandleInboundMessage::class)->handle(telegramText('no leg extension machine here')))->toBe('Then leg press with the pad, 3x12.')
        ->and($coach->calls[0]['context']['recent_exchanges'][0]['you_wrote_first'])->toBe('Leg extension 3x12 today.');
});

it('sends the morning message only on chats that have a channel', function (): void {
    $user = activeLinkedUser();
    ChannelIdentity::create(['user_id' => $user->id, 'provider' => 'simulate', 'external_id' => (string) $user->id]);
    fakeCoach();
    $channel = fakeChannel();

    app(SendDailyCoaching::class)->handle($user);

    expect(array_column($channel->sent, 'chat_id'))->toBe(['4242']);
});

it('stops the morning message for a person who blocked the bot', function (): void {
    $user = activeLinkedUser();
    fakeCoach();
    fakeChannel()->unreachable('4242');

    app(SendDailyCoaching::class)->handle($user);

    expect($user->fresh()->daily_coaching_hour)->toBeNull();
    $this->assertDatabaseCount('coach_turns', 1);
});

it('still sends on the other chats when one chat is down', function (): void {
    $user = activeLinkedUser();
    ChannelIdentity::create(['user_id' => $user->id, 'provider' => 'telegram', 'external_id' => '5151']);
    fakeCoach();
    $channel = fakeChannel()->down('4242');

    app(SendDailyCoaching::class)->handle($user);

    expect(array_column($channel->sent, 'chat_id'))->toBe(['5151'])
        ->and($user->fresh()->daily_coaching_hour)->toBe(8);
});

it('does not ask the coach again when a retried job finds today\'s message already made', function (): void {
    $user = activeLinkedUser();
    $user->coachTurns()->create(['question' => 'Morning message', 'answer' => 'Leg day.', 'kind' => CoachTurnKind::Daily]);
    $coach = fakeCoach();
    $channel = fakeChannel();

    app()->call([new SendDailyCoachingTo($user), 'handle']);

    expect($coach->calls)->toBe([])
        ->and($channel->sent)->toBe([]);
});

it('never shows the morning message as something the person asked', function (): void {
    $user = activeLinkedUser();
    $user->coachTurns()->create(['question' => 'Morning message for Friday', 'answer' => 'Leg day.', 'kind' => CoachTurnKind::Daily]);
    $this->travel(1)->minutes();
    $asked = $user->coachTurns()->create(['question' => 'what next?', 'answer' => 'Rows.', 'kind' => CoachTurnKind::Asked]);
    $profile = fakeProfileBuilder();

    app(UpdateProfile::class)->handle($user);

    expect(CoachContext::for($user)->toArray()['recent_exchanges'])->toBe([
        ['at' => $asked->created_at->subMinute()->format('Y-m-d H:i'), 'you_wrote_first' => 'Leg day.'],
        ['at' => $asked->created_at->format('Y-m-d H:i'), 'they_asked' => 'what next?', 'you_answered' => 'Rows.'],
    ])
        ->and(collect($profile->calls[0]['logs'])->where('type', 'coach_question')->pluck('message')->all())->toBe(['what next?']);
});

it('tries again in ten minutes when the AI is down, and says nothing yet', function (): void {
    $user = activeLinkedUser();
    fakeCoach()->failing();
    $channel = fakeChannel();
    $job = new SendDailyCoachingTo($user)->withFakeQueueInteractions();

    app()->call($job->handle(...));

    $job->assertReleased(delay: 600);
    expect($channel->sent)->toBe([]);
});

it('tells the person once when the last try fails, instead of skipping the day in silence', function (): void {
    $user = activeLinkedUser(chatId: '5151');
    fakeCoach()->failing();
    $channel = fakeChannel();
    $job = new SendDailyCoachingTo($user)->withFakeQueueInteractions();
    $job->job->attempts = 3;

    app()->call($job->handle(...));

    $job->assertNotReleased();
    expect($channel->sent)->toHaveCount(1)
        ->and($channel->sent[0]['chat_id'])->toBe('5151')
        ->and($channel->sent[0]['text'])->toBe(SendDailyCoaching::COULD_NOT_WRITE);
});
