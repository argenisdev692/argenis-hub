<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Modules\LeadScout\Domain\Entities\Contact;
use Modules\LeadScout\Domain\Ports\ContactRepositoryPort;
use Modules\LeadScout\Domain\Ports\FetchedPageRepositoryPort;

/**
 * Retention janitor (spec FR-27, T070): decisors uncontacted past their
 * 30-day deadline and contacted ones 12 months after the last interaction
 * are anonymized (cargo kept for metrics); page markdown older than 30
 * days is emptied (hash + forms summary kept, evidence lives on signals).
 */
final readonly class PruneLeadContactsHandler
{
    public function __construct(
        private ContactRepositoryPort $contacts,
        private FetchedPageRepositoryPort $pages,
    ) {}

    /**
     * @return array{contacts: int, pages: int}
     */
    public function handle(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $contacts = 0;

        foreach ($this->contacts->retentionCandidates() as ['contact' => $contact, 'lastTouchAt' => $lastTouchAt]) {
            if (self::isDue($contact, $lastTouchAt, $now)) {
                $this->contacts->anonymize($contact->id, $now);
                $contacts++;
            }
        }

        $pages = $this->pages->pruneContentFetchedBefore($now->subDays(30), $now);

        return ['contacts' => $contacts, 'pages' => $pages];
    }

    private static function isDue(Contact $contact, ?DateTimeImmutable $lastTouchAt, CarbonImmutable $now): bool
    {
        if ($lastTouchAt !== null) {
            return $lastTouchAt < $now->subMonths(12);
        }

        return $contact->contactDeadlineAt !== null && $contact->contactDeadlineAt < $now;
    }
}
