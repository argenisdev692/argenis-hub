<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Domain\Ports\TransactionPort;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvAuditEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioMetricAnswerEloquentModel;

/** Stores candidate-supplied metric answers for later rewrites (T-072, FR-3). */
final readonly class SubmitMetricAnswersHandler
{
    public function __construct(private TransactionPort $db) {}

    /**
     * @param  array<string, string>  $answers  question => answer
     */
    public function handle(string $auditUuid, array $answers, int $userId): int
    {
        return $this->db->atomic(function () use ($auditUuid, $answers, $userId): int {
            $audit = StudioCvAuditEloquentModel::query()
                ->ownedBy($userId)
                ->where('uuid', $auditUuid)
                ->first();

            if ($audit === null) {
                throw new PostingNotFoundException("Audit {$auditUuid} not found.");
            }

            foreach ($answers as $question => $answer) {
                StudioMetricAnswerEloquentModel::query()->updateOrCreate(
                    ['audit_id' => $audit->id, 'question' => $question],
                    ['user_id' => $userId, 'cv_id' => $audit->cv_id, 'answer' => $answer, 'answered_at' => now()],
                );
            }

            return count($answers);
        });
    }
}
