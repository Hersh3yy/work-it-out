<?php

declare(strict_types=1);

namespace App\Fitness;

/** How an exercise moves (method card 3: a balanced week touches each lifting pattern). */
enum MovementPattern: string
{
    case Push = 'push';
    case Pull = 'pull';
    case Squat = 'squat';
    case Hinge = 'hinge';
    case Carry = 'carry';
    case Core = 'core';
    case Jump = 'jump';
    case Run = 'run';
    case Cardio = 'cardio';
    case Sport = 'sport';
    case Mobility = 'mobility';
}
