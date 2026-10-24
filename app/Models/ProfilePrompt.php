<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One profile question asked to a user.
 *
 * @property int $id
 * @property int $user_id
 * @property string $question_key
 * @property Carbon $asked_at
 * @property Carbon|null $answered_at
 * @property Carbon|null $skipped_at
 */
#[Fillable(['user_id', 'question_key', 'asked_at', 'answered_at', 'skipped_at'])]
final class ProfilePrompt extends Model
{
    protected function casts(): array
    {
        return [
            'asked_at' => 'datetime',
            'answered_at' => 'datetime',
            'skipped_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
