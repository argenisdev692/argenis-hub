<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

enum SearchStatus: string
{
    case Ok = 'ok';
    case Failed = 'failed';
    case QuotaExhausted = 'quota_exhausted';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
