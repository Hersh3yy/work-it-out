<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Profile\ProfileFacts;
use App\Profile\ProfileSummary;
use Illuminate\Console\Command;

final class ProfileShow extends Command
{
    protected $signature = 'profile:show {email} {--facts : Also print the computed facts as JSON} {--versions=1 : How many written versions to print}';

    protected $description = 'Print one user\'s profile: numbers, written notes and their sources (read-only)';

    public function handle(ProfileSummary $summary): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No user with that email.');

            return self::FAILURE;
        }

        $this->line($summary->for($user));

        foreach ($user->profileVersions()->limit(max(1, (int) $this->option('versions')))->get() as $version) {
            $this->newLine();
            $this->info(sprintf('Version %d (%s, %s, model %s)', $version->id, $version->kind, $version->created_at->toDateTimeString(), $version->model ?? '?'));

            foreach ($version->notes as $note) {
                $this->line('- '.$note['text'].'  ['.implode(', ', $note['cites']).']');
            }
        }

        if ($this->option('facts')) {
            $this->newLine();
            $this->line((string) json_encode(ProfileFacts::for($user)->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }
}
