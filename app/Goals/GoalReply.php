<?php

declare(strict_types=1);

namespace App\Goals;

use App\Models\Goal;

/** How goals read in the chat: after saving, and in /goals. */
final class GoalReply
{
    public const string NONE = 'No goals yet. Tell me one with a date, like "rave on 13 December" or "5k in 8 weeks".';

    public static function saved(Goal $goal): string
    {
        return 'Goal saved: '.$goal->describe().'.';
    }

    /** "Rave on Sun 13 Dec, 28 days to go: build. Endurance close, recovery ready, mobility not measured yet." */
    public static function line(Goal $goal, ?GoalReadiness $readiness): string
    {
        if ($readiness === null) {
            return ucfirst($goal->name).': no date yet.';
        }

        return ucfirst($goal->describe()).': '.$readiness->phase->value.'. '.$readiness->describe().'.';
    }
}
