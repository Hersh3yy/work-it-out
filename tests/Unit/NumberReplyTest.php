<?php

declare(strict_types=1);

use App\Interpretation\NumberReply;

it('reads the number, unit and whether the rest belongs to the answer', function (string $text, float $value, string $unit, ?string $followUp): void {
    $reply = NumberReply::from($text);

    expect($reply->value)->toBe($value)
        ->and($reply->unit)->toBe($unit)
        ->and($reply->followUp())->toBe($followUp);
})->with([
    ['70', 70.0, '', null],
    ['32,5 kg', 32.5, 'kg', null],
    ['5 reps each set', 5.0, 'reps', null],
    ['about 12', 12.0, '', null],
    ['70kg....now what next ?', 70.0, 'kg', 'now what next ?'],
    ['28 min', 28.0, 'min', null],
]);

it('is not an answer without a leading number or with a second number', function (string $text): void {
    expect(NumberReply::from($text))->toBeNull();
})->with(['dunno', 'bench 3x5 80', '3x5 squat 100', '70 and the last one 75']);
