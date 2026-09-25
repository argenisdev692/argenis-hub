<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Ai;

use Modules\CvJobStudio\Domain\Enums\AiPurpose;
use Modules\CvJobStudio\Domain\Enums\RequirementNature;
use Modules\CvJobStudio\Domain\Enums\RequirementTag;
use Modules\CvJobStudio\Domain\Ports\RequirementExtractorPort;

/**
 * Requirement extraction through `AiCallExecutor` (T-057): cached
 * extraction-rubric layer on JD text trimmed by `JdTextTrimmer`, skipped
 * entirely when requirements already exist for the same `source_text_hash`
 * (the handler checks). Tags/nature validated against enums on the way out.
 *
 * The rubric layer is the highest-volume cached prefix in the module (one call
 * per new posting text, CHG-20): it carries the engine-owned tag/nature
 * contract and the prompt version, never posting content, so every extraction
 * in a run — and across runs, inside the cache TTL — reuses one prefix.
 */
final readonly class LaravelAiRequirementExtractor implements RequirementExtractorPort
{
    public function __construct(private AiCallExecutor $calls) {}

    public function extract(string $postingText, int $userId): array
    {
        $purpose = AiPurpose::RequirementExtraction;
        $version = (string) config("ai.purposes.{$purpose->value}.prompt_version", 'v2');

        $rubric = CvSnapshotLayer::rubric(self::extractionContract(), $version);

        $cached = CvSnapshotLayer::cached(
            $purpose->value,
            $rubric,
            "<posting_text>\n{$postingText}\n</posting_text>",
            $version,
        );

        // Extraction is content-addressed (the handler owns the skip-if-hashed
        // check), but the spend is charged to the requesting user (LLM10).
        $result = $this->calls->call($purpose, ExtractRequirementsAgent::class, $cached->asSingleMessage(), $userId, null, $cached);

        // Array-access reads: the structured payload is not the response
        // object's property table, so `(array) $response` would look for
        // `requirements` next to `usage`/`meta` and find nothing.
        $response = $result['response'];

        /** @var list<array<string, mixed>> $requirements */
        $requirements = (array) ($response['requirements'] ?? []);

        return [
            'requirements' => $requirements,
            'responsibilities' => array_values((array) ($response['responsibilities'] ?? [])),
            'provider' => $result['provider'],
            'model' => $result['model'] ?? 'default',
            'prompt_version' => $version,
        ];
    }

    /**
     * The vocabulary the layer is built from: enum-backed and therefore stable
     * for the life of the code, which is what makes the prefix cacheable.
     *
     * @return array<string, list<string>>
     */
    private static function extractionContract(): array
    {
        return [
            'tag' => array_map(static fn (RequirementTag $case): string => $case->value, RequirementTag::cases()),
            'nature' => array_map(static fn (RequirementNature $case): string => $case->value, RequirementNature::cases()),
        ];
    }
}
