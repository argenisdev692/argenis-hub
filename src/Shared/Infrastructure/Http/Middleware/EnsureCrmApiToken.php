<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared-secret gate for Astro (and other server-side CRM clients).
 *
 * Expects `Authorization: Bearer {CRM_API_TOKEN}` or `X-CRM-Api-Token: {token}`.
 * Compared with {@see hash_equals()} against `config('services.crm.api_token')`.
 *
 * Fail-closed: an empty/missing env token rejects every request (401). This is
 * intentional — misconfigured production must not leave landing POSTs open.
 *
 * OWASP note: the token is only secret if Astro keeps it on the server (SSR /
 * server routes / build-time fetch). Never expose it as `PUBLIC_*` / `VITE_*`.
 * Pair with throttle + honeypot; a static token alone is not bot-proof if leaked.
 */
final class EnsureCrmApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.crm.api_token', '');

        if ($expected === '') {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $provided = $this->extractToken($request);

        if ($provided === null || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('X-CRM-Api-Token');

        if (is_string($header) && $header !== '') {
            return $header;
        }

        $authorization = $request->header('Authorization');

        if (! is_string($authorization) || $authorization === '') {
            return null;
        }

        if (preg_match('/^Bearer\s+(\S+)$/i', $authorization, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
