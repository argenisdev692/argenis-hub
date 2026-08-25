<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Http\Request;
use Laravel\Fortify\Fortify;
use Modules\Auth\Application\Commands\RecordFailedLoginHandler;

/**
 * Feeds every rejected credential attempt into the lockout counter (FR-05).
 *
 * Listening to the framework's own `Failed` event rather than forking Fortify's
 * controllers means passkey, two-factor and password failures are all counted
 * through one path, and a future Fortify upgrade cannot silently bypass it.
 */
final readonly class TrackFailedLoginListener
{
    public function __construct(
        private RecordFailedLoginHandler $recordFailure,
        private Request $request,
    ) {}

    public function handle(Failed $event): void
    {
        $email = $event->credentials[Fortify::username()] ?? null;

        if (! is_string($email) || $email === '') {
            return;
        }

        (void) $this->recordFailure->handle(
            email: $email,
            threshold: (int) config('auth-security.lockout.max_attempts'),
            lockMinutes: (int) config('auth-security.lockout.duration_minutes'),
            ipAddress: $this->request->ip(),
            userAgent: $this->request->userAgent(),
        );
    }
}
