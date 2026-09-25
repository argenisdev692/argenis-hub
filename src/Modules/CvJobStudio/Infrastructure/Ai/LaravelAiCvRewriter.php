<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Ai;

use Modules\CvJobStudio\Domain\Enums\AiPurpose;
use Modules\CvJobStudio\Domain\Ports\CvRewriterPort;

/** ATS-safe rewrite through `AiCallExecutor` (T-073). */
final readonly class LaravelAiCvRewriter implements CvRewriterPort
{
    public function __construct(private AiCallExecutor $calls) {}

    public function rewrite(array $structure, array $protectedBlock, string $language, int $userId): array
    {
        $prompt = json_encode(['structure' => $structure, 'protected_block' => $protectedBlock, 'language' => $language], JSON_THROW_ON_ERROR);

        $result = $this->calls->call(AiPurpose::CvRewrite, RewriteCvAgent::class, $prompt, $userId);

        // The rewritten CV lives in the structured payload, not in the
        // response object's property table.
        $data = (array) ($result['response']->structured ?? []);

        return [...$data, 'provider' => $result['provider'], 'model' => $result['model'] ?? 'default'];
    }
}
