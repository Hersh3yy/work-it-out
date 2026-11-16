<?php

declare(strict_types=1);

namespace App\Fitness;

use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Every quality of one person in plain words and numbers: its energy, the
 * level earned, the power today, today's readiness and when it last worked.
 * What the coach, the profile writer and the app read; nothing here is
 * computed by a model.
 */
final readonly class QualitySheet
{
    private function __construct(
        public QualityLevel $level,
        public QualityPower $power,
        public QualityReadiness $readiness,
    ) {}

    public static function for(User $user, ?CarbonInterface $on = null): self
    {
        $history = TrainingHistory::for($user, $on);

        return new self(QualityLevel::for($user, $on), QualityPower::from($history), QualityReadiness::from($history));
    }

    /**
     * @return list<array{energy: string, quality: string, level: int, power: float|null, power_measures: array<string, float|int|string|null>|null, readiness: int, last_trained_on: string|null, limited_by: list<string>}>
     */
    public function toArray(): array
    {
        return array_map(fn (Quality $quality): array => [
            'energy' => $quality->energy()->value,
            'quality' => $quality->value,
            'level' => $this->level->of($quality),
            'power' => $this->power->of($quality)?->value,
            'power_measures' => $this->power->of($quality)?->measures,
            'readiness' => $this->readiness->of($quality)->value,
            'last_trained_on' => $this->readiness->of($quality)->lastTrainedOn?->toDateString(),
            'limited_by' => $this->readiness->of($quality)->limitedBy,
        ], Quality::cases());
    }

    /**
     * "Fresh today: endurance, core. Still recovering: strength (40, trained Sat 14 Nov)."
     */
    public function todayLine(): string
    {
        $fresh = [];
        $recovering = [];

        foreach (Quality::cases() as $quality) {
            $reading = $this->readiness->of($quality);

            if ($reading->value >= (int) config('fitness.readiness.full')) {
                $fresh[] = $quality->value;

                continue;
            }

            $recovering[] = "{$quality->value} ({$reading->value}, ".implode(', ', $this->why($quality)).')';
        }

        return trim(($fresh === [] ? '' : 'Fresh today: '.implode(', ', $fresh).'. ')
            .($recovering === [] ? '' : 'Still recovering: '.implode(', ', $recovering).'.'));
    }

    /**
     * @return list<string> "trained Sat 14 Nov", "lower back"
     */
    private function why(Quality $quality): array
    {
        $reading = $this->readiness->of($quality);
        $trained = $quality->tiresWhenTrained() && $reading->lastTrainedOn !== null
            && $reading->lastTrainedOn->diffInDays(today()) < count((array) config('fitness.readiness.after_training'));

        return [...($trained ? ['trained '.$reading->lastTrainedOn->format('D j M')] : []), ...$reading->limitedBy];
    }
}
