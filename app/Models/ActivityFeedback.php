<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * The three-coach reaction to a logged activity.
 *
 * @property int         $id
 * @property int         $user_id
 * @property string|null $loggable_type
 * @property int|null    $loggable_id
 * @property string|null $raw_message
 * @property string      $log_summary
 * @property string|null $lt_surge
 * @property string|null $shen
 * @property string|null $latika
 */
final class ActivityFeedback extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'loggable_type',
        'loggable_id',
        'raw_message',
        'log_summary',
        'lt_surge',
        'shen',
        'latika',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasOne<DiaryEntry, $this> */
    public function diaryEntry(): HasOne
    {
        return $this->hasOne(DiaryEntry::class);
    }
}
