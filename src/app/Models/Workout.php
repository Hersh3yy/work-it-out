<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workout extends Model
{
    use HasFactory;

    protected $fillable = [
        'raw_input',      // The text description input
        'parsed_data',    // JSON field containing parsed workout data
        'performed_at'    // When the workout was done
    ];

    protected $casts = [
        'parsed_data' => 'array',
        'performed_at' => 'datetime'
    ];

    public function exerciseSets()
    {
        return $this->hasMany(ExerciseSet::class);
    }
}
