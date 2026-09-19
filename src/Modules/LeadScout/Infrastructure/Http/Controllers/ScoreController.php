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
        return response()->json(['data' => ScoreResultData::fromEntity($score->handle($uuid), $uuid)]);
    }
}
