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

    public function judge(string $cvText, ?string $targetJobTitle, int $userId): array
    {
        // Both inputs are untrusted user text: fenced, never spliced into an
        // instruction (LLM01).
        $prompt = ($targetJobTitle !== null ? "<target_role>\n{$targetJobTitle}\n</target_role>\n\n" : '')
            ."<cv>\n{$cvText}\n</cv>";

        $result = $this->calls->call(AiPurpose::CvJudge, JudgeCvAgent::class, $prompt, $userId);

        // The verdict lives in the structured payload, not in the response
        // object's property table (`(array) $response` would yield usage/meta).
        $data = (array) ($result['response']->structured ?? []);

        return [...$data, 'provider' => $result['provider'], 'model' => $result['model'] ?? 'default'];
    }
}
