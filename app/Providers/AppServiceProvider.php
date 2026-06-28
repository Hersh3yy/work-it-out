<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?? $request->ip());
        });

        // AI trainer chat: 20 requests per authenticated user per hour.
        RateLimiter::for('trainer-chat', function (Request $request): Limit {
            return Limit::perHour(20)
                ->by($request->user()?->id ?? $request->ip());
        });
    }
}
