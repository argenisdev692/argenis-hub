<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

/**
 * How the company entered the pipeline (spec §7 Company.origen).
 */
enum CompanyOrigin: string
{
    case JobPosting = 'job_posting';
    case Discovery = 'discovery';
    case Manual = 'manual';
    case Import = 'import';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
