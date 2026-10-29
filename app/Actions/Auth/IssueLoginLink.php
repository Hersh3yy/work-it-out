<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\LoginCode;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * A one-time sign-in link for the app, handed to the user over a channel that
 * already proves who they are (their linked Telegram). Hash stored, 15
 * minutes, single use; a new link retires the older unused ones.
 */
final readonly class IssueLoginLink
{
    public const int VALID_MINUTES = 15;

    public function handle(User $user): string
    {
        LoginCode::query()->where('user_id', $user->id)->whereNull('used_at')->update(['expires_at' => now()]);

        $code = Str::random(32);

        LoginCode::query()->create([
            'user_id' => $user->id,
            'code_hash' => LoginCode::hash($code),
            'expires_at' => now()->addMinutes(self::VALID_MINUTES),
        ]);

        return rtrim((string) config('app.web_url'), '/').'/login?token='.$code;
    }
}
