<?php

declare(strict_types=1);

/*
| One catalogue of exercises: the canonical name, the aliases that are
| renamed to it ("bp" is Bench Press; only names that were renamed before,
| so nobody's history splits over two spellings), the words that mark a
| variant without renaming it ("treadmill" is a run, "push-ups" stay as
| written), the movement pattern, the qualities it trains and the body areas
| it loads. Read through App\Fitness\ExerciseCatalogue; nothing else lists
| exercises. Every entry trains at least one quality.
*/
return [
    // Push
    ['name' => 'Bench Press', 'aliases' => ['bench', 'bench press', 'bp', 'flat bench', 'barbell bench press'], 'words' => [], 'pattern' => 'push', 'qualities' => ['strength'], 'loads' => ['shoulder', 'chest', 'elbow']],
    ['name' => 'Overhead Press', 'aliases' => ['ohp', 'overhead press', 'military press'], 'words' => ['press'], 'pattern' => 'push', 'qualities' => ['strength'], 'loads' => ['shoulder', 'elbow']],
    ['name' => 'Dip', 'aliases' => [], 'words' => ['dips'], 'pattern' => 'push', 'qualities' => ['strength'], 'loads' => ['shoulder', 'chest', 'elbow']],
    ['name' => 'Push-up', 'aliases' => [], 'words' => ['pushups', 'push'], 'pattern' => 'push', 'qualities' => ['strength'], 'loads' => ['shoulder', 'chest', 'elbow', 'wrist']],

    // Squat and single leg
    ['name' => 'Squat', 'aliases' => ['squat', 'squats', 'back squat', 'back squats'], 'words' => [], 'pattern' => 'squat', 'qualities' => ['strength'], 'loads' => ['lower_back', 'knee', 'hip']],
    ['name' => 'Lunge', 'aliases' => [], 'words' => ['lunges'], 'pattern' => 'squat', 'qualities' => ['strength'], 'loads' => ['knee', 'hip']],
    ['name' => 'Leg Press', 'aliases' => ['leg press'], 'words' => [], 'pattern' => 'squat', 'qualities' => ['strength'], 'loads' => ['knee', 'hip', 'lower_back']],

    // Hinge
    ['name' => 'Deadlift', 'aliases' => ['deadlift', 'deadlifts', 'dl'], 'words' => [], 'pattern' => 'hinge', 'qualities' => ['strength'], 'loads' => ['lower_back', 'hip']],
    ['name' => 'Romanian Deadlift', 'aliases' => ['rdl', 'rdls', 'romanian deadlift'], 'words' => ['romanian'], 'pattern' => 'hinge', 'qualities' => ['strength'], 'loads' => ['lower_back', 'hip']],
    ['name' => 'Good Morning', 'aliases' => [], 'words' => [], 'pattern' => 'hinge', 'qualities' => ['strength'], 'loads' => ['lower_back']],
    ['name' => 'Clean', 'aliases' => [], 'words' => ['power clean'], 'pattern' => 'hinge', 'qualities' => ['strength', 'power'], 'loads' => ['lower_back', 'shoulder', 'wrist']],
    ['name' => 'Snatch', 'aliases' => [], 'words' => [], 'pattern' => 'hinge', 'qualities' => ['strength', 'power'], 'loads' => ['lower_back', 'shoulder', 'wrist']],
    ['name' => 'Kettlebell Swing', 'aliases' => [], 'words' => ['kb swing', 'kb swings', 'swing'], 'pattern' => 'hinge', 'qualities' => ['power'], 'loads' => ['lower_back', 'hip']],

    // Pull
    ['name' => 'Barbell Row', 'aliases' => ['barbell row', 'barbell rows', 'bent over row', 'bent over rows'], 'words' => ['row'], 'pattern' => 'pull', 'qualities' => ['strength'], 'loads' => ['lower_back', 'upper_back', 'elbow']],
    ['name' => 'Pull-up', 'aliases' => ['pull-up', 'pullups'], 'words' => [], 'pattern' => 'pull', 'qualities' => ['strength'], 'loads' => ['shoulder', 'elbow', 'upper_back']],
    ['name' => 'Chin-up', 'aliases' => ['chin-up', 'chinups'], 'words' => [], 'pattern' => 'pull', 'qualities' => ['strength'], 'loads' => ['shoulder', 'elbow', 'upper_back']],
    ['name' => 'Lat Pulldown', 'aliases' => ['lat pulldown', 'lat pulldowns', 'pulldown', 'pulldowns'], 'words' => [], 'pattern' => 'pull', 'qualities' => ['strength'], 'loads' => ['shoulder', 'upper_back']],
    ['name' => 'Biceps Curl', 'aliases' => [], 'words' => ['bicep curl', 'bicep curls', 'biceps curls', 'curl'], 'pattern' => 'pull', 'qualities' => ['strength'], 'loads' => ['elbow', 'wrist']],

    // Jump and throw
    ['name' => 'Box Jump', 'aliases' => [], 'words' => ['box jumps', 'box', 'jump', 'jumping'], 'pattern' => 'jump', 'qualities' => ['power'], 'loads' => ['knee', 'ankle']],
    ['name' => 'Medicine Ball Slam', 'aliases' => [], 'words' => ['ball slam', 'ball slams', 'med ball slam', 'slam', 'throw'], 'pattern' => 'jump', 'qualities' => ['power'], 'loads' => ['shoulder']],

    // Run and cardio
    ['name' => 'Run', 'aliases' => ['run', 'running', 'jog', 'jogging'], 'words' => ['treadmill', '5k', '10k', 'marathon'], 'pattern' => 'run', 'qualities' => ['endurance'], 'loads' => ['knee', 'ankle', 'foot', 'hip']],
    ['name' => 'Sprint', 'aliases' => [], 'words' => ['sprints', 'sprinting'], 'pattern' => 'run', 'qualities' => ['power', 'endurance'], 'loads' => ['knee', 'ankle', 'hip']],
    ['name' => 'Walk', 'aliases' => [], 'words' => ['walking', 'walks', 'incline walk', 'hike', 'hiking'], 'pattern' => 'cardio', 'qualities' => ['endurance', 'recovery'], 'loads' => []],
    ['name' => 'Rowing Machine', 'aliases' => [], 'words' => ['rowing', 'rower', 'erg'], 'pattern' => 'cardio', 'qualities' => ['endurance'], 'loads' => ['lower_back', 'knee']],
    ['name' => 'Elliptical', 'aliases' => [], 'words' => ['cross trainer'], 'pattern' => 'cardio', 'qualities' => ['endurance'], 'loads' => []],
    ['name' => 'Stair Climber', 'aliases' => [], 'words' => ['stairmaster', 'stairs'], 'pattern' => 'cardio', 'qualities' => ['endurance'], 'loads' => ['knee']],

    // Sports (their sessions are counted per sport in the records)
    ['name' => 'Cycling', 'aliases' => [], 'words' => ['bike', 'biking', 'cycle', 'spinning'], 'pattern' => 'sport', 'qualities' => ['endurance'], 'loads' => ['knee', 'hip']],
    ['name' => 'Swimming', 'aliases' => [], 'words' => ['swim'], 'pattern' => 'sport', 'qualities' => ['endurance'], 'loads' => ['shoulder']],
    ['name' => 'Padel', 'aliases' => [], 'words' => [], 'pattern' => 'sport', 'qualities' => ['endurance'], 'loads' => []],
    ['name' => 'Tennis', 'aliases' => [], 'words' => [], 'pattern' => 'sport', 'qualities' => ['endurance'], 'loads' => []],
    ['name' => 'Squash', 'aliases' => [], 'words' => [], 'pattern' => 'sport', 'qualities' => ['endurance'], 'loads' => []],
    ['name' => 'Badminton', 'aliases' => [], 'words' => [], 'pattern' => 'sport', 'qualities' => ['endurance'], 'loads' => []],
    ['name' => 'Football', 'aliases' => [], 'words' => ['soccer'], 'pattern' => 'sport', 'qualities' => ['endurance'], 'loads' => []],
    ['name' => 'Basketball', 'aliases' => [], 'words' => [], 'pattern' => 'sport', 'qualities' => ['endurance'], 'loads' => []],
    ['name' => 'Volleyball', 'aliases' => [], 'words' => [], 'pattern' => 'sport', 'qualities' => ['endurance'], 'loads' => []],
    ['name' => 'Climbing', 'aliases' => [], 'words' => ['bouldering'], 'pattern' => 'sport', 'qualities' => ['strength', 'endurance'], 'loads' => []],
    ['name' => 'Boxing', 'aliases' => [], 'words' => [], 'pattern' => 'sport', 'qualities' => ['endurance', 'power'], 'loads' => []],

    // Core and carry
    ['name' => 'Plank', 'aliases' => [], 'words' => ['planks', 'side plank', 'hold'], 'pattern' => 'core', 'qualities' => ['core'], 'loads' => ['shoulder', 'lower_back']],
    ['name' => "Farmer's Carry", 'aliases' => [], 'words' => ['farmer carry', 'farmers walk', 'farmer walk', 'carry', 'carries', 'farmer', 'farmers'], 'pattern' => 'carry', 'qualities' => ['core', 'strength'], 'loads' => ['shoulder', 'wrist', 'lower_back', 'hip']],
    ['name' => 'Hollow Hold', 'aliases' => [], 'words' => ['hollow'], 'pattern' => 'core', 'qualities' => ['core'], 'loads' => []],
    ['name' => 'Dead Bug', 'aliases' => [], 'words' => ['dead bugs'], 'pattern' => 'core', 'qualities' => ['core'], 'loads' => []],
    ['name' => 'Pallof Press', 'aliases' => [], 'words' => ['pallof'], 'pattern' => 'core', 'qualities' => ['core'], 'loads' => []],
    ['name' => 'Sit-up', 'aliases' => [], 'words' => ['situps'], 'pattern' => 'core', 'qualities' => ['core'], 'loads' => []],
    ['name' => 'Crunch', 'aliases' => [], 'words' => ['crunches'], 'pattern' => 'core', 'qualities' => ['core'], 'loads' => []],
    ['name' => 'Ab Rollout', 'aliases' => [], 'words' => ['ab wheel', 'rollout', 'rollouts'], 'pattern' => 'core', 'qualities' => ['core'], 'loads' => ['shoulder', 'lower_back']],
    ['name' => 'Leg Raise', 'aliases' => [], 'words' => ['hanging leg raise'], 'pattern' => 'core', 'qualities' => ['core'], 'loads' => []],

    // Mobility and recovery
    ['name' => 'Stretching', 'aliases' => [], 'words' => ['stretch', 'stretches'], 'pattern' => 'mobility', 'qualities' => ['mobility', 'recovery'], 'loads' => []],
    ['name' => 'Yoga', 'aliases' => [], 'words' => [], 'pattern' => 'mobility', 'qualities' => ['mobility', 'recovery'], 'loads' => []],
    ['name' => 'Mobility Work', 'aliases' => [], 'words' => ['mobility'], 'pattern' => 'mobility', 'qualities' => ['mobility'], 'loads' => []],
    ['name' => 'Foam Rolling', 'aliases' => [], 'words' => ['foam roll', 'foam roller'], 'pattern' => 'mobility', 'qualities' => ['recovery'], 'loads' => []],
];
