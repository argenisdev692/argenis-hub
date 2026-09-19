<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\LeadScout\Application\Commands\LockDecisionRuleHandler;
use Modules\LeadScout\Application\DTOs\DecisionRuleData;

/**
 * Decision-rule endpoints (spec US-6, FR-19, plan §5).
 */
final readonly class DecisionRuleController
{
    public function store(DecisionRuleData $data, LockDecisionRuleHandler $rules): JsonResponse
    {
        $rule = $rules->create($data);

        return response()->json(['data' => [
            'uuid' => $rule->uuid,
            'sample_size' => $rule->sampleSize,
            'window_days' => $rule->windowDays,
            'thresholds' => $rule->thresholds,
            'locked_at' => null,
            'result' => null,
        ]], 201);
    }

    public function lock(string $uuid, LockDecisionRuleHandler $rules): JsonResponse
    {
        ['rule' => $rule, 'evaluation' => $evaluation] = $rules->lock($uuid);

        return response()->json(['data' => [
            'uuid' => $rule->uuid,
            'sample_size' => $rule->sampleSize,
            'window_days' => $rule->windowDays,
            'thresholds' => $rule->thresholds,
            'locked_at' => $rule->lockedAt?->format(DATE_ATOM),
            'result' => $evaluation['outcome']->value,
            'evaluation' => [
                'outcome' => $evaluation['outcome']->value,
                'contacted' => $evaluation['contacted'],
                'positives' => $evaluation['positives'],
                'rate' => $evaluation['rate'],
                'expected_low' => $evaluation['expected_low'],
                'expected_high' => $evaluation['expected_high'],
                'conclusive' => $evaluation['conclusive'],
                'message' => $evaluation['message'],
            ],
        ]]);
    }
}
