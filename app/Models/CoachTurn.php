<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CoachTurnKind;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One coach message: an answer to their question, or the morning message the
 * coach wrote first. Conversation, not facts.
 *
 * @property int $id
 * @property int $user_id
 * @property string $question what they asked, or the brief for the morning message
 * @property string $answer
 * @property CoachTurnKind $kind
 * @property string|null $depth quick, plan or explain (App\Coach\AnswerDepth)
 * @property string|null $model
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'question', 'answer', 'depth', 'kind', 'model'])]
final class CoachTurn extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['kind' => CoachTurnKind::class];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isDaily(): bool
    {
        return $this->kind === CoachTurnKind::Daily;
    }

    /** @param  Builder<self>  $query */
    #[Scope]
    protected function asked(Builder $query): void
    {
        $query->where('kind', CoachTurnKind::Asked);
    }

    /** @param  Builder<self>  $query */
    #[Scope]
    protected function dailySince(Builder $query, CarbonInterface $since): void
    {
        $query->where('kind', CoachTurnKind::Daily)->where('created_at', '>=', $since);
    }
}
