<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Modules\LeadScout\Domain\Entities\Company;
use Modules\LeadScout\Domain\Entities\Contact;
use Modules\LeadScout\Domain\Entities\ContactChannel;
use Modules\LeadScout\Domain\Enums\EmailKind;
use Modules\LeadScout\Domain\Enums\LegalRuleStatus;
use Modules\LeadScout\Domain\Ports\SuppressionRepositoryPort;
use Modules\LeadScout\Domain\Services\ChannelAdvisor;
use Modules\LeadScout\Domain\Services\SuppressionGate;

/**
 * Ranks a company's channels by legal × commercial fit (spec US-5, US-12,
 * FR-44) with the country rules from config and the DGC-list state. One
 * source for the lead detail and the draft, so both advise identically.
 */
final readonly class AdviseContactChannelsHandler
{
    public function __construct(
        private ChannelAdvisor $advisor,
        private SuppressionGate $gate,
        private SuppressionRepositoryPort $suppressions,
        private Config $config,
    ) {}

    /**
     * @param  list<ContactChannel>  $channels
     * @param  list<Contact>  $contacts  live decisors
     * @return array{ranked: list<array{uuid: ?string, type: string, url: ?string, rank: int, allowed: bool, blocked_reason: ?string, warning: ?string}>, recommended_uuid: ?string, cold_email_allowed: bool, employment_application: bool}
     */
    #[\NoDiscard]
    public function handle(Company $company, array $channels, array $contacts, bool $hasOffer, bool $isEmploymentOffer): array
    {
        $primary = Contact::primaryOf($contacts);

        return $this->advisor->advise(
            array_map(static fn (ContactChannel $channel): array => [
                'uuid' => $channel->uuid,
                'type' => $channel->channelType->value,
                'url' => $channel->url,
                'generic_email' => $channel->genericEmail,
                'status' => $channel->status->value,
                'audience' => $channel->audience?->value,
            ], $channels),
            [
                'country' => $company->country,
                'has_offer' => $hasOffer,
                'is_employment_offer' => $isEmploymentOffer,
                'discovery_without_offer' => $company->origin->value === 'discovery' && ! $hasOffer,
                'has_decisor' => $contacts !== [],
                'nominative_email' => $primary?->emailKind === EmailKind::Nominative ? $primary->publishedEmail : null,
                'dgc_listed' => $this->suppressions->isDgcListed($company->canonicalDomain, $company->taxId, $company->name),
                'dgc_list_stale' => $this->gate->dgcListIsStale($this->suppressions->latestDgcImportAt(), CarbonImmutable::now()),
            ],
            $this->contactRules(),
        );
    }

    /**
     * Legal state a send through this channel would carry (spec FR-44).
     */
    #[\NoDiscard]
    public function legalRuleStatus(Company $company, ContactChannel $channel): ?LegalRuleStatus
    {
        return $this->advisor->legalRuleStatus($channel->channelType, $company->country, $this->contactRules());
    }

    /**
     * @return list<array{country: string, medium: string, mailbox: string, decision: string, legal_status: string}>
     */
    private function contactRules(): array
    {
        return (array) $this->config->get('lead-scout.contact_rules', []);
    }
}
