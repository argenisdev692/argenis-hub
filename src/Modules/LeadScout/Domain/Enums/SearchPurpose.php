<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

enum SearchPurpose: string
{
    case Discovery = 'discovery';
    case Resolve = 'resolve';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
