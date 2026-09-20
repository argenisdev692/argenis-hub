<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Domain\Ports\StudioPostingRepositoryPort;
use Modules\CvJobStudio\Domain\Services\PostingTextMinimiser;

/**
 * Candidate-pasted JD for a reference (T-158, FR-53): the pasted text is
 * untrusted input under the same containment as any JD (minimised, then the
 * normal pipeline — gates, requirements, fit, opportunity). Labelled
 * "supplied by you". No request is ever made to the reference's host.
 */
final readonly class PasteJobTextHandler
{
    public function __construct(
        private PostingTextMinimiser $minimiser,
        private StudioPostingRepositoryPort $postings,
    ) {}

    public function handle(string $postingUuid, string $text, int $userId): void
    {
        if (! $this->postings->attachPastedText($postingUuid, $userId, $this->minimiser->minimise($text, null))) {
            throw new PostingNotFoundException("Reference {$postingUuid} not found.");
        }
    }
}
