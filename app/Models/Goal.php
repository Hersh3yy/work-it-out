<?php

declare(strict_types=1);

namespace App\Models;

use App\Goals\GoalKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A goal the person stated, with the day it is for once they named one.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $activity_log_id
 * @property GoalKind $kind
 * @property string $name
 * @property string $description
 * @property Carbon|null $target_on
 */
#[Fillable(['user_id', 'activity_log_id', 'kind', 'name', 'description', 'target_on'])]
final class Goal extends Model
{
    protected function casts(): array
    {
        return [
            'kind' => GoalKind::class,
            'target_on' => 'date',
        ];
    }

    /**
     * Goals still ahead, and goals still waiting for their date; nearest first.
     *
     * @param  Builder<Goal>  $query
     * @return Builder<Goal>
     */
    #[Scope]
    protected function ahead(Builder $query): Builder
    {
        return $query->where(static fn (Builder $q) => $q->whereNull('target_on')->orWhereDate('target_on', '>=', today()->toDateString()))
            ->orderByRaw('target_on is null')
            ->orderBy('target_on');
    }

    public function daysLeft(): ?int
    {
        return $this->target_on === null ? null : (int) today()->diffInDays($this->target_on->copy()->startOfDay());
    }

    /** "rave on Sun 13 Dec, 28 days to go" for receipts, /goals and the coach. */
    public function describe(): string
    {
        if ($this->target_on === null) {
            return $this->name.', no date yet';
        }

        $left = match ($this->daysLeft()) {
            0 => 'today',
            1 => '1 day to go',
            default => $this->daysLeft().' days to go',
        };

        return $this->name.' on '.$this->target_on->format('D j M').', '.$left;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ActivityLog, $this> */
    public function activityLog(): BelongsTo
    {
        return $this->belongsTo(ActivityLog::class);
    }
}
