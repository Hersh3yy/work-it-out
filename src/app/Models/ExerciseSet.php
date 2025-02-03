<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExerciseSet extends Model
{
    use HasFactory;

    protected $fillable = [
        'workout_id',
        'exercise_id',
        'reps',
        'weight',
        'notes',
        'order'
    ];

    public function workout()
    {
        return $this->belongsTo(Workout::class);
    }

    public function exercise()
    {
        return $this->belongsTo(Exercise::class);
    }
}
