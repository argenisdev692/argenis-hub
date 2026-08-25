# Spec — Enterprise Authentication Module

> SDD Phase 1 artifact. Stack-agnostic: technology choices live in `plan.md` (Phase 4).
> Source prompt: `prompt-laravel-enterprise-auth.md` (analyzed against current project state).

## 1. Summary

A full enterprise-grade authentication and authorization module for the web application: registration with mandatory email verification, secure login with brute-force protection and account lockout, **opt-in** two-factor authentication (TOTP + single-use backup codes), password lifecycle management with history policy, active session/device management, a complete authentication audit trail, and role-based access control with five roles.

> **Scope revisions (2026-08-25).** Two decisions taken after the original draft:
>
> 1. **Social login is descoped.** `laravel/socialite` is not installed and no OAuth code exists. US-05, FR-10, the `LinkedSocialAccount` entity and tasks T16–T18 are retained below for the record but marked **DESCOPED**; nothing in them is built. Revisit only when the product actually needs a provider.
> 2. **Two-factor authentication is opt-in for every role.** The original FR-09 forced admin/superadmin into enrolment before reaching privileged areas. That gate is removed: users enable TOTP from Settings → Security when they choose to.

## 2. Motivation

The application currently has a minimal authentication scaffold (login/registration routes exist, dashboard behind `auth`+`verified`) but none of the enterprise controls required for a production SaaS: no brute-force defense beyond basic throttling, no 2FA, no session visibility/revocation for users, no audit trail of auth events, and no RBAC enforcement. This module closes that gap so the platform can be exposed to real users safely.

## 3. Actors

| Actor | Description |
|---|---|
| **Guest** | Unauthenticated visitor. Can register, log in, verify email, reset password. |
| **User** | Authenticated user with verified email. Standard role. |
| **Moderator** | User with content-moderation permissions. |
| **Admin** | Privileged user. 2FA is opt-in (shorter idle session lifetime applies). |
| **Superadmin** | Highest privilege. 2FA is opt-in (shorter idle session lifetime applies). |

## 4. User Stories & Acceptance Criteria

### US-01 — Registration
**As a** guest **I want to** create an account with email + password **so that** I can access the platform.

- **Given** a guest submits a valid registration form, **when** the form is processed, **then** an account is created in an unverified state and a 6-digit OTP code is emailed, expiring in 30 minutes.
- **Given** a registration form, **when** the password does not meet policy (12+ chars, uppercase, number, symbol), **then** validation rejects it with clear errors.
- **Given** more than 3 registrations from the same IP within an hour, **when** a 4th is attempted, **then** it is rejected with a Retry-After indication.

### US-02 — Email Verification (OTP, mandatory)
**As a** registered user **I want to** verify my email with a 6-digit code **so that** I can access the app.

- **Given** an unverified user enters the correct OTP code within 30 minutes, **when** submitted, **then** the account becomes verified and access is granted.
- **Given** an expired or already-used code, **when** submitted, **then** verification fails and the user can request a new code (rate-limited).
- **Given** an unverified user, **when** they try to access protected pages, **then** they are blocked until verification completes.
- **Given** an unverified user, **when** they request a new code more than 1 time per 60 seconds or 5 times per hour, **then** requests are throttled. *(Resolved: see `clarify.md` Q2)*

### US-03 — Login / Logout
**As a** user **I want to** log in and out securely **so that** my account is protected.

- **Given** valid credentials, **when** login succeeds, **then** the session identifier is regenerated and a "remember me" secure token may be issued.
- **Given** invalid credentials, **when** login fails, **then** the attempt is logged with IP + user agent.
- **Given** more than 5 failed attempts per IP+email per minute, **when** another is attempted, **then** it is rejected with a `Retry-After` header.
- **Given** 10 accumulated failures for an account, **when** the 10th fails, **then** the account is locked for 15 minutes and the user is informed.
- **Given** an authenticated user, **when** they log out, **then** the current session is invalidated; "logout everywhere" invalidates ALL sessions.
- **Given** 10 accumulated failures for an account (counted across IPs), **when** the 10th fails, **then** the account is locked for 15 minutes and the user is informed. *(Resolved: see `clarify.md` Q4)*

### US-04 — Two-Factor Authentication (TOTP)
**As a** user **I want to** enable TOTP 2FA with my authenticator app **so that** my account resists password theft.

- **Given** a user without 2FA, **when** they start setup, **then** a QR code (compatible with Google Authenticator, Authy, Microsoft Authenticator) plus a secret is shown.
- **Given** a pending setup, **when** the user submits a valid TOTP code, **then** 2FA is activated and 8 single-use backup codes (stored hashed) are revealed once.
- **Given** a 2FA-enabled user, **when** they log in with valid credentials, **then** they must pass a TOTP challenge before the session is created.
- **Given** a lost authenticator, **when** the user submits a valid backup code, **then** access is granted and that code is invalidated.
- **Given** a user, **when** they request backup-code regeneration, **then** current password confirmation is required and old codes are voided.
- **Given** a 2FA-enabled user, **when** they opt to "trust this device", **then** the 2FA challenge is skipped on that device for 30 days via a tamper-proof signed cookie.
- **Given** more than 5 failed 2FA attempts per user in 5 minutes, **when** another is attempted, **then** it is rejected.
- **Given** a user of ANY role, **when** they have not enabled 2FA, **then** they still reach every area their permissions allow — enrolment is never forced. *(Revised 2026-08-25, see §1)*

### US-05 — Social Login  🚫 DESCOPED (2026-08-25 — not built, see §1)
**As a** user **I want to** sign in with Google or GitHub **so that** I don't manage another password.

- **Given** a provider callback with a verified provider email matching an existing local account, **when** the user consents, **then** the social identity is linked to that account.
- **Given** no matching local account, **when** the OAuth callback succeeds, **then** a new user is provisioned and marked email-verified (if the provider email is verified).
- **Given** a provider email that is NOT verified by the provider, **when** the callback arrives, **then** auto-linking is refused (account-takeover protection) and the user is routed to a safe flow.
- **Given** an authenticated user, **when** they unlink a provider, **then** the link is removed only if another sign-in method remains (password or another provider).
- The provider, provider user id, and token metadata are persisted per linked account.

### US-06 — Password Management
**As a** user **I want to** reset and change my password safely **so that** I keep control of my account.

- **Given** a password reset request, **when** submitted, **then** a 6-digit OTP (single-use, 30-minute expiry) is emailed — never a link/token URL.
- **Given** a valid OTP + new password meeting policy, **when** submitted, **then** the password changes, all sessions are invalidated, and a notification email is sent.
- **Given** a new password equal to any of the last 5 passwords, **when** submitted, **then** it is rejected.
- **Given** an authenticated user changes their password, **when** successful, **then** other sessions are invalidated and they are notified by email.
- Reset requests are rate-limited: 3 per IP per 15 minutes.

### US-07 — Sessions & Devices
**As a** user **I want to** see and revoke my active sessions **so that** I can respond to suspicious access.

- **Given** an authenticated user, **when** they view the sessions page, **then** every active session is listed with IP, user agent (device/browser), and last activity.
- **Given** the sessions list, **when** the user revokes one, **then** that session is terminated; "revoke all" terminates every session except the current one.
- **Given** a login from a device/IP combination never seen before for that user, **when** login succeeds, **then** the user receives a new-device alert email.
- Session/token expiry is role-dependent: standard users 24h, admins 1h.

### US-08 — Audit Trail
**As a** superadmin **I want to** a complete auth audit log **so that** I can investigate security incidents.

- Every auditable auth event is recorded with actor, event type, IP address, user agent, and timestamp: successful/failed login, logout, password change, password reset, email verification, 2FA enable/disable/confirm, backup-code usage, session revocation, account lockout.
- Audit entries cannot be modified or deleted by normal application flows.
- Audit entries are pruned automatically after 12 months. *(Resolved: see `clarify.md` Q5)*

### US-09 — RBAC
**As a** developer/admin **I want to** roles and permissions enforced server-side **so that** access control cannot be bypassed client-side.

- Five roles exist: superadmin, admin, moderator, user, guest.
- Every protected route declares required permission(s); policies guard resources.
- The frontend receives the user's permission set and shows/hides UI accordingly (defense-in-depth only; backend is authoritative).
- Role/permission changes are audit-logged.

## 5. Functional Requirements

| ID | Requirement |
|---|---|
| FR-01 | Email+password registration with password policy (12+ chars, uppercase, number, symbol) |
| FR-02 | Email verification via 6-digit OTP, 30-min expiry, single use; access blocked until verified |
| FR-03 | Login with session regeneration, remember-me, individual + global logout |
| FR-04 | Rate limiting: login 5/min (IP+email), register 3/h (IP), reset 3/15min (IP), 2FA 5/5min (user) |
| FR-05 | Account lockout: 15 minutes after 10 failed attempts |
| FR-06 | `Retry-After` header on throttled responses; every failed attempt logged (IP + user agent) |
| FR-07 | TOTP 2FA: QR setup, RFC 6238 compatibility, 8 hashed single-use backup codes, regeneration with password confirmation |
| FR-08 | 2FA middleware on protected routes; trusted-device option (30 days, signed cookie) |
| FR-09 | 2FA is opt-in for every role; enrolment lives in Settings → Security *(revised 2026-08-25)* |
| ~~FR-10~~ 🚫 DESCOPED | Social login (Google, GitHub): link by verified provider email, provision new users, store provider identity, unlink with fallback-method guard |
| FR-11 | Password reset via 6-digit OTP (single use, 30 min); no link/token URLs |
| FR-12 | Password history: last 5 passwords not reusable |
| FR-13 | Password change invalidates other sessions + notification email |
| FR-14 | Session list (IP, user agent, last activity), individual revocation, revoke-all-except-current |
| FR-15 | New device/IP detection with alert email |
| FR-16 | ~~API token auth~~ **(Out of scope — deferred, see `clarify.md` Q6)** |
| FR-17 | Full auth audit log with IP, user agent, and per-event details |
| FR-18 | RBAC: 5 roles, permission middleware, policies, Inertia permission props |
| FR-19 | 2FA secret and backup codes encrypted at rest |
| FR-20 | CSRF protection on all state-changing forms; security headers on all responses |

## 6. Non-Functional Requirements

| ID | Requirement |
|---|---|
| NFR-01 | Security: satisfies the project OWASP baseline (all 15+ items) — Argon2id hashing, secure cookies, sanitized production errors, no secrets in logs |
| NFR-02 | Performance: auth endpoints respond < 300 ms at p95 excluding mail delivery (mail is queued) |
| NFR-03 | Scalability: rate limiting and session storage must work in a multi-server deployment |
| NFR-04 | Testability: every user story covered by automated tests (unit + feature), including 401/403/429 cases |
| NFR-05 | Auditability: every security-relevant event traceable end-to-end |
| NFR-06 | Usability: mobile-first auth pages, accessible (WCAG AA), localized error messages |
| NFR-07 | No secrets hardcoded — all credentials via environment configuration |

## 7. Conceptual Data Entities (no physical schema here)

- **User** — identity, credentials state, verification state, 2FA state (secret, backup codes, confirmed flag), password metadata, profile fields (already exists in the project).
- ~~**LinkedSocialAccount**~~ 🚫 DESCOPED — provider, provider user id, token metadata, link timestamps; many-per-user.
- **PasswordHistoryEntry** — previous password hashes (hash-only), per user.
- **AuthAuditEvent** — event type, actor, IP, user agent, timestamp, context.
- **KnownDevice** (or equivalent signal) — device/IP fingerprint per user for new-device detection.
- **Role / Permission** — RBAC structures (5 roles; permissions defined per capability).
- **OneTimePasswordCode** — transient 6-digit codes (verification + reset) with expiry and consumption state.

## 8. Out of Scope

- ~~API token auth for mobile/external clients~~ ✅ **DELIVERED 2026-08-25** (supersedes `clarify.md` Q6): `POST /api/auth/login`, `POST /api/auth/refresh`, `POST /api/auth/logout`, `GET /api/auth/me`. Sanctum bearer tokens rotated per device on login and on refresh, role-based expiry (1440 min standard / 60 min privileged), and the API re-enforces lockout, email verification and the two-factor challenge rather than bypassing them. Covered by `tests/Feature/Api/AuthApiTest.php`.
- WebAuthn/passkey login (already available in the platform; not part of this module's scope — may be surfaced later).
- Magic-link / passwordless email login.
- SSO enterprise (SAML, OIDC federation).
- Admin UI for managing other users' roles (only role enforcement + seeding here).
- Email delivery infrastructure itself (uses existing project mail stack).

## 9. Assumptions & Decisions (resolved in `clarify.md`)

- **Assumption:** Email verification and password reset MUST use OTP codes, not signed URLs — treated as a hard requirement from the source prompt.
- **Decided (Q1):** Build on the already-installed headless auth backbone (Fortify); enterprise features layered via its customization points — not the fully-custom controller layout from the source prompt.
- **Decided (Q6):** Web-session auth only in this phase; API token endpoints (`/api/auth/*`) are out of scope.
- **Decided (Q3):** Redis required in production; code stays store-agnostic so local dev works with database drivers.
- **Defaulted (Q2):** OTP resend throttling — 1 per 60 s, 5 per hour per identity.
- **Defaulted (Q4):** Lockout keyed by account (email), failures counted across IPs, 15 min.
- **Defaulted (Q5):** Audit retention — 12 months with scheduled pruning.
- **Open (Q7):** OTP mechanism — prefer the already-installed OTP package if research confirms it backs both flows; validated in Phase 3.
- **Defaulted (Q8):** Trusted-device signal — signed, HTTP-only, secure cookie bound to user + device fingerprint hash, 30 days, revocable; mechanism validated in Phase 3.

## 10. Success Criteria

1. A guest can register → verify email via OTP → log in → enable 2FA → re-login passing the TOTP challenge — end-to-end, covered by automated tests.
2. Brute force (11 rapid failures) locks the account for 15 minutes; all throttles emit `Retry-After`; every failure is audit-logged with IP + UA.
3. A user can view and revoke all sessions; a login from a new device triggers the alert email.
4. Any role can enrol in 2FA from Settings → Security, and no role is blocked from privileged routes for lacking it.
5. ~~Social login links/unlinks safely with account-takeover protection.~~ 🚫 DESCOPED — not built.
6. Password reset works via OTP only; last-5-password reuse is rejected; password change kills other sessions.
7. The full existing test suite plus the new module tests pass (`php artisan test --compact`).
