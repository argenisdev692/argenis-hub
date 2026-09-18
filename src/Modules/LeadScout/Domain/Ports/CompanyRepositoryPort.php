<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use Illuminate\Support\Collection;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSuppressionEloquentModel;

/**
 * Company persistence (mandatory port in the intermediate baseline).
 * Also answers suppression lookups — suppression is company-adjacent and
 * a dedicated port for one query would be indirection, not DIP.
 */
interface CompanyRepositoryPort
{
    public function findByDomain(string $domain): ?ScoutCompanyEloquentModel;

    public function findByUuid(string $uuid): ?ScoutCompanyEloquentModel;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ScoutCompanyEloquentModel;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(ScoutCompanyEloquentModel $company, array $attributes): ScoutCompanyEloquentModel;

    /**
     * Candidate suppressions for an exact-match decision in SuppressionGate.
     *
     * @return Collection<int, ScoutSuppressionEloquentModel>
     */
    public function suppressionsMatching(?string $domain, ?string $taxId, ?string $name): Collection;
}
