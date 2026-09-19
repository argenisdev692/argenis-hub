<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Queue;

use Modules\LeadScout\Infrastructure\Logging\ApplicationLogger;
use Throwable;

/**
 * Final-failure hook shared by every LeadScout job (OWASP A10:2025,
 * BACKEND-PHP §10). Logs the job and exception class only — never the
 * message, which may echo page content or contact data.
 */
trait ReportsPipelineFailure
{
    public function failed(?Throwable $exception): void
    {
        app(ApplicationLogger::class)->pipelineWarning('job.failed', [
            'job' => class_basename(static::class),
            'exception' => $exception === null ? null : $exception::class,
        ]);
    }
}
