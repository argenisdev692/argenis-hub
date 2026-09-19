<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\ScoutCompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\LeadScout\Domain\Enums\ActivityStatus;
use Modules\LeadScout\Domain\Enums\CompanyOrigin;
use Modules\LeadScout\Domain\Enums\CompanyType;
use Modules\LeadScout\Domain\Enums\EmployeeRange;
use Modules\LeadScout\Domain\ValueObjects\LeadCriteria;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Target company aggregate root: identity + vitality + public legal-person
 * data (allowlist, spec FR-38). Never a natural person (spec FR-39).
 *
 * @property int $id
 * @property string $uuid
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('scout_companies')]
#[Fillable([
    'uuid',
    'canonical_domain',
    'aliases',
    'name',
    'country',
    'origin',
    'origin_ref',
    'discovery_wave',
    'company_type',
    'employee_range',
    'team_size_observed',
    'has_decision_maker',
    'needs_research',
    'activity_status',
    'last_activity_at',
    'timezone_overlap_hours',
    'legal_name',
    'legal_form',
    'tax_id',
    'registry_info',
    'city',
    'founded_year',
    'services',
    'sectors',
    'site_languages',
    'client_companies',
    'public_urls',
    'public_data_evidence',
])]
final class ScoutCompanyEloquentModel extends Model
{
    /** @use HasFactory<ScoutCompanyFactory> */
    use HasFactory, HasUuids, LogsActivity;

    /** @var list<string> */
    protected $hidden = ['id'];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origin' => CompanyOrigin::class,
            'company_type' => CompanyType::class,
            'employee_range' => EmployeeRange::class,
            'has_decision_maker' => 'boolean',
            'needs_research' => 'boolean',
            'activity_status' => ActivityStatus::class,
            'last_activity_at' => 'datetime',
            'timezone_overlap_hours' => 'integer',
            'founded_year' => 'integer',
            'aliases' => 'array',
            'services' => 'array',
            'sectors' => 'array',
            'site_languages' => 'array',
            'client_companies' => 'array',
            'public_urls' => 'array',
            'public_data_evidence' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'canonical_domain', 'country', 'company_type', 'employee_range', 'activity_status', 'needs_research'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('lead-scout.company');
    }

    /**
     * @return HasMany<ScoutJobPostingEloquentModel, $this>
     */
    public function postings(): HasMany
    {
        return $this->hasMany(ScoutJobPostingEloquentModel::class, 'company_id');
    }

    /**
     * @return HasMany<ScoutFetchedPageEloquentModel, $this>
     */
    public function fetchedPages(): HasMany
    {
        return $this->hasMany(ScoutFetchedPageEloquentModel::class, 'company_id');
    }

    /**
     * @return HasMany<ScoutFetchAttemptEloquentModel, $this>
     */
    public function fetchAttempts(): HasMany
    {
        return $this->hasMany(ScoutFetchAttemptEloquentModel::class, 'company_id');
    }

    /**
     * @return HasMany<ScoutSignalEloquentModel, $this>
     */
    public function signals(): HasMany
    {
        return $this->hasMany(ScoutSignalEloquentModel::class, 'company_id');
    }

    /**
     * @return HasMany<ScoutScoreResultEloquentModel, $this>
     */
    public function scoreResults(): HasMany
    {
        return $this->hasMany(ScoutScoreResultEloquentModel::class, 'company_id');
    }

    /**
     * @return HasMany<ScoutContactEloquentModel, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(ScoutContactEloquentModel::class, 'company_id');
    }

    /**
     * @return HasMany<ScoutContactChannelEloquentModel, $this>
     */
    public function contactChannels(): HasMany
    {
        return $this->hasMany(ScoutContactChannelEloquentModel::class, 'company_id');
    }

    /**
     * @return HasMany<ScoutOutreachEloquentModel, $this>
     */
    public function outreaches(): HasMany
    {
        return $this->hasMany(ScoutOutreachEloquentModel::class, 'company_id');
    }

    /**
     * Single source for bandeja + export queries (BACKEND-PHP §5.2, DRY):
     * the ONLY place the filter chain lives, so list and export can never
     * disagree on which rows a filter selects.
     *
     * @param  Builder<ScoutCompanyEloquentModel>  $query
     * @return Builder<ScoutCompanyEloquentModel>
     */
    public function scopeApplyFilters(Builder $query, LeadCriteria $criteria): Builder
    {
        // Qualified: the bandeja joins `scout_score_results`, which also has `created_at`.
        $createdAt = $this->qualifyColumn('created_at');
        $values = static fn (array $cases): array => array_map(static fn (\BackedEnum $case): string|int => $case->value, $cases);

        return $query
            ->when($criteria->search !== null, fn (Builder $q): Builder => $q->where(function (Builder $q) use ($criteria): void {
                $term = '%'.$criteria->search.'%';
                $q->where('name', 'like', $term)->orWhere('canonical_domain', 'like', $term);
            }))
            ->when($criteria->countries !== null, fn (Builder $q): Builder => $q->whereIn('country', $criteria->countries))
            ->when($criteria->companyTypes !== null, fn (Builder $q): Builder => $q->whereIn('company_type', $values($criteria->companyTypes)))
            ->when($criteria->origins !== null, fn (Builder $q): Builder => $q->whereIn('origin', $values($criteria->origins)))
            ->when($criteria->needsResearch !== null, fn (Builder $q): Builder => $q->where('needs_research', $criteria->needsResearch))
            ->when($criteria->tiers !== null, fn (Builder $q): Builder => $q->whereHas('scoreResults', fn (Builder $q): Builder => $q
                ->where('is_current', true)->whereIn('tier', $values($criteria->tiers))))
            ->when($criteria->signalDimensions !== null, fn (Builder $q): Builder => $q->whereHas('signals', fn (Builder $q): Builder => $q
                ->whereIn('dimension', $values($criteria->signalDimensions))))
            ->when($criteria->stages !== null, fn (Builder $q): Builder => $q->whereHas('outreaches', fn (Builder $q): Builder => $q
                ->whereIn('stage', $values($criteria->stages))))
            ->when($criteria->createdFrom !== null && $criteria->createdTo !== null, fn (Builder $q): Builder => $q
                ->whereBetween($createdAt, [$criteria->createdFrom, $criteria->createdTo]))
            ->when($criteria->createdFrom !== null && $criteria->createdTo === null, fn (Builder $q): Builder => $q
                ->where($createdAt, '>=', $criteria->createdFrom))
            ->when($criteria->createdFrom === null && $criteria->createdTo !== null, fn (Builder $q): Builder => $q
                ->where($createdAt, '<=', $criteria->createdTo));
    }

    protected static function newFactory(): ScoutCompanyFactory
    {
        return ScoutCompanyFactory::new();
    }
}
