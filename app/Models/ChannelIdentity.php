<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A chat account (Telegram today, maybe WhatsApp later) linked to a user.
 *
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $external_id
 */
#[Fillable(['user_id', 'provider', 'external_id'])]
final class ChannelIdentity extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
