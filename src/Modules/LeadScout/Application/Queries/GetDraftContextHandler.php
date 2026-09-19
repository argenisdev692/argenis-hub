<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Queries;

use Modules\LeadScout\Domain\Entities\Signal;
use Modules\LeadScout\Domain\Enums\Tier;
use Modules\LeadScout\Domain\Exceptions\CompanyNotFoundException;
use Modules\LeadScout\Domain\Exceptions\SuppressedException;
use Modules\LeadScout\Domain\Exceptions\TierNotContactableException;
use Modules\LeadScout\Domain\Ports\CompanyRepositoryPort;
use Modules\LeadScout\Domain\Ports\ContactChannelRepositoryPort;
use Modules\LeadScout\Domain\Ports\ContactRepositoryPort;
use Modules\LeadScout\Domain\Ports\JobPostingRepositoryPort;
use Modules\LeadScout\Domain\Ports\ProfileRepositoryPort;
use Modules\LeadScout\Domain\Ports\ScoreResultRepositoryPort;
use Modules\LeadScout\Domain\Ports\SignalRepositoryPort;
use Modules\LeadScout\Domain\Ports\SuppressionRepositoryPort;
use Modules\LeadScout\Domain\Services\SuppressionGate;
use Modules\LeadScout\Domain\ValueObjects\DraftContext;

/**
 * Loads what a draft is grounded on — only for a company that may be
 * contacted: not suppressed (FR-43) and currently Tier A/B (FR-40).
 */
final readonly class GetDraftContextHandler
{
    public function __construct(
        private CompanyRepositoryPort $companies,
        private ScoreResultRepositoryPort $scores,
        private SignalRepositoryPort $signals,
        private JobPostingRepositoryPort $postings,
        private ContactRepositoryPort $contacts,
        private ContactChannelRepositoryPort $channels,
        private ProfileRepositoryPort $profiles,
        private SuppressionGate $gate,
        private SuppressionRepositoryPort $suppressions,
    ) {}

    /**
     * @throws CompanyNotFoundException
     * @throws SuppressedException
     * @throws TierNotContactableException
     */
    public function handle(string $companyUuid, int $operatorId): DraftContext
    {
        $company = $this->companies->byUuid($companyUuid) ?? throw new CompanyNotFoundException($companyUuid);

        $candidates = $this->suppressions->matching($company->canonicalDomain, $company->taxId, $company->name);

        if ($this->gate->isSuppressed($company->canonicalDomain, $company->taxId, $company->name, $candidates)) {
            throw new SuppressedException;
        }

        $tier = $this->scores->currentTier($company->id);

        if ($tier !== Tier::A && $tier !== Tier::B) {
            throw new TierNotContactableException;
        }

        // Highest confidence first (stable: ties keep insertion order).
        $signals = $this->signals->forCompany($company->id);
        usort($signals, static fn (Signal $a, Signal $b): int => $b->confidence <=> $a->confidence);

        return new DraftContext(
            company: $company,
            signals: $signals,
            hasOffer: $this->postings->activeForCompany($company->id) !== [],
            contacts: $this->contacts->liveForCompany($company->id),
            channels: $this->channels->activeForCompany($company->id),
            profile: $this->profiles->current($operatorId),
        );
    }
}
