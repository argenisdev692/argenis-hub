<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

enum SourceStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Failing = 'failing';
    case QuotaExhausted = 'quota_exhausted';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
