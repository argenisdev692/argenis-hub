<?php

declare(strict_types=1);

namespace Modules\Auth\Application\Commands;

use Modules\Auth\Domain\Ports\PasswordHistoryPort;

/**
 * Appends the freshly set credential to the user's reuse history (FR-12).
 *
 * Receives the ALREADY HASHED password: hashing is a framework concern that
 * belongs to the caller, and passing plaintext through the Application layer
 * would put a secret one careless log statement away from disclosure.
 */
final readonly class RecordPasswordChangeHandler
{
    public function __construct(private PasswordHistoryPort $history) {}

    public function handle(string $userUuid, string $hashedPassword): void
    {
        $this->history->record($userUuid, $hashedPassword);
    }
}
