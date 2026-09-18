<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

/**
 * Draft variant follows the triggering signal (spec US-5 CA-2).
 */
enum MessageVariant: string
{
    case Vacancy = 'vacancy';
    case Stack = 'stack';
    case Legacy = 'legacy';
    case Sector = 'sector';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
