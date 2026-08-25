<?php

declare(strict_types=1);

namespace Modules\Auth\Application\Queries;

use Modules\Auth\Application\DTOs\AuthSessionData;
use Modules\Auth\Domain\Ports\AuthSessionTrackerPort;
use Modules\Auth\Domain\ValueObjects\AuthSessionSnapshot;

/**
 * The user's own active sessions, current one first (FR-14).
 *
 * Side-effect free. Not paginated on purpose: the result set is bounded by the
 * number of devices ONE person is signed in from, so pagination would add a
 * contract for a list that is single digits long (YAGNI).
 */
final readonly class ListActiveAuthSessionsHandler
{
    public function __construct(private AuthSessionTrackerPort $sessions) {}

    /**
     * @return list<AuthSessionData>
     */
    #[\NoDiscard('Query handlers exist for their return value.')]
    public function handle(string $userUuid, string $currentSessionId): array
    {
        return array_map(
            static fn (AuthSessionSnapshot $snapshot): AuthSessionData => AuthSessionData::fromSnapshot($snapshot),
            $this->sessions->activeFor($userUuid, $currentSessionId),
        );
    }
}
