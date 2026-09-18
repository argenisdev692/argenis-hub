<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

/**
 * AI call purposes, each with its own default + fallback in `scout_ai_settings`
 * (spec US-10, FR-22).
 */
enum AiPurpose: string
{
    case Extraction = 'extraction';
    case Drafting = 'drafting';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
