<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Laravel\Fortify\Fortify;
use Modules\Auth\Domain\Ports\AccountLockPort;

/**
 * First step of the Fortify login pipeline: refuse credentials for an account
 * serving a brute-force lockout (FR-05).
 *
 * Runs before Fortify validates the password, so a locked account cannot be
 * probed at all during the window.
 *
 * Answers with 429 + `Retry-After` (FR-06) rather than a validation error: the
 * condition is "too many requests, come back later", and the header is what
 * tells clients — and our own tests — exactly how long the wait is. An address
 * that matches no user is never locked, so the response cannot be used to
 * enumerate accounts.
 */
final readonly class EnsureAccountIsNotLocked
{
    public function __construct(private AccountLockPort $locks) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $submitted = (string) $request->input(Fortify::username(), '');

        $email = $submitted
            |> trim(...)
            |> mb_strtolower(...);

        $lockedUntil = $this->locks->lockedUntil($email);

        if ($lockedUntil === null) {
            return $next($request);
        }

        $retryAfter = max(1, $lockedUntil->getTimestamp() - time());

        throw new ThrottleRequestsException(
            message: trans('auth.throttle', ['seconds' => $retryAfter]),
            headers: ['Retry-After' => (string) $retryAfter],
        );
    }
}
