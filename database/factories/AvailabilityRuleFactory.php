<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Availability\Infrastructure\Persistence\Eloquent\Models\AvailabilityRuleEloquentModel;

/**
 * @extends Factory<AvailabilityRuleEloquentModel>
 */
final class AvailabilityRuleFactory extends Factory
{
    protected $model = AvailabilityRuleEloquentModel::class;

    /**
     * Weekdays cycle 1..5 across successive instances so `count(n)` never piles
     * every row onto the same day, and the default window is the standard
     * morning shift — non-overlapping with the afternoon one used by
     * {@see self::slot()} in split-shift tests.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid7(),
            'day_of_week' => fake()->numberBetween(1, 5),
            'start_time' => '09:00',
            'end_time' => '13:00',
            'is_available' => true,
        ];
    }

    /**
     * Pin the rule to a weekday (0 = Sunday … 6 = Saturday, Carbon-aligned).
     */
    public function forDay(int $dayOfWeek): self
    {
        return $this->state(fn (): array => ['day_of_week' => $dayOfWeek]);
    }

    /**
     * Pin the open window, as `H:i` boundaries.
     */
    public function slot(string $start, string $end): self
    {
        return $this->state(fn (): array => ['start_time' => $start, 'end_time' => $end]);
    }

    /**
     * A closed weekly slot — never emitted by the resolver.
     */
    public function unavailable(): self
    {
        return $this->state(fn (): array => ['is_available' => false]);
    }
}
