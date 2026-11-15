<?php

declare(strict_types=1);

namespace Tests;

use App\Contracts\Ai\CoachAdvisor;
use App\Contracts\Ai\LimitationParser;
use App\Contracts\Ai\MessageClassifier;
use App\Contracts\Ai\ProfileBuilder;
use App\Contracts\Ai\SmartLogParser;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\FakeCoachAdvisor;
use Tests\Fakes\FakeLimitationParser;
use Tests\Fakes\FakeMessageClassifier;
use Tests\Fakes\FakeProfileBuilder;
use Tests\Fakes\FakeSmartLogParser;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bindEveryAiPortToItsFake();

        // A test that forgets Http::fake() fails loudly instead of calling a provider.
        Http::preventStrayRequests();

        // Failed logins wait 200 ms on purpose against timing attacks; tests need no such wait.
        config(['auth.timebox_duration' => 0]);
    }

    /**
     * A test reaches a real adapter only by asking for it with useRealAdapter().
     */
    private function bindEveryAiPortToItsFake(): void
    {
        $this->app->instance(MessageClassifier::class, new FakeMessageClassifier);
        $this->app->instance(SmartLogParser::class, FakeSmartLogParser::workout());
        $this->app->instance(LimitationParser::class, new FakeLimitationParser);
        $this->app->instance(CoachAdvisor::class, new FakeCoachAdvisor);
        $this->app->instance(ProfileBuilder::class, new FakeProfileBuilder);
    }
}
