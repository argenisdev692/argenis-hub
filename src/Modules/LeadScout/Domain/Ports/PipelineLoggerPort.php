<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

/**
 * Structured pipeline log (spec T076). Implementations redact secrets and
 * personal data before anything is written.
 */
interface PipelineLoggerPort
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function pipeline(string $event, array $context = []): void;

    /**
     * @param  array<string, mixed>  $context
     */
    public function pipelineWarning(string $event, array $context = []): void;
}
