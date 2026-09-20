<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Exceptions\RecordNotFoundException;
use Modules\CvJobStudio\Domain\Ports\CvSourcePort;
use Modules\CvJobStudio\Domain\Ports\CvStructureParserPort;
use Modules\CvJobStudio\Domain\Ports\ProjectSourcePort;
use Modules\CvJobStudio\Domain\Ports\StudioCvRepositoryPort;
use Throwable;

/**
 * Parses a CV into addressable rows (T-068, FR-4): reads `Modules\Cvs`
 * read-only (never modifies it — GAP-A1), merges portfolio projects as
 * `kind = project` entries (T-008), and stores the parser rows. Re-runnable;
 * `raw_text` stays the source of truth. Bullets are embedded best-effort
 * after commit — an embedding outage delays RAG, never the parse (NFR-17).
 */
final readonly class ParseCvStructureHandler
{
    public function __construct(
        private CvSourcePort $cvs,
        private ProjectSourcePort $projects,
        private CvStructureParserPort $parser,
        private StudioCvRepositoryPort $studioCvs,
        private StoreEmbeddingHandler $embeddings,
    ) {}

    /** @return string the new structure's uuid */
    #[\NoDiscard]
    public function handle(?string $cvUuid, int $userId): string
    {
        $cv = $cvUuid !== null
            ? $this->cvs->findForUser($cvUuid, $userId)
            : $this->cvs->primaryForUser($userId);

        if ($cv === null || ($cv['raw_text'] ?? null) === null) {
            throw new RecordNotFoundException('No CV with extracted text found.');
        }

        // LLM call outside the transaction (see AuditCvHandler).
        $parsed = $this->parser->parse((string) $cv['raw_text'], $userId);

        $structure = $this->studioCvs->storeStructure(
            $userId,
            $cv['cv_id'],
            hash('sha256', (string) $cv['raw_text']),
            $parsed,
            $this->projects->projectsForUser($userId),
        );

        foreach ($structure['bullets'] as $bullet) {
            $text = trim($bullet['text']);

            if ($text === '') {
                continue;
            }

            try {
                $this->embeddings->handle('cv_bullet', $bullet['id'], $text, $userId);
            } catch (Throwable) {
                break;
            }
        }

        return $structure['uuid'];
    }
}
