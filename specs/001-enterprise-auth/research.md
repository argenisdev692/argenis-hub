# Research — Enterprise Authentication Module

> SDD Phase 3 artifact. All findings verified live (Context7 + Tavily, Aug 2026). Each finding maps to a stack-table row in `plan.md`.

## R1 — Auth backbone: Fortify 1.38 customization points (VERIFIED — Context7 `/laravel/fortify`)

| Capability | Finding |
|---|---|
| Login pipeline | `Fortify::loginThrough()` / `authenticateThrough()` rebuilds the pipeline array — custom steps (lockout check, new-device detection, forced-2FA redirect) can be inserted cleanly. |
| 2FA challenge | `RedirectsIfTwoFactorAuthenticatable` stores `login.id` + `login.remember` in session; user is NOT authenticated during the TOTP challenge. Challenge view via `Fortify::twoFactorChallengeView()`. Redirect behavior customizable via `Fortify::redirectUserForTwoFactorAuthenticationUsing()`. |
| 2FA confirmation | Fortify 1.38 has `ConfirmedTwoFactorAuthenticationController` (`POST /user/confirmed-two-factor-authentication`) — matches existing `two_factor_confirmed_at` column on `App\Models\User`. |
| Views/responses | All views bind to overridable contracts (`VerifyEmailViewResponse`, `RequestPasswordResetLinkViewResponse`, etc.) — Inertia rendering is a plain binding swap. |
| Notifications | Email verification + password reset notifications are overridable on the User model (`sendEmailVerificationNotification`, `sendPasswordResetNotification`) — the hook for OTP flows. |
| Throttling | `config('fortify.limiters.login')` integrates with `RateLimiter::for()`. |

**Conclusion:** Fortify 1.38 covers login/logout/register/2FA/reset/verification endpoints with all needed customization points. Clarify Q1 decision (Fortify backbone) is validated.

## R2 — OTP mechanism: spatie/laravel-one-time-passwords 1.1.2 (VERIFIED — Context7 `/spatie/laravel-one-time-passwords`)

| Capability | Finding |
|---|---|
| Generation | `$user->sendOneTimePassword()` — creates + emails the code, deletes previous codes (single active per user). |
| Consumption | Lower-level `CreateOneTimePasswordAction` / `ConsumeOneTimePasswordAction` usable WITHOUT the package's built-in OTP login (we need verification + reset, not OTP login). |
| Code shape | 6 numeric digits (configurable), expiry configurable (default **2 min → must set 30 min**). |
| Single use | Consumed codes are **deleted** from the table. |
| Rate limiting | Built-in consume throttle: 5 attempts / 60 s / user (configurable). |
| Same-origin | Enforced by default (`DefaultOriginEnforcer`) — code requested from browser X can't be consumed from browser Y. |
| Notification | Custom notification class supported (config `notification`). |

**Trade-off (accepted):** the package stores the code as a plain string in `one_time_passwords.password` (no hashing). Accepted because: codes are short-lived (30 min), deleted on consumption, single-active-per-user, and DB read access implies a compromised server anyway. Mitigation: DB-level access controls; documented as residual risk in `plan.md`.

**Conclusion:** Clarify Q7 resolved — use the installed spatie package for BOTH email-verification and password-reset OTP, with `default_expires_in_minutes: 30`.

## R3 — Social login account-takeover protection (VERIFIED — Tavily, GHSA-g38m-r43w-p2q7 / CVE-2026-53516, July 2026)

A HIGH-severity advisory published July 2026 (Better Auth, same flaw class as Microsoft "nOAuth" 2023 and Sign-in-with-Apple JWT 2020) documents the exact attack our design must prevent:

- **Attack (pre-account hijacking):** attacker pre-registers the victim's email locally (row stays unverified) → later, an OAuth identity whose provider asserts `email_verified: true` gets **auto-linked by email match alone** → attacker keeps password access to the victim's OAuth identity.
- **Correct mitigation:** auto-link ONLY when **BOTH** (a) the provider's email is verified AND (b) the **local** account's email is already verified. Never auto-link into an unverified local row — require the owner to complete OTP verification first.
- Companion advisory (GHSA-qq9h-g4jm-xgf3): when proving control of an email on a previously-unverified account, any pre-existing credentials set before the proof must be invalidated.

**Design rules adopted:**
1. Google/GitHub only (both always return verified emails — still check the flag, never trust).
2. Existing local user + verified local email + verified provider email → link.
3. Existing local user + **unverified** local email → NO auto-link; route to OTP verification flow.
4. New user from OAuth → create with `email_verified_at` set only if provider email verified; else unverified.
5. Never store raw provider tokens longer than needed; store only what unlink requires.

## R4 — Rate limiting & lockout in Laravel 13 (VERIFIED — Context7 `/laravel/docs` 13.x + Tavily: WorkOS 2026 guide, benjamincrozat.com)

- `RateLimiter::for()` with per-key limits (`Limit::perMinute(5)->by($email.'|'.$ip)`) is the canonical mechanism; throttled responses return 429 with headers incl. `Retry-After` — customizable via `Limit::response(fn ($request, $headers) => response(..., 429, $headers))`.
- WorkOS 2026 guidance confirms: rate-limit login/register/reset, implement account lockout after repeated failures, use `Password::min(12)->letters()->mixedCase()->numbers()->uncompromised()` for policy. Note: **NIST/WorkOS recommend length + breach-corpus check over strict symbol rules** — our spec requires symbols too (kept: spec is authoritative, `->symbols()` exists).
- Account lockout is not built into Fortify's default pipeline beyond throttling — our custom pipeline step + cache counter implements the 10-failures/15-min lockout (by account, per clarify Q4).
- Cache-agnostic: `RateLimiter` uses the default cache store — works locally (database) and in production (Redis), satisfying clarify Q3.

## R5 — Session & device management (VERIFIED — Context7 `/laravel/docs` 13.x)

- `Auth::logoutOtherDevices($currentPassword)` + `auth.session` middleware exist in core Laravel 13 — covers "logout everywhere" and invalidation on password change.
- **CRITICAL CONSTRAINT:** listing active sessions with IP/user-agent/last-activity requires reading the `sessions` TABLE — only the `database` session driver stores `user_id`, `ip_address`, `user_agent`, `last_activity`. Production will use **Redis sessions** (project hard rule) where this is impossible.
- **Design consequence:** build a dedicated `auth_sessions` (session tracking) table: `user_id`, `session_id`, `ip`, `user_agent`, `device_hash`, `created_at`, `last_seen_at`, maintained at login + on activity. This powers BOTH the sessions list (any driver) AND new-device detection (compare `device_hash`). Revocation = delete tracked row + `Auth::logoutOtherDevices` semantics / session driver invalidation.
- Role-based session lifetime (24 h user / 1 h admin): implement via `IdleTimeout`-style middleware reading role from the authenticated user (pattern from project OWASP skill §15.5).

## R6 — 2FA trusted-device cookie (VERIFIED — Tavily deploynix.io Aug 2026 + project OWASP §15.5)

- Current guidance (passkey/TOTP rollout articles, 2026): recovery/backup codes single-use, hashed at rest, shown once — matches our spec.
- Trusted-device: signed, HTTP-only, `Secure`, `SameSite=Lax` cookie containing user id + device hash + expiry, signed with APP_KEY (`Cookie::queue` with signing enabled — Laravel signs all cookie values by default when `APP_KEY` set). Revocation: disable-2FA and "logout everywhere" must clear it. 30-day TTL.

## R7 — Password policy & history (VERIFIED — Tavily WorkOS 2026 + Laravel 13 `Password` rule)

- `Password::min(12)->letters()->mixedCase()->numbers()->symbols()->uncompromised()` covers the spec's policy + breach-corpus check (haveibeenpwned k-anonymity).
- Password history (last 5) has no framework support → custom check: `password_histories` table (user_id, hashed password, changed_at), validated inside the change/reset flow. Hash each history entry with the app hasher (Argon2id per project config).

## R8 — Eloquent ORM best practices (Laravel 13 / PHP 8.5) (VERIFIED — project skill + Tavily WorkOS performance checklist)

The project's own backend skill (`BACKEND-PHP/SKILL.md` §4, §4.1) is the canon; Tavily 2026 sources align with it:

- `Model::shouldBeStrict()` in `AppServiceProvider` (already project mandate); eager-load with explicit columns — auth module loads `User::with(['roles:id,name...'])` for permission props (avoid N+1 on shared layout).
- Index every auth lookup column: `users.email` (unique), `one_time_passwords.expires_at` (package migration indexes it), `auth_sessions.user_id + last_seen_at`, `password_histories.user_id + created_at`, `activity_log.causer_id + created_at`.
- `casts()` method form (Laravel 11+) — already used by `App\Models\User` ✓.
- Permission checks: cache roles/permissions per user with short TTL (WorkOS: 5 min + explicit invalidation on change) — spatie permission 8 supports `RolesAndPermissionsCache` via its own cache config.
- Audit log pruning: `MassPrunable` + scheduled `model:prune` at 03:00 for 12-month retention (clarify Q5) — pattern from project skill §4.1 #11.
- PHP 8.5 idioms per project skill §0–§1: `declare(strict_types=1)`, `final readonly` services with constructor promotion, `#[\NoDiscard]` on handlers returning results, `match` for branching, pipe operator `|>` for normalization chains in command handlers.

## R9 — Security headers & CSP (VERIFIED — project OWASP skill §5)

Project already mandates `bepsvpt/secure-headers` + `spatie/laravel-csp` — but neither appears in `composer show --direct`. **Open decision for plan:** these are project-standard packages; installing them is a dependency change requiring approval (flagged in plan stack table as `[UNVERIFIED-INSTALLED]` → must confirm with user in plan review). Alternative: Laravel 13's built-in header middleware set (minimal). The auth module requires at minimum: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, HSTS in production.

## R10 — Database: Supabase (PostgreSQL) (VERIFIED — Tavily, supabase.com official Laravel quickstart + SSL docs)

User decision: the database will be the user's **existing Supabase project** — no new infrastructure; the user copies their existing connection URL/endpoints into `.env`. Current local `.env` uses SQLite — the module switches the connection to the existing Supabase credentials.

| Concern | Finding (official Supabase docs) |
|---|---|
| Connection | `DB_CONNECTION=pgsql` + `DB_URL` using the **Session Pooler** string (port 5432, Supavisor session mode). The Transaction Pooler (6543) does NOT support prepared statements — avoid for Laravel. |
| SSL | Laravel's default `sslmode=prefer` silently falls back to plaintext. **MUST set `sslmode: require`** (or `verify-full` with the Supabase CA cert). Supabase-side SSL enforcement available via dashboard/API. |
| Schema | Supabase exposes the `public` schema through its Data API — official recommendation: create a dedicated schema (e.g., `laravel`) and set `search_path` in `config/database.php`. |
| Auth | Supabase Auth is NOT used — Laravel/Fortify implements its own auth on top of the Postgres database (explicitly confirmed by Supabase docs). Bills only database resources. |
| Compatibility | All planned tables (auth sessions tracking, password history, OTP, activity log, spatie permission) are plain Postgres — no incompatibilities. UUIDs, partial indexes, and `jsonb` all supported. |

**Impact on earlier research:** R5's dedicated `auth_sessions` tracking table becomes even more valuable — the `database` session driver on Supabase Postgres is viable for staging, while production keeps Redis per project rules; the tracking table works identically in both.

**Config tasks derived:** paste the existing Supabase connection URL into `.env`/`.env.example` (`DB_CONNECTION=pgsql` + `DB_URL`); set `sslmode=require` (or keep whatever the user's endpoint enforces); verify pooler mode — session mode (port 5432) works as-is, transaction mode (6543) requires disabling prepared statements; run `php artisan migrate` against Supabase.

## Stack verification summary

| Component | Status | Source |
|---|---|---|
| Laravel 13.26.1 / PHP 8.5.9 | ✅ installed | composer show |
| Fortify 1.38 | ✅ installed, customization points verified | Context7 |
| Sanctum 4.3.3 | ✅ installed (not used this phase) | composer show |
| spatie/laravel-one-time-passwords 1.1.2 | ✅ installed, fits OTP requirement | Context7 |
| spatie/laravel-permission 8.3.0 | ✅ installed | composer show |
| spatie/laravel-activitylog 5.1.0 | ✅ installed | composer show |
| pragmarx/google2fa-laravel 3.0.1 + bacon-qr-code | ✅ installed (Fortify uses google2fa internally for its 2FA) | composer show |
| laravel/socialite | ❌ NOT installed — must `composer require` (dependency change, flagged for approval) | composer show |
| Redis (predis/phpredis) | ⚠️ phpredis configured in .env but drivers are database — production-only concern | .env |
| bepsvpt/secure-headers + spatie/laravel-csp | ❌ NOT installed despite project skill mandating them — flagged | composer show |

**Contradiction surfaced (per skill hard rule):** none of the research contradicts spec/clarify decisions. The sessions-listing vs Redis-sessions conflict (R5) is resolved by design (dedicated tracking table), not by spec change.
