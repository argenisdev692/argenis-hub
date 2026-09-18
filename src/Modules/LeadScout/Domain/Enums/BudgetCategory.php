<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

/**
 * Monthly budget buckets (spec US-8, FR-14).
 */
enum BudgetCategory: string
{
    case Search = 'search';
    case Extraction = 'extraction';
    case Ai = 'ai';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
