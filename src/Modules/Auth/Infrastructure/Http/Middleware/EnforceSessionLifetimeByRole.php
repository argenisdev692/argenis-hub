<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role-dependent idle timeout (US-07): 24 h for standard users, 1 h for
 * privileged roles, both configurable.
 *
 * Sliding expiration — every authenticated request refreshes the clock, so the
 * limit bites on inactivity, not on session age. Follows the OWASP §15.5 idle
 * timeout pattern: sign out, invalidate, rotate the CSRF token.
 *
 * Registered as the `auth.session.lifetime` route middleware.
 */
final readonly class EnforceSessionLifetimeByRole
{
    private const string LAST_ACTIVITY_KEY = 'auth.last_activity';

    public function __construct(private StatefulGuard $guard) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $request->hasSession()) {
            return $next($request);
        }

        $session = $request->session();
        $lastActivity = $session->get(self::LAST_ACTIVITY_KEY);

        if (is_string($lastActivity) && $this->hasExpired($lastActivity, $this->idleMinutesFor($user))) {
            $this->guard->logout();
            $session->invalidate();
            $session->regenerateToken();

            return redirect()->route('login')->with('status', __('Your session expired due to inactivity.'));
        }

        $session->put(self::LAST_ACTIVITY_KEY, Carbon::now()->toIso8601String());

        return $next($request);
    }

    private function hasExpired(string $lastActivity, int $idleMinutes): bool
    {
        return Carbon::parse($lastActivity)->addMinutes($idleMinutes)->isPast();
    }

    private function idleMinutesFor(mixed $user): int
    {
        /** @var list<string> $privilegedRoles */
        $privilegedRoles = config('auth-security.privileged_roles', []);

        return match ($user->hasAnyRole($privilegedRoles)) {
            true => (int) config('auth-security.session_lifetime.privileged_minutes'),
            false => (int) config('auth-security.session_lifetime.default_minutes'),
        };
    }
}
