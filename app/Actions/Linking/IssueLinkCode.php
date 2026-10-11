<?php

declare(strict_types=1);

namespace App\Actions\Linking;

use App\Models\ChannelLinkCode;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Creates a one-time invite code for a user. The plain code is returned once
 * (it goes into the invite link) and only its hash is stored.
 */
final readonly class IssueLinkCode
{
    public const int VALID_DAYS = 7;

    /**
     * @return array{code: string, expires_at: Carbon}
     */
    public function handle(User $user): array
    {
        $code = Str::random(16);
        $expiresAt = now()->addDays(self::VALID_DAYS);

        ChannelLinkCode::query()->create([
            'user_id' => $user->id,
            'code_hash' => ChannelLinkCode::hash($code),
            'expires_at' => $expiresAt,
        ]);

        return ['code' => $code, 'expires_at' => $expiresAt];
    }
}
