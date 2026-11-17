<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ai\AiJob;
use App\Ai\CoachTrial;
use App\Ai\CoachTrialAnswer;
use App\Coach\CoachContext;
use App\Enums\TrainerPersona;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\Events\AgentFailed;
use Laravel\Ai\Events\AgentPrompted;

/**
 * The same coach question on several models, one after the other, so a
 * model switch is judged on real answers (roadmap n41). Saves nothing: no
 * coach turn, no log, and no ai_calls row (the usage listener is switched
 * off for this run; the tokens and cost are printed instead).
 */
#[Description('Ask the coach the same question on two or more models side by side; saves nothing')]
#[Signature('coach:try
        {question : The question, as the person would text it}
        {--model=* : provider:model, e.g. mistral:mistral-large-4-0; a bare model uses the coach job\'s provider}
        {--user= : Email or id whose history the coach reads; without it the coach sees an empty history}')]
final class CoachTry extends Command
{
    public function handle(CoachTrial $trial): int
    {
        $models = (array) $this->option('model');
        $user = $this->person();

        if ($models === [] || $user === null) {
            $this->error($models === [] ? 'Give at least one --model=provider:model.' : 'No user with that email or id.');

            return self::FAILURE;
        }

        Event::forget(AgentPrompted::class);
        Event::forget(AgentFailed::class);

        $context = CoachContext::for($user);
        $persona = $user->trainer_persona ?? TrainerPersona::LtSurge;

        foreach ($models as $spec) {
            [$provider, $model] = self::split((string) $spec);
            $this->show($trial->ask($context, $persona, (string) $this->argument('question'), $provider, $model));
        }

        return self::SUCCESS;
    }

    /** The person named by --user, or an unsaved one with no history. */
    private function person(): ?User
    {
        $who = $this->option('user');

        if ($who === null) {
            return new User(['name' => 'Nobody yet']);
        }

        return User::query()->where('email', mb_strtolower(trim((string) $who)))->orWhere('id', (int) $who)->first();
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private static function split(string $spec): array
    {
        return str_contains($spec, ':')
            ? explode(':', $spec, 2)
            : [AiJob::Coach->provider() ?? AiJob::Log->provider(), $spec];
    }

    private function show(CoachTrialAnswer $answer): void
    {
        $this->newLine();
        $this->info(sprintf('== %s', $answer->label));

        if ($answer->error !== null) {
            $this->error($answer->error);

            return;
        }

        $cost = $answer->cost();
        $this->line(sprintf('%d in, %d out, %.1f s, %s', $answer->inputTokens, $answer->outputTokens, $answer->ms / 1000, $cost === null ? 'unpriced' : '$'.number_format($cost, 5)));
        $this->line($answer->answer);
    }
}
