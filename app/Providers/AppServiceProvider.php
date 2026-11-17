<?php

declare(strict_types=1);

namespace App\Providers;

use App\Ai\SdkCoachAdvisor;
use App\Ai\SdkLimitationParser;
use App\Ai\SdkMessageClassifier;
use App\Ai\SdkProfileBuilder;
use App\Ai\SdkSmartLogParser;
use App\Ai\SystemOne\SystemOneClient;
use App\Ai\SystemOneMessageClassifier;
use App\Channels\Telegram\TelegramChannel;
use App\Channels\Telegram\TelegramClient;
use App\Contracts\Ai\CoachAdvisor;
use App\Contracts\Ai\LimitationParser;
use App\Contracts\Ai\MessageClassifier;
use App\Contracts\Ai\ProfileBuilder;
use App\Contracts\Ai\SmartLogParser;
use App\Contracts\Channels\ChatChannel;
use App\Contracts\Stats\PersonalRecords;
use App\Interpretation\RuleClassifier;
use App\Services\Stats\PersonalRecordService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Ports → adapters. Swap any binding for a fake in tests
        // (see tests/Fakes) or a different provider in production.
        $this->app->bind(SmartLogParser::class, SdkSmartLogParser::class);
        $this->app->bind(ProfileBuilder::class, SdkProfileBuilder::class);
        $this->app->bind(MessageClassifier::class, fn ($app): MessageClassifier => match ($driver = (string) config('ai.classifier.driver', 'llm')) {
            'laya', 'jev' => new SystemOneMessageClassifier(
                $app->make(RuleClassifier::class),
                SystemOneClient::fromConfig($driver),
                (float) config('ai.classifier.min_confidence', 0.6),
                config('ai.classifier.fallback') ? $app->make(SdkMessageClassifier::class) : null,
            ),
            default => $app->make(SdkMessageClassifier::class),
        });
        $this->app->bind(LimitationParser::class, SdkLimitationParser::class);
        $this->app->bind(CoachAdvisor::class, SdkCoachAdvisor::class);
        $this->app->bind(PersonalRecords::class, PersonalRecordService::class);

        $this->app->bind(TelegramClient::class, fn (): TelegramClient => new TelegramClient((string) config('services.telegram.bot_token')));
        $this->app->bind(ChatChannel::class, TelegramChannel::class);
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($request->user()?->id ?? $request->ip()));

        // Sign-in links: five tries a minute per address.
        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(5)->by('login|'.$request->ip()));

        // The app's log door calls the AI: 20 messages per person per hour.
        RateLimiter::for('smart-log', fn (Request $request): Limit => Limit::perHour(20)
            ->by($request->user()?->id ?? $request->ip()));
    }
}
