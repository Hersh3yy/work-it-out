<?php

declare(strict_types=1);

use App\Contracts\Ai\NutritionParser;
use App\Contracts\Ai\PlanGenerator;
use App\Contracts\Ai\SmartLogParser;
use App\Contracts\Ai\TrainerChat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeNutritionParser;
use Tests\Fakes\FakePlanGenerator;
use Tests\Fakes\FakeSmartLogParser;
use Tests\Fakes\FakeTrainerChat;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/*
| One-line helpers to swap any AI port for its in-memory fake. Each returns
| the fake instance so tests can queue replies and assert recorded calls.
*/

function fakeTrainerChat(?FakeTrainerChat $fake = null): FakeTrainerChat
{
    $fake ??= new FakeTrainerChat;
    app()->instance(TrainerChat::class, $fake);

    return $fake;
}

function fakePlanGenerator(?FakePlanGenerator $fake = null): FakePlanGenerator
{
    $fake ??= new FakePlanGenerator;
    app()->instance(PlanGenerator::class, $fake);

    return $fake;
}

function fakeSmartLogParser(?FakeSmartLogParser $fake = null): FakeSmartLogParser
{
    $fake ??= FakeSmartLogParser::workout();
    app()->instance(SmartLogParser::class, $fake);

    return $fake;
}

function fakeNutritionParser(?FakeNutritionParser $fake = null): FakeNutritionParser
{
    $fake ??= new FakeNutritionParser;
    app()->instance(NutritionParser::class, $fake);

    return $fake;
}
