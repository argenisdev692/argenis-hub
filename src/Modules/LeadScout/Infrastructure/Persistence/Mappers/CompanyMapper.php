<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Mappers;

use Modules\LeadScout\Domain\Entities\Company;
use Modules\LeadScout\Domain\Enums\ActivityStatus;
use Modules\LeadScout\Domain\Enums\EmployeeRange;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;

final readonly class CompanyMapper
{
    public static function toEntity(ScoutCompanyEloquentModel $model): Company
    {
        return new Company(
            id: $model->id,
            uuid: $model->uuid,
            canonicalDomain: $model->canonical_domain,
            name: $model->name,
            country: $model->country,
            origin: $model->origin,
            originRef: $model->origin_ref,
            discoveryWave: $model->discovery_wave === null ? null : (string) $model->discovery_wave,
            companyType: $model->company_type,
            employeeRange: $model->employee_range ?? EmployeeRange::Unknown,
            teamSizeObserved: $model->team_size_observed,
            hasDecisionMaker: (bool) $model->has_decision_maker,
            needsResearch: (bool) $model->needs_research,
            activityStatus: $model->activity_status ?? ActivityStatus::Unknown,
            lastActivityAt: $model->last_activity_at?->toDateTimeImmutable(),
            timezoneOverlapHours: $model->timezone_overlap_hours,
            legalName: $model->legal_name,
            legalForm: $model->legal_form,
            taxId: $model->tax_id,
            registryInfo: $model->registry_info,
            city: $model->city,
            foundedYear: $model->founded_year,
            aliases: $model->aliases ?? [],
            services: $model->services ?? [],
            sectors: $model->sectors ?? [],
            siteLanguages: $model->site_languages ?? [],
            clientCompanies: $model->client_companies ?? [],
            publicUrls: $model->public_urls ?? [],
            publicDataEvidence: $model->public_data_evidence ?? [],
            createdAt: $model->created_at?->toDateTimeImmutable(),
            updatedAt: $model->updated_at?->toDateTimeImmutable(),
        );
    }
}
