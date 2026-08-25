<?php

declare(strict_types=1);

namespace Modules\Auth\Domain\Ports;

/**
 * Password reuse history (FR-12 — the last N passwords may not be reused).
 *
 * Only hashes cross this boundary in one direction: `record()` receives an
 * already-hashed credential, and `matchesRecent()` performs the comparison
 * inside the adapter so no stored hash is ever handed back to a caller.
 */
interface PasswordHistoryPort
{
    public function record(string $userUuid, string $hashedPassword): void;

    /**
     * True when the plaintext matches any of the user's retained past passwords.
     */
    public function matchesRecent(string $userUuid, string $plainPassword): bool;

    public function forget(string $userUuid): void;
}
