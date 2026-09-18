<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

/**
 * Page slots the enricher looks for (sitemap/home links matched by
 * multilingual keywords, max 4 per company).
 */
enum PageType: string
{
    case Home = 'home';
    case Services = 'services';
    case About = 'about';
    case Team = 'team';
    case Jobs = 'jobs';
    case Contact = 'contact';
    case Cases = 'cases';
    case Blog = 'blog';
    case Partners = 'partners';
    case Legal = 'legal';
    case Privacy = 'privacy';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
