<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Exceptions;

use DomainException;

/**
 * One or more uploaded sources cannot be accepted at submit (HTTP 422 · AD-2):
 * never uploaded, over the size limit, or not the size that was declared.
 */
final class SourceUploadInvalidException extends DomainException
{
    public const string MISSING = 'source_missing';

    public const string TOO_LARGE = 'source_too_large';

    public const string SIZE_MISMATCH = 'source_size_mismatch';

    /**
     * @param  array<string, string>  $errorsBySourceUuid
     */
    private function __construct(
        public readonly array $errorsBySourceUuid,
    ) {
        parent::__construct('Some videos were not uploaded correctly.');
    }

    /**
     * @param  array<string, string>  $errorsBySourceUuid  source uuid → one of the constants above
     */
    public static function forSources(array $errorsBySourceUuid): self
    {
        return new self($errorsBySourceUuid);
    }
}
