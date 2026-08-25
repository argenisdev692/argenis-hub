<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Auth\Application\Commands\TrustCurrentDeviceHandler;

/**
 * "Trust this device for 30 days" (FR-08).
 *
 * Behind `auth` + confirmed 2FA: only a user who has just proved possession of
 * their second factor may hand out a shortcut around it.
 */
final readonly class TrustedDeviceController
{
    public function __construct(private TrustCurrentDeviceHandler $trustDevice) {}

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_if($user->two_factor_confirmed_at === null, 403);

        $this->trustDevice->handle(
            userUuid: (string) $user->uuid,
            userAgent: $request->userAgent(),
            ipAddress: $request->ip(),
        );

        return back()->with('status', __('This device will skip the two-factor challenge for :days days.', [
            'days' => (int) config('auth-security.trusted_devices.days'),
        ]));
    }
}
