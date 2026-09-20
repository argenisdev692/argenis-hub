<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Enums\RequirementNature;
use Modules\CvJobStudio\Domain\Enums\RequirementTag;
use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Domain\Ports\RequirementExtractorPort;
use Modules\CvJobStudio\Domain\Ports\StudioPostingRepositoryPort;
use Modules\CvJobStudio\Domain\Services\JdTextTrimmer;
use Throwable;

/**
 * Requirement extraction (T-057): runs on JD text trimmed by `JdTextTrimmer`,
 * skipped entirely when requirements already exist for the same
 * `source_text_hash` — the same text is extracted exactly once. Stores
 * `extracted_by_provider`, `extracted_by_model` and `prompt_version` per row.
 * Tags/nature are validated against enums on the way out. Requirements are
 * embedded best-effort after commit — an embedding outage delays RAG,
 * never the extraction (NFR-17).
 */
final readonly class ExtractRequirementsHandler
{
    private const string PROMPT_VERSION = 'v2';

    public function __construct(
        private RequirementExtractorPort $extractor,
        private JdTextTrimmer $trimmer,
        private StudioPostingRepositoryPort $postings,
        private StoreEmbeddingHandler $embeddings,
    ) {}

    /** @return list<array{id: int, canonical_name: string, raw_text: string}> */
    #[\NoDiscard]
    public function handle(string $postingUuid, int $userId, int $maxInputChars = 20000): array
    {
        $posting = $this->postings->scoringContext($postingUuid, $userId)
            ?? throw new PostingNotFoundException("Posting {$postingUuid} not found.");

        $hash = hash('sha256', $posting->latestText);
        $existing = $this->postings->requirementsForText($posting->id, $userId, $hash);

        if ($existing !== []) {
            return $existing;
        }

        $extracted = $this->extractor->extract($this->trimmer->trim($posting->latestText, $maxInputChars), $userId);

        $valid = [];

        foreach ($extracted['requirements'] as $requirement) {
            $tag = RequirementTag::tryFrom((string) ($requirement['tag'] ?? ''));
            $nature = RequirementNature::tryFrom((string) ($requirement['nature'] ?? ''));

            if ($tag === null || $nature === null) {
                continue;
            }

            $valid[] = [
                'canonical_name' => (string) $requirement['canonical_name'],
                'raw_text' => (string) ($requirement['raw_text'] ?? ''),
                'tag' => $tag,
                'nature' => $nature,
            ];
        }

        $rows = $this->postings->storeRequirements($posting->id, $userId, $hash, $valid, [
            'provider' => $extracted['provider'],
            'model' => $extracted['model'],
            'prompt_version' => self::PROMPT_VERSION,
        ]);

        foreach ($rows as $row) {
            $text = trim($row['raw_text'] !== '' ? $row['raw_text'] : $row['canonical_name']);

            if ($text === '') {
                continue;
            }

            try {
                $this->embeddings->handle('posting_requirement', $row['id'], $text, $userId);
            } catch (Throwable) {
                break;
            }
        }

        return $rows;
    }
}
