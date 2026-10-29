<?php

declare(strict_types=1);

namespace Tests;

use App\Contracts\Ai\LimitationParser;
use App\Contracts\Ai\MessageClassifier;
use App\Contracts\Ai\ProfileBuilder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Fakes\FakeLimitationParser;
use Tests\Fakes\FakeMessageClassifier;
use Tests\Fakes\FakeProfileBuilder;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // No test reaches a real classifier model unless it rebinds this.
        $this->app->instance(MessageClassifier::class, new FakeMessageClassifier);
        $this->app->instance(ProfileBuilder::class, new FakeProfileBuilder);
        $this->app->instance(LimitationParser::class, new FakeLimitationParser);
    }
}
