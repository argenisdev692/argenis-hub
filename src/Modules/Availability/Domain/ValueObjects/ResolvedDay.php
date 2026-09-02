<?php

declare(strict_types=1);

namespace Modules\Availability\Domain\ValueObjects;

/**
 * The effective availability of a single calendar date after applying the
 * precedence chain: an active date exception wins over the weekly rule, and a
 * default-closed day is the fallback. National holidays are materialised ahead
 * of time as `holiday`-sourced exception rows, so at resolve time they surface
 * through the exception branch. `source` is therefore one of `exception`,
 * `rule`, or `default` — it records which layer decided the outcome so callers
 * (and the UI) can explain *why* a day is open or closed.
 */
final readonly class ResolvedDay
{
    /**
     * @param  list<TimeSlot>  $slots
     */
    public function __construct(
        public string $date,
        public bool $isOpen,
        public array $slots,
        public ?string $reason,
        public string $source,
    ) {}

    /**
     * @param  list<TimeSlot>  $slots
     */
    public static function open(string $date, array $slots, string $source, ?string $reason = null): self
    {
        return new self($date, true, $slots, $reason, $source);
    }

    public static function closed(string $date, string $source, ?string $reason = null): self
    {
        return new self($date, false, [], $reason, $source);
    }

    /**
     * @return array{date: string, is_open: bool, slots: list<array{start: string, end: string}>, reason: string|null, source: string}
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'is_open' => $this->isOpen,
            'slots' => array_map(static fn (TimeSlot $slot): array => $slot->toArray(), $this->slots),
            'reason' => $this->reason,
            'source' => $this->source,
        ];
    }
}
