<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

/**
 * What kind of contact an outreach records (spec FR-41): a white-label
 * capacity offer or a direct job application (Vía 2, OP-23, measured apart).
 */
enum OutreachKind: string
{
    case ContractorOffer = 'contractor_offer';
    case EmploymentApplication = 'employment_application';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
