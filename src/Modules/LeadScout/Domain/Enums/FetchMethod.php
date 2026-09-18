<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

/**
 * Cost ladder step that produced a page (spec FR-11).
 */
enum FetchMethod: string
{
    case Cache = 'cache';
    case Robots = 'robots';
    case Http = 'http';
    case Firecrawl = 'firecrawl';
    case Api = 'api';
    case Rss = 'rss';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
