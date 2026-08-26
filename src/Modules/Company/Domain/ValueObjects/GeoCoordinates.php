<?php

declare(strict_types=1);

namespace Modules\Company\Domain\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * A point on the globe, as returned by the address autocomplete.
 *
 * Two invariants the raw `double` columns cannot express:
 *
 * 1. **Range** — latitude is bounded to ±90 and longitude to ±180. A swapped
 *    pair (the classic autocomplete integration bug) is rejected the moment the
 *    latitude exceeds 90 rather than silently pinning the company to the wrong
 *    hemisphere.
 * 2. **Both or neither** — a lone latitude is not a location. {@see fromNullable()}
 *    collapses a half-filled pair to `null` so the columns can never disagree.
 *
 * The coordinates are written by the Google Places selection and are never shown
 * in the edit form (they are hidden inputs), which is exactly why they need a
 * guard here: nobody is looking at them.
 */
final readonly class GeoCoordinates implements Stringable
{
    public float $latitude;

    public float $longitude;

    public function __construct(float $latitude, float $longitude)
    {
        if ($latitude < -90.0 || $latitude > 90.0) {
            throw new InvalidArgumentException("Latitude [{$latitude}] is outside the range -90..90.");
        }

        if ($longitude < -180.0 || $longitude > 180.0) {
            throw new InvalidArgumentException("Longitude [{$longitude}] is outside the range -180..180.");
        }

        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    /**
     * Build a pair, or `null` when either half is missing.
     */
    public static function fromNullable(?float $latitude, ?float $longitude): ?self
    {
        return match (true) {
            $latitude === null, $longitude === null => null,
            default => new self($latitude, $longitude),
        };
    }

    public function __toString(): string
    {
        return $this->latitude.','.$this->longitude;
    }
}
