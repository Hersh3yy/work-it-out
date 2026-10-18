<?php

declare(strict_types=1);

namespace App\Actions\Linking;

use App\Enums\ChatProvider;
use App\Models\ChannelIdentity;
use App\Models\ChannelLinkCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Links the chat account that sent a valid code to the code's user.
 *
 * Two proofs together: the code shows the person got the invite, and the
 * provider (Telegram) reports which account sent it, which the sender cannot
 * fake. A code works once and expires; an account already linked to someone
 * else is never moved.
 */
final readonly class RedeemLinkCode
{
    public function handle(ChatProvider $provider, string $senderId, string $code): ?User
    {
        return DB::transaction(function () use ($provider, $senderId, $code): ?User {
            $link = ChannelLinkCode::findValid($code, lock: true);

            if ($link === null) {
                return null;
            }

            $existing = ChannelIdentity::query()
                ->where('provider', $provider->value)
                ->where('external_id', $senderId)
                ->first();

            if ($existing !== null && $existing->user_id !== $link->user_id) {
                return null;
            }

            ChannelIdentity::query()->updateOrCreate(
                ['provider' => $provider, 'external_id' => $senderId],
                ['user_id' => $link->user_id],
            );

            $link->update(['used_at' => now()]);

            return $link->user;
        });
    }
}
