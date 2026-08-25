<?php

declare(strict_types=1);

namespace Shared\Domain\Ports;

/**
 * Explicit business-action audit trail.
 *
 * Domain-pure contract: parameters are primitives / plain objects so the Domain
 * layer never imports Eloquent or Laravel. The Infrastructure adapter
 * (SpatieActivityLogAdapter) maps them onto spatie/laravel-activitylog.
 *
 * Passive model lifecycle logging stays on the `LogsActivity` trait — this port
 * is only for explicit actions ("user exported invoices", "campaign published").
 */
interface AuditPort
{
    /**
     * @param  array<string, mixed>  $properties
     * @param  object|null  $subject  the Eloquent model the action was performed on
     * @param  object|null  $causer  the actor (usually the authenticated user)
     */
    public function log(
        string $event,
        ?object $subject = null,
        array $properties = [],
        ?object $causer = null,
        ?string $logName = null,
    ): void;
}
