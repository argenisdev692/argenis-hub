<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Ports;

/**
 * Structured pipeline log.
 *
 * Exists so the Application layer can record why a requirement was dropped
 * without reaching for a facade: `Log::` in a command is an Infrastructure
 * dependency, and the layering test (OWASP §12) fails on exactly that. The
 * LeadScout module already ships the same seam as `PipelineLoggerPort`.
 *
 * Implementations must redact secrets and personal data before writing, and
 * must never receive raw posting text — a job description is untrusted
 * third-party content and this log is read by humans and by other tooling.
 */
interface StudioPipelineLoggerPort
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function pipeline(string $event, array $context = []): void;

    /**
     * Something degraded but the run continues. This is the level the
     * requirement-drop report uses: a provider drifting off the enum contract
     * loses rows, and without this line the only symptom is an insight report
     * that reads as "no one asks for this".
     *
     * @param  array<string, mixed>  $context
     */
    public function pipelineWarning(string $event, array $context = []): void;
}
