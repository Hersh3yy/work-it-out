<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A one-time code that links a chat account to this user.
 *
 * @property int $id
 * @property int $user_id
 * @property string $code_hash
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 */
#[Fillable(['user_id', 'code_hash', 'expires_at', 'used_at'])]
final class ChannelLinkCode extends Model
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public static function hash(string $code): string
    {
        return hash('sha256', $code);
    }

    /**
     * The unused, unexpired code with this value, if any.
     */
    public static function findValid(string $code, bool $lock = false): ?self
    {
        $query = self::query()
            ->where('code_hash', self::hash($code))
            ->whereNull('used_at')
            ->where('expires_at', '>', now());

        return ($lock ? $query->lockForUpdate() : $query)->first();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
