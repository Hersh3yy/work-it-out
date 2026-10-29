<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\LoginCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Turns a valid, unused, unexpired sign-in code into the user it belongs to,
 * and burns it.
 */
final readonly class RedeemLoginCode
{
    public function handle(string $code): ?User
    {
        return DB::transaction(function () use ($code): ?User {
            $login = LoginCode::query()
                ->where('code_hash', LoginCode::hash($code))
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if ($login === null) {
                return null;
            }

            $login->update(['used_at' => now()]);

            return $login->user;
        });
    }
}
