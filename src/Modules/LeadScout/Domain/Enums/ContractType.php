<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

enum ContractType: string
{
    case Freelance = 'freelance';
    case Employment = 'employment';
    case Unknown = 'unknown';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
