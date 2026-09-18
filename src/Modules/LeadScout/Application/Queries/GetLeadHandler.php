<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Queries;

use Modules\LeadScout\Application\DTOs\ContactData;
use Modules\LeadScout\Application\DTOs\LeadDetailData;
use Modules\LeadScout\Application\DTOs\OutreachData;
use Modules\LeadScout\Domain\Enums\ContractType;
use Modules\LeadScout\Domain\Exceptions\CompanyNotFoundException;
use Modules\LeadScout\Domain\Services\ChannelAdvisor;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSuppressionEloquentModel;

/**
 * Single bandeja detail read (spec US-5, T060): the whole graph in ≤ 5
 * queries — company, postings, current score + reasons (+signals),
 * decisors, channels, outreaches (+opportunities). Relations load on
 * explicit columns with the user-facing DTOs as the only shape.
 */
final readonly class GetLeadHandler
{
    public function __construct(private ChannelAdvisor $advisor) {}

    public function handle(string $uuid): LeadDetailData
    {
        $company = ScoutCompanyEloquentModel::query()
            ->where('uuid', $uuid)
            ->with([
                'postings:id,company_id,uuid,title,status,source_url,contract_type',
                'scoreResults' => fn ($query) => $query->where('is_current', true),
                'scoreResults.reasons' => fn ($query) => $query->orderBy('id'),
                'scoreResults.reasons.signal:id,signal_key,evidence_url,evidence_excerpt',
                'contacts' => fn ($query) => $query->whereNull('anonymized_at')->orderByDesc('is_primary'),
                'contactChannels' => fn ($query) => $query->orderBy('id'),
                'outreaches' => fn ($query) => $query->orderByDesc('created_at'),
                'outreaches.opportunities' => fn ($query) => $query->orderBy('id'),
                'outreaches.contactChannel' => fn ($query) => $query->select(['id', 'uuid']),
                'outreaches.opportunities:id,outreach_id',
            ])
            ->first([
                'id', 'uuid', 'name', 'canonical_domain', 'country', 'company_type', 'origin',
                'origin_ref', 'discovery_wave', 'employee_range', 'team_size_observed',
                'has_decision_maker', 'needs_research', 'activity_status', 'tax_id',
                'legal_name', 'city', 'founded_year', 'services', 'sectors', 'created_at', 'updated_at',
            ])
            ?? throw new CompanyNotFoundException($uuid);

        $score = $company->scoreResults->first();

        $primary = $company->contacts->firstWhere('is_primary', true) ?? $company->contacts->first();

        $advice = $this->advisor->advise(
            $company->contactChannels->map(static fn ($channel): array => [
                'uuid' => $channel->uuid,
                'type' => $channel->channel_type->value,
                'url' => $channel->url,
                'generic_email' => $channel->generic_email,
                'status' => $channel->status->value,
                'audience' => $channel->audience?->value,
            ])->all(),
            [
                'country' => $company->country,
                'has_offer' => $company->postings->isNotEmpty(),
                'is_employment_offer' => $company->postings->contains(
                    static fn ($posting): bool => $posting->contract_type === ContractType::Employment,
                ),
                'discovery_without_offer' => $company->origin->value === 'discovery' && $company->postings->isEmpty(),
                'has_decisor' => $company->contacts->isNotEmpty(),
                'nominative_email' => $primary?->email_kind?->value === 'nominative' ? $primary->published_email : null,
                'dgc_listed' => $this->dgcListed($company),
                'dgc_list_stale' => $this->dgcListStale(),
            ],
            (array) config('lead-scout.contact_rules', []),
        );

        $channelAudience = $company->contactChannels->keyBy('uuid');

        return new LeadDetailData(
            company: [
                'uuid' => $company->uuid,
                'name' => $company->name,
                'domain' => $company->canonical_domain,
                'country' => $company->country,
                'company_type' => $company->company_type?->value,
                'origin' => $company->origin->value,
                'origin_ref' => $company->origin_ref,
                'discovery_wave' => $company->discovery_wave,
                'employee_range' => $company->employee_range->value,
                'team_size_observed' => $company->team_size_observed,
                'has_decision_maker' => (bool) $company->has_decision_maker,
                'needs_research' => (bool) $company->needs_research,
                'activity_status' => $company->activity_status->value,
                'legal_name' => $company->legal_name,
                'city' => $company->city,
                'founded_year' => $company->founded_year,
                'services' => $company->services,
                'sectors' => $company->sectors,
            ],
            postings: $company->postings->map(static fn ($posting): array => [
                'uuid' => $posting->uuid,
                'title' => $posting->title,
                'status' => $posting->status->value,
                'source_url' => $posting->source_url,
            ])->all(),
            score: $score === null ? null : [
                'subscores' => $score->subscores ?? [],
                'lead_score' => $score->lead_score,
                'confidence' => $score->confidence,
                'tier' => $score->tier->value,
                'discard_reason' => $score->discard_reason?->value,
                'rules_version' => $score->rules_version,
            ],
            reasons: $score === null ? [] : $score->reasons->map(static fn ($reason): array => [
                'points' => $reason->points,
                'explanation' => $reason->explanation,
                'signal_key' => $reason->signal?->signal_key,
                'evidence_url' => $reason->signal?->evidence_url,
                'evidence_excerpt' => $reason->signal?->evidence_excerpt,
            ])->all(),
            decisors: $company->contacts->map(
                static fn ($contact): ContactData => ContactData::fromModel($contact),
            )->all(),
            channels: array_map(
                static fn (array $ranked): array => $ranked + [
                    'audience' => $channelAudience->get($ranked['uuid'])?->audience?->value,
                    'evidence_url' => $channelAudience->get($ranked['uuid'])?->evidence_url,
                ],
                $advice['ranked'],
            ),
            outreaches: $company->outreaches->map(
                static fn ($outreach): OutreachData => OutreachData::fromModel($outreach, $company->uuid),
            )->all(),
            coldEmailAllowed: (bool) $advice['cold_email_allowed'],
            employmentApplication: (bool) $advice['employment_application'],
        );
    }

    private function dgcListed(ScoutCompanyEloquentModel $company): bool
    {
        return ScoutSuppressionEloquentModel::query()
            ->where('source', 'dgc_list')
            ->where(function ($query) use ($company): void {
                $query->where('canonical_domain', $company->canonical_domain);

                if ($company->tax_id !== null) {
                    $query->orWhere('tax_id', $company->tax_id);
                }

                $query->orWhere('name', $company->name);
            })
            ->exists();
    }

    private function dgcListStale(): bool
    {
        $latest = ScoutSuppressionEloquentModel::query()
            ->where('source', 'dgc_list')
            ->max('created_at');

        return $latest === null || $latest < now()->subMonths(3)->toDateTimeString();
    }
}
