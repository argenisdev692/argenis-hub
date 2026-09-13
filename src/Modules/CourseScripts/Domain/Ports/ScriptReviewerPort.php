<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Ports;

use Modules\CourseScripts\Domain\Exceptions\GenerationProviderException;
use Modules\CourseScripts\Domain\ValueObjects\ReviewVerdict;
use Modules\CourseScripts\Domain\ValueObjects\ScriptDraft;
use Modules\CourseScripts\Domain\ValueObjects\VideoWritingContext;

/**
 * The independent second review (US-13), used only in runs with
 * `with_review = true`. One provider call per method.
 */
interface ScriptReviewerPort
{
    public const array SCRIPT_DIMENSIONS = ['coverage', 'duration', 'format_fidelity', 'continuity', 'errors_to_avoid', 'integrity'];

    public const array PRACTICE_DIMENSIONS = ['realism', 'designed_contrasts', 'figures', 'integrity'];

    /**
     * @throws GenerationProviderException
     */
    public function reviewScript(VideoWritingContext $context, ScriptDraft $draft, string $provider): ReviewVerdict;

    /**
     * @throws GenerationProviderException
     */
    public function reviewPractice(VideoWritingContext $context, ScriptDraft $draft, string $provider): ReviewVerdict;
}
