<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Audit;

use Shared\Domain\Ports\AuditPort;

use function activity;

/**
 * AuditPort implemented over spatie/laravel-activitylog ^5.
 *
 * Never log secrets/PII — callers pass only safe, business-meaningful properties.
 */
final readonly class SpatieActivityLogAdapter implements AuditPort
{
    public function log(
        string $event,
        ?object $subject = null,
        array $properties = [],
        ?object $causer = null,
        ?string $logName = null,
    ): void {
        $logger = activity($logName ?? 'default');

        if ($causer !== null) {
            $logger->causedBy($causer);
        }

        if ($subject !== null) {
            $logger->performedOn($subject);
        }

        $logger
            ->withProperties($properties)
            ->event($event)
            ->log($event);
    }
}
