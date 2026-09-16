<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Exceptions;

use DomainException;

/**
 * The review names a cut this edit never proposed (HTTP 422). Approving an id
 * from another edit, or a made-up one, must not be silently ignored: the owner
 * would believe a cut was applied that never will be.
 */
final class InvalidCutReviewException extends DomainException
{
    public const string CODE = 'invalid_cut_review';

    /**
     * @param  list<string>  $unknownCutIds
     */
    private function __construct(
        public readonly array $unknownCutIds,
    ) {
        parent::__construct('Some of the selected cuts do not belong to this edit. Reload it and review again.');
    }

    /**
     * @param  list<string>  $unknownCutIds
     */
    public static function unknownCuts(array $unknownCutIds): self
    {
        return new self($unknownCutIds);
    }
}
