<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Domain\Ports\StudioPostingRepositoryPort;
use Modules\CvJobStudio\Domain\Ports\TransactionPort;
use Modules\CvJobStudio\Domain\Services\PostingTextMinimiser;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingTextEloquentModel;

/**
 * Candidate-pasted JD for a reference (T-158, FR-53): the pasted text is
 * untrusted input under the same containment as any JD (minimised, then the
 * normal pipeline — gates, requirements, fit, opportunity). Labelled
 * "supplied by you". No request is ever made to the reference's host.
 */
final readonly class PasteJobTextHandler
{
    public function __construct(
        private PostingTextMinimiser $minimiser,
        private StudioPostingRepositoryPort $postings,
        private TransactionPort $db,
    ) {}

    #[\NoDiscard]
    public function handle(string $postingUuid, string $text, int $userId): StudioPostingEloquentModel
    {
        return $this->db->atomic(function () use ($postingUuid, $text, $userId): StudioPostingEloquentModel {
            $posting = StudioPostingEloquentModel::query()
                ->ownedBy($userId)
                ->where('uuid', $postingUuid)
                ->where('status', 'reference')
                ->lockForUpdate()
                ->first();

            if ($posting === null) {
                throw new PostingNotFoundException("Reference {$postingUuid} not found.");
            }

            $minimised = $this->minimiser->minimise($text, null);

            StudioPostingTextEloquentModel::query()->create([
                'user_id' => $userId,
                'posting_id' => $posting->id,
                'ladder_step' => 'candidate_pasted',
                'completeness' => 'full',
                'text' => $minimised,
                'char_count' => mb_strlen($minimised),
                'fetched_at' => now(),
            ]);

            $posting->update(['status' => 'new']);

            return $posting->refresh();
        });
    }
}
