<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Modules\LeadScout\Application\DTOs\DecisionRuleData;
use Modules\LeadScout\Domain\Entities\DecisionRule;
use Modules\LeadScout\Domain\Enums\DecisionOutcome;
use Modules\LeadScout\Domain\Enums\OutreachStage;
use Modules\LeadScout\Domain\Exceptions\DecisionRuleLockedException;
use Modules\LeadScout\Domain\Exceptions\InvalidInputException;
use Modules\LeadScout\Domain\Ports\DecisionRuleRepositoryPort;
use Modules\LeadScout\Domain\Ports\OutreachRepositoryPort;
use Modules\LeadScout\Domain\Services\DecisionRuleEvaluator;

/**
 * Decision-rule lifecycle (spec FR-19, T067): create versions freely,
 * lock once before measuring. A locked rule is immutable — locking an
 * already-locked row answers 409. The evaluation reads the funnel, so
 * the branch shown is always current data against frozen thresholds.
 */
final readonly class LockDecisionRuleHandler
{
    public function __construct(
        private DecisionRuleEvaluator $evaluator,
        private DecisionRuleRepositoryPort $rules,
        private OutreachRepositoryPort $outreaches,
        private Config $config,
    ) {}

    public function create(DecisionRuleData $data): DecisionRule
    {
        $defaults = (array) $this->config->get('lead-scout.decision_rule_defaults', []);

        return $this->rules->create(
            sampleSize: $data->sampleSize ?? (int) ($defaults['sample_size'] ?? 150),
            windowDays: $data->windowDays ?? (int) ($defaults['window_days'] ?? 56),
            thresholds: $data->thresholds ?? ($defaults['thresholds'] ?? ['scale_at' => 0.05, 'stop_below' => 0.01]),
        );
    }

    /**
     * Locks the rule and stores the branch its current sample points to.
     *
     * @return array{rule: DecisionRule, evaluation: array{outcome: DecisionOutcome, contacted: int, positives: int, rate: float, expected_low: float, expected_high: float, conclusive: bool, message: string}}
     */
    public function lock(string $uuid): array
    {
        $rule = $this->rules->byUuid($uuid)
            ?? throw InvalidInputException::withMessages(['rule' => 'Decision rule not found.']);

        if ($rule->isLocked()) {
            throw new DecisionRuleLockedException;
        }

        $evaluation = $this->evaluator->evaluate(
            [
                'sample_size' => $rule->sampleSize,
                'window_days' => $rule->windowDays,
                'thresholds' => $rule->thresholds,
            ],
            $this->outreaches->countSent(),
            $this->outreaches->countInStages(OutreachStage::positiveOutcomes()),
        );

        $today = CarbonImmutable::now();
        $locked = $this->rules->lock($rule, $today, $today->addDays($rule->windowDays), $evaluation['outcome']);

        return ['rule' => $locked, 'evaluation' => $evaluation];
    }
}
