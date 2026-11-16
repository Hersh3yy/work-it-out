<?php

declare(strict_types=1);

namespace App\Fitness;

use App\Enums\BodyArea;
use InvalidArgumentException;

/** One exercise of the catalogue (config/exercises.php). */
final readonly class Exercise
{
    /**
     * @param  list<string>  $aliases  names renamed to this one
     * @param  list<string>  $words  words that mark a variant of it, kept as written
     * @param  non-empty-list<Quality>  $qualities
     * @param  list<BodyArea>  $loads
     */
    public function __construct(
        public string $name,
        public array $aliases,
        public array $words,
        public MovementPattern $pattern,
        public array $qualities,
        public array $loads,
    ) {}

    /**
     * @param  array{name: string, aliases: list<string>, words: list<string>, pattern: string, qualities: list<string>, loads: list<string>}  $entry
     */
    public static function fromConfig(array $entry): self
    {
        $qualities = array_map(Quality::from(...), $entry['qualities']);

        if ($qualities === []) {
            throw new InvalidArgumentException("{$entry['name']} trains no quality.");
        }

        return new self(
            $entry['name'],
            $entry['aliases'],
            $entry['words'],
            MovementPattern::from($entry['pattern']),
            $qualities,
            array_map(BodyArea::from(...), $entry['loads']),
        );
    }

    /**
     * Everything a person may write for it: the name, the aliases, the words.
     *
     * @return list<string>
     */
    public function terms(): array
    {
        return [$this->name, ...$this->aliases, ...$this->words];
    }
}
