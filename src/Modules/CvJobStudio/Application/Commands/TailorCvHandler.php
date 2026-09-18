<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Enums\AiPurpose;
use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Domain\Ports\StudioPostingRepositoryPort;
use Modules\CvJobStudio\Domain\Ports\TransactionPort;
use Modules\CvJobStudio\Infrastructure\Ai\AiCallExecutor;
use Modules\CvJobStudio\Infrastructure\Ai\CvSnapshotLayer;
use Modules\CvJobStudio\Infrastructure\Ai\TailorCvAgent;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvVersionEloquentModel;

/**
 * Per-posting tailoring (T-077, FR-22/FR-24): re-orders and re-words skills
 * and summary toward the posting's vocabulary without introducing absent
 * skills, linked to the posting it was produced for. Runs through
 * `AiCallExecutor` with the cached snapshot layer + protected block.
 */
final readonly class TailorCvHandler
{
    public function __construct(
        private AiCallExecutor $calls,
        private StudioPostingRepositoryPort $postings,
        private TransactionPort $db,
    ) {}

    #[\NoDiscard]
    public function handle(string $postingUuid, array $cvSnapshot, array $protectedBlock, string $language, int $userId): StudioCvVersionEloquentModel
    {
        $posting = $this->postings->scoringContext($postingUuid, $userId);

        if ($posting === null) {
            throw new PostingNotFoundException("Posting {$postingUuid} not found.");
        }

        $hash = hash('sha256', json_encode($cvSnapshot, JSON_THROW_ON_ERROR));
        $layer = CvSnapshotLayer::snapshot($cvSnapshot, $hash);

        $tail = json_encode([
            'protected_block' => $protectedBlock,
            'language' => $language,
            'requirements' => $posting->requirements->map(
                static fn ($requirement): array => ['name' => $requirement->canonical_name, 'tag' => $requirement->tag->value],
            )->all(),
        ], JSON_THROW_ON_ERROR);

        $cached = CvSnapshotLayer::cached(AiPurpose::Tailor->value, $layer, $tail, $hash);

        $result = $this->calls->call(AiPurpose::Tailor, TailorCvAgent::class, $cached->asSingleMessage(), $userId, null, $cached);

        /** @var array{summary: string, skills: list<string>, provenance: list<array{output_bullet: string, source_bullet: string}>} $data */
        $data = (array) $result['response'];

        return $this->db->atomic(static fn (): StudioCvVersionEloquentModel => StudioCvVersionEloquentModel::query()->create([
            'user_id' => $userId,
            'cv_id' => 0,
            'posting_id' => $posting->id,
            'purpose' => 'tailored',
            'language' => $language,
            'content' => ['summary' => $data['summary'], 'skills' => $data['skills']],
            'bullet_provenance' => $data['provenance'],
            'rules_version' => 2,
        ]));
    }
}
