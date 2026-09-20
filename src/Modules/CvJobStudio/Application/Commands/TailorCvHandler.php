<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Application\DTOs\StudioCvVersionData;
use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Domain\Exceptions\RecordNotFoundException;
use Modules\CvJobStudio\Domain\Exceptions\StructureNotConfirmedException;
use Modules\CvJobStudio\Domain\Ports\CvTailorPort;
use Modules\CvJobStudio\Domain\Ports\StudioCvRepositoryPort;
use Modules\CvJobStudio\Domain\Ports\StudioPostingRepositoryPort;

/**
 * Per-posting tailoring (T-077, FR-22/FR-24): re-orders and re-words skills
 * and summary toward the posting's vocabulary without introducing absent
 * skills, linked to the posting it was produced for. The CV snapshot and the
 * protected block resolve server-side from a confirmed structure — the chat
 * panel sends only `language` + operator `notes`, never CV content. Notes are
 * advisory: the agent must still ground every skill in the source CV
 * (OWASP LLM01). Prompt-cache layering lives in the `CvTailorPort` adapter.
 */
final readonly class TailorCvHandler
{
    private const int MAX_NOTES_CHARS = 2000;

    public function __construct(
        private CvTailorPort $tailor,
        private StudioPostingRepositoryPort $postings,
        private StudioCvRepositoryPort $cvs,
    ) {}

    #[\NoDiscard]
    public function handle(string $postingUuid, string $structureUuid, string $language, int $userId, ?string $notes = null): StudioCvVersionData
    {
        $posting = $this->postings->scoringContext($postingUuid, $userId)
            ?? throw new PostingNotFoundException("Posting {$postingUuid} not found.");

        $structure = $this->cvs->structureForUser($structureUuid, $userId)
            ?? throw RecordNotFoundException::of('structure', $structureUuid);

        if (! $structure->isConfirmed) {
            throw new StructureNotConfirmedException("Structure {$structureUuid} is not confirmed.");
        }

        $trimmedNotes = $notes !== null && trim($notes) !== ''
            ? trim($notes) |> (static fn (string $note): string => mb_substr($note, 0, self::MAX_NOTES_CHARS))
            : null;

        $requirements = array_map(
            static fn (array $requirement): array => ['name' => $requirement['canonical_name'], 'tag' => $requirement['tag']->value],
            $posting->requirements,
        );

        $tailored = $this->tailor->tailor($structure->snapshot, $structure->protectedBullets, $requirements, $language, $trimmedNotes, $userId);

        return $this->cvs->createVersion([
            'user_id' => $userId,
            'cv_id' => $structure->cvId,
            'structure_id' => $structure->id,
            'posting_id' => $posting->id,
            'purpose' => 'tailored',
            'language' => $language,
            'content' => [
                'summary' => $tailored['summary'],
                'skills' => $tailored['skills'],
            ],
            'bullet_provenance' => $tailored['provenance'],
            'rules_version' => 2,
        ]);
    }
}
