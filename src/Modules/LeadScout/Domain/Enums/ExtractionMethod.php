<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

enum ExtractionMethod: string
{
    case Rule = 'rule';
    case Ai = 'ai';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
