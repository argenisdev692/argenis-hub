<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Exceptions;

use RuntimeException;

/**
 * An AI provider call failed or returned an unusable structure. Carries only a
 * sanitized reason code — provider text can echo prompts containing author
 * content and is logged, never shown (FR-55).
 */
final class GenerationProviderException extends RuntimeException
{
    public function __construct(public readonly string $reasonCode, public readonly string $step)
    {
        parent::__construct(sprintf('The AI provider failed during "%s" (%s).', $step, $reasonCode));
    }

    public static function providerFailed(string $step): self
    {
        return new self('provider_unavailable', $step);
    }

    public static function invalidOutput(string $step): self
    {
        return new self('invalid_output', $step);
    }
}
