<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

/**
 * Manual send medium recorded on every `sent` outreach (spec FR-41).
 * `employment_application` tracks direct job replies (Vía 2, OP-23) apart.
 */
enum OutreachChannel: string
{
    case Email = 'email';
    case ContactForm = 'contact_form';
    case JobPosting = 'job_posting';
    case LinkedinManual = 'linkedin_manual';
    case EmploymentApplication = 'employment_application';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
