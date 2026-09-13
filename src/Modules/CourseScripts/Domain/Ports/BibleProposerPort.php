<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Ports;

use Modules\CourseScripts\Application\DTOs\CourseBibleData;
use Modules\CourseScripts\Domain\Exceptions\GenerationProviderException;
use Modules\CourseScripts\Domain\ValueObjects\BibleProposalContext;

/**
 * Proposes a course bible so the author never has to write one (FR-11).
 * One provider call per proposal.
 */
interface BibleProposerPort
{
    /**
     * @throws GenerationProviderException
     */
    public function propose(BibleProposalContext $context, string $provider): CourseBibleData;
}
