<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Listeners;

use Illuminate\Auth\Events\Login;
use Modules\Auth\Application\Commands\ClearLoginFailuresHandler;
use Modules\Auth\Infrastructure\Http\Middleware\TrackAuthSessionActivity;

/**
 * A successful sign-in clears the brute-force state, so a user who simply
 * mistyped their password a few times is never punished later (FR-05).
 *
 * Session tracking is NOT done here — see {@see TrackAuthSessionActivity}
 * for why the session id is not yet final at this point.
 */
final readonly class TrackSuccessfulLoginListener
{
    public function __construct(private ClearLoginFailuresHandler $clearFailures) {}

    public function handle(Login $event): void
    {
        $this->clearFailures->handle((string) $event->user->email);
    }
}
