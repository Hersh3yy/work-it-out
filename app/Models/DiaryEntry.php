<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Diary line for one smart-log event: the log's factual summary.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $activity_log_id
 * @property string $content
 */
final class DiaryEntry extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'activity_log_id',
        'content',
    ];

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
