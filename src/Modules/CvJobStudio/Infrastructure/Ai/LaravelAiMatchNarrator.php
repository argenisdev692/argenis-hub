<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Ai;

use Modules\CvJobStudio\Domain\Enums\AiPurpose;
use Modules\CvJobStudio\Domain\Ports\MatchNarratorPort;

/** Match narrative through `AiCallExecutor` (T-156); the agent sees the stored breakdown only. */
final readonly class LaravelAiMatchNarrator implements MatchNarratorPort
{
    public function __construct(private AiCallExecutor $calls) {}

    public function narrate(array $breakdown, int $userId): array
    {
        $prompt = json_encode($breakdown, JSON_THROW_ON_ERROR);

        $response = $this->calls->call(AiPurpose::MatchNarrative, NarrateMatchAgent::class, $prompt, $userId)['response'];

        return ['why' => (string) ($response['why'] ?? ''), 'gaps' => (string) ($response['gaps'] ?? '')];
    }
}
