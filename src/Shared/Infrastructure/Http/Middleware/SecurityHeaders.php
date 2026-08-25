<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Laravel\Horizon\Horizon;
use Laravel\Telescope\Telescope;
use Shared\Providers\SharedServiceProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Emits the OWASP-baseline security response headers plus a per-request,
 * nonce-based Content-Security-Policy.
 *
 * The nonce is resolved from the request-scoped `csp-nonce` container binding
 * (see {@see SharedServiceProvider}) and handed to Vite
 * BEFORE the view renders, so every `@vite` / `@viteReactRefresh` tag produced
 * by the Blade root view carries a matching `nonce` attribute. This keeps
 * `script-src` free of `'unsafe-inline'`.
 *
 * Registered ahead of the Inertia middleware in bootstrap/app.php so the
 * headers survive Inertia (SSR) responses.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var string $nonce */
        $nonce = app('csp-nonce');

        // Bind the nonce to Vite before the response is generated so emitted
        // asset tags inherit it.
        Vite::useCspNonce($nonce);

        $response = $next($request);

        foreach ($this->staticHeaders($request) as $header => $value) {
            $response->headers->set($header, $value);
        }

        $response->headers->set(
            'Content-Security-Policy',
            match (true) {
                $this->isApiDocsRequest($request) => $this->apiDocsContentSecurityPolicy(),
                $this->isMonitoringRequest($request) => $this->monitoringContentSecurityPolicy(),
                default => $this->contentSecurityPolicy($nonce),
            },
        );

        return $response;
    }

    /**
     * Transport-agnostic hardening headers.
     *
     * @return array<string, string>
     */
    private function staticHeaders(Request $request): array
    {
        $headers = [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Permitted-Cross-Domain-Policies' => 'none',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Permissions-Policy' => (string) config('security.headers.permissions_policy'),
        ];

        // HSTS only over HTTPS to avoid pinning plain-HTTP local development.
        if ((bool) config('security.headers.hsts.enabled') && $request->isSecure()) {
            $value = 'max-age='.(int) config('security.headers.hsts.max_age');

            if ((bool) config('security.headers.hsts.include_subdomains')) {
                $value .= '; includeSubDomains';
            }

            if ((bool) config('security.headers.hsts.preload')) {
                $value .= '; preload';
            }

            $headers['Strict-Transport-Security'] = $value;
        }

        return $headers;
    }

    /**
     * The Scramble API docs UI (Stoplight Elements) boots from the unpkg CDN and
     * initializes via inline `<script>`/`<style>` tags inside a vendor Blade view
     * that cannot carry our per-request nonce. Under the strict app CSP all of it
     * is blocked and the page renders blank. This matches ONLY that internal,
     * access-restricted tooling page (guarded by Scramble's `RestrictedDocsAccess`
     * middleware) so it can receive a scoped CSP; every other route stays
     * nonce-strict. Default Scramble paths: `docs/api` (UI) + `docs/api.json`
     * (spec). Update these if `Scramble::configure()->expose()` changes them.
     */
    private function isApiDocsRequest(Request $request): bool
    {
        return $request->is('docs/api', 'docs/api.json', 'docs/api/*');
    }

    /**
     * Horizon and Telescope render their dashboards from vendor Blade views
     * ({@see Horizon::css()}/{@see Horizon::js()},
     * {@see Telescope::css()}/{@see Telescope::js()})
     * that inline the compiled CSS/JS directly into unscoped `<style>`/`<script>`
     * tags with no CSP nonce. Under the nonce-strict app policy every one of
     * those tags is silently dropped, leaving an unstyled, non-interactive
     * page. Access to both dashboards is already restricted by their own
     * `viewHorizon`/`viewTelescope` gates, so relaxing the CSP only for these
     * paths does not weaken the app's public attack surface.
     */
    private function isMonitoringRequest(Request $request): bool
    {
        $horizonPath = trim((string) config('horizon.path', 'horizon'), '/');
        $telescopePath = trim((string) config('telescope.path', 'telescope'), '/');

        return $request->is($horizonPath, "{$horizonPath}/*", $telescopePath, "{$telescopePath}/*");
    }

    /**
     * Scoped CSP for the Scramble docs UI only (see {@see self::isApiDocsRequest()}).
     * Permits exactly what Stoplight Elements needs — its unpkg bundle, the inline
     * boot script/style, `eval`-based syntax highlighting, and CDN webfonts — while
     * keeping `object-src`/`frame-ancestors`/`base-uri` locked down. Same-origin
     * `connect-src` is enough because the OpenAPI document is inlined into the page
     * and "Try It" calls hit this app's own API under the current session.
     */
    private function apiDocsContentSecurityPolicy(): string
    {
        $directives = [
            'default-src' => ["'self'"],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
            'frame-ancestors' => ["'none'"],
            'form-action' => ["'self'"],
            'img-src' => ["'self'", 'data:', 'https:'],
            'font-src' => ["'self'", 'data:', 'https://fonts.gstatic.com'],
            'style-src' => ["'self'", "'unsafe-inline'", 'https://unpkg.com', 'https://fonts.googleapis.com'],
            'script-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'", 'https://unpkg.com'],
            // Stoplight's "Try It" JSON editor spawns web workers from blob URLs.
            'worker-src' => ["'self'", 'blob:'],
            'connect-src' => ["'self'"],
        ];

        return collect($directives)
            ->map(static fn (array $sources, string $name): string => $name.' '.implode(' ', $sources))
            ->implode('; ');
    }

    /**
     * Scoped CSP for the Horizon/Telescope dashboards only (see
     * {@see self::isMonitoringRequest()}). Both dashboards inline their CSS/JS
     * without a nonce, so `'unsafe-inline'` is required here; their Vue 3
     * runtime also compiles templates via `new Function()`, so `'unsafe-eval'`
     * is required on `script-src`. `fonts.bunny.net` serves the Figtree
     * webfont both dashboards load.
     */
    private function monitoringContentSecurityPolicy(): string
    {
        $directives = [
            'default-src' => ["'self'"],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
            'frame-ancestors' => ["'none'"],
            'form-action' => ["'self'"],
            'img-src' => ["'self'", 'data:'],
            'font-src' => ["'self'", 'data:', 'https://fonts.bunny.net'],
            'style-src' => ["'self'", "'unsafe-inline'", 'https://fonts.bunny.net'],
            'script-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'"],
            'connect-src' => ["'self'"],
        ];

        return collect($directives)
            ->map(static fn (array $sources, string $name): string => $name.' '.implode(' ', $sources))
            ->implode('; ');
    }

    /**
     * Build the nonce-based CSP. Production stays strict on scripts; styles use
     * the CSP3 split (`style-src-elem` / `style-src-attr`) because Vue 3 +
     * PrimeVue Volt inject runtime `<style>` tags and `style=""` attributes
     * that cannot carry the per-request nonce. Putting a nonce on `style-src`
     * makes browsers ignore `'unsafe-inline'` (see the console error the SPA
     * hits otherwise). Local development also relaxes scripts for Vite HMR.
     */
    private function contentSecurityPolicy(string $nonce): string
    {
        $isLocal = app()->environment('local');

        $scriptSrc = ["'self'", "'nonce-{$nonce}'", ...(array) config('security.headers.csp.script_src', [])];
        $connectSrc = ["'self'", ...(array) config('security.headers.csp.connect_src', [])];
        $imgSrc = ["'self'", ...(array) config('security.headers.csp.img_src', [])];
        $fontSrc = ["'self'", ...(array) config('security.headers.csp.font_src', [])];

        // External stylesheets (`@vite` → /build/assets/*.css) + runtime <style>
        // injection from Volt overlays. No nonce here — nonce would nullify
        // 'unsafe-inline' and break Dialog/Drawer/Toast positioning.
        $styleSrcElem = ["'self'", "'unsafe-inline'"];
        $styleSrcAttr = ["'unsafe-inline'"];

        if ($isLocal) {
            $scriptSrc[] = "'unsafe-eval'";
            $connectSrc[] = 'ws:';
            $connectSrc[] = 'wss:';
        }

        $directives = [
            'default-src' => ["'self'"],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
            'frame-ancestors' => ["'none'"],
            'form-action' => ["'self'"],
            'img-src' => $imgSrc,
            'font-src' => $fontSrc,
            'script-src' => $scriptSrc,
            'style-src-elem' => $styleSrcElem,
            'style-src-attr' => $styleSrcAttr,
            'connect-src' => $connectSrc,
        ];

        return collect($directives)
            ->map(static fn (array $sources, string $name): string => $name.' '.implode(' ', $sources))
            ->implode('; ');
    }
}
