<?php

declare(strict_types=1);

namespace Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Availability\Domain\ValueObjects\ExceptionSource;
use Modules\Availability\Infrastructure\Persistence\Eloquent\Models\AvailabilityExceptionEloquentModel;

/**
 * @extends Factory<AvailabilityExceptionEloquentModel>
 */
final class AvailabilityExceptionFactory extends Factory
{
    protected $model = AvailabilityExceptionEloquentModel::class;

    /**
     * Distinct future day per generated row. `availability_exceptions` carries a
     * partial unique index on `date WHERE deleted_at IS NULL`, so a random or
     * constant default would make `factory()->count(n)` collide; a monotonic
     * counter keeps every row on its own date without the caller having to pass
     * one.
     */
    private static int $dayOffset = 0;

    /**
     * Defaults to a closure (`is_available = false`, no hours) — the common case
     * and the only shape that needs no companion state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid7(),
            'date' => CarbonImmutable::today()->addDays(++self::$dayOffset)->toDateString(),
            'is_available' => false,
            'start_time' => null,
            'end_time' => null,
            'reason' => fake()->sentence(3),
            'source' => ExceptionSource::Manual,
        ];
    }

    /**
     * Pin the exception to a calendar date (`Y-m-d`).
     */
    public function on(string $date): self
    {
        return $this->state(fn (): array => ['date' => $date]);
    }

    /**
     * A forced-open day, carrying its own hours.
     */
    public function open(string $start, string $end): self
    {
        return $this->state(fn (): array => [
            'is_available' => true,
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    /**
     * A system-materialised national holiday — the only provenance the resync
     * is allowed to purge.
     */
    public function holiday(): self
    {
        return $this->state(fn (): array => ['source' => ExceptionSource::Holiday]);
    }
}
