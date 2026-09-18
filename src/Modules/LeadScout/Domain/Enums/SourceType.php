<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

enum SourceType: string
{
    case JobApi = 'job_api';
    case Rss = 'rss';
    case Directory = 'directory';
    case Search = 'search';
    case CompanyWebsite = 'company_website';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
