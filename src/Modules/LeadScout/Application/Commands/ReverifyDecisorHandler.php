<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Carbon\CarbonImmutable;
use Modules\LeadScout\Domain\Entities\Contact;
use Modules\LeadScout\Domain\Ports\CompanyPageFetcherPort;
use Modules\LeadScout\Domain\Ports\ContactRepositoryPort;

/**
 * Accuracy before drafting (spec FR-28): decisor data older than 90 days
 * is re-fetched from its evidence page. Gone from the source → anonymized
 * with a warning; unreachable source → warning, human call.
 */
final readonly class ReverifyDecisorHandler
{
    private const int REVERIFY_AFTER_DAYS = 90;

    public function __construct(
        private CompanyPageFetcherPort $fetcher,
        private ContactRepositoryPort $contacts,
    ) {}

    /**
     * @return list<string> warnings for the operator
     */
    public function handle(?Contact $decisor): array
    {
        if ($decisor === null || $decisor->lastVerifiedAt === null) {
            return [];
        }

        $now = CarbonImmutable::now();

        if ($decisor->lastVerifiedAt > $now->subDays(self::REVERIFY_AFTER_DAYS)) {
            return [];
        }

        if ($decisor->evidenceUrl === null) {
            return ['Decisor data is older than 90 days and has no evidence page to re-verify: confirm manually.'];
        }

        $result = $this->fetcher->fetch($decisor->companyId, $decisor->evidenceUrl);

        if (! $result->succeeded()) {
            return ['Could not re-verify the decisor page: confirm the person manually before sending.'];
        }

        if (! str_contains(mb_strtolower((string) $result->markdown), mb_strtolower(trim((string) $decisor->fullName)))) {
            $this->contacts->anonymize($decisor->id, $now);

            return ['The decisor no longer appears on the evidence page and was anonymized.'];
        }

        $this->contacts->markVerified($decisor->id, $now);

        return [];
    }
}
