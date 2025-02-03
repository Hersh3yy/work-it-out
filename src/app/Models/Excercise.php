<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Excercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category'
    ];

    public function exerciseSets()
    {
        return $this->hasMany(ExerciseSet::class);
    }
}
