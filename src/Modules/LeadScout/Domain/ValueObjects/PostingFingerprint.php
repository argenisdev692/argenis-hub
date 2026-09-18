<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\ValueObjects;

use Carbon\CarbonImmutable;

/**
 * Stable posting identity (spec FR-4): normalized company + normalized title
 * + location + ISO week. The same offer seen in two sources within one week
 * collapses to a single posting with both sources linked (US-2 CA-2).
 */
final readonly class PostingFingerprint
{
    private function __construct(public string $value) {}

    public static function make(
        string $company,
        string $title,
        ?string $location,
        CarbonImmutable $date,
    ): self {
        $parts = [
            self::normalize($company),
            self::normalize($title),
            self::normalize($location ?? ''),
            $date->format('o-\\WW'),
        ];

        return new self(hash('sha256', implode('|', $parts)));
    }

    #[\NoDiscard('Normalized text must be captured')]
    public static function normalize(string $text): string
    {
        return $text
            |> trim(...)
            |> mb_strtolower(...)
            |> (fn (string $v): string => preg_replace('/\s+/', ' ', $v) ?? $v);
    }
}
