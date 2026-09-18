<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Ai;

use Modules\CvJobStudio\Domain\Enums\AiPurpose;
use Modules\CvJobStudio\Domain\Ports\CvJudgePort;

/**
 * CV audit through `AiCallExecutor` (T-071). Failover applies here: `cv_judge`
 * grades the candidate's CV, not model output, so the writer≠judge separation
 * rule does not conflict (analyze.md C-M).
 */
final readonly class LaravelAiCvJudge implements CvJudgePort
{
    public function __construct(private AiCallExecutor $calls) {}

    public function judge(string $cvText, ?string $targetJobTitle): array
    {
        $prompt = ($targetJobTitle !== null ? "Target role: {$targetJobTitle}\n\n" : '').$cvText;

        $result = $this->calls->call(AiPurpose::CvJudge, JudgeCvAgent::class, $prompt, 0);

        /** @var array<string, mixed> $data */
        $data = (array) $result['response'];

        return [...$data, 'provider' => $result['provider'], 'model' => $result['model'] ?? 'default'];
    }
}
