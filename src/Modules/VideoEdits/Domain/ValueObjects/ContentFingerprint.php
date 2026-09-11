<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * SHA-256 of a source file's bytes — the key that lets V2 reuse a stored
 * transcript for identical re-uploads even though source files are deleted (EX-6).
 */
final readonly class ContentFingerprint
{
    public function __construct(
        public string $sha256,
    ) {
        if (preg_match('/^[0-9a-f]{64}$/', $sha256) !== 1) {
            throw new InvalidArgumentException('A content fingerprint must be a lowercase hex SHA-256 digest.');
        }
    }
}
