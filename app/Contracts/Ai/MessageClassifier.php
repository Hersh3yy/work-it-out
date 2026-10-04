<?php

declare(strict_types=1);

namespace App\Contracts\Ai;

use App\Interpretation\Classification;

/**
 * Port for the classify step: which kind of message is this?
 *
 * Today the adapter is rules first, then the language model. A trained
 * classifier (embeddings, SetFit) or a typed decision model (Jev) can replace
 * it without touching the parser or the conversation.
 *
 * @see https://refactoring.guru/design-patterns/adapter
 */
interface MessageClassifier
{
    public function classify(string $text): Classification;
}
