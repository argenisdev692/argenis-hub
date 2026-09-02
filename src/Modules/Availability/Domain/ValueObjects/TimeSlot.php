<?php

declare(strict_types=1);

namespace Modules\Availability\Domain\ValueObjects;

/**
 * An open window within a day, expressed as `H:i` (24h) boundaries. The
 * appointment engine slices these into bookable slots of a given duration.
 */
final readonly class TimeSlot
{
    public function __construct(
        public string $start,
        public string $end,
    ) {}

    /**
     * Build from raw DB `time` values (`HH:MM:SS`), trimming to `H:i`.
     */
    public static function fromRaw(string $start, string $end): self
    {
        return new self(substr($start, 0, 5), substr($end, 0, 5));
    }

    /**
     * @return array{start: string, end: string}
     */
    public function toArray(): array
    {
        return ['start' => $this->start, 'end' => $this->end];
    }
}
