<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Application\DTOs\IngestPostingData;
use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Domain\Ports\StudioPostingRepositoryPort;
use Modules\CvJobStudio\Domain\Ports\StudioProfileRepositoryPort;
use Modules\CvJobStudio\Domain\Services\GateEvaluator;
use Modules\CvJobStudio\Domain\Services\PostingTextMinimiser;
use Modules\CvJobStudio\Domain\Services\RemoteScopeClassifier;
use Modules\CvJobStudio\Domain\ValueObjects\CanonicalPostingUrl;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;

/**
 * Manual intake: canonicalise → classify scope → gate (G1/G2/G3, pass AND
 * fail stored with reasons) → persist atomically through the posting port.
 * Text is minimised before storage (T-149, NFR-8). Gate failures are
 * recorded, never scored (FR-12).
 */
final readonly class IngestPostingHandler
{
    public function __construct(
        private RemoteScopeClassifier $scopes,
        private GateEvaluator $gates,
        private PostingTextMinimiser $minimiser,
        private StudioProfileRepositoryPort $profiles,
        private StudioPostingRepositoryPort $postings,
    ) {}

    #[\NoDiscard]
    public function handle(IngestPostingData $data, int $userId): StudioPostingEloquentModel
    {
        $profile = $this->profiles->findByUuidForUser($data->profileUuid, $userId);

        if ($profile === null) {
            throw new PostingNotFoundException("Profile {$data->profileUuid} not found.");
        }

        $canonical = new CanonicalPostingUrl($data->canonicalUrl);
        $scope = $this->scopes->classify($data->title, $data->locationText);
        $text = $data->text !== null ? $this->minimiser->minimise($data->text, null) : null;

        $verdicts = $this->gates->evaluate(
            [
                'remote_scope' => $scope->value,
                'title' => $data->title,
                'text' => $text ?? '',
                'url' => $canonical->value,
            ],
            [
                'accepted_remote_scopes' => $profile->accepted_remote_scopes ?? [],
                'stack_must' => $profile->stack_must ?? [],
                'stack_reject' => $profile->stack_reject ?? [],
            ],
        );

        return $this->postings->ingest(
            [
                'user_id' => $userId,
                'profile_id' => $profile->id,
                'canonical_url' => $canonical->value,
                'url_hash' => $canonical->hash(),
                'source' => $data->source,
                'employer_name' => $data->employerName,
                'title' => $data->title,
                'location_text' => $data->locationText,
                'remote_scope' => $scope->value,
                'status' => 'new',
                'discovery_channel' => $data->discoveryChannel,
            ],
            $text,
            $data->requirements,
            $verdicts,
        );
    }
}
