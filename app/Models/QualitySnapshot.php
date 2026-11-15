<?php

declare(strict_types=1);

namespace App\Models;

use App\Fitness\Quality;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One quality of one person on one day (App\Jobs\WriteQualitySnapshots).
 *
 * @property int $id
 * @property int $user_id
 * @property Quality $quality
 * @property Carbon $day
 * @property int $level_points
 * @property float|null $power
 * @property int $readiness
 */
#[Fillable(['user_id', 'quality', 'day', 'level_points', 'power', 'readiness'])]
final class QualitySnapshot extends Model
{
    protected function casts(): array
    {
        return [
            'quality' => Quality::class,
            'day' => 'date',
            'level_points' => 'integer',
            'power' => 'float',
            'readiness' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
