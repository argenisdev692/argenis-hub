<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Listeners;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Modules\Auth\Domain\Events\NewDeviceDetected;
use Modules\Auth\Infrastructure\Notifications\NewDeviceDetectedNotification;

/**
 * Emails the account owner when a sign-in comes from an unfamiliar device (FR-15).
 *
 * Queued: the alert must never delay the login response (NFR-02).
 */
final readonly class SendNewDeviceAlertListener implements ShouldQueue
{
    public function handle(NewDeviceDetected $event): void
    {
        $user = User::query()->where('uuid', $event->userUuid)->first();

        $user?->notify(new NewDeviceDetectedNotification(
            ipAddress: $event->ipAddress,
            userAgent: $event->userAgent,
            occurredAt: Carbon::instance($event->occurredAt)->toDayDateTimeString(),
        ));
    }
}
