<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Listeners;

use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Modules\Auth\Domain\Ports\AuthSessionTrackerPort;

/**
 * Closes the tracking row when a user signs out, so the sessions list only ever
 * shows sessions that can actually still be used (FR-14).
 */
final readonly class ReleaseAuthSessionListener
{
    public function __construct(
        private AuthSessionTrackerPort $sessions,
        private Request $request,
    ) {}

    public function handle(Logout $event): void
    {
        if ($event->user === null || ! $this->request->hasSession()) {
            return;
        }

        $this->sessions->release((string) $event->user->uuid, $this->request->session()->getId());
    }
}
