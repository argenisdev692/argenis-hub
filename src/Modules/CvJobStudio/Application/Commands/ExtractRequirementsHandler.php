<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Enums\RequirementNature;
use Modules\CvJobStudio\Domain\Enums\RequirementTag;
use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Domain\Ports\RequirementExtractorPort;
use Modules\CvJobStudio\Domain\Ports\StudioPipelineLoggerPort;
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
        private StudioPipelineLoggerPort $logger,
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
        $discarded = [];

        foreach ($extracted['requirements'] as $requirement) {
            $tag = self::tag((string) ($requirement['tag'] ?? ''));
            $nature = self::nature((string) ($requirement['nature'] ?? ''));

            if ($tag === null || $nature === null) {
                // Dropped, but previously dropped SILENTLY. A model that
                // drifts from the enum contract (a weaker provider, a prompt
                // edit) loses requirements with nothing in the log, and the only
                // symptom is an insight report that looks empty.
                //
                // The keys mirror the requirement shape so the entry is
                // greppable with the same terms as the stored rows.
                // `raw_text` is deliberately absent: it is verbatim untrusted
                // posting content, and the canonical name is what identifies
                // the drift.
                $discarded[] = [
                    'canonical_name' => mb_substr((string) ($requirement['canonical_name'] ?? ''), 0, 80),
                    'tag' => mb_substr((string) ($requirement['tag'] ?? ''), 0, 24),
                    'nature' => mb_substr((string) ($requirement['nature'] ?? ''), 0, 24),
                ];

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

        if ($discarded !== []) {
            $this->logger->pipelineWarning('requirements_discarded', [
                'posting_uuid' => $postingUuid,
                'user_id' => $userId,
                'provider' => $extracted['provider'],
                'model' => $extracted['model'],
                'kept' => count($valid),
                'discarded_count' => count($discarded),
                'discarded' => $discarded,
            ]);
        }

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

    /**
     * Model output is case- and wording-unstable ("Required", "must-have",
     * "hard skill") while the domain enums are exact. Normalize before the
     * strict lookup so a capitalization never costs a paid extraction.
     */
    private static function tag(string $raw): ?RequirementTag
    {
        $normalized = mb_strtolower(trim($raw));

        return RequirementTag::tryFrom($normalized)
            ?? RequirementTag::tryFrom(self::TAG_ALIASES[$normalized] ?? '');
    }

    private static function nature(string $raw): ?RequirementNature
    {
        $normalized = mb_strtolower(trim($raw));

        return RequirementNature::tryFrom($normalized)
            ?? RequirementNature::tryFrom(self::NATURE_ALIASES[$normalized] ?? '');
    }

    /** @var array<string, string> */
    private const array TAG_ALIASES = [
        'must-have' => 'required',
        'must have' => 'required',
        'mandatory' => 'required',
        'essential' => 'required',
        'musthave' => 'required',
        'nice-to-have' => 'preferred',
        'nice to have' => 'preferred',
        'plus' => 'bonus',
    ];

    /** @var array<string, string> */
    private const array NATURE_ALIASES = [
        'hard skill' => 'hard',
        'hard skills' => 'hard',
        'technical' => 'hard',
        'soft skill' => 'soft',
        'soft skills' => 'soft',
        'interpersonal' => 'soft',
        'communication' => 'soft',
    ];
}
