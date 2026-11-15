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
 * @property string|null $reply what is sent once it is handled, kept until the send succeeds
 * @property string $status waiting, processing, answered, done, failed or resent
 * @property int $attempts
 * @property Carbon|null $handled_at
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'provider', 'chat_id', 'source', 'text', 'reply', 'status', 'attempts', 'handled_at'])]
final class WaitingMessage extends Model
{
    public const string WAITING = 'waiting';

    /** A worker has it and is asking the AI. */
    public const string PROCESSING = 'processing';

    /** Handled and logged; the reply is stored but not sent yet. */
    public const string ANSWERED = 'answered';

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

    /**
     * Takes the message for this worker, counting the try. False when it is
     * no longer waiting (another worker has it, or it was resent or done).
     */
    public function claim(): bool
    {
        $claimed = self::query()
            ->whereKey($this->id)
            ->where('status', self::WAITING)
            ->update(['status' => self::PROCESSING, 'attempts' => $this->attempts + 1, 'updated_at' => now()]) === 1;

        if ($claimed) {
            $this->refresh();
        }

        return $claimed;
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [self::DONE, self::FAILED, self::RESENT], true);
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
