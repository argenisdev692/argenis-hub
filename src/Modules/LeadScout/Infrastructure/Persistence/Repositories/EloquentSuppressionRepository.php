<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Repositories;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\LeadScout\Domain\Entities\Suppression;
use Modules\LeadScout\Domain\Enums\SuppressionSource;
use Modules\LeadScout\Domain\Ports\SuppressionRepositoryPort;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSuppressionEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Mappers\SuppressionMapper;

final readonly class EloquentSuppressionRepository implements SuppressionRepositoryPort
{
    public function matching(?string $domain, ?string $taxId, ?string $name): array
    {
        if ($domain === null && $taxId === null && $name === null) {
            return [];
        }

        return ScoutSuppressionEloquentModel::query()
            ->where(self::anyOf($domain, $taxId, $name))
            ->get()
            ->map(SuppressionMapper::toEntity(...))
            ->values()
            ->all();
    }

    public function suppressDomain(string $domain, SuppressionSource $source, string $reason): Suppression
    {
        return DB::transaction(static fn (): Suppression => SuppressionMapper::toEntity(
            ScoutSuppressionEloquentModel::query()->firstOrCreate(
                ['canonical_domain' => $domain],
                ['source' => $source->value, 'reason' => $reason],
            ),
        ));
    }

    public function recordDgcListing(?string $taxId, ?string $domain, ?string $name, string $period, string $reason): bool
    {
        return DB::transaction(static function () use ($taxId, $domain, $name, $period, $reason): bool {
            $existing = ScoutSuppressionEloquentModel::query()->where(self::anyOf($domain, $taxId, $name))->first();

            if ($existing !== null) {
                $existing->update([
                    'tax_id' => $taxId ?? $existing->tax_id,
                    'canonical_domain' => $domain ?? $existing->canonical_domain,
                    'name' => $name ?? $existing->name,
                    'source' => SuppressionSource::DgcList->value,
                    'list_period' => $period,
                ]);

                return false;
            }

            ScoutSuppressionEloquentModel::query()->create([
                'canonical_domain' => $domain,
                'tax_id' => $taxId,
                'name' => $name,
                'source' => SuppressionSource::DgcList->value,
                'reason' => $reason,
                'list_period' => $period,
            ]);

            return true;
        });
    }

    public function isDgcListed(string $domain, ?string $taxId, string $name): bool
    {
        return ScoutSuppressionEloquentModel::query()
            ->where('source', SuppressionSource::DgcList->value)
            ->where(self::anyOf($domain, $taxId, $name))
            ->exists();
    }

    public function latestDgcImportAt(): ?DateTimeImmutable
    {
        $latest = ScoutSuppressionEloquentModel::query()
            ->where('source', SuppressionSource::DgcList->value)
            ->max('created_at');

        return $latest === null ? null : CarbonImmutable::parse((string) $latest)->toDateTimeImmutable();
    }

    /**
     * @return \Closure(Builder<ScoutSuppressionEloquentModel>): void
     */
    private static function anyOf(?string $domain, ?string $taxId, ?string $name): \Closure
    {
        return static function (Builder $query) use ($domain, $taxId, $name): void {
            $query->when($domain !== null, static fn (Builder $q) => $q->orWhere('canonical_domain', $domain))
                ->when($taxId !== null, static fn (Builder $q) => $q->orWhere('tax_id', $taxId))
                ->when($name !== null, static fn (Builder $q) => $q->orWhere('name', $name));
        };
    }
}
