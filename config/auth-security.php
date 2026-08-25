<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Enterprise authentication hardening
|--------------------------------------------------------------------------
|
| Every threshold the Auth module enforces lives here rather than inside the
| handlers, so an operator can tune brute-force policy without a deploy and a
| reviewer can read the whole policy on one screen. Traceability to the module
| spec (specs/001-enterprise-auth) is noted per block.
|
| Kept out of config/fortify.php on purpose: republishing the vendor config
| must never silently reset these values.
|
*/

return [

    /*
     * FR-05 — account lockout. Counted per account across IP addresses
     * (clarify Q4): per-IP counting is trivially defeated by a distributed
     * attack. `decay_minutes` is how long the rolling failure counter lives;
     * `duration_minutes` is how long the account stays locked once it trips.
     */
    'lockout' => [
        'max_attempts' => (int) env('AUTH_LOCKOUT_MAX_ATTEMPTS', 10),
        'decay_minutes' => (int) env('AUTH_LOCKOUT_DECAY_MINUTES', 15),
        'duration_minutes' => (int) env('AUTH_LOCKOUT_DURATION_MINUTES', 15),
    ],

    /*
     * FR-02 / FR-11 — 6-digit one-time codes for email verification and
     * password reset. The expiry is mirrored into config/one-time-passwords.php;
     * resend limits come from clarify Q2.
     */
    'otp' => [
        'expires_in_minutes' => (int) env('AUTH_OTP_EXPIRES_IN_MINUTES', 30),
        'resend' => [
            'per_minute' => (int) env('AUTH_OTP_RESEND_PER_MINUTE', 1),
            'per_hour' => (int) env('AUTH_OTP_RESEND_PER_HOUR', 5),
        ],
    ],

    /*
     * FR-08 — "trust this device" window for skipping the TOTP challenge.
     */
    'trusted_devices' => [
        'days' => (int) env('AUTH_TRUSTED_DEVICE_DAYS', 30),
    ],

    /*
     * US-07 — idle session lifetime by role. Privileged accounts get a much
     * shorter leash than standard users.
     */
    'session_lifetime' => [
        'default_minutes' => (int) env('AUTH_SESSION_IDLE_MINUTES', 1440),
        'privileged_minutes' => (int) env('AUTH_PRIVILEGED_SESSION_IDLE_MINUTES', 60),
    ],

    /*
     * Sanctum personal access tokens for the mobile/external API.
     *
     * `ttl_minutes` mirrors the web idle lifetimes above so a stolen bearer
     * token is never longer-lived than a stolen session cookie. Tokens are
     * rotated on every login and on every refresh, so the previous token stops
     * working the moment a new one is handed out.
     */
    'api_tokens' => [
        'ttl_minutes' => (int) env('AUTH_API_TOKEN_TTL_MINUTES', 1440),
        'privileged_ttl_minutes' => (int) env('AUTH_API_PRIVILEGED_TOKEN_TTL_MINUTES', 60),
    ],

    /*
     * US-07 — roles treated as privileged for session hardening. Two-factor
     * authentication is NOT forced on them: enrolment is opt-in from
     * Settings → Security for every role alike. The only thing this list
     * still drives is the shorter idle lifetime above.
     */
    'privileged_roles' => ['SUPER_ADMIN', 'ADMIN'],
];
