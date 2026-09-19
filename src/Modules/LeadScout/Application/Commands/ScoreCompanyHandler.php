<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Illuminate\Contracts\Config\Repository as Config;
use Modules\LeadScout\Domain\Entities\JobPosting;
use Modules\LeadScout\Domain\Entities\ScoreResult;
use Modules\LeadScout\Domain\Entities\Signal;
use Modules\LeadScout\Domain\Enums\SignalNature;
use Modules\LeadScout\Domain\Exceptions\CompanyNotFoundException;
use Modules\LeadScout\Domain\Exceptions\SuppressedException;
use Modules\LeadScout\Domain\Ports\CompanyRepositoryPort;
use Modules\LeadScout\Domain\Ports\JobPostingRepositoryPort;
use Modules\LeadScout\Domain\Ports\PipelineLoggerPort;
use Modules\LeadScout\Domain\Ports\ProfileRepositoryPort;
use Modules\LeadScout\Domain\Ports\ScoreResultRepositoryPort;
use Modules\LeadScout\Domain\Ports\SignalRepositoryPort;
use Modules\LeadScout\Domain\Ports\SuppressionRepositoryPort;
use Modules\LeadScout\Domain\Ports\TransactionPort;
use Modules\LeadScout\Domain\Services\ScoringEngine;
use Modules\LeadScout\Domain\Services\SuppressionGate;
use Modules\LeadScout\Domain\Services\TierClassifier;
use Modules\LeadScout\Domain\ValueObjects\SkillTaxonomy;

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
        private SignalRepositoryPort $signals,
        private JobPostingRepositoryPort $postings,
        private ProfileRepositoryPort $profiles,
        private ScoreResultRepositoryPort $scores,
        private SuppressionGate $gate,
        private SuppressionRepositoryPort $suppressions,
        private TransactionPort $transaction,
        private PipelineLoggerPort $log,
        private Config $config,
    ) {}

    public function handle(string $companyUuid, ?int $userId = null, bool $extraRoundDone = false): ScoreResult
    {
        $company = $this->companies->byUuid($companyUuid) ?? throw new CompanyNotFoundException($companyUuid);

        // An unsubscribe prevails over any re-score (spec FR-43, T084).
        $candidates = $this->suppressions->matching($company->canonicalDomain, $company->taxId, $company->name);

        if ($this->gate->isSuppressed($company->canonicalDomain, $company->taxId, $company->name, $candidates)) {
            throw new SuppressedException;
        }

        $signals = $this->signals->forCompany($company->id);
        $profile = $this->profiles->current($userId);

        $signalArrays = array_map(static fn (Signal $signal): array => [
            'id' => $signal->id,
            'signal_key' => $signal->signalKey,
            'dimension' => $signal->dimension->value,
            'nature' => $signal->nature->value,
            'confidence' => $signal->confidence,
            'evidence_url' => $signal->evidenceUrl,
            'captured_at' => $signal->capturedAt?->format('Y-m-d H:i:s') ?? '',
            'value_text' => $signal->valueText,
        ], $signals);

        $scored = $this->engine->score(
            $signalArrays,
            $profile === null ? [] : $profile->confirmedSkills,
            $this->requiredTechs($company->id),
            [
                'weights' => (array) $this->config->get('lead-scout.scoring.weights'),
                'inference_weight' => (float) $this->config->get('lead-scout.scoring.inference_weight', 0.6),
                'overlap_hours' => (array) $this->config->get('lead-scout.geo.overlap_hours', []),
            ],
            $company->country,
        );

        $factKeys = array_values(array_unique(array_map(
            static fn (Signal $signal): string => $signal->signalKey,
            array_filter($signals, static fn (Signal $signal): bool => $signal->nature === SignalNature::Fact),
        )));

        $classified = $this->tiers->classify(
            $scored['leadScore'],
            $scored['confidence'],
            $scored['subscores']['technical'],
            $company->activityStatus->value,
            $company->employeeRange->value,
            $company->teamSizeObserved,
            $factKeys,
            $scored['flags'],
            $company->country,
            (array) $this->config->get('lead-scout.scoring.tiers')
                + ['needs_research' => (array) $this->config->get('lead-scout.scoring.needs_research', [])],
        );

        $signalIdByKey = [];

        foreach ($signals as $signal) {
            $signalIdByKey[$signal->signalKey] ??= $signal->id;
        }

        // A pre-existing flag clears once its extra round was consumed
        // (the dispatch paths always fetch first); otherwise the fresh
        // verdict applies. A manual rescore without fetching keeps it.
        $hadFlag = $company->needsResearch;
        $needsResearch = $hadFlag ? ! $extraRoundDone : $classified['needsResearch'];

        $result = $this->transaction->run(function () use ($company, $profile, $scored, $classified, $signalIdByKey, $needsResearch): ScoreResult {
            $result = $this->scores->recordCurrent(
                companyId: $company->id,
                profileId: $profile?->id,
                rulesVersion: (string) $this->config->get('lead-scout.rules_version'),
                subscores: $scored['subscores'],
                leadScore: $scored['leadScore'],
                confidence: $scored['confidence'],
                tier: $classified['tier'],
                discardReason: $classified['discardReason'],
                reasons: array_map(static fn (array $reason): array => [
                    'signal_id' => $signalIdByKey[$reason['signal_key']] ?? null,
                    'points' => $reason['points'],
                    'explanation' => $reason['explanation'],
                ], $scored['reasons']),
            );

            $this->companies->setNeedsResearch($company, $needsResearch);

            return $result;
        });

        $this->log->pipeline('scored', [
            'company' => $company->uuid,
            'score' => $result->leadScore,
            'tier' => $result->tier->value,
            'rules' => $result->rulesVersion,
        ]);

        return $result;
    }

    /**
     * Taxonomy terms required by active postings (for the unconfirmed-tech
     * penalty, plan §3.3: −10 each, cap −30).
     *
     * @return list<string>
     */
    private function requiredTechs(int $companyId): array
    {
        $haystack = implode("\n", array_map(
            static fn (JobPosting $posting): string => $posting->title.' '.($posting->bodyText ?? ''),
            $this->postings->activeForCompany($companyId),
        ));

        return array_values(array_filter(
            array_keys(SkillTaxonomy::TERMS),
            static fn (string $term): bool => SkillTaxonomy::mentions($haystack, $term),
        ));
    }
}
