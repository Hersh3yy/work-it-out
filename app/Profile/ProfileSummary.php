<?php

declare(strict_types=1);

namespace App\Profile;

use App\Models\User;

/**
 * The profile as plain text for chat (/me) and the console: the numbers
 * first, then the latest written notes.
 */
final readonly class ProfileSummary
{
    public function for(User $user): string
    {
        $facts = ProfileFacts::for($user)->toArray();
        $entered = $facts['entered_by_user'];
        $week = $facts['this_week'];
        $weight = $facts['body_weight'];

        $lines = ['Your profile'];
        $lines[] = 'Goal: '.($entered['main_goal'] === null ? 'not set yet' : str_replace('_', ' ', $entered['main_goal']));
        $lines[] = 'Plan: '.($entered['training_days_per_week'] === null ? 'not set yet' : $entered['training_days_per_week'].' training days a week');
        $lines[] = sprintf('This week: %d sessions, score %s%%, streak %d days', $week['sessions'], rtrim(rtrim(number_format($week['weekly_score_percent'], 1), '0'), '.'), $week['streak_days']);
        $lines[] = 'Body weight: '.match (true) {
            $weight['latest'] === null => 'nothing logged yet',
            $weight['entries'] === 1 => $weight['latest']['kg'].' kg',
            default => sprintf('%s kg (%+.1f kg since %s)', $weight['latest']['kg'], $weight['change_kg'], $weight['first']['date']),
        };
        $lines[] = sprintf('Logged so far: %d sessions, %d messages', $facts['totals']['workout_sessions'], $facts['totals']['logged_messages']);

        $version = $user->profileVersions()->first();

        if ($version === null) {
            $lines[] = '';
            $lines[] = 'No written notes yet. They appear a few minutes after your next log.';

            return implode("\n", $lines);
        }

        $lines[] = '';
        $lines[] = 'Notes (updated '.$version->created_at->diffForHumans().')';

        foreach ($version->notes as $note) {
            $lines[] = '- '.$note['text'];
        }

        return implode("\n", $lines);
    }
}
