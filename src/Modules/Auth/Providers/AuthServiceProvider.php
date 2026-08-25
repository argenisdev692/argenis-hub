<?php

declare(strict_types=1);

namespace Modules\Auth\Providers;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Cookie\QueueingFactory as CookieJar;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\CanonicalizeUsername;
use Laravel\Fortify\Actions\EnsureLoginIsNotThrottled;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Contracts\RedirectsIfTwoFactorAuthenticatable;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Modules\Auth\Domain\Events\NewDeviceDetected;
use Modules\Auth\Domain\Events\UserPasswordChanged;
use Modules\Auth\Domain\Ports\AccountLockPort;
use Modules\Auth\Domain\Ports\AuthSessionTrackerPort;
use Modules\Auth\Domain\Ports\LoginAttemptTrackerPort;
use Modules\Auth\Domain\Ports\PasswordHistoryPort;
use Modules\Auth\Domain\Ports\TrustedDevicePort;
use Modules\Auth\Infrastructure\Http\Middleware\EnforceSessionLifetimeByRole;
use Modules\Auth\Infrastructure\Http\Middleware\EnsureAccountIsNotLocked;
use Modules\Auth\Infrastructure\Http\Middleware\TrackAuthSessionActivity;
use Modules\Auth\Infrastructure\Http\Middleware\TrustedDeviceAwareTwoFactorRedirect;
use Modules\Auth\Infrastructure\Listeners\AuthAuditSubscriber;
use Modules\Auth\Infrastructure\Listeners\HandlePasswordChangedListener;
use Modules\Auth\Infrastructure\Listeners\ReleaseAuthSessionListener;
use Modules\Auth\Infrastructure\Listeners\SendNewDeviceAlertListener;
use Modules\Auth\Infrastructure\Listeners\TrackFailedLoginListener;
use Modules\Auth\Infrastructure\Listeners\TrackSuccessfulLoginListener;
use Modules\Auth\Infrastructure\Persistence\Repositories\CacheLoginAttemptTracker;
use Modules\Auth\Infrastructure\Persistence\Repositories\EloquentAccountLockRepository;
use Modules\Auth\Infrastructure\Persistence\Repositories\EloquentAuthSessionRepository;
use Modules\Auth\Infrastructure\Persistence\Repositories\EloquentPasswordHistoryRepository;
use Modules\Auth\Infrastructure\Security\CookieTrustedDeviceRegistry;
use RuntimeException;

/**
 * Composition root for the enterprise authentication module.
 *
 * Everything the module adds to Fortify is wired from here — ports to adapters,
 * the extra login-pipeline step, the trusted-device-aware two-factor contract,
 * the rate limiters, the middleware aliases, the audit subscriber and the module
 * routes — so the application layer stays free of module internals and the whole
 * integration surface is readable in one file.
 */
final class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->bindPorts();
    }

    public function boot(): void
    {
        $this->configureRateLimiters();
        $this->configureLoginPipeline();
        $this->registerMiddleware();
        $this->registerWebRoutes();
        $this->registerApiRoutes();
        $this->throttleFortifyRegistration();
        $this->registerListeners();
    }

    private function bindPorts(): void
    {
        $this->app->bind(AccountLockPort::class, EloquentAccountLockRepository::class);
        $this->app->bind(AuthSessionTrackerPort::class, EloquentAuthSessionRepository::class);

        $this->app->bind(
            LoginAttemptTrackerPort::class,
            static fn ($app): CacheLoginAttemptTracker => new CacheLoginAttemptTracker(
                $app->make(Cache::class),
                (int) config('auth-security.lockout.decay_minutes'),
            ),
        );

        $this->app->bind(
            PasswordHistoryPort::class,
            static fn ($app): EloquentPasswordHistoryRepository => new EloquentPasswordHistoryRepository(
                $app->make(Hasher::class),
            ),
        );

        $this->app->bind(
            TrustedDevicePort::class,
            static fn ($app): CookieTrustedDeviceRegistry => new CookieTrustedDeviceRegistry(
                $app->make(Request::class),
                $app->make(CookieJar::class),
                (int) config('auth-security.trusted_devices.days'),
            ),
        );

        // Fortify resolves this contract when deciding whether to challenge for
        // TOTP; swapping the binding is how the module adds trusted devices
        // without forking Fortify's pipeline.
        $this->app->bind(RedirectsIfTwoFactorAuthenticatable::class, TrustedDeviceAwareTwoFactorRedirect::class);
    }

    /**
     * FR-04 — the limiters Fortify does not already own.
     *
     * `login`, `two-factor` and `passkeys` stay in FortifyServiceProvider: they
     * are applied by Fortify's own routes through `throttle:{name}`, which
     * already answers 429 with `Retry-After` (FR-06). The limiters below are
     * given the same treatment explicitly.
     */
    private function configureRateLimiters(): void
    {
        RateLimiter::for('register', fn (Request $request) => $this->limit(3, 60, (string) $request->ip()));

        RateLimiter::for('password-reset', fn (Request $request) => $this->limit(3, 15, (string) $request->ip()));

        // Resending a code: 1 per minute AND 5 per hour, per identity (clarify Q2).
        RateLimiter::for('otp-resend', function (Request $request): array {
            $key = (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());

            return [
                $this->limit((int) config('auth-security.otp.resend.per_minute'), 1, $key),
                $this->limit((int) config('auth-security.otp.resend.per_hour'), 60, $key),
            ];
        });

        RateLimiter::for('otp-verify', fn (Request $request) => $this->limit(
            5,
            5,
            (string) ($request->user()?->getAuthIdentifier() ?? $request->ip()),
        ));

        // Emailed second factor at the login challenge. Nobody is authenticated
        // yet, so the key is the challenged account combined with the origin:
        // the account id alone is attacker-controlled once they hold valid
        // credentials, and the IP alone would let one tenant of a shared
        // address burn everyone else's quota.
        RateLimiter::for('two-factor-email-send', function (Request $request): array {
            $key = $this->challengeKey($request);

            return [
                $this->limit((int) config('auth-security.otp.resend.per_minute'), 1, $key),
                $this->limit((int) config('auth-security.otp.resend.per_hour'), 60, $key),
            ];
        });

        RateLimiter::for(
            'two-factor-email-verify',
            fn (Request $request) => $this->limit(5, 5, $this->challengeKey($request)),
        );

        // API token issuance. Keyed by email+IP rather than IP alone so an
        // attacker cannot exhaust the quota of an account they do not control.
        RateLimiter::for('api-login', fn (Request $request) => $this->limit(
            5,
            1,
            mb_strtolower(trim((string) $request->input('email'))).'|'.$request->ip(),
        ));

        // Authenticated token operations (refresh / logout / me), per token owner.
        RateLimiter::for('api-session', fn (Request $request) => $this->limit(
            60,
            1,
            (string) ($request->user()?->getAuthIdentifier() ?? $request->ip()),
        ));
    }

    /**
     * Throttle key for the two-factor challenge, where no user is authenticated
     * but `login.id` names the account the challenge belongs to.
     */
    private function challengeKey(Request $request): string
    {
        return (string) $request->session()->get('login.id', 'guest').'|'.$request->ip();
    }

    private function limit(int $attempts, int $minutes, string $key): Limit
    {
        return Limit::perMinutes($minutes, $attempts)
            ->by($key)
            ->response(static fn (Request $request, array $headers) => response(
                ['message' => __('Too many requests. Please slow down.')],
                429,
                $headers,
            ));
    }

    /**
     * FR-05 — the lockout gate runs before anything else, so a locked account
     * cannot be probed at all. The rest of the pipeline is Fortify's default,
     * reproduced faithfully rather than trimmed.
     */
    private function configureLoginPipeline(): void
    {
        Fortify::authenticateThrough(fn (): array => array_filter([
            EnsureAccountIsNotLocked::class,
            config('fortify.limiters.login') ? null : EnsureLoginIsNotThrottled::class,
            config('fortify.lowercase_usernames') ? CanonicalizeUsername::class : null,
            Features::enabled(Features::twoFactorAuthentication()) ? RedirectsIfTwoFactorAuthenticatable::class : null,
            AttemptToAuthenticate::class,
            PrepareAuthenticatedSession::class,
        ]));
    }

    /**
     * Listener wiring, declared rather than discovered.
     *
     * Laravel 13.26 has no `#[AsEventListener]` attribute and its listener
     * auto-discovery only scans `app/Listeners`, so a module living under
     * `src/Modules` must register its own reactions. Declaring them here also
     * makes the module's full event surface readable in one place.
     */
    private function registerListeners(): void
    {
        Event::listen(Failed::class, [TrackFailedLoginListener::class, 'handle']);
        Event::listen(Login::class, [TrackSuccessfulLoginListener::class, 'handle']);
        Event::listen(Logout::class, [ReleaseAuthSessionListener::class, 'handle']);
        Event::listen(NewDeviceDetected::class, [SendNewDeviceAlertListener::class, 'handle']);
        Event::listen(UserPasswordChanged::class, [HandlePasswordChangedListener::class, 'handle']);

        Event::subscribe(AuthAuditSubscriber::class);
    }

    private function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        // Session tracking and role-based idle timeout apply to the whole web
        // surface, not only to this module's routes.
        $router->pushMiddlewareToGroup('web', TrackAuthSessionActivity::class);
        $router->pushMiddlewareToGroup('web', EnforceSessionLifetimeByRole::class);
    }

    private function registerWebRoutes(): void
    {
        Route::middleware('web')->group(__DIR__.'/../Infrastructure/Routes/web.php');
    }

    /**
     * Mounted under the `api` prefix so Scramble documents them alongside the
     * rest of the API surface (`config('scramble.api_path')`).
     */
    private function registerApiRoutes(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__.'/../Infrastructure/Routes/api.php');
    }

    /**
     * FR-04 — registration is capped at 3 per IP per hour.
     *
     * Fortify exposes limiter slots for login, two-factor, passkeys and
     * verification, but not for registration, and its route is declared inside
     * the package. Appending the middleware to the already-registered named
     * route is the only way to cover it without republishing Fortify's routes —
     * and it keeps working across package upgrades.
     */
    private function throttleFortifyRegistration(): void
    {
        $this->app->booted(static function (): void {
            $routes = Route::getRoutes();

            // Fortify names its routes after they are added, so the collection's
            // name lookup can still be stale here — refresh before resolving.
            $routes->refreshNameLookups();

            $route = $routes->getByName('register.store');

            if ($route === null) {
                throw new RuntimeException(
                    'FR-04: register.store not found, throttle:register was not applied.'
                );
            }

            $route->middleware('throttle:register');
        });
    }
}
