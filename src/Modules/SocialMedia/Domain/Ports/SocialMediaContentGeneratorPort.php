<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Domain\Ports;

use Modules\SocialMedia\Application\DTOs\GeneratedSocialMediaContentData;
use Modules\SocialMedia\Application\DTOs\GenerateSocialMediaContentData;
use Modules\SocialMedia\Domain\Services\ContentQualityEvaluator;

/**
 * Step 2, writing half: ONE text draft (fresh Tavily research + all 5
 * platforms + image concepts). No artwork, no audio, no self-assessment —
 * scoring belongs to {@see SocialMediaContentEvaluatorPort} and rendering to
 * {@see SocialMediaAssetRendererPort}.
 *
 * That split is what makes the quality loop affordable: a rejected attempt
 * costs one text completion, not six images and a voiceover.
 *
 * `$iteration` is 1 and `$previousWeaknesses` empty on the first call; from
 * iteration 2 onward the job passes back
 * {@see ContentQualityEvaluator::identifyWeaknesses()} so the writer targets
 * the specific scores that failed instead of retrying blind.
 */
interface SocialMediaContentGeneratorPort
{
    /**
     * @param  list<array{score: string, current: int, target: int, gap: int, explanation: string}>  $previousWeaknesses
     */
    public function generate(
        string $contentUuid,
        GenerateSocialMediaContentData $data,
        int $iteration = 1,
        array $previousWeaknesses = [],
        ?object $causer = null,
    ): GeneratedSocialMediaContentData;
}
