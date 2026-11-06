<?php

declare(strict_types=1);

use App\Ai\Agents\ProfileAgent;
use App\Profile\NoteLabel;

it('prefixes a note with its section', function (): void {
    expect(NoteLabel::Gaps->prefix('No body weight logged yet.'))->toBe('Gaps: No body weight logged yet.')
        ->and(NoteLabel::tryFrom('strength'))->toBe(NoteLabel::Strength);
});

it('tells the profile writer every section and asks for gaps every time', function (): void {
    $instructions = (new ProfileAgent)->instructions();

    foreach (NoteLabel::cases() as $label) {
        expect($instructions)->toContain('- '.$label->value.': ');
    }

    expect($instructions)->toContain('always a "gaps" note');
});
