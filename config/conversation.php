<?php

declare(strict_types=1);

use App\Conversation\Answers\ExerciseQuestionAnswer;
use App\Conversation\Answers\LimitationChoiceAnswer;
use App\Conversation\Answers\ProfileQuestionAnswer;
use App\Conversation\Answers\RedFlagScreenAnswer;

return [

    /*
    | Open questions a plain message can answer without AI, tried in this
    | order. A question about the log itself always wins over a profile question.
    */
    'answer_handlers' => [
        ExerciseQuestionAnswer::class,
        LimitationChoiceAnswer::class,
        RedFlagScreenAnswer::class,
        ProfileQuestionAnswer::class,
    ],

];
