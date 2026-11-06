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
 * job's model (config ai.jobs.profile), falling back to the log job's. The
 * answer is rebuilt from known keys only; citation checks are UpdateProfile's.
 */
final readonly class SdkProfileBuilder implements ProfileBuilder
{
    public function build(ProfileFacts $facts, array $previousNotes, array $recentEntries): array
    {
        $prompt = json_encode([
            'FACTS' => $facts->toArray(),
            'PREVIOUS NOTES' => $previousNotes,
            'RECENT ENTRIES' => $recentEntries,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $raw = (new ProfileAgent)->prompt(
            (string) $prompt,
            provider: config('ai.jobs.profile.provider') ?? config('ai.jobs.log.provider'),
            model: $this->modelName(),
        )->toArray();

        if (! is_array($raw['notes'] ?? null)) {
            throw AiUnavailable::because(ProfileAgent::class, 'payload missing notes');
        }

        return array_values(array_filter(array_map(self::note(...), $raw['notes'])));
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

    public function modelName(): ?string
    {
        return config('ai.jobs.profile.model') ?? config('ai.jobs.log.model');
    }
}
