<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Queries;

use Modules\LeadScout\Application\DTOs\ContactData;
use Modules\LeadScout\Application\DTOs\LeadDetailData;
use Modules\LeadScout\Application\DTOs\OutreachData;
use Modules\LeadScout\Domain\Entities\Contact;
use Modules\LeadScout\Domain\Entities\ContactChannel;
use Modules\LeadScout\Domain\Entities\JobPosting;
use Modules\LeadScout\Domain\Enums\ContractType;
use Modules\LeadScout\Domain\Exceptions\CompanyNotFoundException;
use Modules\LeadScout\Domain\Ports\CompanyRepositoryPort;
use Modules\LeadScout\Domain\Ports\ContactChannelRepositoryPort;
use Modules\LeadScout\Domain\Ports\ContactRepositoryPort;
use Modules\LeadScout\Domain\Ports\JobPostingRepositoryPort;
use Modules\LeadScout\Domain\Ports\OutreachRepositoryPort;
use Modules\LeadScout\Domain\Ports\ScoreResultRepositoryPort;

/**
 * Single bandeja detail read (spec US-5, T060): company, postings, current
 * score + reasons (with their signal evidence), decisors, ranked channels
 * and outreaches (+ opportunities), each read through its repository.
 */
final readonly class GetLeadHandler
{
    public function __construct(
        private CompanyRepositoryPort $companies,
        private JobPostingRepositoryPort $postings,
        private ScoreResultRepositoryPort $scores,
        private ContactRepositoryPort $contacts,
        private ContactChannelRepositoryPort $channels,
        private OutreachRepositoryPort $outreaches,
        private AdviseContactChannelsHandler $channelAdvice,
    ) {}

    public function handle(string $uuid): LeadDetailData
    {
        $company = $this->companies->byUuid($uuid) ?? throw new CompanyNotFoundException($uuid);

        $postings = $this->postings->forCompany($company->id);
        $score = $this->scores->currentFor($company->id);
        $channels = $this->channels->forCompany($company->id);

        // Primary decisor first; the sort is stable, so the rest keep their order.
        $contacts = $this->contacts->liveForCompany($company->id);
        usort($contacts, static fn (Contact $a, Contact $b): int => $b->isPrimary <=> $a->isPrimary);

        $advice = $this->channelAdvice->handle(
            $company,
            $channels,
            $contacts,
            $postings !== [],
            array_any($postings, static fn (JobPosting $posting): bool => $posting->contractType === ContractType::Employment),
        );

        $channelsByUuid = array_column(
            array_map(static fn (ContactChannel $channel): array => ['uuid' => $channel->uuid, 'channel' => $channel], $channels),
            'channel',
            'uuid',
        );

        return new LeadDetailData(
            company: [
                'uuid' => $company->uuid,
                'name' => $company->name,
                'domain' => $company->canonicalDomain,
                'country' => $company->country,
                'company_type' => $company->companyType?->value,
                'origin' => $company->origin->value,
                'origin_ref' => $company->originRef,
                'discovery_wave' => $company->discoveryWave,
                'employee_range' => $company->employeeRange->value,
                'team_size_observed' => $company->teamSizeObserved,
                'has_decision_maker' => $company->hasDecisionMaker,
                'needs_research' => $company->needsResearch,
                'activity_status' => $company->activityStatus->value,
                'legal_name' => $company->legalName,
                'city' => $company->city,
                'founded_year' => $company->foundedYear,
                'services' => $company->services,
                'sectors' => $company->sectors,
            ],
            postings: array_map(static fn (JobPosting $posting): array => [
                'uuid' => $posting->uuid,
                'title' => $posting->title,
                'status' => $posting->status->value,
                'source_url' => $posting->sourceUrl,
            ], $postings),
            score: $score === null ? null : [
                'subscores' => $score->subscores,
                'lead_score' => $score->leadScore,
                'confidence' => $score->confidence,
                'tier' => $score->tier->value,
                'discard_reason' => $score->discardReason?->value,
                'rules_version' => $score->rulesVersion,
            ],
            reasons: $score === null ? [] : array_map(static fn (array $reason): array => [
                'points' => $reason['points'],
                'explanation' => $reason['explanation'],
                'signal_key' => $reason['signal_key'],
                'evidence_url' => $reason['evidence_url'],
                'evidence_excerpt' => $reason['evidence_excerpt'],
            ], $score->reasons),
            decisors: array_map(ContactData::fromEntity(...), $contacts),
            channels: array_map(
                static function (array $ranked) use ($channelsByUuid): array {
                    $channel = $ranked['uuid'] === null ? null : ($channelsByUuid[$ranked['uuid']] ?? null);

                    return $ranked + [
                        'audience' => $channel?->audience?->value,
                        'evidence_url' => $channel?->evidenceUrl,
                    ];
                },
                $advice['ranked'],
            ),
            outreaches: array_map(OutreachData::fromEntity(...), $this->outreaches->forCompany($company->id)),
            coldEmailAllowed: $advice['cold_email_allowed'],
            employmentApplication: $advice['employment_application'],
        );
    }
}
