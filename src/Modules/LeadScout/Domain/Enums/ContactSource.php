<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

enum ContactSource: string
{
    case JobPosting = 'job_posting';
    case Website = 'website';
    case Manual = 'manual';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
