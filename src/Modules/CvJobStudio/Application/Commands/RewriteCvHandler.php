<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Application\DTOs\StudioCvVersionData;
use Modules\CvJobStudio\Domain\Exceptions\RecordNotFoundException;
use Modules\CvJobStudio\Domain\Exceptions\StructureNotConfirmedException;
use Modules\CvJobStudio\Domain\Ports\CvRewriterPort;
use Modules\CvJobStudio\Domain\Ports\StudioCvRepositoryPort;

/**
 * ATS-safe rewrite as a new version (T-073, FR-5): the source CV is never
 * overwritten. Requires a confirmed structure (RK-6).
 */
final readonly class RewriteCvHandler
{
    public function __construct(
        private CvRewriterPort $rewriter,
        private StudioCvRepositoryPort $cvs,
    ) {}

    #[\NoDiscard]
    public function handle(string $structureUuid, string $language, int $userId): StudioCvVersionData
    {
        $structure = $this->cvs->structureForUser($structureUuid, $userId)
            ?? throw RecordNotFoundException::of('structure', $structureUuid);

        if (! $structure->isConfirmed) {
            throw new StructureNotConfirmedException("Structure {$structureUuid} is not confirmed.");
        }

        $rewritten = $this->rewriter->rewrite($structure->document, $structure->profileFacts, $language, $userId);

        return $this->cvs->createVersion([
            'user_id' => $userId,
            'cv_id' => $structure->cvId,
            'structure_id' => $structure->id,
            'purpose' => 'ats_rewrite',
            'language' => $language,
            'content' => $rewritten['sections'],
            'bullet_provenance' => $rewritten['cut_notes'],
            'rules_version' => 2,
        ]);
    }
}
