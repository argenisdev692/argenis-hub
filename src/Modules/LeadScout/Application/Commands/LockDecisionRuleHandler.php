<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Illuminate\Validation\ValidationException;
use Modules\LeadScout\Application\DTOs\DecisionRuleData;
use Modules\LeadScout\Domain\Enums\DecisionOutcome;
use Modules\LeadScout\Domain\Exceptions\DecisionRuleLockedException;
use Modules\LeadScout\Domain\Services\DecisionRuleEvaluator;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutDecisionRuleEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOutreachEloquentModel;

/**
 * Decision-rule lifecycle (spec FR-19, T067): create versions freely,
 * lock once before measuring. A locked rule is immutable — locking an
 * already-locked row answers 409. The evaluation reads the funnel, so
 * the branch shown is always current data against frozen thresholds.
 */
final readonly class LockDecisionRuleHandler
{
    public function __construct(private DecisionRuleEvaluator $evaluator) {}

    public function create(DecisionRuleData $data): ScoutDecisionRuleEloquentModel
    {
        $defaults = (array) config('lead-scout.decision_rule_defaults', []);

        return ScoutDecisionRuleEloquentModel::query()->create([
            'sample_size' => $data->sampleSize ?? (int) ($defaults['sample_size'] ?? 150),
            'window_days' => $data->windowDays ?? (int) ($defaults['window_days'] ?? 56),
            'thresholds' => $data->thresholds ?? ($defaults['thresholds'] ?? ['scale_at' => 0.05, 'stop_below' => 0.01]),
        ]);
    }

    /**
     * @return array{rule: ScoutDecisionRuleEloquentModel, evaluation: array{outcome: DecisionOutcome, contacted: int, positives: int, rate: float, expected_low: float, expected_high: float, conclusive: bool, message: string}}
     */
    public function lock(string $uuid): array
    {
        $rule = ScoutDecisionRuleEloquentModel::query()->where('uuid', $uuid)->first();

        if ($rule === null) {
            throw ValidationException::withMessages(['rule' => 'Decision rule not found.']);
        }

        if ($rule->locked_at !== null) {
            throw new DecisionRuleLockedException;
        }

        $rule->update([
            'locked_at' => now(),
            'period_starts_at' => today()->toDateString(),
            'period_ends_at' => today()->addDays($rule->window_days)->toDateString(),
        ]);

        return ['rule' => $rule->refresh(), 'evaluation' => $this->evaluate($rule)];
    }

    /**
     * @return array{outcome: DecisionOutcome, contacted: int, positives: int, rate: float, expected_low: float, expected_high: float, conclusive: bool, message: string}
     */
    public function evaluateCurrent(): array
    {
        $rule = ScoutDecisionRuleEloquentModel::query()->whereNotNull('locked_at')->orderByDesc('locked_at')->first();

        if ($rule === null) {
            return $this->evaluator->evaluate(
                ['sample_size' => 150, 'window_days' => 56, 'thresholds' => ['scale_at' => 0.05, 'stop_below' => 0.01]],
                $this->contacted(),
                $this->positives(),
            );
        }

        return $this->evaluate($rule);
    }

    /**
     * @return array{outcome: DecisionOutcome, contacted: int, positives: int, rate: float, expected_low: float, expected_high: float, conclusive: bool, message: string}
     */
    private function evaluate(ScoutDecisionRuleEloquentModel $rule): array
    {
        return $this->evaluator->evaluate(
            [
                'sample_size' => $rule->sample_size,
                'window_days' => $rule->window_days,
                'thresholds' => $rule->thresholds ?? ['scale_at' => 0.05, 'stop_below' => 0.01],
            ],
            $this->contacted(),
            $this->positives(),
        );
    }

    private function contacted(): int
    {
        return ScoutOutreachEloquentModel::query()
            ->whereNotNull('sent_at')
            ->count();
    }

    private function positives(): int
    {
        return ScoutOutreachEloquentModel::query()
            ->whereIn('stage', ['positive', 'call', 'trial', 'won', 'recurrent'])
            ->count();
    }
}
