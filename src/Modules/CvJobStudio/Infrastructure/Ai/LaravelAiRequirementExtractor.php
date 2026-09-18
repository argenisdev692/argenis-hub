<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Ai;

use Modules\CvJobStudio\Domain\Enums\AiPurpose;
use Modules\CvJobStudio\Domain\Ports\RequirementExtractorPort;

/**
 * Requirement extraction through `AiCallExecutor` (T-057): cached
 * extraction-rubric layer on JD text trimmed by `JdTextTrimmer`, skipped
 * entirely when requirements already exist for the same `source_text_hash`
 * (the handler checks). Tags/nature validated against enums on the way out.
 */
final readonly class LaravelAiRequirementExtractor implements RequirementExtractorPort
{
    public function __construct(private AiCallExecutor $calls) {}

    public function extract(string $postingText): array
    {
        // userId 0: extraction is content-addressed, not user-scoped; the
        // handler owns the user scope and the skip-if-hashed check.
        $result = $this->calls->call(AiPurpose::RequirementExtraction, ExtractRequirementsAgent::class, $postingText, 0);

        /** @var array{requirements: list<array<string, mixed>>, responsibilities: list<array<string, mixed>>} $data */
        $data = (array) $result['response'];

        return [
            'requirements' => $data['requirements'],
            'responsibilities' => $data['responsibilities'],
            'provider' => $result['provider'],
            'model' => $result['model'] ?? 'default',
            'prompt_version' => 'v2',
        ];
    }
}
