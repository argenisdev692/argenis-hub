<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Enums\RequirementNature;
use Modules\CvJobStudio\Domain\Enums\RequirementTag;
use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Domain\Ports\RequirementExtractorPort;
use Modules\CvJobStudio\Domain\Ports\StudioPostingRepositoryPort;
use Modules\CvJobStudio\Domain\Ports\TransactionPort;
use Modules\CvJobStudio\Domain\Services\JdTextTrimmer;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRequirementEloquentModel;
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
    public function __construct(
        private RequirementExtractorPort $extractor,
        private JdTextTrimmer $trimmer,
        private StudioPostingRepositoryPort $postings,
        private TransactionPort $db,
        private StoreEmbeddingHandler $embeddings,
    ) {}

    /** @return list<StudioRequirementEloquentModel> */
    #[\NoDiscard]
    public function handle(string $postingUuid, int $userId, int $maxInputChars = 20000): array
    {
        $posting = $this->postings->scoringContext($postingUuid, $userId);

        if ($posting === null) {
            throw new PostingNotFoundException("Posting {$postingUuid} not found.");
        }

        $text = (string) $posting->texts->sortByDesc('id')->first()?->text;
        $hash = hash('sha256', $text);

        $existing = StudioRequirementEloquentModel::query()
            ->where('user_id', $userId)
            ->where('posting_id', $posting->id)
            ->where('source_text_hash', $hash)
            ->get();

        if ($existing->isNotEmpty()) {
            return $existing->all();
        }

        $extracted = $this->extractor->extract($this->trimmer->trim($text, $maxInputChars), $userId);

        $rows = $this->db->atomic(function () use ($posting, $userId, $hash, $extracted): array {
            $rows = [];

            foreach ($extracted['requirements'] as $requirement) {
                $tag = RequirementTag::tryFrom((string) ($requirement['tag'] ?? ''));
                $nature = RequirementNature::tryFrom((string) ($requirement['nature'] ?? ''));

                if ($tag === null || $nature === null) {
                    continue;
                }

                $rows[] = StudioRequirementEloquentModel::query()->create([
                    'user_id' => $userId,
                    'posting_id' => $posting->id,
                    'canonical_name' => (string) $requirement['canonical_name'],
                    'raw_text' => (string) ($requirement['raw_text'] ?? ''),
                    'tag' => $tag->value,
                    'nature' => $nature->value,
                    'source_text_hash' => $hash,
                    'extracted_by_provider' => $extracted['provider'],
                    'extracted_by_model' => $extracted['model'],
                    'prompt_version' => 'v2',
                ]);
            }

            return $rows;
        });

        foreach ($rows as $row) {
            $text = trim((string) ($row->raw_text !== '' ? $row->raw_text : $row->canonical_name));

            if ($text === '') {
                continue;
            }

            try {
                (void) $this->embeddings->handle('posting_requirement', $row->id, $text, $userId);
            } catch (Throwable) {
                break;
            }
        }

        return $rows;
    }
}
