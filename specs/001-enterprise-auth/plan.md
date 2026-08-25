# Plan — Enterprise Authentication Module

> SDD Phase 4 artifact. Every decision traces to `spec.md` (requirement) or `research.md` (evidence). `[UNVERIFIED]` marks choices without research backing.

## 1. Verified Technology Stack

| Component | Version | Status | Source |
|---|---|---|---|
| PHP | 8.5.9 | ✅ installed | `php -v` |
| Laravel | 13.26.1 | ✅ installed | `composer show` |
| Laravel Fortify (auth backbone) | 1.38.0 | ✅ installed — customization points verified | R1 |
| spatie/laravel-one-time-passwords (OTP 6 dígitos) | 1.1.2 | ✅ installed — fits verification + reset | R2 |
| spatie/laravel-permission (RBAC) | 8.3.0 | ✅ installed | stack audit |
| spatie/laravel-activitylog (audit) | 5.1.0 | ✅ installed | stack audit |
| pragmarx/google2fa + bacon-qr-code (TOTP/QR) | pulled in by Fortify | ✅ installed as Fortify dependencies. The `pragmarx/google2fa-laravel` wrapper was removed on 2026-08-25 — Fortify implements 2FA itself and never used it. | stack audit |
| Laravel Sanctum | 4.3.3 | ✅ installed — NOT used this phase (out of scope) | clarify Q6 |
| ~~laravel/socialite (Google + GitHub OAuth)~~ | — | 🚫 **DESCOPED 2026-08-25** — never installed, no OAuth code exists | R3 |
| Database: user's existing Supabase (PostgreSQL) | — | ✅ endpoints provided by user, copy into `.env` | R10 |
| Brevo SMTP relay (`MailInterface` → `BrevoMailAdapter`) | — | ✅ wired 2026-08-25: `brevo` mailer in `config/mail.php`, all auth mail routed through it | — |
| Redis | production-only | ✅ code stays store-agnostic (RateLimiter over default cache store) | R4, clarify Q3 |
| bepsvpt/secure-headers + spatie/laravel-csp | latest | ✅ **to install** — approved by user in plan review | R9 |
| Testing: Pest 5.1 | 5.1.1 | ✅ installed | composer show |

## 2. Architecture

**Pattern:** Fortify backbone + modular hexagonal module `src/Modules/Auth/` (intermediate architecture — qualifies: ≥2 third-party integrations: OAuth providers, TOTP, mail).

**Principle:** Fortify owns the auth HTTP endpoints (login, register, 2FA, reset, verification). Our module NEVER re-implements those flows — it plugs in via:
1. **Fortify Actions** (`app/Actions/Fortify/`) — CreateNewUser, ResetUserPassword, PasswordValidationRules (policy + history + breach check).
2. **Fortify pipeline** (`Fortify::loginThrough`) — custom steps: account lockout check → new-device detection. *(Forced-2FA enforcement for admins was dropped on 2026-08-25 — 2FA is opt-in for every role.)*
3. **Notification overrides** on `App\Models\User` — OTP codes instead of signed URLs for verification + reset.
4. **Custom routes/controllers ONLY** where Fortify has no equivalent: sessions management, 2FA trusted-device, OTP verification entry points.

**Layering (per ARCHITECTURE-PHP skill):**
- `Domain/` — Value Objects (`DeviceFingerprint`), events (`UserLockedOut`, `NewDeviceLoginDetected`, `TwoFactorEnabled`...), ports (`SessionTrackerPort`, `PasswordHistoryPort`, `AuditPort`).
- `Application/` — Commands/Queries/Handlers: `TrackLoginAttemptHandler`, `LockAccountHandler`, `ListActiveSessionsHandler`, `RevokeSessionHandler`, `RegenerateBackupCodesHandler`...
- `Infrastructure/` — Eloquent models + repositories, spatie activitylog adapter, Fortify glue, middleware (`EnsureAccountNotLocked`, `SessionLifetimeByRole`), controllers, notifications, Inertia page controllers.

## 3. Physical Data Model (Supabase Postgres)

| Table | Key columns | Notes |
|---|---|---|
| `users` (exists) | + no new columns needed | `two_factor_*`, `password_changed_at` already present. Add `locked_until` (timestamp, nullable). |
| `one_time_passwords` (package migration) | morphs + expires_at (indexed) | Run package migration; config expiry → 30 min. |
| ~~`linked_social_accounts`~~ 🚫 DESCOPED | uuid PK, user_id FK, provider (google/github), provider_user_id (unique composite w/ provider), provider_email, provider_email_verified, token (encrypted, nullable), created_at/updated_at/deleted_at | Unlink + takeover protection state. |
| `password_histories` | uuid PK, user_id FK, password_hash, changed_at; index (user_id, changed_at) | Last 5 enforced in logic; prune older on insert. |
| `auth_sessions` (session tracking) | uuid PK, user_id FK, session_id (unique), ip_address, user_agent, device_hash, created_at, last_seen_at, revoked_at; index (user_id, last_seen_at) | Powers sessions list + new-device detection; driver-agnostic (R5). |
| `activity_log` (package migration) | package defaults + `ip_address`, `user_agent` properties | Per-event context via properties JSON. Prune 12 months (MassPrunable, S03:00). |
| spatie permission tables | package defaults | Roles: superadmin, admin, moderator, user, guest + permission set. |

All tables: UUID PKs, `SoftDeletes` on domain tables (not on `one_time_passwords`/`auth_sessions` — ephemeral), universal timestamps, `#[Fillable]`/`#[Hidden]` attributes on models.

## 4. API / Route Contracts (derived from user stories)

### Fortify-owned (existing, customized via actions/notifications/views — NOT new routes)
| Method | Route | FR |
|---|---|---|
| POST | /register | FR-01 |
| POST | /login | FR-03, FR-04, FR-05 |
| POST | /logout | FR-03 |
| POST | /forgot-password | FR-11 |
| POST | /reset-password | FR-11, FR-12, FR-13 |
| POST | /email/verification-notification | FR-02 (rate-limited) |
| POST | /user/two-factor-authentication (+ confirm, QR, recovery codes) | FR-07 |
| POST | /two-factor-challenge | FR-07, FR-04 |

### Module-owned (new)
| Method | Route | Middleware | FR |
|---|---|---|---|
| POST | /email/verify-otp | guest, throttle:otp | FR-02 |
| ~~GET~~ | ~~/auth/{provider}/redirect~~ | 🚫 DESCOPED | ~~FR-10~~ |
| ~~GET~~ | ~~/auth/{provider}/callback~~ | 🚫 DESCOPED | ~~FR-10~~ |
| ~~DELETE~~ | ~~/auth/linked/{uuid}~~ | 🚫 DESCOPED | ~~FR-10~~ |
| GET | /sessions | auth, verified | FR-14 |
| DELETE | /sessions/{uuid} | auth, verified, whereUuid | FR-14 |
| POST | /sessions/revoke-all-others | auth, verified | FR-14 |
| POST | /two-factor/trusted-device | auth, verified | FR-08 |
| GET | /sessions (page), /profile/two-factor (page) | auth, verified | FR-07, FR-14 |

### Rate limiters (`RateLimiter::for`)
| Name | Limit | Key | FR |
|---|---|---|---|
| login | 5/min | email\|ip | FR-04 |
| register | 3/hour | ip | FR-04 |
| password-reset | 3/15min | ip | FR-04 |
| otp | 1/60s + 5/hour | email | FR-04 (Q2) |
| two-factor | 5/5min | user id | FR-04 |
| ~~social~~ | — | 🚫 DESCOPED | ~~FR-10~~ |

All throttled responses: 429 + `Retry-After` (Laravel default, verified R4).

## 5. Folder Structure

```
app/
├── Actions/Fortify/            # CreateNewUser, ResetUserPassword, PasswordValidationRules (modified)
├── Models/User.php             # + MustVerifyEmail, locked_until, notification overrides
├── Providers/FortifyServiceProvider.php  # + loginThrough pipeline, views, limiters
src/Modules/Auth/
├── Domain/
│   ├── Events/                 # UserLockedOut, NewDeviceLoginDetected...
│   ├── Ports/                  # SessionTrackerPort, PasswordHistoryPort, AuditPort
│   └── ValueObjects/           # DeviceFingerprint
├── Application/
│   ├── Commands/               # TrackLoginAttempt, LockAccount,
│   │                           # RevokeSession, RevokeAllOtherSessions, RegenerateBackupCodes, TrustDevice
│   │                           # VerifyEmailOtp, ResetPasswordWithOtp, ChangePassword
│   ├── Queries/                # ListActiveSessions
│   └── DTOs/                   # SessionData (Spatie Data, SnakeCaseMapper)
├── Infrastructure/
│   ├── Http/Controllers/       # OtpVerificationController, SessionController,
│   │                           # TrustedDeviceController + Inertia page controllers
│   ├── Middleware/             # EnsureAccountNotLocked, SessionLifetimeByRole
│   ├── Notifications/          # NewDeviceLogin, PasswordChanged, AccountLocked, OtpNotification (wraps spatie)
│   ├── Persistence/            # AuthSessionEloquentModel,
│   │                           # PasswordHistoryEloquentModel, Mappers, Repositories
│   └── Audit/                  # SpatieActivityLogAdapter
├── Providers/AuthServiceProvider.php
routes/auth.php                 # module routes (required from web.php)
resources/js/pages/Auth/        # VerifyOtp.vue, TwoFactorChallenge.vue, TwoFactorSetup.vue,
                                # BackupCodes.vue, Sessions.vue (Vite pages)
```

## 6. Testing Strategy (Pest 5)

| Suite | Coverage |
|---|---|
| `tests/Feature/Auth/RegistrationTest.php` | US-01: policy, OTP sent, throttle 3/h |
| `tests/Feature/Auth/EmailVerificationTest.php` | US-02: OTP valid/expired/used, resend throttle, blocked access |
| `tests/Feature/Auth/LoginTest.php` | US-03: success regenerates session, 5/min throttle + Retry-After, lockout at 10, audit rows |
| `tests/Feature/Auth/TwoFactorTest.php` | US-04: setup+confirm flow, challenge, backup codes single-use + regen, trusted device, 5/5min throttle. Opt-in enrolment covered by `OptionalTwoFactorTest.php` |
| ~~`tests/Feature/Auth/SocialLoginTest.php`~~ | 🚫 DESCOPED 2026-08-25 — not built |
| `tests/Feature/Auth/BrandedAuthEmailsTest.php` | Auth mail renders through the branded layout (logo + brand palette) and leaves via the Brevo mailer |
| `tests/Feature/Auth/PasswordTest.php` | US-06: OTP reset, history last-5, session invalidation, notification |
| `tests/Feature/Auth/SessionsTest.php` | US-07: list/revoke/revoke-all, new-device email, role lifetimes |
| `tests/Feature/Auth/RbacTest.php` | US-09: 403 for missing permission on protected routes, permission props |
| `tests/Feature/Auth/AuditTest.php` | US-08: every event type written with IP+UA |
| `tests/Architecture/LayersTest.php` (extend) | module layer boundaries |

Factories: `UserFactory` states (unverified, with-2fa, admin, locked). Mail faked (`Notification::fake()`). 401/403/429 cases mandatory per OWASP NFR-04.

## 7. Security (OWASP baseline mapping)

| OWASP item | Implementation |
|---|---|
| §1 Access control | All module routes `auth`+`verified` (+permission where applicable); `->whereUuid`; policies for session/social ownership |
| §2 Authentication | Fortify + Argon2id (project config), session regenerate on login, invalidate+regenerateToken on logout, 5/min throttle, lockout pipeline step |
| §3 Injection | FormRequest/Spatie Data validation everywhere; `$fillable`/`#[Fillable]` allowlists |
| §4 Crypto | `Crypt::encryptString` for social tokens; Fortify encrypts 2FA secret + recovery codes; hashed password history; no secrets in logs |
| §5 Misconfig | `sslmode=require` to Supabase; secure-headers (HSTS, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy) + laravel-csp with per-request nonces registered before Inertia middleware; `APP_DEBUG=false` prod |
| §8 Integrity | OTP single-use + same-origin enforced (package); CSRF on all Inertia forms |
| §9 Logging | spatie activitylog with explicit properties (ip, user_agent) — never codes/passwords |
| §10 Exceptions | sanitized errors; lockout/throttle as first-class states, not exceptions |
| §14 Resource consumption | all 6 rate limiters (§4 table); notifications queued |

## 8. Risks

| Risk | Mitigation |
|---|---|
| Supabase pooler mode mismatch (6543 transaction mode breaks prepared statements) | Verify user's URL at config task T02; document `DB_URL` session-mode preference |
| ~~Socialite dependency approval~~ | 🚫 DESCOPED 2026-08-25 — never installed |
| OTP stored plain in DB (package design) | Accepted residual risk (R2); 30-min expiry, single-active, deleted on use |
| Redis absent locally | All rate-limit/session code store-agnostic; production .env mandates Redis |
| secure-headers/csp not installed | Approved by user in plan review — install both in foundations task; register before Inertia middleware for SSR |
| Fortify upgrade coupling | Customization only via documented extension points (Actions, pipeline, contracts) |

## 9. Traceability Matrix (spec → plan)

| Spec | Covered by |
|---|---|
| US-01 / FR-01 | §4 Fortify /register + CreateNewUser + PasswordValidationRules; §3 password_histories seed on register |
| US-02 / FR-02 | §4 /email/verify-otp + notification override + OTP config 30 min |
| US-03 / FR-03,04,05,06 | §4 login pipeline (lockout, device detect) + rate limiters + audit |
| US-04 / FR-07,08,09 | §4 Fortify 2FA endpoints + TrustedDeviceController + opt-in enrolment from Settings → Security (no forced enrolment) |
| ~~US-05 / FR-10~~ | 🚫 DESCOPED 2026-08-25 — not built |
| US-06 / FR-11,12,13 | §4 forgot/reset + ResetUserPassword + history check + notifications |
| US-07 / FR-14,15,16* | §4 /sessions* + auth_sessions tracking + NewDeviceLogin (*FR-16 deferred) |
| US-08 / FR-17 | spatie activitylog adapter + all events + 12-month prune |
| US-09 / FR-18 | spatie permission: 5 roles seeded + middleware + Inertia permission props |
| FR-19, FR-20 | Fortify-encrypted 2FA at rest; CSRF default; §7 table |
| NFR-01..07 | §6 tests; §7 security; queued notifications (NFR-02); store-agnostic (NFR-03) |

## 10. Decisions resolved in plan review

1. **secure-headers + laravel-csp**: user approved installing both as part of this module (foundations task).
2. Supabase URL mode (session 5432 vs transaction 6543) verified at task T02 — user provides the existing endpoints.
