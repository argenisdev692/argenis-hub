<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Enums\AiPurpose;
use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Domain\Ports\TransactionPort;
use Modules\CvJobStudio\Infrastructure\Ai\AiCallExecutor;
use Modules\CvJobStudio\Infrastructure\Ai\CvSnapshotLayer;
use Modules\CvJobStudio\Infrastructure\Ai\TranslateCvAgent;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvVersionEloquentModel;
use Shared\Infrastructure\AI\PromptCache\PromptLayer;

/**
 * ES / EN / PT-PT variants (T-078, FR-23): the untranslatable-terms list +
 * the version content form the long-lived cached layer, the target language
 * the tail — three languages share one cached prefix.
 */
final readonly class TranslateCvHandler
{
    public function __construct(
        private AiCallExecutor $calls,
        private TransactionPort $db,
    ) {}

    #[\NoDiscard]
    public function handle(string $versionUuid, string $language, int $userId): StudioCvVersionEloquentModel
    {
        $version = StudioCvVersionEloquentModel::query()
            ->ownedBy($userId)
            ->where('uuid', $versionUuid)
            ->first();

        if ($version === null) {
            throw new PostingNotFoundException("Version {$versionUuid} not found.");
        }

        $content = (array) $version->content;
        $hash = hash('sha256', json_encode($content, JSON_THROW_ON_ERROR));
        $terms = implode(', ', (array) config('cv-job-studio.untranslatable_terms', []));

        $layer = PromptLayer::long("CV version {$hash}. Untranslatable terms (keep in English): {$terms}.\n".json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $cached = CvSnapshotLayer::cached(AiPurpose::Translate->value, $layer, "Target language: {$language}", $hash);

        $result = $this->calls->call(AiPurpose::Translate, TranslateCvAgent::class, $cached->asSingleMessage(), $userId, null, $cached);

        /** @var array{sections: list<array<string, mixed>>} $data */
        $data = (array) $result['response'];

        return $this->db->atomic(static fn (): StudioCvVersionEloquentModel => StudioCvVersionEloquentModel::query()->create([
            'user_id' => $userId,
            'cv_id' => $version->cv_id,
            'structure_id' => $version->structure_id,
            'profile_id' => $version->profile_id,
            'posting_id' => $version->posting_id,
            'purpose' => $version->purpose,
            'language' => $language,
            'content' => $data['sections'],
            'parent_version_id' => $version->id,
            'rules_version' => 2,
        ]));
    }
}
