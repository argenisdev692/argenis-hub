<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Repositories;

use Modules\LeadScout\Domain\Entities\Company;
use Modules\LeadScout\Domain\Enums\CompanyOrigin;
use Modules\LeadScout\Domain\Enums\CompanyType;
use Modules\LeadScout\Domain\Ports\CompanyRepositoryPort;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Mappers\CompanyMapper;

/**
 * Every write runs inside a transaction; reads select explicit columns and
 * eager-load on demand (N+1 discipline, BACKEND-PHP §4.1).
 */
final readonly class EloquentCompanyRepository implements CompanyRepositoryPort
{
    public function byUuid(string $uuid): ?Company
    {
        $model = ScoutCompanyEloquentModel::query()->where('uuid', $uuid)->first();

        return $model === null ? null : CompanyMapper::toEntity($model);
    }

    public function byDomain(string $domain): ?Company
    {
        $model = ScoutCompanyEloquentModel::query()->where('canonical_domain', $domain)->first();

        return $model === null ? null : CompanyMapper::toEntity($model);
    }

    public function byId(int $id): ?Company
    {
        $model = ScoutCompanyEloquentModel::query()->find($id);

        return $model === null ? null : CompanyMapper::toEntity($model);
    }

    public function byAlias(string $domain): ?Company
    {
        $model = ScoutCompanyEloquentModel::query()->whereJsonContains('aliases', $domain)->first();

        return $model === null ? null : CompanyMapper::toEntity($model);
    }

    public function register(
        string $canonicalDomain,
        string $name,
        CompanyOrigin $origin,
        ?string $originRef,
        ?string $country = null,
        ?string $discoveryWave = null,
        ?int $timezoneOverlapHours = null,
    ): Company {
        return CompanyMapper::toEntity(ScoutCompanyEloquentModel::query()->create([
            'canonical_domain' => $canonicalDomain,
            'name' => $name,
            'origin' => $origin->value,
            'origin_ref' => $originRef,
            'country' => $country,
            'discovery_wave' => $discoveryWave,
            'timezone_overlap_hours' => $timezoneOverlapHours,
        ]));
    }

    public function recordPublicData(Company $company, array $fields, array $evidence): void
    {
        $this->write($company, [...$fields, 'public_data_evidence' => $evidence]);
    }

    public function recordDecisionMakers(Company $company, bool $hasDecisionMaker, ?int $teamSizeObserved): void
    {
        $this->write($company, [
            'has_decision_maker' => $hasDecisionMaker,
            'team_size_observed' => $teamSizeObserved ?? $company->teamSizeObserved,
        ]);
    }

    public function recordClassification(Company $company, ?CompanyType $type, ?int $teamSizeObserved): void
    {
        $this->write($company, array_filter([
            'company_type' => $type?->value,
            'team_size_observed' => $teamSizeObserved,
        ], static fn (mixed $value): bool => $value !== null));
    }

    public function setNeedsResearch(Company $company, bool $needsResearch): void
    {
        $this->write($company, ['needs_research' => $needsResearch]);
    }

    /**
     * Model update, so the activity log records the change.
     *
     * @param  array<string, mixed>  $changes
     */
    private function write(Company $company, array $changes): void
    {
        if ($changes !== []) {
            ScoutCompanyEloquentModel::query()->findOrFail($company->id)->update($changes);
        }
    }
}
