<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\LeadScout\Application\Commands\ScoreCompanyHandler;
use Modules\LeadScout\Application\DTOs\ScoreResultData;
use Modules\LeadScout\Domain\Exceptions\CompanyNotFoundException;

/**
 * Score endpoints (spec US-4, plan §5).
 */
final readonly class ScoreController
{
    /**
     * @throws CompanyNotFoundException
     */
    public function rescore(string $uuid, ScoreCompanyHandler $score): JsonResponse
    {
        $result = $score->handle($uuid);

        $reasons = $result->reasons()
            ->orderBy('id')
            ->get(['signal_id', 'points', 'explanation'])
            ->map(static fn ($reason): array => [
                'signal_key' => $reason->signal?->signal_key ?? 'unconfirmed_tech',
                'points' => $reason->points,
                'explanation' => $reason->explanation,
            ])
            ->all();

        return response()->json(['data' => ScoreResultData::fromResult($result, $uuid, $reasons)]);
    }
}
