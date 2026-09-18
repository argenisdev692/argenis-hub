<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

/**
 * Company taxonomy (spec US-3): who can buy white-label capacity.
 */
enum CompanyType: string
{
    case SoftwareAgency = 'software_agency';
    case Consultancy = 'consultancy';
    case ProductCompany = 'product_company';
    case Recruiter = 'recruiter';
    case LargeOutsourcer = 'large_outsourcer';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
