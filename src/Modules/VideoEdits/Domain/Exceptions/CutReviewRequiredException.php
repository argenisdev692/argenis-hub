<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Exceptions;

use RuntimeException;

/**
 * Not a failure: a producer has cuts that need the owner's approval before
 * anything is rendered, so the current pass must stop here.
 *
 * An exception rather than a return value on purpose. A producer that forgot
 * to signal, or a pipeline that forgot to check, would silently render without
 * asking — the one outcome the review exists to prevent. An uncaught signal
 * fails loudly instead.
 */
final class CutReviewRequiredException extends RuntimeException
{
    public function __construct(
        public readonly int $proposedCutCount,
    ) {
        parent::__construct('The proposed cuts are waiting for the owner\'s review.');
    }
}
