<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One question to the coach and its answer. Conversation, not facts.
 *
 * @property int $id
 * @property int $user_id
 * @property string $question
 * @property string $answer
 * @property string|null $depth quick, plan or explain (App\Coach\AnswerDepth)
 * @property string|null $model
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'question', 'answer', 'depth', 'kind', 'model'])]
final class CoachTurn extends Model
{
    /** They asked. */
    public const string ASKED = 'asked';

    /** The coach wrote first, the morning message. */
    public const string DAILY = 'daily';

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
