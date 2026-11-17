<?php

declare(strict_types=1);

namespace App\Actions\Accounts;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Removes a person and everything they logged, on request. The database
 * cascades every table that belongs to them; app tokens and web sessions
 * have no foreign key, so they go here. AI call records stay without a
 * person (their user id becomes null) so the month's costs still add up.
 */
final readonly class DeleteUser
{
    public function handle(User $user): void
    {
        DB::transaction(static function () use ($user): void {
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        });
    }
}
