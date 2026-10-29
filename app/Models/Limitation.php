<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BodyArea;
use App\Enums\LimitationSeverity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An injury or limitation the user stated.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $activity_log_id
 * @property BodyArea|null $body_area
 * @property LimitationSeverity|null $severity
 * @property string $note
 * @property Carbon $started_on
 * @property Carbon|null $expected_until
 * @property Carbon|null $resolved_at
 */
#[Fillable(['user_id', 'activity_log_id', 'body_area', 'severity', 'note', 'started_on', 'expected_until', 'resolved_at'])]
final class Limitation extends Model
{
    protected function casts(): array
    {
        return [
            'body_area' => BodyArea::class,
            'severity' => LimitationSeverity::class,
            'started_on' => 'date',
            'expected_until' => 'date',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Limitation>  $query
     * @return Builder<Limitation>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function areaLabel(): string
    {
        return $this->body_area?->label() ?? 'unspecified area';
    }

    /**
     * "lower back, go easy until 12 Nov" for receipts, /me and the app.
     */
    public function describe(): string
    {
        $until = $this->expected_until === null ? '' : ', until '.$this->expected_until->format('j M');

        return $this->areaLabel().': '.$this->note.$until;
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
