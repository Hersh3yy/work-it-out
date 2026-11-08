<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Agents\ProfileAgent;
use App\Contracts\Ai\ProfileBuilder;
use App\Exceptions\AiUnavailable;
use App\Profile\NoteLabel;
use App\Profile\ProfileFacts;

/**
 * Laravel AI SDK adapter for the ProfileBuilder port. Runs on the profile
 * job's model chain (its model, then the log job's, then ai.fallbacks): a
 * profile written by the smaller model beats no profile. The answer is
 * rebuilt from known keys only; citation checks are UpdateProfile's.
 */
final class SdkProfileBuilder implements ProfileBuilder
{
    private ?string $usedModel = null;

    public function build(ProfileFacts $facts, array $previousNotes, array $recentEntries): array
    {
        $prompt = (string) json_encode([
            'FACTS' => $facts->toArray(),
            'PREVIOUS NOTES' => $previousNotes,
            'RECENT ENTRIES' => $recentEntries,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $raw = ModelChain::ask('profile', function (?string $provider, ?string $model) use ($prompt): array {
            $this->usedModel = $model;

            return (new ProfileAgent)->prompt($prompt, provider: $provider, model: $model)->toArray();
        });

        if (! is_array($raw['notes'] ?? null)) {
            throw AiUnavailable::because(ProfileAgent::class, 'payload missing notes');
        }

        return array_values(array_filter(array_map(self::note(...), $raw['notes'])));
    }

    public function modelName(): ?string
    {
        return $this->usedModel ?? ModelChain::for('profile')[0]['model'];
    }

    /**
     * @return array{text: string, cites: list<string>}|null
     */
    private static function note(mixed $note): ?array
    {
        if (! is_array($note) || ! is_string($note['text'] ?? null) || trim($note['text']) === '') {
            return null;
        }

        $cites = is_array($note['cites'] ?? null) ? $note['cites'] : [];
        $label = is_string($note['label'] ?? null) ? NoteLabel::tryFrom(mb_strtolower($note['label'])) : null;
        $text = mb_substr(trim($note['text']), 0, 240);

        return [
            'text' => $label === null ? $text : $label->prefix($text),
            'cites' => array_slice(array_values(array_filter($cites, 'is_string')), 0, 10),
        ];
    }
}
