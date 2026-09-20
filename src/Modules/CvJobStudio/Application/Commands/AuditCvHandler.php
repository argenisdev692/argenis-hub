<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Application\DTOs\StudioCvAuditData;
use Modules\CvJobStudio\Domain\Exceptions\RecordNotFoundException;
use Modules\CvJobStudio\Domain\Ports\CvJudgePort;
use Modules\CvJobStudio\Domain\Ports\CvSourcePort;
use Modules\CvJobStudio\Domain\Ports\StudioCvRepositoryPort;

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
        private StudioCvRepositoryPort $studioCvs,
    ) {}

    #[\NoDiscard]
    public function handle(?string $cvUuid, ?string $targetJobTitle, int $userId): StudioCvAuditData
    {
        $cv = $cvUuid !== null
            ? $this->cvs->findForUser($cvUuid, $userId)
            : $this->cvs->primaryForUser($userId);

        if ($cv === null || ($cv['raw_text'] ?? null) === null) {
            throw new RecordNotFoundException('No CV with extracted text found.');
        }

        // The LLM call stays OUTSIDE the transaction: a 90 s provider call must
        // not hold a connection open, and a later rollback must not erase the
        // spend the ledger already recorded.
        $judgement = $this->judge->judge((string) $cv['raw_text'], $targetJobTitle, $userId);

        return $this->studioCvs->createAudit([
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
    }
}
