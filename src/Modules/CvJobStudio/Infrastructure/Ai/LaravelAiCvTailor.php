<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Ai;

use Modules\CvJobStudio\Domain\Enums\AiPurpose;
use Modules\CvJobStudio\Domain\Ports\CvTailorPort;

/**
 * Per-posting tailoring through `AiCallExecutor` (T-077): the CV snapshot is
 * the long-lived cached layer, the protected block + requirements + operator
 * notes the tail. Notes are advisory — the agent must still ground every
 * skill in the source CV (OWASP LLM01).
 */
final readonly class LaravelAiCvTailor implements CvTailorPort
{
    public function __construct(private AiCallExecutor $calls) {}

    public function tailor(array $cvSnapshot, array $protectedBlock, array $requirements, string $language, ?string $notes, int $userId): array
    {
        $hash = hash('sha256', json_encode($cvSnapshot, JSON_THROW_ON_ERROR));

        $tail = json_encode([
            'protected_block' => $protectedBlock,
            'language' => $language,
            'operator_notes' => $notes,
            'requirements' => $requirements,
        ], JSON_THROW_ON_ERROR);

        $cached = CvSnapshotLayer::cached(AiPurpose::Tailor->value, CvSnapshotLayer::snapshot($cvSnapshot, $hash), $tail, $hash);

        // Array-access reads: the structured response is `ArrayAccess`, so this
        // works on the real SDK object and on test doubles alike.
        $response = $this->calls->call(AiPurpose::Tailor, TailorCvAgent::class, $cached->asSingleMessage(), $userId, null, $cached)['response'];

        return [
            'summary' => (string) ($response['summary'] ?? ''),
            'skills' => array_values((array) ($response['skills'] ?? [])),
            'provenance' => array_values((array) ($response['provenance'] ?? [])),
        ];
    }
}
