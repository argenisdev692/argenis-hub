<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Exceptions\RecordNotFoundException;
use Modules\CvJobStudio\Domain\Ports\StudioCvRepositoryPort;

/** Stores candidate-supplied metric answers for later rewrites (T-072, FR-3). */
final readonly class SubmitMetricAnswersHandler
{
    public function __construct(private StudioCvRepositoryPort $cvs) {}

    /**
     * @param  array<string, string>  $answers  question => answer
     */
    public function handle(string $auditUuid, array $answers, int $userId): int
    {
        $audit = $this->cvs->auditRefForUser($auditUuid, $userId)
            ?? throw RecordNotFoundException::of('audit', $auditUuid);

        return $this->cvs->saveMetricAnswers($audit['id'], $audit['cv_id'], $userId, $answers);
    }
}
