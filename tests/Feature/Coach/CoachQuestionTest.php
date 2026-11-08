<?php

declare(strict_types=1);

use App\Actions\Conversation\KeepMessage;
use App\Channels\HandleInboundMessage;
use App\Coach\AnswerDepth;
use App\Coach\CoachContext;
use App\Enums\BodyArea;
use App\Enums\MessageKind;
use App\Enums\TrainerPersona;
use App\Interpretation\Interpreter;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\FakeCoachAdvisor;
use Tests\Fakes\FakeSmartLogParser;

/*
 * The asking door: a question gets one model call over the living context
 * and is never saved as a fact (PLAN.md section 4, 2026-11-01).
 */

it('answers a coach question from the living context and saves no log', function (): void {
    $user = linkedUser();
    $user->update(['trainer_persona' => TrainerPersona::Shen]);
    $user->limitations()->create(['body_area' => BodyArea::LowerBack, 'note' => 'strained it deadlifting', 'started_on' => today()->subDays(3)]);
    classifierSays(MessageKind::CoachQuestion);
    $coach = fakeCoach(new FakeCoachAdvisor("1. Chest-supported row 4x12\n2. Leg extension 3x15\n3. Incline walk 10 min"));

    $reply = app(HandleInboundMessage::class)->handle(telegramText('30 min, 3 things, go'));

    expect($reply)->toStartWith('1. Chest-supported row')
        ->and($coach->calls)->toHaveCount(1)
        ->and($coach->calls[0]['persona'])->toBe(TrainerPersona::Shen)
        ->and($coach->calls[0]['question'])->toBe('30 min, 3 things, go')
        ->and($coach->calls[0]['depth'])->toBe(AnswerDepth::Plan)
        ->and($coach->calls[0]['context']['limitations'][0]['area'])->toBe('lower back')
        ->and($coach->calls[0]['context']['limitations'][0]['day'])->toBe(4)
        ->and($coach->calls[0]['context']['limitations'][0]['exercises_that_load_it'])->toContain('deadlift', 'squat');
    $this->assertDatabaseCount('activity_logs', 0);
    $this->assertDatabaseHas('coach_turns', ['user_id' => $user->id, 'question' => '30 min, 3 things, go', 'depth' => 'plan', 'model' => 'fake-coach']);
});

it('gives the coach what was logged today and the last exchange', function (): void {
    $user = linkedUser();
    fakeSmartLogParser(new FakeSmartLogParser([
        'log_type' => 'workout', 'summary' => 'Lat pulldown 3x10 @ 50 kg',
        'exercises' => [['exercise_name' => 'Lat pulldown', 'sets' => 3, 'reps' => 10, 'weight_kg' => 50.0]],
    ]));
    app(HandleInboundMessage::class)->handle(telegramText('lat pulldown 3x10 50'));
    $coach = fakeCoach();
    classifierSays(MessageKind::CoachQuestion);
    $chat = app(HandleInboundMessage::class);

    $chat->handle(telegramText('make a plan for 40 min'));
    $chat->handle(telegramText('deadhangs next, not in the 3 things'));

    $context = $coach->calls[1]['context'];
    expect($context['recent_logs'][0]['message'])->toBe('lat pulldown 3x10 50')
        ->and($context['recent_logs'][0]['date'])->toBe(today()->toDateString())
        ->and($context['recent_logs'][0]['exercises'])->toBe(['Lat Pulldown 3x10 @ 50 kg'])
        ->and($context['recent_exchanges'])->toHaveCount(1)
        ->and($context['recent_exchanges'][0]['they_asked'])->toBe('make a plan for 40 min');
});

it('caps coach questions per day without touching logging', function (): void {
    config(['coach.daily_limit' => 2]);
    linkedUser();
    classifierSays(MessageKind::CoachQuestion);
    $coach = fakeCoach();
    $chat = app(HandleInboundMessage::class);

    $chat->handle(telegramText('foam roller?'));
    $chat->handle(telegramText('massage chair?'));

    expect($chat->handle(telegramText('heat or ice?')))->toContain("today's 2 coach questions used")
        ->and($coach->calls)->toHaveCount(2);
});

it('keeps a coach question for later when the AI is down', function (): void {
    Queue::fake();
    linkedUser();
    classifierSays(MessageKind::CoachQuestion);
    fakeCoach((new FakeCoachAdvisor)->failing());

    expect(app(HandleInboundMessage::class)->handle(telegramText('foam roller?')))->toBe(KeepMessage::REPLY);
    $this->assertDatabaseCount('coach_turns', 0);
    $this->assertDatabaseHas('waiting_messages', ['text' => 'foam roller?']);
});

it('keeps the context the same size however long someone has logged', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 40) as $i) {
        $user->activityLogs()->create([
            'source' => 'telegram', 'log_type' => 'general', 'raw_message' => str_repeat('very long message ', 60),
            'summary' => 'Note '.$i, 'logged_on' => today()->subDays($i)->toDateString(),
        ]);
    }

    foreach (range(1, 10) as $i) {
        $user->coachTurns()->create(['question' => str_repeat('why ', 400), 'answer' => str_repeat('because ', 400)]);
    }

    $context = CoachContext::for($user);

    expect($context->toArray()['recent_logs'])->toHaveCount(config('coach.context.recent_logs'))
        ->and($context->toArray()['recent_exchanges'])->toHaveCount(config('coach.context.turns'))
        ->and(mb_strlen($context->toJson()))->toBeLessThan(16000);
});

it('leaves old exchanges out of the context', function (): void {
    $user = User::factory()->create();
    $turn = $user->coachTurns()->create(['question' => 'foam roller?', 'answer' => 'No, not on the lower back.']);
    $turn->forceFill(['created_at' => now()->subDays(4)])->save();

    expect(CoachContext::for($user)->toArray()['recent_exchanges'])->toBe([]);
});

it('sends an unplaceable reply to the coach right after an answer', function (): void {
    $user = linkedUser();
    $user->coachTurns()->create(['question' => 'foam roller?', 'answer' => 'Not on the lower back.']);
    classifierSays(MessageKind::Unknown);
    $coach = fakeCoach(new FakeCoachAdvisor('Understood.'));

    expect(app(HandleInboundMessage::class)->handle(telegramText('you hallucinated that, i never asked for that')))->toBe('Understood.')
        ->and($coach->calls[0]['context']['recent_exchanges'][0]['they_asked'])->toBe('foam roller?');
});

it('keeps the not-understood reply when there was no recent coach answer', function (): void {
    $user = linkedUser();
    $turn = $user->coachTurns()->create(['question' => 'foam roller?', 'answer' => 'Not on the lower back.']);
    $turn->forceFill(['created_at' => now()->subHours(2)])->save();
    classifierSays(MessageKind::Unknown);
    $coach = fakeCoach();

    expect(app(HandleInboundMessage::class)->handle(telegramText('rainbow butterfly')))->toBe(Interpreter::NOT_UNDERSTOOD)
        ->and($coach->calls)->toBe([]);
});
