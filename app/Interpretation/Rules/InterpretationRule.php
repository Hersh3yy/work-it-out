<?php

declare(strict_types=1);

namespace App\Interpretation\Rules;

/**
 * One named check on a parsed message: a sentence you can read, a class you
 * can test. It may add a question, or stop the log with a reply.
 */
interface InterpretationRule
{
    /**
     * @param  array<string, mixed>  $parsed  normalized parser payload
     */
    public function apply(string $text, array $parsed): RuleResult;
}
