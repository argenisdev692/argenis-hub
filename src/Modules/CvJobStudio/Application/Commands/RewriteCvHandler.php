<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Domain\Exceptions\StructureNotConfirmedException;
use Modules\CvJobStudio\Domain\Ports\CvRewriterPort;
use Modules\CvJobStudio\Domain\Ports\TransactionPort;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvStructureEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvVersionEloquentModel;

/**
 * ATS-safe rewrite as a new version (T-073, FR-5): the source CV is never
 * overwritten. Requires a confirmed structure (RK-6).
 */
final readonly class RewriteCvHandler
{
    public function __construct(
        private CvRewriterPort $rewriter,
        private TransactionPort $db,
    ) {}

    #[\NoDiscard]
    public function handle(string $structureUuid, string $language, int $userId): StudioCvVersionEloquentModel
    {
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

        $rewritten = $this->rewriter->rewrite(
            $structure->toArray(),
            (array) $structure->profile_facts,
            $language,
            $userId,
        );

        return $this->db->atomic(static fn (): StudioCvVersionEloquentModel => StudioCvVersionEloquentModel::query()->create([
            'user_id' => $userId,
            'cv_id' => $structure->cv_id,
            'structure_id' => $structure->id,
            'purpose' => 'ats_rewrite',
            'language' => $language,
            'content' => $rewritten['sections'],
            'bullet_provenance' => $rewritten['cut_notes'],
            'rules_version' => 2,
        ]));
    }
}
