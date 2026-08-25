<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Fortify;
use Modules\Auth\Domain\Events\AccountLockedOut;
use Modules\Auth\Domain\Events\NewDeviceDetected;
use Modules\Auth\Domain\Events\UserPasswordChanged;
use Shared\Domain\Ports\AuditPort;

use function request;

/**
 * The complete authentication audit trail (FR-17), in one place.
 *
 * A subscriber rather than a listener per event: auditing is a SINGLE reaction —
 * "write this security-relevant fact to the trail" — and the event type is data,
 * not a new behaviour. Fifteen near-identical listener classes would be
 * duplication wearing a pattern's clothes.
 *
 * Every entry carries IP address and user agent (FR-17) and nothing else from
 * the request: no codes, no tokens, no passwords ever reach the log (OWASP §9).
 */
final readonly class AuthAuditSubscriber
{
    private const string LOG_NAME = 'auth';

    /**
     * The request is resolved per entry, never injected: a subscriber is built
     * once when it registers its listeners, so an injected Request would be
     * frozen at boot time and every audit entry would carry the wrong — or an
     * empty — IP address and user agent.
     */
    public function __construct(private AuditPort $audit) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Registered::class, fn (Registered $e) => $this->record('registered', $e->user));
        $events->listen(Login::class, fn (Login $e) => $this->record('login', $e->user));
        $events->listen(Logout::class, fn (Logout $e) => $this->record('logout', $e->user));
        $events->listen(Verified::class, fn (Verified $e) => $this->record('email_verified', $e->user));

        $events->listen(Failed::class, fn (Failed $e) => $this->record('login_failed', $e->user, [
            'attempted_email' => $e->credentials[Fortify::username()] ?? null,
        ]));

        $events->listen(TwoFactorAuthenticationChallenged::class, fn ($e) => $this->record('two_factor_challenged', $e->user));
        $events->listen(TwoFactorAuthenticationEnabled::class, fn ($e) => $this->record('two_factor_enabled', $e->user));
        $events->listen(TwoFactorAuthenticationConfirmed::class, fn ($e) => $this->record('two_factor_confirmed', $e->user));
        $events->listen(TwoFactorAuthenticationDisabled::class, fn ($e) => $this->record('two_factor_disabled', $e->user));
        $events->listen(TwoFactorAuthenticationFailed::class, fn ($e) => $this->record('two_factor_failed', $e->user));
        $events->listen(RecoveryCodeReplaced::class, fn ($e) => $this->record('recovery_code_used', $e->user));
        $events->listen(RecoveryCodesGenerated::class, fn ($e) => $this->record('recovery_codes_regenerated', $e->user));

        $events->listen(UserPasswordChanged::class, fn (UserPasswordChanged $e) => $this->record(
            'password_changed',
            $this->userByUuid($e->userUuid),
        ));

        $events->listen(NewDeviceDetected::class, fn (NewDeviceDetected $e) => $this->record(
            'new_device_detected',
            $this->userByUuid($e->userUuid),
        ));

        $events->listen(AccountLockedOut::class, fn (AccountLockedOut $e) => $this->record(
            'account_locked_out',
            null,
            [
                'attempted_email' => $e->email,
                'failed_attempts' => $e->failedAttempts,
                'locked_until' => $e->lockedUntil->format(DATE_ATOM),
            ],
        ));
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function record(string $event, ?object $subject, array $properties = []): void
    {
        $request = request();

        $this->audit->log(
            event: $event,
            subject: $subject,
            properties: [
                ...$properties,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
            causer: $subject,
            logName: self::LOG_NAME,
        );
    }

    private function userByUuid(string $uuid): ?User
    {
        return User::query()->where('uuid', $uuid)->first();
    }
}
