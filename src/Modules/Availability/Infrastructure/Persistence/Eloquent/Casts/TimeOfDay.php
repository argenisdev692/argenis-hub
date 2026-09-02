<?php

declare(strict_types=1);

namespace Modules\Availability\Infrastructure\Persistence\Eloquent\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Modules\Availability\Domain\ValueObjects\TimeSlot;

/**
 * Normalises a wall-clock time column to `HH:MM:SS` on write.
 *
 * The API speaks `H:i` (`date_format:H:i` on every rule/exception payload) while
 * the columns are declared `time`. PostgreSQL coerces `10:00` to `10:00:00` on
 * insert, but SQLite stores the literal string — so without this cast the same
 * row reads back differently per engine, and everything downstream that trims
 * `HH:MM:SS` to `H:i` ({@see TimeSlot::fromRaw()},
 * the export transformers) is correct only by accident.
 *
 * Writing one canonical shape keeps the stored value engine-independent and
 * makes the lexicographic comparisons in the overlap check well-defined.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
final readonly class TimeOfDay implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : (string) $value;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $time = (string) $value;

        // `H:i` → `H:i:s`; anything already carrying seconds passes through.
        return preg_match('/^\d{2}:\d{2}$/', $time) === 1 ? $time.':00' : $time;
    }
}
