<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\LeadScout\Domain\Enums\PostingStatus;
use Modules\LeadScout\Domain\Exceptions\CompanyNotFoundException;
use Modules\LeadScout\Domain\Exceptions\SuppressedException;
use Modules\LeadScout\Domain\Ports\CompanyRepositoryPort;
use Modules\LeadScout\Domain\Services\ScoringEngine;
use Modules\LeadScout\Domain\Services\SuppressionGate;
use Modules\LeadScout\Domain\Services\TierClassifier;
use Modules\LeadScout\Domain\ValueObjects\SkillTaxonomy;
use Modules\LeadScout\Infrastructure\Logging\ApplicationLogger;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutProfileEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutScoreResultEloquentModel;

/**
 * Scores a company from its STORED signals (spec US-4, FR-8, T034):
 * deterministic engine + tier classifier → exactly one `is_current` result
 * with FK-linked reasons → `needs_research` flag back on the company.
 * Same stored input always yields the same result (US-4 CA-4).
 */
final readonly class ScoreCompanyHandler
{
    public function __construct(
        private ScoringEngine $engine,
        private TierClassifier $tiers,
        private CompanyRepositoryPort $companies,
        private SuppressionGate $gate,
        private ApplicationLogger $log,
    ) {}

    public function handle(string $companyUuid, ?int $userId = null, bool $extraRoundDone = false): ScoutScoreResultEloquentModel
    {
        $company = ScoutCompanyEloquentModel::query()->where('uuid', $companyUuid)->first()
            ?? throw new CompanyNotFoundException($companyUuid);

        // An unsubscribe prevails over any re-score (spec FR-43, T084).
        $candidates = $this->companies->suppressionsMatching($company->canonical_domain, $company->tax_id, $company->name)
            ->map(static fn ($row): array => [
                'canonical_domain' => $row->canonical_domain,
                'tax_id' => $row->tax_id,
                'name' => $row->name,
            ])
            ->all();

        if ($this->gate->isSuppressed($company->canonical_domain, $company->tax_id, $company->name, $candidates)) {
            throw new SuppressedException;
        }

        $hadFlag = (bool) $company->needs_research;

        $signals = $company->signals()->orderBy('id')->get();
        $profile = $this->profile($userId);
        $required = $this->requiredTechs($company);

        $signalArrays = $signals->map(static fn ($signal): array => [
            'id' => $signal->id,
            'signal_key' => $signal->signal_key,
            'dimension' => $signal->dimension->value,
            'nature' => $signal->nature->value,
            'confidence' => $signal->confidence,
            'evidence_url' => $signal->evidence_url,
            'captured_at' => $signal->captured_at?->toDateTimeString() ?? '',
            'value_text' => $signal->value_text,
        ])->all();

        $rules = [
            'weights' => (array) config('lead-scout.scoring.weights'),
            'inference_weight' => (float) config('lead-scout.scoring.inference_weight', 0.6),
        ];

        $scored = $this->engine->score(
            $signalArrays,
            $profile?->confirmed_skills ?? [],
            $required,
            $rules,
            $company->country,
        );

        $factKeys = array_values(array_unique(array_map(
            static fn (array $signal): string => $signal['signal_key'],
            array_filter($signalArrays, static fn (array $signal): bool => $signal['nature'] === 'fact'),
        )));

        $classified = $this->tiers->classify(
            $scored['leadScore'],
            $scored['confidence'],
            $scored['subscores']['technical'],
            $company->activity_status->value,
            $company->employee_range->value,
            $company->team_size_observed,
            $factKeys,
            $scored['flags'],
            $company->country,
            (array) config('lead-scout.scoring.tiers') + ['needs_research' => (array) config('lead-scout.scoring.needs_research', [])],
        );

        return DB::transaction(function () use ($company, $profile, $scored, $classified, $signals, $hadFlag, $extraRoundDone): ScoutScoreResultEloquentModel {
            ScoutScoreResultEloquentModel::query()
                ->where('company_id', $company->id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            $result = ScoutScoreResultEloquentModel::query()->create([
                'company_id' => $company->id,
                'profile_id' => $profile?->id,
                'rules_version' => (string) config('lead-scout.rules_version'),
                'subscores' => $scored['subscores'],
                'lead_score' => $scored['leadScore'],
                'confidence' => $scored['confidence'],
                'tier' => $classified['tier']->value,
                'discard_reason' => $classified['discardReason']?->value,
                'is_current' => true,
            ]);

            $byKey = [];

            foreach ($signals as $signal) {
                $byKey[$signal->signal_key] ??= $signal->id;
            }

            foreach ($scored['reasons'] as $reason) {
                $result->reasons()->create([
                    'signal_id' => $byKey[$reason['signal_key']] ?? null,
                    'points' => $reason['points'],
                    'explanation' => $reason['explanation'],
                ]);
            }

            // A pre-existing flag clears once its extra round was consumed
            // (the dispatch paths always fetch first); otherwise the fresh
            // verdict applies. A manual rescore without fetching keeps it.
            $needsResearch = $hadFlag && ! $extraRoundDone
                ? true
                : ($hadFlag ? false : $classified['needsResearch']);

            $company->update(['needs_research' => $needsResearch]);

            $result = $result->refresh();

            $this->log->pipeline('scored', [
                'company' => $company->uuid,
                'score' => $result->lead_score,
                'tier' => $result->tier->value,
                'rules' => $result->rules_version,
            ]);

            return $result;
        });
    }

    private function profile(?int $userId): ?ScoutProfileEloquentModel
    {
        $query = ScoutProfileEloquentModel::query()->where('is_current', true);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->orderByDesc('id')->first();
    }

    /**
     * Taxonomy terms required by active postings (for the unconfirmed-tech
     * penalty, plan §3.3: −10 each, cap −30).
     *
     * @return list<string>
     */
    private function requiredTechs(ScoutCompanyEloquentModel $company): array
    {
        $haystack = $company->postings()
            ->where('status', PostingStatus::Active->value)
            ->get(['title', 'body_text'])
            ->map(static fn ($posting): string => $posting->title.' '.($posting->body_text ?? ''))
            ->implode("\n");

        $required = [];

        foreach (array_keys(SkillTaxonomy::TERMS) as $term) {
            if (SkillTaxonomy::mentions($haystack, $term)) {
                $required[] = $term;
            }
        }

        return $required;
    }
}
