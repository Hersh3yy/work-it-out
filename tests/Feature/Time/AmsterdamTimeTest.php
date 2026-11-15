<?php

declare(strict_types=1);

use App\Actions\Coach\SendDailyCoaching;
use App\Channels\HandleInboundMessage;
use App\Channels\Telegram\TelegramChannel;
use App\Models\ActivityLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Fakes\FakeChatChannel;
use Tests\Fakes\FakeSmartLogParser;

/*
 * People log from Amsterdam: dates, the day caps and the morning message all
 * use one timezone (audit 2026-11-08 tier 3, audit 2026-11-14 "Smaller").
 */

it('keeps one timezone for the whole app', function (): void {
    expect(config('app.timezone'))->toBe('Europe/Amsterdam')
        ->and(config('coach.daily'))->not->toHaveKey('timezone');
});

it('files a log sent at 00:30 in Amsterdam on that day', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-16 00:30', 'Europe/Amsterdam'));
    linkedUser();
    fakeSmartLogParser(FakeSmartLogParser::workout());

    app(HandleInboundMessage::class)->handle(telegramText('benched 100 3x5'));

    expect(ActivityLog::sole()->logged_on->toDateString())->toBe('2026-11-16');
});

it('gives the morning brief the same weekday and date just after midnight', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-16 00:30', 'Europe/Amsterdam'));
    $user = linkedUser();
    $coach = fakeCoach();
    app()->instance(TelegramChannel::class, new FakeChatChannel);

    app(SendDailyCoaching::class)->handle($user);

    expect($coach->calls[0]['question'])->toContain('Morning message for Monday, 2026-11-16.');
});

it('moves the timestamps stored in UTC to Amsterdam time once', function (): void {
    $user = User::factory()->create();
    DB::table('users')->where('id', $user->id)->update(['created_at' => '2026-07-01 10:00:00', 'updated_at' => '2026-01-15 10:00:00']);
    $migration = require database_path('migrations/2026_11_15_000003_store_timestamps_in_amsterdam_time.php');

    $migration->up();

    expect(DB::table('users')->where('id', $user->id)->first(['created_at', 'updated_at']))
        ->created_at->toStartWith('2026-07-01 12:00:00')
        ->updated_at->toStartWith('2026-01-15 11:00:00');
})->skip(fn (): bool => DB::getDriverName() !== 'pgsql', 'Production runs Postgres; SQLite test databases are always fresh.');
