<?php

declare(strict_types=1);

use App\Actions\Conversation\KeepMessage;
use App\Channels\HandleInboundMessage;
use App\Channels\Telegram\TelegramChannel;
use App\Coach\CoachContext;
use App\Enums\ChatProvider;
use App\Jobs\ProcessWaitingMessage;
use App\Models\WaitingMessage;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\FakeChatChannel;
use Tests\Fakes\FakeSmartLogParser;

/*
 * 7 Nov 14:21: a connection error lost Hiren's bench message; he resent it
 * by hand and sets were counted twice. Now the message is kept, retried and
 * answered in the chat by itself.
 */

function aiDownForOneMessage(string $text = 'benched 100 3x5'): WaitingMessage
{
    Queue::fake();
    linkedUser();
    fakeSmartLogParser(FakeSmartLogParser::workout()->failing());
    app(HandleInboundMessage::class)->handle(telegramText($text));

    return WaitingMessage::query()->sole();
}

function runRetry(WaitingMessage $waiting): FakeChatChannel
{
    $channel = new FakeChatChannel;
    app()->instance(TelegramChannel::class, $channel);
    app()->call([new ProcessWaitingMessage($waiting), 'handle']);

    return $channel;
}

it('schedules the first retry a minute later', function (): void {
    aiDownForOneMessage();

    Queue::assertPushed(ProcessWaitingMessage::class, fn (ProcessWaitingMessage $job): bool => $job->delay !== null);
    expect(ProcessWaitingMessage::delayBefore(1))->toBe(60);
});

it('answers the kept message in the chat once the AI is back', function (): void {
    $waiting = aiDownForOneMessage();
    fakeSmartLogParser(FakeSmartLogParser::workout());

    $channel = runRetry($waiting);

    expect($channel->sent)->toHaveCount(1)
        ->and($channel->sent[0]['chat_id'])->toBe('4242')
        ->and($channel->sent[0]['text'])->toStartWith("About \"benched 100 3x5\":\n\nLogged:")
        ->and($waiting->fresh()->status)->toBe(WaitingMessage::DONE);
    $this->assertDatabaseCount('activity_logs', 1);
});

it('waits longer and stays quiet while tries remain', function (): void {
    $waiting = aiDownForOneMessage();

    $channel = runRetry($waiting);

    expect($channel->sent)->toBe([])
        ->and($waiting->fresh()->status)->toBe(WaitingMessage::WAITING)
        ->and($waiting->fresh()->attempts)->toBe(1);
});

it('says so after the last try instead of going silent', function (): void {
    $waiting = aiDownForOneMessage();
    $waiting->update(['attempts' => ProcessWaitingMessage::maxTries() - 1]);

    $channel = runRetry($waiting);

    expect($channel->sent[0]['text'])->toBe('I still could not process "benched 100 3x5" after several tries. Send it again when you like.')
        ->and($waiting->fresh()->status)->toBe(WaitingMessage::FAILED);
});

it('handles a resent copy once and drops the kept one', function (): void {
    $waiting = aiDownForOneMessage();
    fakeSmartLogParser(FakeSmartLogParser::workout());

    app(HandleInboundMessage::class)->handle(telegramText('benched 100 3x5'));
    $channel = runRetry($waiting);

    expect($waiting->fresh()->status)->toBe(WaitingMessage::RESENT)
        ->and($channel->sent)->toBe([]);
    $this->assertDatabaseCount('activity_logs', 1);
});

it('quotes a long message briefly', function (): void {
    $waiting = aiDownForOneMessage("Ended up going gym today\n\nI'm starting with bench press barbell, sets of 5 reps");

    expect($waiting->excerpt())->toBe("Ended up going gym today I'm starting with bench press barb…");
});

it('tells the coach which messages are still waiting', function (): void {
    $waiting = aiDownForOneMessage();

    expect(CoachContext::for($waiting->user)->toArray()['messages_not_handled_yet'])->toBe([
        ['sent_at' => $waiting->created_at->format('Y-m-d H:i'), 'message' => 'benched 100 3x5'],
    ]);
});

it('keeps the reply text the person sees', function (): void {
    expect(KeepMessage::REPLY)->toContain('no need to send it again');
});

it('answers a kept message from a chat without a channel and sends nothing', function (): void {
    $waiting = aiDownForOneMessage();
    $waiting->update(['provider' => ChatProvider::Simulate]);
    fakeSmartLogParser(FakeSmartLogParser::workout());

    expect(runRetry($waiting)->sent)->toBe([])
        ->and($waiting->fresh()->status)->toBe(WaitingMessage::DONE);
    $this->assertDatabaseCount('activity_logs', 1);
});

/*
 * Safe to repeat (audit 2026-11-08 tier 1 item 5): the row is claimed before
 * the AI is asked, the reply is stored before it is sent, a bug ends it with
 * one message instead of ten silent retries.
 */

it('does nothing when another worker has already claimed the kept message', function (): void {
    $waiting = aiDownForOneMessage();
    $waiting->update(['status' => WaitingMessage::PROCESSING]);
    $parser = fakeSmartLogParser(FakeSmartLogParser::workout());

    expect(runRetry($waiting)->sent)->toBe([])
        ->and($parser->calls)->toBe([]);
    $this->assertDatabaseCount('activity_logs', 0);
});

it('answers a resend with "on it" while the kept copy is being handled', function (): void {
    $waiting = aiDownForOneMessage();
    $waiting->update(['status' => WaitingMessage::PROCESSING]);
    $parser = fakeSmartLogParser(FakeSmartLogParser::workout());

    $reply = app(HandleInboundMessage::class)->handle(telegramText('benched 100 3x5'));

    expect($reply)->toBe(KeepMessage::BEING_HANDLED)
        ->and($parser->calls)->toBe([]);
});

it('sends the stored reply again without logging again when only the send failed', function (): void {
    $waiting = aiDownForOneMessage();
    fakeSmartLogParser(FakeSmartLogParser::workout());
    $down = (new FakeChatChannel)->down('4242');
    app()->instance(TelegramChannel::class, $down);

    app()->call([new ProcessWaitingMessage($waiting), 'handle']);
    expect($down->sent)->toBe([])
        ->and($waiting->fresh()->status)->toBe(WaitingMessage::ANSWERED);

    $channel = runRetry($waiting);

    expect($channel->sent)->toHaveCount(1)
        ->and($channel->sent[0]['text'])->toStartWith("About \"benched 100 3x5\":\n\nLogged:")
        ->and($waiting->fresh()->status)->toBe(WaitingMessage::DONE);
    $this->assertDatabaseCount('activity_logs', 1);
});

it('tells the person once and stops when the retry hits a bug', function (): void {
    $waiting = aiDownForOneMessage();
    fakeSmartLogParser(FakeSmartLogParser::workout()->broken());
    $channel = new FakeChatChannel;
    app()->instance(TelegramChannel::class, $channel);
    $job = new ProcessWaitingMessage($waiting);

    expect(fn () => app()->call($job->handle(...)))->toThrow(RuntimeException::class);
    $job->failed(new RuntimeException('Fake bug'));

    expect($waiting->fresh()->status)->toBe(WaitingMessage::FAILED)
        ->and($channel->sent)->toHaveCount(1)
        ->and($channel->sent[0]['text'])->toBe('I could not process "benched 100 3x5" because of a fault on my side. Send it again when you like.')
        ->and($job->maxExceptions)->toBe(1);
});

it('does not tell the coach that the message it is answering is still waiting', function (): void {
    $waiting = aiDownForOneMessage();
    $waiting->update(['status' => WaitingMessage::PROCESSING]);

    expect(CoachContext::for($waiting->user)->toArray()['messages_not_handled_yet'])->toBe([]);
});

it('does not keep a message when the parser has a bug, so it is not retried as an outage', function (): void {
    Queue::fake();
    linkedUser();
    fakeSmartLogParser(FakeSmartLogParser::workout()->broken());

    expect(fn () => app(HandleInboundMessage::class)->handle(telegramText('benched 100 3x5')))->toThrow(RuntimeException::class);
    expect(WaitingMessage::count())->toBe(0);
    Queue::assertNotPushed(ProcessWaitingMessage::class);
});
