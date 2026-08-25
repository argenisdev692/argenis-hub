<?php

declare(strict_types=1);

namespace Modules\Auth\Application\Commands;

use Modules\Auth\Domain\Ports\AuthSessionTrackerPort;

/**
 * Revokes one tracked session (FR-14).
 *
 * Ownership is part of the contract, not an afterthought: the user uuid is
 * always paired with the session uuid so a session belonging to somebody else
 * can never be revoked by guessing an identifier (OWASP §11 / API1).
 */
final readonly class RevokeAuthSessionHandler
{
    public function __construct(private AuthSessionTrackerPort $sessions) {}

    /**
     * @return bool false when the session does not exist or is not owned by the user
     */
    #[\NoDiscard('The caller must turn a false result into a 404/403 rather than reporting success.')]
    public function handle(string $userUuid, string $sessionUuid): bool
    {
        return $this->sessions->revoke($userUuid, $sessionUuid);
    }
}
