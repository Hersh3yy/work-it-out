<?php

declare(strict_types=1);

namespace App\Interpretation;

use App\Ai\Agents\ClassifierAgent;
use App\Ai\Agents\SmartLogAgent;
use App\Contracts\Ai\MessageClassifier;
use App\Contracts\Ai\SmartLogParser;
use App\Enums\MessageKind;
use App\Exceptions\AiUnavailable;
use App\Interpretation\Rules\BareNumberIsNotBodyWeight;
use App\Interpretation\Rules\InterpretationRule;
use App\Interpretation\Rules\RepsTimesSetsHasTwoReadings;
use App\Models\User;
use App\Services\Ai\AiCall;

/**
 * Classify, parse, then the named rules. Saves nothing: every door (chat,
 * app, `log:parse`) gets the same answer and decides what to do with it.
 *
 * @throws AiUnavailable when the classifier or parser is down
 */
final readonly class Interpreter
{
    public const string NOT_UNDERSTOOD = "I didn't catch a workout or a weight in that. You can text me things like:\n"
        ."bench 3x8 80\n"
        ."ran 5k in 28 min\n"
        ."104.5kg\n"
        .'Or /help for more.';

    public const string COACH_LATER = 'Asking the coaches over chat is coming soon. For now I log workouts and your weight; /help shows how.';

    /** @var list<class-string<InterpretationRule>> in order */
    private const array RULES = [
        BareNumberIsNotBodyWeight::class,
        RepsTimesSetsHasTwoReadings::class,
    ];

    public function __construct(
        private MessageClassifier $classifier,
        private SmartLogParser $parser,
        private AiCall $ai,
    ) {}

    public function interpret(User $user, string $text): Interpretation
    {
        $classification = $this->ai->run($user, ClassifierAgent::class, fn (): Classification => $this->classifier->classify($text));

        if ($classification->kind === MessageKind::Unknown) {
            return new Interpretation($classification, reply: self::NOT_UNDERSTOOD);
        }

        if ($classification->kind === MessageKind::CoachQuestion) {
            return new Interpretation($classification, reply: self::COACH_LATER);
        }

        if (! $classification->kind->isLoggable()) {
            return new Interpretation($classification, reply: self::NOT_UNDERSTOOD);
        }

        $parsed = $classification->kind === MessageKind::BodyWeight && $classification->decidedBy === 'rules'
            ? self::weightFromRules($text)
            : $this->ai->run($user, SmartLogAgent::class, fn (): array => $this->parser->parse($user, $text));

        foreach (self::RULES as $rule) {
            $result = app($rule)->apply($text, $parsed);
            $parsed = $result->parsed;

            if ($result->stopReply !== null) {
                return new Interpretation($classification, $parsed, $result->stopReply);
            }
        }

        return new Interpretation($classification, $parsed);
    }

    /**
     * "104.5kg" needs no model: the number is the weight.
     *
     * @return array<string, mixed>
     */
    private static function weightFromRules(string $text): array
    {
        preg_match('/\d{2,3}(?:[.,]\d+)?/', $text, $m);
        $kg = (float) str_replace(',', '.', $m[0] ?? '0');

        return [
            'log_type' => 'biometrics',
            'summary' => sprintf('Weight %s kg', rtrim(rtrim(number_format($kg, 2, '.', ''), '0'), '.')),
            'weight_kg_stat' => $kg,
            'exercises' => [],
            'questions' => [],
        ];
    }
}
