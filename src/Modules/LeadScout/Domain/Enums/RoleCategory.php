<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

/**
 * Decision-maker categories (spec US-11): only these are ever persisted.
 * Anything else (recruiters, workers, ambiguous) is discarded at extraction.
 */
enum RoleCategory: string
{
    case Founder = 'founder';
    case Executive = 'executive';
    case TechnicalLead = 'technical_lead';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
