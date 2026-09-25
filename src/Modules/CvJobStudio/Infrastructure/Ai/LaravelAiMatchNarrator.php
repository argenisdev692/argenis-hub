<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Ai;

use Modules\CvJobStudio\Domain\Enums\AiPurpose;
use Modules\CvJobStudio\Domain\Ports\MatchNarratorPort;
use Shared\Infrastructure\AI\PromptCache\CacheablePrompt;
use Shared\Infrastructure\AI\PromptCache\PromptLayer;

/**
 * Match narrative through `AiCallExecutor` (T-156); the agent sees the stored
 * breakdown only.
 *
 * CHG-20 proposed the confirmed CV structure as the long-lived layer. The
 * narrative deliberately receives the score breakdown alone — widening it to
 * carry CV content would put text the story does not need (and NFR-2 does not
 * permit) in front of the model. The shared prefix is instead the engine's own
 * scoring contract, which is identical for every posting in a run and forever:
 * sixty calls per run reuse one cached block, and the tail stays the breakdown.
 */
final readonly class LaravelAiMatchNarrator implements MatchNarratorPort
{
    public function __construct(private AiCallExecutor $calls) {}

    public function narrate(array $breakdown, int $userId): array
    {
        $purpose = AiPurpose::MatchNarrative;

        $cached = new CacheablePrompt(
            layers: [self::scoringContractLayer()],
            tail: json_encode($breakdown, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            cacheKey: 'cvjs:'.$purpose->value.':rules-'.(int) config('cv-job-studio.rules_version', 0),
        );

        $response = $this->calls->call($purpose, NarrateMatchAgent::class, $cached->asSingleMessage(), $userId, null, $cached)['response'];

        return ['why' => (string) ($response['why'] ?? ''), 'gaps' => (string) ($response['gaps'] ?? '')];
    }

    /**
     * Bands, blend and sub-score weights straight from the engine config: the
     * vocabulary a narrative has to speak in, and fixed until `rules_version`
     * moves.
     */
    private static function scoringContractLayer(): PromptLayer
    {
        $contract = [
            'blend' => (array) config('cv-job-studio.blend', []),
            'bands' => (array) config('cv-job-studio.bands', []),
            'deterministic_weights' => (array) config('cv-job-studio.deterministic.weights', []),
        ];

        ksort($contract);

        return PromptLayer::long(
            'Scoring contract '.(int) config('cv-job-studio.rules_version', 0).":\n"
            .json_encode($contract, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        );
    }
}
