<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LogSource;
use App\Enums\LogType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One free-text log message and what it produced.
 *
 * The loggable is the workout session, body weight or meal the message
 * landed in. A message merged into an earlier session points at that session;
 * its own exercise entries carry this log's id.
 *
 * @property string $id
 * @property int $user_id
 * @property LogSource $source
 * @property LogType $log_type
 * @property string $raw_message
 * @property string $summary
 * @property string|null $loggable_type
 * @property string|null $loggable_id
 * @property list<array{field: string, question: string}>|null $questions
 * @property Carbon $logged_on
 */
#[Fillable([
    'user_id',
    'source',
    'log_type',
    'raw_message',
    'summary',
    'loggable_type',
    'loggable_id',
    'questions',
    'logged_on',
])]
final class ActivityLog extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'source' => LogSource::class,
            'log_type' => LogType::class,
            'questions' => 'array',
            'logged_on' => 'date',
        ];
    }

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

    /** @return HasMany<ExerciseEntry, $this> */
    public function exerciseEntries(): HasMany
    {
        return $this->hasMany(ExerciseEntry::class);
    }
}
