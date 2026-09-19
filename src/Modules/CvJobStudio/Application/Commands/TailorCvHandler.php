<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Enums\AiPurpose;
use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Domain\Exceptions\StructureNotConfirmedException;
use Modules\CvJobStudio\Domain\Ports\StudioPostingRepositoryPort;
use Modules\CvJobStudio\Domain\Ports\TransactionPort;
use Modules\CvJobStudio\Infrastructure\Ai\AiCallExecutor;
use Modules\CvJobStudio\Infrastructure\Ai\CvSnapshotLayer;
use Modules\CvJobStudio\Infrastructure\Ai\TailorCvAgent;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvStructureEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvVersionEloquentModel;

/**
 * Per-posting tailoring (T-077, FR-22/FR-24): re-orders and re-words skills
 * and summary toward the posting's vocabulary without introducing absent
 * skills, linked to the posting it was produced for. The CV snapshot and the
 * protected block resolve server-side from a confirmed structure — the chat
 * panel sends only `language` + operator `notes`, never CV content. Notes are
 * advisory: the agent must still ground every skill in the source CV
 * (OWASP LLM01). Runs through `AiCallExecutor` with the cached snapshot
 * layer + protected block.
 */
final readonly class TailorCvHandler
{
    public function __construct(
        private AiCallExecutor $calls,
        private StudioPostingRepositoryPort $postings,
        private TransactionPort $db,
    ) {}

    #[\NoDiscard]
    public function handle(string $postingUuid, string $structureUuid, string $language, int $userId, ?string $notes = null): StudioCvVersionEloquentModel
    {
        $posting = $this->postings->scoringContext($postingUuid, $userId);

        if ($posting === null) {
            throw new PostingNotFoundException("Posting {$postingUuid} not found.");
        }

        $structure = StudioCvStructureEloquentModel::query()
            ->ownedBy($userId)
            ->with(['entries.bullets', 'skills'])
            ->where('uuid', $structureUuid)
            ->first();

        if ($structure === null) {
            throw new PostingNotFoundException("Structure {$structureUuid} not found.");
        }

        if ($structure->confirmed_at === null) {
            throw new StructureNotConfirmedException("Structure {$structureUuid} is not confirmed.");
        }

        $cvSnapshot = [
            'entries' => $structure->entries->map(
                static fn ($entry): array => [
                    'ordinal' => $entry->ordinal,
                    'organization' => $entry->organization,
                    'role_title' => $entry->role_title,
                ],
            )->all(),
            'skills' => $structure->skills->map(
                static fn ($skill): array => [
                    'canonical_name' => $skill->canonical_name,
                    'evidence' => $skill->evidence,
                ],
            )->all(),
        ];

        $protectedBlock = $structure->entries
            ->where('is_protected', true)
            ->flatMap(static fn ($entry) => $entry->bullets->map(
                static fn ($bullet): string => (string) $bullet->text,
            ))
            ->values()
            ->all();

        $trimmedNotes = $notes !== null && trim($notes) !== ''
            ? mb_substr(trim($notes), 0, 2000)
            : null;

        $hash = hash('sha256', json_encode($cvSnapshot, JSON_THROW_ON_ERROR));
        $layer = CvSnapshotLayer::snapshot($cvSnapshot, $hash);

        $tail = json_encode([
            'protected_block' => $protectedBlock,
            'language' => $language,
            'operator_notes' => $trimmedNotes,
            'requirements' => $posting->requirements->map(
                static fn ($requirement): array => ['name' => $requirement->canonical_name, 'tag' => $requirement->tag->value],
            )->all(),
        ], JSON_THROW_ON_ERROR);

        $cached = CvSnapshotLayer::cached(AiPurpose::Tailor->value, $layer, $tail, $hash);

        $result = $this->calls->call(AiPurpose::Tailor, TailorCvAgent::class, $cached->asSingleMessage(), $userId, null, $cached);

        // Array-access reads: the structured response is `ArrayAccess`, so this
        // works on the real SDK object and on test doubles alike.
        $response = $result['response'];

        return $this->db->atomic(static function () use ($response, $structure, $posting, $language, $userId): StudioCvVersionEloquentModel {
            return StudioCvVersionEloquentModel::query()->create([
                'user_id' => $userId,
                'cv_id' => $structure->cv_id,
                'structure_id' => $structure->id,
                'posting_id' => $posting->id,
                'purpose' => 'tailored',
                'language' => $language,
                'content' => [
                    'summary' => (string) ($response['summary'] ?? ''),
                    'skills' => array_values((array) ($response['skills'] ?? [])),
                ],
                'bullet_provenance' => array_values((array) ($response['provenance'] ?? [])),
                'rules_version' => 2,
            ]);
        });
    }
}
