<?php

declare(strict_types=1);

use App\Enums\TrainerPersona;

it('gives every coach a voice for injuries, speed requests and corrections', function (TrainerPersona $coach): void {
    expect($coach->voice())
        ->toContain('When they are hurt:')
        ->toContain('When they ask for speed')
        ->toContain('When they change their mind or correct you:')
        ->toContain('Tone example, never reuse its words')
        ->and($coach->systemPrompt())->toStartWith('You are '.$coach->label());
})->with(TrainerPersona::cases());

it('keeps nutrition out of the recovery coach in this phase', function (): void {
    expect(TrainerPersona::Latika->voice())->toContain('You do not give nutrition advice');
});
