<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One dated note on how an active limitation is going, in the user's words.
 *
 * @property int $id
 * @property int $limitation_id
 * @property string|null $activity_log_id
 * @property string $note
 * @property Carbon $logged_on
 */
#[Fillable(['limitation_id', 'activity_log_id', 'note', 'logged_on'])]
final class LimitationUpdate extends Model
{
    protected function casts(): array
    {
        return ['logged_on' => 'date'];
    }

    /** @return BelongsTo<ActivityLog, $this> */
    public function activityLog(): BelongsTo
    {
        return $this->belongsTo(ActivityLog::class);
    }

    /** @return BelongsTo<Limitation, $this> */
    public function limitation(): BelongsTo
    {
        return $this->belongsTo(Limitation::class);
    }
}
