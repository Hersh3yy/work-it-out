<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One version of a user's written profile.
 *
 * @property int $id
 * @property int $user_id
 * @property string $kind rolling or full
 * @property list<array{text: string, cites: list<string>}> $notes
 * @property string|null $based_on_log_id
 * @property string|null $model
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'kind', 'notes', 'based_on_log_id', 'model'])]
final class ProfileVersion extends Model
{
    public const string ROLLING = 'rolling';

    public const string FULL = 'full';

    protected function casts(): array
    {
        return ['notes' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
