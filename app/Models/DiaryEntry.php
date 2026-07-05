<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AI-synthesised diary entry — one per smart-log event.
 *
 * @property int         $id
 * @property int         $user_id
 * @property int|null    $activity_feedback_id
 * @property string      $content
 */
final class DiaryEntry extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'activity_feedback_id',
        'content',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ActivityFeedback, $this> */
    public function activityFeedback(): BelongsTo
    {
        return $this->belongsTo(ActivityFeedback::class);
    }
}
