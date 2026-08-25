<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Modules\Auth\Application\Commands\RegisterAuthSessionHandler;
use Modules\Auth\Domain\Ports\AuthSessionTrackerPort;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the sessions list honest (FR-14, FR-15). Three jobs, all keyed off the
 * CURRENT session id:
 *
 *  1. Sign the user out the moment their session is revoked from another device.
 *  2. Refresh `last_seen_at` for a session already being tracked.
 *  3. Start tracking a session that is not — which is also where a login from an
 *     unfamiliar device is detected and alerted.
 *
 * Registration deliberately does NOT happen in a `Login` event listener: the
 * framework regenerates the session id twice during login (once in
 * `SessionGuard::login()`, again in Fortify's `PrepareAuthenticatedSession`), so
 * any id captured there is stale before the next request. Registering from the
 * middleware also covers every other way a session comes into being — remember-me
 * re-authentication included — instead of only the interactive login path.
 */
final readonly class TrackAuthSessionActivity
{
    public function __construct(
        private AuthSessionTrackerPort $sessions,
        private RegisterAuthSessionHandler $registerSession,
        private StatefulGuard $guard,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $request->hasSession()) {
            return $next($request);
        }

        $sessionId = $request->session()->getId();

        if ($this->sessions->isRevoked($sessionId)) {
            return $this->signOut($request);
        }

        $userUuid = (string) $user->uuid;

        if (! $this->sessions->touch($userUuid, $sessionId)) {
            (void) $this->registerSession->handle(
                userUuid: $userUuid,
                sessionId: $sessionId,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            );
        }

        return $next($request);
    }

    private function signOut(Request $request): Response
    {
        $this->guard->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', __('This session was signed out from another device.'));
    }
}
