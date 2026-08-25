# Tasks — Enterprise Authentication Module

> SDD Phase 5 artifact. Ordered by dependency. `[P]` = parallelizable (no shared files, no deps between them). Each task leaves the system compiling/testable. Boxes are only checked after its tests pass.

## Group A — Foundations

- [ ] T01 — Install dependencies: `composer require bepsvpt/secure-headers spatie/laravel-csp` *(socialite descoped 2026-08-25)*. Publish spatie permission + activitylog + one-time-passwords config/migrations. `composer dump-autoload` (adds `Modules\` + `Shared\` PSR-4 if absent).
- [ ] T02 — Database switch to user's existing Supabase: set `DB_CONNECTION=pgsql` + `DB_URL` (user-provided endpoints) in `.env`/`.env.example`, `sslmode=require`; detect pooler mode (6543 → disable prepared statements / prefer session 5432); run `php artisan migrate` (baseline Laravel tables + package migrations on Supabase). Verify with a smoke test (`php artisan migrate:status`).
- [ ] T03 — Security headers + CSP: register `secure-headers` and `laravel-csp` middleware BEFORE `\Inertia\Middleware` in `bootstrap/app.php`; nonce-per-request (`csp_nonce()`) in `app.blade.php` for `<script>`/`<style>`; HSTS in production only. Test: response-header assertions on any GET route.

## Group B — Data model

- [ ] T04 — Migrations: `locked_until` on users; `password_histories`; `auth_sessions` (indexes per plan §3). All UUID PKs, soft deletes on domain tables.
- [ ] T05 — Eloquent models + `User` wiring: `PasswordHistoryEloquentModel`, `AuthSessionEloquentModel` (final, `#[Fillable]`, `#[Hidden(['id'])]`, `casts()` method, `LogsActivity` with `logOnly([...])` where aggregate); `App\Models\User` + `MustVerifyEmail`, `locked_until` cast, inverse relations (`passwordHistories()`, `authSessions()`), `sendEmailVerificationNotification`/`sendPasswordResetNotification` overrides → OTP notifications. Both sides of every FK typed (plan §3; BACKEND-PHP §4).
- [ ] T06 — Domain + Application skeletons: `src/Modules/Auth/` tree (Events, Ports, ValueObjects, Commands, Queries, DTOs, Infrastructure, Providers) registered in `bootstrap/providers.php`; `AuthServiceProvider` binds ports (SessionTrackerPort, PasswordHistoryPort, SocialAccountPort, AuditPort → Eloquent/Socialite/Spatie adapters). `tests/Architecture/LayersTest.php` extended to cover the new module.

## Group C — Core auth (Fortify backbone)

- [ ] T07 — Registration + password policy: `PasswordValidationRules` → `Password::min(12)->letters()->mixedCase()->numbers()->symbols()->uncompromised()`; `CreateNewUser` seeds `password_histories` row; throttles `register` 3/h. Tests: RegistrationTest.
- [ ] T08 — OTP config + email verification: set `one-time-passwords` config (expiry 30 min, 6 digits, notification → custom `OtpNotification`); route `POST /email/verify-otp` + handler `VerifyEmailOtp` (consume code, mark verified, audit); resend throttle `otp` 1/60s + 5/h; `verified` middleware blocks unverified (verify existing behavior); Inertia page `VerifyOtp.vue`. Tests: EmailVerificationTest.
- [ ] T09 — Login hardening: `Fortify::loginThrough` pipeline + `EnsureAccountNotLocked` middleware step + `TrackLoginAttemptHandler` (failure count by email across IPs, 10 → `locked_until` +15 min, `UserLockedOut` event audited); rate limiter `login` 5/min by email|ip with 429 + Retry-After; failed attempts audit-logged with IP+UA. Tests: LoginTest (incl. lockout + Retry-After header).
- [ ] T10 — Password reset via OTP: `forgot-password` sends OTP (notification override, throttle `password-reset` 3/15min); `reset-password` consumes OTP + policy + history (last-5 rejection) + invalidate other sessions + `PasswordChanged` email; audit all steps. Tests: PasswordTest.
- [ ] T11 — Logout: Fortify logout invalidates + regenerates token; "logout everywhere" action (`RevokeAllOtherSessionsHandler` + `Auth::logoutOtherDevices`) wired to UI. Tests: SessionsTest (partial).

## Group D — Two-Factor (TOTP)

- [ ] T12 — 2FA enable/confirm/disable via Fortify endpoints (confirm flow with `two_factor_confirmed_at`); `RegenerateBackupCodesHandler` requiring password confirmation; 8 hashed single-use recovery codes (Fortify native); audit events (enable/disable/confirm/backup-code use); throttle `two-factor` 5/5min. Tests: TwoFactorTest.
- [ ] T13 — Trusted device: `TrustDeviceHandler` + `POST /two-factor/trusted-device` sets signed, HTTP-only, Secure, SameSite=Lax cookie (user id + device hash, 30 days); `EnsureTwoFactorVerified` middleware skips challenge when cookie valid; revocation on 2FA disable + logout-everywhere. Tests: TwoFactorTest (trusted-device cases).
- [x] T14 — ~~Forced 2FA for admin/superadmin~~ 🚫 **REVERSED 2026-08-25**: 2FA is opt-in for every role. `EnsureTwoFactorIsEnrolled` and its route guard were removed; `OptionalTwoFactorTest.php` asserts no role is held back.
- [ ] T15 — 2FA Inertia pages: `TwoFactorChallenge.vue` (TOTP input + backup-code toggle), `TwoFactorSetup.vue` (QR), `BackupCodes.vue`. Tests: browser-less feature tests assert views render + flows complete.

## Group E — Social login  🚫 DESCOPED 2026-08-25 (never built — see spec.md §1)

- [ ] ~~T16~~ 🚫 DESCOPED — `SocialiteSocialAuthAdapter` implementing the R3 rules: link only when provider email verified AND local email verified; unverified local → route to OTP verification flow; new user → provision (+`email_verified_at` only if provider verified); GitHub email verification nuance handled (primary verified email endpoint). Tests: SocialLoginTest (mocked Socialite user objects; MUST include the CVE-2026-53516 pre-hijack case).
- [ ] ~~T17~~ 🚫 DESCOPED — Routes + controller: `/auth/{provider}/redirect|callback` (google, github; throttle `social`), `DELETE /auth/linked/{uuid}` with fallback-method guard (never remove last sign-in method); tokens encrypted at rest (`Crypt::encryptString`); `SocialAccountLinked/Unlinked` audited. Tests: SocialLoginTest.
- [ ] ~~T18~~ 🚫 DESCOPED — `SocialAccounts.vue` page (link/unlink UI). Tests: feature test asserts page + guard behavior.

## Group F — Sessions & devices

- [ ] T19 — `SessionTrackerPort` implementation: record login (session_id, ip, UA, device_hash, last_seen), update `last_seen_at` via lightweight middleware, revocation writes `revoked_at`; `NewDeviceLoginDetected` → `NewDeviceLogin` email (queued) comparing device_hash. Tests: SessionsTest.
- [ ] T20 — Routes + controller + page: `GET /sessions`, `DELETE /sessions/{uuid}`, `POST /sessions/revoke-all-others`; `Sessions.vue` (list IP/UA/last activity/current marker, revoke buttons); `SessionLifetimeByRole` middleware (24h user / 1h admin idle-based). Tests: SessionsTest.

## Group G — RBAC + Audit

- [ ] T21 — Roles/permissions seed: superadmin, admin, moderator, user, guest + permission set (`app/Enums/Permissions.php` single source); Spatie permission cache config; role/permission assignment AFTER user creation (never via fillable). Tests: RbacTest (403 cases + permission props to Inertia).
- [ ] T22 — Audit wiring: `SpatieActivityLogAdapter` (+ip_address, user_agent, country-optional properties) on every auth event per FR-17 list; `MassPrunable` + `Schedule::command('model:prune')` daily 03:00, 12-month retention. Tests: AuditTest (one assertion per event type).
- [ ] T23 — Inertia permission props: shared `auth.user.permissions` handle (eager `roles:id,name` + `permissions` — N+1-safe), `<PermissionGuard>` usage in auth pages. Tests: RbacTest.

## Group H — Closeout

- [ ] T24 — Full suite green: `php artisan test --compact` (all new + pre-existing tests); `vendor/bin/pint --dirty --format agent`; `composer audit`.
- [ ] T25 — Final traceability pass against `spec.md` §5/§10 via plan §9 matrix; gaps become new tasks here; write `analyze.md` findings as resolved.
- [ ] T26 — `.env.example` completeness (Supabase URL placeholders, Google/GitHub OAuth keys, OTP config, session hardening keys per OWASP §15.5) + README-less handoff note in `SSD-SUMMARY.md` (Phase 8).

## Parallelization map

- After T01–T03: T04 `[P]` × T07–T08 prep.
- After T06: C/D/E/F groups largely parallel per-task (different files): T09 `[P]` T19.
- T12–T15 sequential (shared middleware/pages). T21 `[P]` T19.
- T22–T23 after their dependent groups. T24–T26 strictly last.
