<?php

declare(strict_types=1);

namespace Tests;

use App\Contracts\Ai\MessageClassifier;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Fakes\FakeMessageClassifier;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // No test reaches a real classifier model unless it rebinds this.
        $this->app->instance(MessageClassifier::class, new FakeMessageClassifier);
    }
}
