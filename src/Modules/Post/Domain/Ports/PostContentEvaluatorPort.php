<?php

declare(strict_types=1);

namespace Modules\Post\Domain\Ports;

use Modules\Post\Application\DTOs\GeneratePostContentData;
use Modules\Post\Application\DTOs\PostContentDraftData;
use Modules\Post\Application\DTOs\PostEvaluationData;
use Modules\Post\Domain\Services\PostContentQualityEvaluator;

/**
 * The judging half of the quality gate: an INDEPENDENT model scores a draft
 * produced by {@see PostContentGeneratorPort}.
 *
 * Independence is the whole point. While one agent both wrote and scored the
 * draft, the gate measured the writer's opinion of itself, which is uniformly
 * generous — the loop exited on iteration 1 with inflated numbers and the
 * thresholds in {@see PostContentQualityEvaluator} never bit. The
 * implementation therefore runs on `config('ai.default_for_evaluation')`, not
 * on the caller's writing provider.
 */
interface PostContentEvaluatorPort
{
    public function evaluate(
        ?string $generationUuid,
        PostContentDraftData $draft,
        GeneratePostContentData $data,
        int $iteration = 1,
        ?object $causer = null,
    ): PostEvaluationData;
}
