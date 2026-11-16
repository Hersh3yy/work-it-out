<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\AiUnavailable;
use App\Interpretation\Interpreter;
use App\Interpretation\Lab\ParsedWorkout;
use App\Interpretation\RuleClassifier;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * The interpreter on its own: classify, parse, named rules, printed as JSON.
 * Saves nothing, so a prompt or rule can be tweaked and tried in seconds.
 * With --file it reads a catalogue (tests/Evals/workouts.txt: a message, a
 * tab, the shape it should save) and prints one line per message beside
 * what it expects, so a prompt or model change can be judged on all forms.
 */
final class LogParse extends Command
{
    protected $signature = 'log:parse
        {text? : The message, as you would text it}
        {--rules-only : Only the plain-rule classifier, no AI call}
        {--file= : A catalogue, one "message<TAB>expected shape" per line ("\\n" is a line break)}
        {--pause=0 : Seconds between messages of a catalogue, to stay under a rate limit}';

    protected $description = 'Show what the interpreter makes of a message, without saving anything';

    public function handle(Interpreter $interpreter, RuleClassifier $rules): int
    {
        if ($this->option('file') !== null) {
            return $this->catalogue($interpreter, (string) $this->option('file'));
        }

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

    private function catalogue(Interpreter $interpreter, string $file): int
    {
        if (! is_file($file)) {
            $this->error("No file at {$file}");

            return self::FAILURE;
        }

        $user = User::query()->first() ?? new User;
        $number = 0;

        foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (trim($line) === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$message, $expected] = array_pad(explode("\t", $line, 2), 2, '');
            $number++;

            if ($number > 1) {
                sleep((int) $this->option('pause'));
            }

            $this->line(sprintf('%02d | %s', $number, $message));
            $this->line('   expected  '.$expected);
            $this->line('   got       '.$this->describe($interpreter, $user, str_replace('\\n', "\n", $message)));
        }

        return self::SUCCESS;
    }

    private function describe(Interpreter $interpreter, User $user, string $message): string
    {
        try {
            $interpretation = $interpreter->interpret($user, $message);
        } catch (AiUnavailable $e) {
            return 'AI unavailable: '.$e->getMessage();
        }

        return ParsedWorkout::describe($interpretation->parsed, $interpretation->reply);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function json(array $data): string
    {
        return (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
