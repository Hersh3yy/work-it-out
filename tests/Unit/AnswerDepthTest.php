<?php

declare(strict_types=1);

use App\Coach\AnswerDepth;

it('sizes a coach answer from the question', function (string $question, AnswerDepth $depth): void {
    expect(AnswerDepth::for($question))->toBe($depth);
})->with([
    ['foam roller?', AnswerDepth::Quick],
    ['massage chairs?', AnswerDepth::Quick],
    ['heat or ice?', AnswerDepth::Quick],
    ['30 min, 3 things, go', AnswerDepth::Plan],
    ['make plan for 40 min work out', AnswerDepth::Plan],
    ['give me a superset with tricep pushdown', AnswerDepth::Plan],
    ['What should I do tomorrow?', AnswerDepth::Plan],
    ['What should I do on Saturday? My goal is weight loss', AnswerDepth::Plan],
    ['So back and legs ? Suggested weight per exercise?', AnswerDepth::Plan],
    ['I will spend 40 min in the gym . Suggest some resistance training exercises', AnswerDepth::Plan],
    ['wat moet ik doen morgen?', AnswerDepth::Plan],
    ['Can you give me my profile info', AnswerDepth::Quick],
    ['and what does voltaren actually do?', AnswerDepth::Explain],
    ['why leg up means knee pain tho', AnswerDepth::Explain],
    ['how do moving and hydration help?', AnswerDepth::Explain],
    ['waarom doet zitten pijn?', AnswerDepth::Explain],
]);

it('gives an explanation more room than a quick answer', function (): void {
    expect(AnswerDepth::Explain->maxTokens())->toBeGreaterThan(AnswerDepth::Plan->maxTokens())
        ->and(AnswerDepth::Plan->maxTokens())->toBeGreaterThan(AnswerDepth::Quick->maxTokens());
});
