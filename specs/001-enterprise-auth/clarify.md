# Clarify — Enterprise Authentication Module

> SDD Phase 2 artifact. Audit trail of every ambiguity surfaced from `spec.md` and how it was resolved.

## Resolved with the user (high-impact)

### Q1 — Auth backbone: Fortify vs custom controllers
**Question:** Build on the already-installed headless auth backend (Fortify 1.38, whose 2FA columns already exist on `App\Models\User`), or build fully custom controllers as the source prompt's file structure suggests?
**Impact:** Determines the entire module architecture, file layout, route ownership, and how much existing code is reused vs replaced. Building parallel custom 2FA would conflict with the existing `TwoFactorAuthenticatable` trait and its columns.
**Resolution (user decision):** **Fortify backbone.** Reuse Fortify's routes/flows; implement enterprise extras via Fortify Actions, Contracts, custom notifications, and dedicated services. The source prompt's controller file structure is adapted, not followed literally.

### Q6 — API token endpoints scope
**Question:** Implement `/api/auth/login|refresh|me` (Sanctum personal access tokens with rotation and role-based expiry) in this phase?
**Impact:** Determines whether Sanctum token issuance, rotation, and per-role expiry logic is built now or deferred.
**Resolution (user decision):** **Web sessions only this phase.** API token flow is OUT OF SCOPE (moved to `spec.md` §8). Sanctum remains installed for future phases; no token endpoints are built now.

### Q3 — Redis strategy
**Question:** The source prompt mandates Redis for cache/sessions/queues/rate-limiting; local dev uses database drivers + SQLite on Windows/Herd.
**Impact:** Environment requirements, CI, and whether local dev must install Redis.
**Resolution (user decision):** **Redis in production, store-agnostic locally.** All rate-limiting/session/cache code must be driver-agnostic (no Redis-specific calls in domain logic); production `.env`/`.env.example` documents Redis as required (per project backend rules, database drivers are banned in production paths).

## Resolved by default (lower-impact)

### Q2 — OTP resend throttling
**Default:** Max 1 resend per 60 seconds, 5 per hour per identity (email). Rationale: balances UX (impatient users) with email-bombing protection (OWASP API4).
**Status:** Resolved by default.

### Q4 — Lockout semantics
**Default:** Lockout keyed by **account (email)**, counting failures across IPs (10 failures → 15 min lock). Rationale: per-IP lockout is trivially bypassed by distributed attacks; account-level lockout creates a DoS vector but the 15-min window + audit alert is the accepted trade-off for this project. Lockout events are audit-logged.
**Status:** Resolved by default.

### Q5 — Audit retention
**Default:** 12 months, then pruned automatically via scheduled task (MassPrunable pattern per project backend skill).
**Status:** Resolved by default.

## Additional ambiguity found during clarification review

### Q7 — Where do the 6-digit OTP codes come from?
**Finding:** The project already ships a first-party OTP package (`spatie/laravel-one-time-passwords` 1.1.2) referenced by the project OWASP skill for exactly this use case (6-digit codes, single use).
**Impact:** Determines whether the OTP mechanism for email verification + password reset is the installed package or custom code.
**Resolution:** Default to the **already-installed OTP package** if Phase 3 research confirms it can back both email verification and password reset with Fortify; otherwise custom hashed-code storage (hashed OTP + expiry + single-use flag) validated at research time. **To be confirmed in `research.md` / `plan.md`.**

### Q8 — 2FA trusted-device cookie scope
**Finding:** "Trust this device for 30 days" needs a tamper-proof signal. The source prompt says "signed cookie".
**Default:** Signed, HTTP-only, secure cookie bound to user id + device fingerprint hash, 30-day expiry, revocable by disabling 2FA and by "logout everywhere". No PII in the cookie value.
**Status:** Resolved by default; exact mechanism validated in Phase 3 research.

## User decisions added after Phase 2 review

### Q9 — Database platform
**Decision (user):** Use the user's **existing Supabase project** — no new PostgreSQL installation. The user copies their existing connection URLs/endpoints into `.env` (`DB_CONNECTION=pgsql` + `DB_URL`). Verified in `research.md` R10: session pooler (5432) works as-is; transaction pooler (6543) requires disabling prepared statements; `sslmode=require` recommended; Supabase Auth NOT used (Laravel/Fortify owns auth). All module tables are plain Postgres — no design changes required; only connection/config tasks added to the plan.

## High-impact blockers status

None remaining — Phase 3 (research) may proceed.
