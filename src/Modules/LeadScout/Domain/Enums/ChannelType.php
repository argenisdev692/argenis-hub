<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

/**
 * Detected contact channels (spec US-12, FR-31). `careers_form` is always
 * extracted but ranks low (usually reaches HR/recruiters).
 */
enum ChannelType: string
{
    case JobPostingApply = 'job_posting_apply';
    case FreelanceCall = 'freelance_call';
    case PartnerPage = 'partner_page';
    case ContactForm = 'contact_form';
    case CareersForm = 'careers_form';
    case GenericEmail = 'generic_email';
    case CompanyNetworkPage = 'company_network_page';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
