<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChatProvider;
use App\Enums\LogSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A message kept because the AI could not handle it when it arrived.
 *
 * @property int $id
 * @property int $user_id
 * @property ChatProvider $provider
 * @property string $chat_id
 * @property LogSource $source
 * @property string $text
 * @property string $status waiting, done, failed or resent
 * @property int $attempts
 * @property Carbon|null $handled_at
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'provider', 'chat_id', 'source', 'text', 'status', 'attempts', 'handled_at'])]
final class WaitingMessage extends Model
{
    public const string WAITING = 'waiting';

    public const string DONE = 'done';

    public const string FAILED = 'failed';

    /** The person sent the same text again; that copy is handled instead. */
    public const string RESENT = 'resent';

    protected function casts(): array
    {
        return [
            'provider' => ChatProvider::class,
            'source' => LogSource::class,
            'attempts' => 'integer',
            'handled_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<WaitingMessage>  $query
     * @return Builder<WaitingMessage>
     */
    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('status', self::WAITING);
    }

    /** The start of the text, to quote it back: "I did 3 more sets of bench…". */
    public function excerpt(): string
    {
        return mb_strimwidth(preg_replace('/\s+/', ' ', trim($this->text)) ?? '', 0, 60, '…');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
