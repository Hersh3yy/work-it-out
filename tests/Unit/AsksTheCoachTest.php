<?php

declare(strict_types=1);

use App\Coach\AsksTheCoach;

it('hears a question for the coach inside a message', function (string $text, bool $asks): void {
    expect(AsksTheCoach::in($text))->toBe($asks);
})->with([
    ['bench 3x5 70, what next?', true],
    ['I did 3 more sets of bench press 70 and some stretching so far, 1 or 2 more things then done, suggest', true],
    ['So I did 6, 7, and 8th set as well. What next', true],
    ['I want to be able to do squats in 1 week. based off that goal, does your advice change', true],
    ['70kg....now what next', true],
    ['squat 5x5 90, wat nu', true],
    ['bench 3x8 80', false],
    ['Yesterday did squats 5x5 90 80 80 80 80 and ran 1k in 6:30', false],
    ['Back is 5 out of 10, standing more at work', false],
]);
