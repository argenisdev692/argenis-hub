---
trigger: always_on
---

# OWASP Security Baseline — Laravel 13 + Vue 3 + Inertia v3

> **Sources of truth (current as of 2026)**:
> - **Web apps** → [OWASP Top 10:2025](https://owasp.org/Top10/) — released November 2025. Two new categories (A03 Software Supply Chain Failures and A10 Mishandling of Exceptional Conditions); SSRF folded into A01 Broken Access Control.
> - **APIs** → [OWASP API Security Top 10:2023](https://owasp.org/API-Security/editions/2023/) (still current).
> - **LLM apps** → [OWASP Top 10 for LLM Applications:2025](https://genai.owasp.org/llm-top-10/) — applies WHEN the project integrates LLM/embeddings (see §16 below).
> - **AI Agents** → [OWASP Top 10 for Agentic Applications 2026](https://genai.owasp.org/) (released Dec 10, 2025) — applies ONLY if the project exposes autonomous agents.
>
> No official "OWASP Top 15" exists. This document **merges** Top 10:2025 + API Top 10:2023 + LLM Top 10:2025 into a practical 15+1-point baseline tuned to the project's stack: PHP 8.5 · Laravel 13 · Vue 3 · Inertia v3 · Spatie (Permission, Data, Activitylog) · Cloudflare R2.

---

## §0 — Authority and scope

- This file is **always-on**: it applies to every backend and frontend change.
- Every PR MUST satisfy the 15 baseline items below or document a justified deviation.
- Concrete implementation rules live in:
  - Backend → `.claude/BACKEND-PHP/SKILL.md`
  - Frontend → `.claude/FRONTEND/SKILL.md`
  - Architecture → `.claude/skills/ARCHITECTURE-PHP/SKILL.md` and `SKILL-SIMPLE-CRUD.md`
  - Vue/Inertia tree → `.claude/skills/ARCHITECTURE-VUE/SKILL.md`
- Use Tavily for fresh CVEs / advisories; use Context7 for current API behavior of any package referenced here.

---

## §1 — Broken Access Control + SSRF (A01:2025 + API1/API3/API5:2023)

> **2025 change**: A01:2021 (Broken Access Control) **absorbed** A10:2021 (SSRF). Both are now treated under A01:2025.

**Backend (Laravel 13)**:
- Deny by default: every route guarded by `auth` middleware unless explicitly public.
- Authorization via **Spatie Permission 8.x**: `Route::middleware('permission:VIEW_USERS')` or Policies with `Gate::authorize()`.
- Always resolve UUID-bound routes via `->whereUuid('uuid')` — never trust raw `id` from the client.
- Object-level checks: every Repository/Handler verifies tenant/owner before mutation. No "trust the URL".
- Property-level checks: filter response shapes via Spatie `Data` DTOs (allowlist). Never `->toArray()` directly on a model.
- **SSRF mitigation (now part of A01)**: outbound HTTP allowlist; block `127.0.0.0/8`, `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`, `169.254.0.0/16` (cloud metadata), `::1`, `fc00::/7`. See §15.

**Frontend (Vue 3 + Inertia v3)**:
- Wrap restricted UI in `<PermissionGuard :permission="'VIEW_X'">` (see `FRONTEND/SKILL.md` §14).
- Authorization is by **`permissions`**, never by `roles`. Roles exist only as backend grouping.
- UI hiding is **defense in depth, not the boundary** — backend MUST re-check.

**Hard rule**: any new endpoint without explicit permission/policy fails review.

---

## §2 — Authentication Failures (A07:2025 + API2:2023)

**Backend**:
- Web routes (primary) → **Laravel sessions** + Inertia. CSRF enforced by `PreventRequestForgery` middleware (Laravel 13 renamed `VerifyCsrfToken`, now `@deprecated` and kept only as a subclass). Beyond token validation it also verifies request origin via the browser's `Sec-Fetch-Site` header — reference the new name in tests and middleware overrides.
- API routes (secondary, mobile/external) → **Laravel Sanctum** with personal access tokens.
- Password hashing: `Hash::make($password)` configured to **`argon2id`** in `config/hashing.php`. Never `md5`/`sha1`/`bcrypt`-only.
- Brute force: throttle login via `RateLimiter::for('login', ...)` (5/min per IP+email).
- Session security: `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax` in production.
- Logout invalidates session AND regenerates token (`$request->session()->invalidate(); $request->session()->regenerateToken();`).
- MFA (when enabled): Laravel Fortify or `pragmarx/google2fa-laravel`.

**Frontend**:
- On logout: `router.visit('/login')` + `queryCache.clear()` to drop any cached PII.
- Never store auth tokens in `localStorage` / `sessionStorage` — Inertia uses session cookies.

> **Sanctum bearer note**: personal access tokens are **opaque random hashes** persisted in the `personal_access_tokens` table — they are NOT JWT. Swagger annotations MUST omit `bearerFormat="JWT"`; use `bearerFormat="Sanctum Token"` or omit `bearerFormat` entirely.

---

## §3 — Injection (A05:2025 + API8:2023)

**Backend**:
- Eloquent everywhere. **`DB::raw()` and `whereRaw()` only with parameter bindings**, never string interpolation.
- Validation gates: every request validated by either `FormRequest` (controller-level) or **Spatie Laravel Data** with attributes (`#[Required]`, `#[Email]`, `#[Max]`, `#[Rule(...)]`).
- Mass assignment: every Eloquent model declares `$fillable` (allowlist) — never use `$guarded = []`.
- Shell exec: forbidden. If unavoidable, use `Symfony\Component\Process\Process` with arg arrays — never `shell_exec`/`exec` with string concat.
- File path traversal: `Storage` API only — never `file_get_contents($userInput)`.

**Frontend**:
- Vue auto-escapes `{{ }}`. **`v-html` is forbidden on user input.** If rich text is required, sanitize on the server (Laravel `Purifier` or HTMLPurifier).
- Form validation (UX layer): `@primevue/forms` + Zod v4 schemas (via `@primevue/forms/resolvers/zod`) — but the backend `Data` DTO remains **authoritative**.
- Never `eval()`, never `new Function(string)`, never `innerHTML = userInput`.

---

## §4 — Cryptographic Failures (A04:2025)

- TLS everywhere: `APP_URL=https://...`, `FORCE_HTTPS=true`, HSTS via secure-headers.
- Passwords: Argon2id (see §2).
- Application encryption: `Crypt::encryptString()` for at-rest sensitive fields (PII, tokens stored intentionally).
- Secrets: **NEVER** committed. Use `.env` + `.env.example` with placeholders. CI uses GitHub Actions secrets / Forge env.
- Logging: `Log::*` MUST never receive `$request->password`, tokens, signed URLs, or full credit card numbers. Use Spatie `LogsActivity` with explicit `logOnly([...])` allowlist.
- Cookies: `secure`, `httpOnly`, `sameSite=lax` (or `strict` for sensitive flows).

---

## §5 — Security Misconfiguration (A02:2025 — UP from #5 to #2 in 2025)

**Backend**:
- Debug mode: `APP_DEBUG=false` in production — non-negotiable.
- Headers via **`bepsvpt/secure-headers`** package: HSTS, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`.
- **Middleware order matters with Inertia v3 SSR**: register `secure-headers` and `laravel-csp` **BEFORE** `\Inertia\Middleware` in `bootstrap/app.php` so headers survive SSR responses.
- CSP via **Spatie `laravel-csp` v3**: explicit allowlist for `script-src`, `style-src`, `img-src`, `connect-src`. No `'unsafe-eval'` in production.
- **CSP carve-out for Tailwind v4 + PrimeVue unstyled/Volt + Vue SFC styles**: PrimeVue runs in unstyled mode and Volt components inject their overlay/animation styles (Dialog, Drawer, Popover, Menu, Toast) at runtime, `tailwindcss-primeui` resolves CSS custom properties at runtime, and Vue SFC `<style>` blocks compile to inline `<style>` tags during SSR. Together these force one of:
  - **Preferred**: nonce-per-request via `Spatie\Csp\Nonce` — inject `<style nonce="{{ csp_nonce() }}">` and `<script nonce="{{ csp_nonce() }}">` in `app.blade.php`.
  - **Acceptable for SPAs without runtime-generated styles**: `style-src 'self' 'sha256-...'` with build-time hash extraction from Vite manifest.
  - **Forbidden**: `style-src 'self' 'unsafe-inline'` in production unless documented as a temporary deviation with mitigation deadline.
- CORS via `config/cors.php`: `allowed_origins` is an **explicit list**, never `['*']` for authenticated endpoints. For wildcard subdomains use `allowed_origins_patterns` with explicit regex (e.g., `'/^https:\/\/[a-z0-9-]+\.example\.com$/'`), never `'*'`.
- Default ServiceProvider, default error pages disabled in production.
- `.env` keys validated at boot (e.g., via `config:cache` + a `BootstrapHealthcheck` command).

> **Note**: `helmet()` is Express middleware and does **NOT** apply to Laravel. Use `secure-headers` + `laravel-csp` instead.

**Frontend**:
- Vite production build minified. `import.meta.env` keys exposed only those prefixed `VITE_*` — never put secrets in client env.
- No source maps shipped to production (`vite.config.ts` → `build.sourcemap: false`).

---

## §6 — Software Supply Chain Failures (A03:2025 — NEW category)

> **2025 change**: A06:2021 (Vulnerable & Outdated Components) was **renamed and broadened** to **A03:2025 Software Supply Chain Failures** — covering compromised dependencies, build-time injection, malicious typosquats, unsigned artifacts, and CI/CD pipeline tampering.

**Backend**:
- `composer.lock` committed. CI runs `composer audit` on every PR.
- Pin major versions in `composer.json`. Review CHANGELOG before any upgrade.
- Quarterly dependency audit; security patches applied within 7 days of advisory.

**Frontend**:
- `package-lock.json` (or `pnpm-lock.yaml`) committed. CI runs `npm audit --omit=dev`.
- Renovate/Dependabot enabled with auto-merge for patch versions only.
- Do NOT add packages from unknown maintainers; verify via Tavily before adding.

---

## §7 — Insecure Design (A06:2025 — DOWN from #4 to #6 in 2025)

- Threat-model every flow that touches: auth, file upload, external API, payment, signature, multi-tenant boundary, AI generation.
- Prefer simple, auditable flows over clever ones.
- Document trust boundaries in the module's `Application/` layer (see Hexagonal Architecture in `BACKEND-PHP/SKILL.md`).
- "Deny by default" applies to design, not just code: new feature flags default OFF.

---

## §8 — Software / Data Integrity Failures (A08:2025 + API10:2023)

**Backend**:
- File uploads: **always** validated for MIME, size, extension, and re-encoded when possible.
  - Images → `intervention/image` re-render to strip EXIF / payload.
  - PDFs → MIME check + `finfo_file()` + size cap.
- **Signed URL TTL by sensitivity**:
  - PII / personal documents → `now()->addMinutes(5)`.
  - Standard private files (invoices, receipts) → `now()->addMinutes(15)`.
  - Heavy exports (Excel/PDF batches) → `now()->addMinutes(30)`.
  - Never expose raw bucket URLs — always `Storage::disk('r2')->temporaryUrl(...)`.
- Webhooks: HMAC signature verification on every callback (Stripe, Resend, etc.). Never trust payload by source IP alone.
- Composer integrity: `composer install --no-scripts` in CI for untrusted runners.

**Frontend**:
- Subresource Integrity (`integrity="..."`) on any externally-loaded `<script>`/`<link>` (rare in Inertia, but applicable for CDN assets).
- Inertia v3 requests are CSRF-protected automatically — do NOT bypass with custom `fetch`.

---

## §9 — Logging & Alerting Failures (A09:2025 + API10:2023)

> **2025 change**: A09:2021 was renamed from "Security Logging and Monitoring Failures" to "Logging & **Alerting** Failures" — emphasis on actionable alerts, not just passive logs.

- Structured JSON logs: configure Monolog `JsonFormatter` in `config/logging.php`.
- OpenTelemetry collector for distributed traces (when applicable).
- **Spatie Activitylog** on every aggregate that mutates state. Mandatory traits: `LogsActivity` + explicit `logOnly([...])` + `logOnlyDirty()` + `dontLogEmptyChanges()`.
- Audit-worthy events (login, permission change, financial mutation, file access) → `AuditPort` in Application layer (see `BACKEND-PHP/SKILL.md`).
- Alerts: failed-login bursts, permission escalations, 5xx spikes, R2 4xx, queue failures.
- **Never log**: passwords, tokens, full credit card numbers, R2 signed URLs, Bearer tokens, `password_confirmation`.

---

## §10 — Mishandling of Exceptional Conditions (A10:2025 — NEW category)

> **2025 NEW**: A10:2025 covers happy-path bias — code that ignores edge cases, leaks stack traces in errors, denial-of-service via error conditions, and crashes on malformed/concurrent input. Especially relevant for AI-generated code.

- `app/Exceptions/Handler.php` returns sanitized JSON / Inertia error responses in production. Stack traces hidden behind `APP_DEBUG`.
- Domain exceptions: each module defines `Domain/Exceptions/` with semantic types (`OrderNotPaidException`, etc.). Mapped to HTTP codes in `Handler::render()`.
- Retries on idempotent ops only. Use `Bus::retryUntil()` or queue worker tries.
- Timeouts: every external HTTP call uses `Http::timeout(5)->retry(2, 200)`. Never unbounded.
- Concurrency: writes to shared resources use `DB::transaction()` + row-level locks (`->lockForUpdate()`) or optimistic versioning.
- Frontend: Inertia v3 `onHttpException` and `onNetworkError` per-visit callbacks must be wired in `app.ts`. `HttpError` base class for typed handling. PrimeVue `useToast()` `.add({ severity: 'error', ... })` for action-level failures.

---

## §11 — Broken Object Level Authorization (API1:2023)

- Every `findByUuid($uuid)` in repositories does:
  1. Load the aggregate.
  2. Verify `tenant_id` / `user_id` / `owner_id` matches `auth()->user()`.
  3. Throw `AuthorizationException` (HTTP 403) on mismatch.
- Policies (`app/Policies/`) for cross-cutting checks; Spatie permissions for capability checks.
- Test coverage: every show/update/delete endpoint has a Pest test verifying 403 for unauthorized owner.

---

## §12 — Broken Object Property Level Authorization (API3:2023)

- Response shaping via Spatie Data DTOs with `#[MapOutputName(SnakeCaseMapper::class)]`. **Allowlist only** — never `Model::all()` directly.
- Mass-assignment input shaping via `FormRequest::validated()` or `Data::from($request)` — never `Model::create($request->all())`.
- Eloquent `$hidden` is fallback, not primary defense.
- Frontend `PaginatedResponse<T>` uses snake_case keys mirroring backend DTOs.

---

## §13 — Broken Function Level Authorization (API5:2023)

- Admin routes live in `routes/admin.php` and ALL are wrapped by `Route::middleware(['auth', 'permission:ADMIN'])`.
- Web vs API: separate route files, separate middleware groups. No accidental admin endpoint exposure on `/api/v1`.
- Sidebar `<PermissionGuard>` mirrors backend permissions 1:1. Permission constants live in a single source: `app/Enums/Permissions.php`.

---

## §14 — Unrestricted Resource Consumption (API4:2023 + API6:2023)

**Backend**:
- Rate limiting via `RateLimiter::for(...)` per route group:
  - `auth`: 60/min
  - `login`/`register`: 5/min
  - `api`: 60/min default, raise per-user when justified
  - `export`: 10/min (Excel/PDF generation is expensive)
- Pagination MANDATORY on every list endpoint. Default 15, cap at 100 (`$perPage = min((int) $request->input('per_page', 15), 100)`).
- File upload size capped at the smallest reasonable value (e.g., 5 MB for invoices, 20 MB for classroom materials).
- Background jobs: queue `default` for fast tasks, `heavy` for exports, with separate workers and `--max-time=120` to recycle memory.
- HTTP timeouts: `Http::timeout(5)`. R2 upload `->timeout(30)`.

**Frontend**:
- Inertia `useForm` shows submit-disabled state to prevent double-submit.
- Pinia Colada `staleTime: 1000 * 60 * 2` default for list queries to avoid request storms.
- Debounce search filters at 300 ms via VueUse `useDebounceFn`.

---

## §15 — Unsafe Consumption of APIs / SSRF (folded into A01:2025 + API10:2023)

> **2025 change**: SSRF (formerly A10:2021) is now part of A01:2025 Broken Access Control. Kept as a separate baseline item here for operational clarity.

- Outbound URLs: explicit allowlist before any `Http::get($url)` where `$url` derives from user input.
- Block private IP ranges via DNS validation: `127.0.0.0/8`, `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`, `169.254.0.0/16` (cloud metadata), `::1`, `fc00::/7`, `fe80::/10`.
- Validate every third-party response: schema check (Spatie Data), status code, content-type.
- Webhook callbacks: verify origin (HMAC), validate payload shape, idempotency key required.
- Cloudflare R2: only via `Storage::disk('r2')`. Signed URLs only — never publish raw bucket URLs.

---

## §15.5 — Hardening Reference Snippets (Laravel 13)

> Practical, copy-paste reference for the most-asked-about hardening areas: **sessions**, **URL protection**, **forms**. All snippets verified against Laravel 13 docs.

### Session hardening

**`.env` (production)**:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.example.com

SESSION_DRIVER=database              # never `file` in multi-server
SESSION_LIFETIME=120                 # minutes — match your idle-timeout policy
SESSION_EXPIRE_ON_CLOSE=false
SESSION_ENCRYPT=true                 # encrypt cookie payload
SESSION_SECURE_COOKIE=true           # HTTPS only
SESSION_HTTP_ONLY=true               # no JS access
SESSION_SAME_SITE=lax                # `strict` for high-risk admin areas
SESSION_DOMAIN=.example.com
SESSION_COOKIE=__Host-vidula_session # `__Host-` prefix locks cookie to origin
```

**Login (regenerate session ID — prevents session fixation):**

```php
public function store(LoginRequest $request): RedirectResponse
{
    $request->authenticate();
    $request->session()->regenerate();           // ✅ new session ID after auth
    return redirect()->intended('/dashboard');
}
```

**Logout (invalidate + regenerate token):**

```php
public function destroy(Request $request): RedirectResponse
{
    Auth::logout();
    $request->session()->invalidate();           // ✅ kill all session data
    $request->session()->regenerateToken();      // ✅ rotate CSRF token
    return redirect('/');
}
```

**Idle timeout middleware (sliding expiration):**

```php
// app/Http/Middleware/IdleTimeout.php
public function handle(Request $request, Closure $next): mixed
{
    $idleMinutes = 30;
    if ($last = $request->session()->get('last_activity')) {
        if (now()->diffInMinutes($last) >= $idleMinutes) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->with('status', 'Session timed out.');
        }
    }
    $request->session()->put('last_activity', now());
    return $next($request);
}
```

### URL protection

**1. UUID route binding (always — no raw `id`):**

```php
Route::get('/users/{user:uuid}', [UserController::class, 'show'])
    ->whereUuid('user')                          // ✅ 404 if not a valid UUID
    ->middleware(['auth', 'permission:VIEW_USERS']);
```

**2. Signed routes (public but tamper-proof):**

```php
use Illuminate\Support\Facades\URL;

// Generate
$url = URL::signedRoute('unsubscribe', ['user' => $user->uuid]);
$temp = URL::temporarySignedRoute('invoice.download', now()->addMinutes(15), ['id' => $invoice->uuid]);

// Validate (route uses `signed` middleware)
Route::get('/unsubscribe/{user}', UnsubscribeController::class)
    ->name('unsubscribe')
    ->middleware('signed');
```

**3. Throttle every public/auth endpoint:**

```php
// bootstrap/app.php → withRouting()
RateLimiter::for('login', fn (Request $r) =>
    Limit::perMinute(5)->by($r->input('email').'|'.$r->ip())
);
RateLimiter::for('api',   fn (Request $r) =>
    Limit::perMinute(60)->by(optional($r->user())->id ?: $r->ip())
);

// Apply
Route::middleware(['throttle:login'])->post('/login', …);
Route::middleware(['throttle:api'])->prefix('api')->group(…);
```

**4. IDOR prevention (object-level authz):**

```php
// In Policy
public function view(User $actor, Order $order): bool
{
    return $actor->tenant_id === $order->tenant_id;   // ✅ tenant scope
}

// In Controller
public function show(Order $order): Response
{
    Gate::authorize('view', $order);                  // ✅ 403 if not owner
    return inertia('Orders/Show', ['order' => OrderData::from($order)]);
}
```

**5. CSP & secure headers** — see §5.

### Form protection

**1. CSRF (Inertia auto-handles XSRF token):**

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'   // ✅ sends X-XSRF-TOKEN automatically
const form = useForm({ name: '', email: '' })
form.post('/users')
</script>
```

For traditional Blade forms: `<form method="POST">@csrf …</form>` (mandatory).

**2. Honeypot (bot trap — `spatie/laravel-honeypot`):**

```php
// routes/web.php
Route::post('/contact', ContactController::class)
    ->middleware(\Spatie\Honeypot\ProtectAgainstSpam::class);
```

```vue
<template>
  <Form :resolver="resolver" @submit="onSubmit">
    <FormField name="name" v-slot="$field"><InputText v-bind="$field.props" /></FormField>
    <!-- ↓ honeypot — invisible field; real users leave it empty -->
    <input type="text" name="my_name" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" />
    <Button type="submit">Send</Button>
  </Form>
</template>
```

**3. Double validation (client Zod v4 + server Spatie Data — server is authoritative):**

```ts
// resources/js/modules/users/schemas/user.schema.ts
import { z } from 'zod'
export const userSchema = z.object({
  name:  z.string().min(1).max(255),
  email: z.string().email().max(255),
})
export type UserSchema = z.infer<typeof userSchema>
```

```vue
<script setup lang="ts">
import { Form, FormField } from '@primevue/forms'
import { zodResolver } from '@primevue/forms/resolvers/zod'
import { userSchema } from '@/modules/users/schemas/user.schema'
const resolver = zodResolver(userSchema)
</script>
```

```php
// Server (authoritative) — Spatie Data mirrors the same shape
final class UserData extends Data {
    public function __construct(
        #[Required, Max(255)] public readonly string $name,
        #[Required, Email, Max(255)] public readonly string $email,
    ) {}
}
```

**4. Rate-limit form submissions:**

```php
Route::post('/contact', ContactController::class)
    ->middleware(['throttle:6,1', \Spatie\Honeypot\ProtectAgainstSpam::class]);
```

**5. File upload validation (MIME + size + extension + re-encode):**

```php
$request->validate([
    'avatar' => ['required', 'file', 'image', 'mimes:jpg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
]);

$path = $request->file('avatar')
    ->store("avatars/{$user->uuid}", ['disk' => 'r2']);   // ✅ R2 + private by default

// Re-encode to strip EXIF / payload (intervention/image)
Image::read($request->file('avatar'))->scale(width: 1024)->save(Storage::disk('r2')->path($path));

// Return signed URL only
return Storage::disk('r2')->temporaryUrl($path, now()->addMinutes(15));
```

**6. One-time passwords (`spatie/laravel-one-time-passwords` — for confirmations & MFA):**

```php
$user->sendOneTimePassword();                          // emails 6-digit code
$ok = $user->attemptLoginUsingOneTimePassword($code);  // validates + invalidates
```

---

## §16 — LLM / GenAI Security (OWASP Top 10 for LLM Applications:2025)

> Applies WHEN any module under `src/Shared/Infrastructure/AI/` (OpenAI, Anthropic, Prism LLM adapters) is active, OR a Reverb/queue worker invokes a model provider.

| LLM Top 10:2025 | Project Mitigation |
|------------------|-------------------|
| **LLM01 Prompt Injection** | Treat user-supplied text as untrusted input even inside system prompts. Use structured templates with delimiters; never concatenate raw user input into a tool-calling prompt. |
| **LLM02 Sensitive Information Disclosure** | Strip PII before sending to upstream models (e.g., `Spatie\Pii\Redactor` or custom regex). Sign data-processing agreements with model providers. Never send R2 signed URLs or session tokens to LLMs. |
| **LLM03 Supply Chain** | Pin model versions (`gpt-4o-2024-08-06`, not `gpt-4o`). Audit fine-tuning datasets. Verify model provider's compliance posture quarterly. |
| **LLM04 Data and Model Poisoning** | Allowlist sources for RAG ingestion. Quarantine user-uploaded content used for embeddings until reviewed. |
| **LLM05 Improper Output Handling** | Treat LLM output as **untrusted user input**: never `eval()`, never `v-html`, never auto-execute SQL, never write to filesystem without validation. Render in Vue via `{{ }}` only. |
| **LLM06 Excessive Agency** | If using tool/function calling: explicit allowlist of tools per role. Human-in-the-loop required for any mutation outside read-only queries. |
| **LLM07 System Prompt Leakage** | Never include secrets in system prompts. Treat system prompt as public information. |
| **LLM08 Vector and Embedding Weaknesses** | Authorize at the row level on RAG queries (per-tenant index or filter). Sanitize embedded content. |
| **LLM09 Misinformation** | Display "AI-generated" labels to users. Add citation/source links when grounding on internal data. |
| **LLM10 Unbounded Consumption** | Per-user rate limit on LLM calls (`RateLimiter::for('llm', ...)`). Token budget per request. Circuit breaker via `Shared/Infrastructure/Resilience/CircuitBreaker`. |

**Backend implementation**: every LLM call goes through `Shared/Infrastructure/AI/AIClientInterface` which enforces: (1) PII redaction, (2) rate limiting, (3) timeout, (4) circuit breaker, (5) audit log via `AuditPort`.

---

## OWASP Baseline Checklist — 15 items (every PR)

Every pull request MUST satisfy **all** of the following or document a justified deviation in the PR description.

- [ ] **1. Access Control + SSRF (§1)** — every route guarded by `auth` + `permission:*` or Policy; UUID-bound via `->whereUuid('uuid')`; outbound HTTP allowlists private IP ranges.
- [ ] **2. Authentication (§2, §15.5)** — Argon2id, throttle `login` 5/min, `regenerate()` on login, `invalidate()`+`regenerateToken()` on logout, secure cookies, no tokens in `localStorage`.
- [ ] **3. Injection (§3)** — Eloquent + `FormRequest`/Spatie `Data` everywhere; `$fillable` allowlist; no `v-html` on user input; no shell exec with concat.
- [ ] **4. Cryptography (§4)** — TLS+HSTS forced; secrets via `.env` only; encrypted at-rest PII; cookies `secure`+`httpOnly`+`sameSite`.
- [ ] **5. Misconfiguration (§5)** — `APP_DEBUG=false`; `secure-headers` + `laravel-csp` with nonces; explicit CORS allowlist; no source maps in prod.
- [ ] **6. Supply Chain (§6)** — `composer.lock` + `package-lock.json` committed; `composer audit` + `npm audit` in CI; pinned majors.
- [ ] **7. Insecure Design (§7)** — threat-modelled for auth/upload/payment/external API/AI flows; deny-by-default feature flags.
- [ ] **8. Integrity (§8)** — uploads validated (MIME+size+ext) + re-encoded (`intervention/image`); webhook HMAC verified.
- [ ] **9. Logging (§9)** — `Log::*` never receives secrets; Spatie `LogsActivity` with explicit `logOnly([...])`.
- [ ] **10. Exceptional Conditions (§10)** — every external call has timeout + retry + circuit breaker (`Shared/Infrastructure/Resilience`); errors sanitized in prod.
- [ ] **11. Object-level authz (§11)** — Policy verifies tenant/owner before mutation; no "trust the URL".
- [ ] **12. Property-level authz (§12)** — responses filtered via Spatie `Data` allowlist; no `->toArray()` on raw model.
- [ ] **13. Function-level authz (§13)** — admin endpoints under separate `permission:*` group; tested with non-admin user.
- [ ] **14. Resource consumption (§14)** — every route has `throttle:*`; LLM calls budgeted per user; pagination required.
- [ ] **15. SSRF + R2 (§15)** — outbound HTTP allowlist; R2 access via signed URLs only.
- [ ] **+1. Sessions / URL / Forms (§15.5)** — session hardening env keys set; signed routes for public-but-protected URLs; honeypot + double validation (Zod v4 + Spatie Data) on every mutating form.
- [ ] **+2. LLM (§16)** — IF AI is in scope: PII redaction, rate limit, output treated as untrusted, model versions pinned.

> Reviewers MUST tick every applicable box before approving. Deviations require a `Justified-OWASP-Deviation:` trailer in the commit referencing this checklist item.

---

## Cross-cutting enforcement rules (always)

- **Validate every input** at the backend boundary: `FormRequest` or Spatie `Data` (backend authoritative).
- **Authenticate + authorize every route**: `auth` middleware + `permission:*` or Policy. No exceptions for "just an internal endpoint".
- **Never log secrets / tokens / PII** — Spatie `LogsActivity` with `logOnly([...])`.
- **Prefer secure defaults** over convenience: deny by default, no implicit trust, no broad CORS, no debug in prod.
- **Treat external integrations as untrusted** until validated: schema check + signature check + timeout.
- **Production errors are sanitized**: no stack traces, no SQL errors, no file paths.
- **Authorization & input validation tested** in Pest: every endpoint has 401/403/422 cases.
- **Review checklist** for every PR: this file's 15 items + the §15 checklist in `BACKEND-PHP/SKILL.md` + the §15 checklist in `FRONTEND/SKILL.md`.

---

## Cross-references

- Backend implementation rules: `.claude/BACKEND-PHP/SKILL.md` §10 (Security), §11 (Audit/Observability)
- Frontend implementation rules: `.claude/FRONTEND/SKILL.md` §14 (Frontend Security)
- Architecture (Hexagonal + DDD): `.claude/skills/ARCHITECTURE-PHP/SKILL.md`
- Simple CRUD baseline: `.claude/skills/ARCHITECTURE-PHP/SKILL-SIMPLE-CRUD.md`
- Vue/Inertia tree: `.claude/skills/ARCHITECTURE-VUE/SKILL.md`
- Workflows: `.claude/workflows/{backend|frontend}-{new|audit}.md`

---

## Numbering map — 2021 → 2025 (for legacy docs)

| 2021 | 2025 | Notes |
|------|------|-------|
| A01 Broken Access Control | A01 Broken Access Control | absorbs A10:2021 SSRF |
| A02 Cryptographic Failures | A04 Cryptographic Failures | ↓ |
| A03 Injection | A05 Injection | ↓ |
| A04 Insecure Design | A06 Insecure Design | ↓ |
| A05 Security Misconfiguration | A02 Security Misconfiguration | ↑ |
| A06 Vulnerable and Outdated Components | A03 Software Supply Chain Failures | renamed + broadened |
| A07 Identification and Authentication Failures | A07 Authentication Failures | renamed |
| A08 Software and Data Integrity Failures | A08 Software and Data Integrity Failures | unchanged |
| A09 Security Logging and Monitoring Failures | A09 Logging & Alerting Failures | renamed |
| A10 Server-Side Request Forgery | (folded into A01) | — |
| (none) | A10 Mishandling of Exceptional Conditions | NEW |
