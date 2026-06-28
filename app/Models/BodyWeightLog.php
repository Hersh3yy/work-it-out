<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BodyWeightLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'logged_at',
    'weight_kg',
    'notes',
])]
class BodyWeightLog extends Model
{
    /** @use HasFactory<BodyWeightLogFactory> */
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'logged_at' => 'date',
            'weight_kg' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
