<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MealType;
use Database\Factories\NutritionLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'logged_at',
    'meal_type',
    'food_name',
    'calories',
    'protein_g',
    'carbs_g',
    'fat_g',
    'notes',
])]
class NutritionLog extends Model
{
    /** @use HasFactory<NutritionLogFactory> */
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'logged_at' => 'datetime',
            'meal_type' => MealType::class,
            'protein_g' => 'decimal:1',
            'carbs_g'   => 'decimal:1',
            'fat_g'     => 'decimal:1',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Logs recorded today (by logged_at date).
     *
     * @param  Builder<NutritionLog>  $query
     * @return Builder<NutritionLog>
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('logged_at', today());
    }

    /**
     * Logs within the current ISO week.
     *
     * @param  Builder<NutritionLog>  $query
     * @return Builder<NutritionLog>
     */
    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('logged_at', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }
}
