<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category'  // e.g., 'strength', 'cardio', etc.
    ];

    public function exerciseSets()
    {
        return $this->hasMany(ExerciseSet::class);
    }
}
