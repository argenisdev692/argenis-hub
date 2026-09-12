<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Exceptions;

use DomainException;

/**
 * The attached script could not be turned into text — an image-only PDF, or a
 * corrupt upload. Permanent: the same bytes will not parse on a retry.
 */
final class ScriptUnreadableException extends DomainException implements PermanentVideoEditFailure
{
    public const string FAILURE_CODE = 'script_unreadable';

    public function __construct(
        string $message,
        private readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public static function noExtractableText(string $fileName): self
    {
        return new self(
            "No text could be read from “{$fileName}”. A scanned or image-only PDF has to be exported as text first.",
            ['file_name' => $fileName],
        );
    }

    public function failureCode(): string
    {
        return self::FAILURE_CODE;
    }

    public function failureDetails(): ?array
    {
        return $this->details;
    }
}
