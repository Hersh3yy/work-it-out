<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dynamic, activity-specific RPG stat for a user.
 *
 * @property int    $id
 * @property int    $user_id
 * @property string $name
 * @property int    $value
 * @property int    $level
 * @property string $unit
 * @property string $category  strength|stamina|vitality
 * @property string|null $change_reason
 * @property \Carbon\Carbon|null $last_updated_at
 */
final class CustomRpgStat extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'name',
        'value',
        'level',
        'unit',
        'category',
        'change_reason',
        'last_updated_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'last_updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
