<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Domain\Ports;

use Modules\SocialMedia\Application\DTOs\ContentEvaluationData;
use Modules\SocialMedia\Application\DTOs\GeneratedSocialMediaContentData;
use Modules\SocialMedia\Application\DTOs\GenerateSocialMediaContentData;
use Modules\SocialMedia\Domain\Services\ContentQualityEvaluator;

/**
 * Step 2, judging half: an INDEPENDENT model scores a draft produced by
 * {@see SocialMediaContentGeneratorPort}.
 *
 * Independence is the whole point. While one agent both wrote and scored, the
 * gate measured the writer's opinion of itself, which is uniformly generous —
 * the loop exited on iteration 1 with inflated numbers and the thresholds in
 * {@see ContentQualityEvaluator} never bit. The implementation therefore runs
 * on `config('ai.default_for_evaluation')`, not on the caller's writing
 * provider.
 */
interface SocialMediaContentEvaluatorPort
{
    public function evaluate(
        string $contentUuid,
        GeneratedSocialMediaContentData $draft,
        GenerateSocialMediaContentData $data,
        int $iteration = 1,
        ?object $causer = null,
    ): ContentEvaluationData;
}
