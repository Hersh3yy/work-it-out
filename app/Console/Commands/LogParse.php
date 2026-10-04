<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\AiUnavailable;
use App\Interpretation\Interpreter;
use App\Interpretation\RuleClassifier;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * The interpreter on its own: classify, parse, named rules, printed as JSON.
 * Saves nothing, so a prompt or rule can be tweaked and tried in seconds.
 */
final class LogParse extends Command
{
    protected $signature = 'log:parse
        {text : The message, as you would text it}
        {--rules-only : Only the plain-rule classifier, no AI call}';

    protected $description = 'Show what the interpreter makes of a message, without saving anything';

    public function handle(Interpreter $interpreter, RuleClassifier $rules): int
    {
        $text = (string) $this->argument('text');

        if ($this->option('rules-only')) {
            $classification = $rules->classify($text);
            $this->line($this->json(['classification' => $classification?->toArray() ?? 'undecided: the model would decide']));

            return self::SUCCESS;
        }

        try {
            $interpretation = $interpreter->interpret(User::query()->first() ?? new User, $text);
        } catch (AiUnavailable $e) {
            $this->error('AI unavailable: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->line($this->json($interpretation->toArray()));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function json(array $data): string
    {
        return (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
