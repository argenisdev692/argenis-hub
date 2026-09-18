<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Domain\Ports\CvJudgePort;
use Modules\CvJobStudio\Domain\Ports\CvSourcePort;
use Modules\CvJobStudio\Domain\Ports\TransactionPort;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvAuditEloquentModel;

/**
 * Recruiter-scan + ATS-readability audit (T-071, FR-2/FR-38/FR-39): the judge
 * supplies verdict + narrative; readability reports the measured recovery
 * ratio apart from the content-issue list — never merged into one number.
 */
final readonly class AuditCvHandler
{
    public function __construct(
        private CvSourcePort $cvs,
        private CvJudgePort $judge,
        private TransactionPort $db,
    ) {}

    #[\NoDiscard]
    public function handle(?string $cvUuid, ?string $targetJobTitle, int $userId): StudioCvAuditEloquentModel
    {
        return $this->db->atomic(function () use ($cvUuid, $targetJobTitle, $userId): StudioCvAuditEloquentModel {
            $cv = $cvUuid !== null
                ? $this->cvs->findForUser($cvUuid, $userId)
                : $this->cvs->primaryForUser($userId);

            if ($cv === null || ($cv['raw_text'] ?? null) === null) {
                throw new PostingNotFoundException('No CV with extracted text found.');
            }

            $judgement = $this->judge->judge((string) $cv['raw_text'], $targetJobTitle);

            return StudioCvAuditEloquentModel::query()->create([
                'user_id' => $userId,
                'cv_id' => $cv['cv_id'],
                'verdict' => $judgement['verdict'],
                'verdict_reasons' => $judgement['verdict_reasons'],
                'target_job_title' => $targetJobTitle,
                'strengths' => $judgement['strengths'],
                'improvements' => $judgement['improvements'],
                'keyword_gaps' => $judgement['keyword_gaps'],
                'xyz_gaps' => $judgement['xyz_gaps'],
                'rules_version' => 2,
            ]);
        });
    }
}
