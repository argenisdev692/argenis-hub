<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\LeadScout\Domain\Ports\CompanyRepositoryPort;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSuppressionEloquentModel;

/**
 * Every write runs inside a transaction; reads select explicit columns and
 * eager-load on demand (N+1 discipline, BACKEND-PHP §4.1).
 */
final readonly class EloquentCompanyRepository implements CompanyRepositoryPort
{
    public function findByDomain(string $domain): ?ScoutCompanyEloquentModel
    {
        return ScoutCompanyEloquentModel::query()->where('canonical_domain', $domain)->first();
    }

    public function findByUuid(string $uuid): ?ScoutCompanyEloquentModel
    {
        return ScoutCompanyEloquentModel::query()->where('uuid', $uuid)->first();
    }

    public function create(array $attributes): ScoutCompanyEloquentModel
    {
        return DB::transaction(
            static fn (): ScoutCompanyEloquentModel => ScoutCompanyEloquentModel::query()->create($attributes),
        );
    }

    public function update(ScoutCompanyEloquentModel $company, array $attributes): ScoutCompanyEloquentModel
    {
        return DB::transaction(static function () use ($company, $attributes): ScoutCompanyEloquentModel {
            $company->update($attributes);

            return $company->refresh();
        });
    }

    public function suppressionsMatching(?string $domain, ?string $taxId, ?string $name): Collection
    {
        return ScoutSuppressionEloquentModel::query()
            ->when($domain !== null, fn ($q) => $q->orWhere('canonical_domain', $domain))
            ->when($taxId !== null, fn ($q) => $q->orWhere('tax_id', $taxId))
            ->when($name !== null, fn ($q) => $q->orWhere('name', $name))
            ->get();
    }
}
