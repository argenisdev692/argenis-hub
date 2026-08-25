---
trigger: always_on
---

# [ABSOLUTE] Non-negotiable constraints — ALWAYS apply

- **Language:** Respond in English at all times.
- **Runtime:** Laravel runs on **Laravel Herd (native Windows)**. There is NO Docker, NO Laravel Sail, NO WSL. Never emit `./vendor/bin/sail ...` — it is denied in `.claude/settings.json`.
- **CLI:** Run commands yourself, directly in this shell, from the project root:
  - Artisan → `php artisan <command>`
  - Composer → `composer <command>` (e.g. `composer dump-autoload`)
  - Formatter → `vendor/bin/pint --dirty --format agent`
  - Tests → `php artisan test --compact` (Pest runner) or `vendor/bin/pest`
    Do not ask the developer to paste output — execute and read it.
- **Tests = Pest:** All tests are written with **Pest 5** (`php artisan make:test --pest {Name}`). No PHPUnit class-style tests in new code. **At the end of every module** (generation, audit, or refactor) you MUST run the module's tests and report the real result: `php artisan test --compact --filter={Module}`, then `php artisan test --compact` for the full suite before declaring the module done. Never claim a module is complete without a green test run pasted from an actual execution.
- **Module finalization (MANDATORY — runs AFTER the backend suite is green):** a backend module is NOT done until this pipeline is executed, in this order, from the project root, with the real output read:
  ```bash
  php artisan optimize:clear             # flush config / route / view / event caches
  php artisan ide-helper:generate        # IDE helpers for facades
  php artisan ide-helper:models --write  # IDE helpers for models (writes docblocks in place)
  php artisan typescript:transform       # regenerate resources/js/generated/generated.d.ts from the Data classes
  vendor/bin/pint                        # format everything
  vendor/bin/pint --test                 # check only (CI gate — must exit 0)
  php artisan scramble:clear             # invalidate the cached spec (controllers changed)
  php artisan scramble:export            # generates api.json at the project root (export_path)
  php artisan scramble:cache             # warm the cached spec
  ```
  `vendor/bin/pint --dirty` (modified files only) stays the fast in-flight formatter while you work; the full `vendor/bin/pint` + `vendor/bin/pint --test` pair is the closing gate. Never declare a module complete without this pipeline actually executed.
- **PHP 8.5:** Follow `.claude/BACKEND-PHP/SKILL.md` §0–§3 — SINGLE SOURCE OF TRUTH for PHP 8.5 syntax.
- **TypeScript:** Strict mode enforced on ALL `.vue` / `.ts` files.
- **Security baseline:** `.claude/OWASP/SKILL.md` is **always-on**. Every backend and frontend change MUST satisfy its 15 baseline items (OWASP Top 10:**2025** + API Top 10:2023 + LLM Top 10:2025 when AI is in scope, adapted to Laravel 13 + Vue 3 + Inertia v3).
- **Context7 (MCP):** Always resolve live docs — never rely on cached training knowledge.
- **Investigate:** Run Tavily search immediately before responding, prioritizing recent/current sources (`time_range: day`, `week`, or `month`) and official docs; avoid historical years unless the task explicitly asks for them.

# [MUST] Before writing any code — read the relevant skill

| Task type                                            | Required reading                                                |
| ---------------------------------------------------- | --------------------------------------------------------------- |
| Security baseline (any backend or frontend change)   | `.claude/OWASP/SKILL.md`                                      |
| PHP / Laravel / Backend / Business                   | `.claude/BACKEND-PHP/SKILL.md`                                |
| PHP simple CRUD / 3–8 fields                         | `.claude/skills/ARCHITECTURE-PHP/SKILL-SIMPLE-CRUD.md`        |
| Vue 3 / Inertia v3 / Pinia / Pinia Colada / Frontend | `.claude/FRONTEND/SKILL.md`                                   |
| CSS / Styles / UI design tokens                      | `.claude/FRONTEND/SKILL.md` §0–§2, §9                         |
| PHP project structure / directory tree               | `.claude/skills/ARCHITECTURE-PHP/SKILL.md`                    |
| Vue / Inertia directory tree / file placement        | `.claude/skills/ARCHITECTURE-VUE/SKILL.md`                    |

> **Rule:** If a skill file covers the task, read it FIRST — no exceptions.
>
> **Solo-dev default (this project is single-developer):** the DEFAULT backend baseline is `SKILL-SIMPLE-CRUD.md` + `/backend-new-crud`. Promote to the intermediate `ARCHITECTURE-PHP/SKILL.md` ONLY when at least ONE of the following is true:
> 1. Aggregate root with domain invariants beyond simple validation (state machines, multi-step lifecycle).
> 2. ≥ 2 third-party integrations live in the module (LLM provider + payment gateway, etc.).
> 3. Cross-module orchestration with domain events that already have ≥ 1 listener.
> 4. The module has > 15 persisted fields OR composes ≥ 2 sub-entities under one aggregate.
> 5. Excel/PDF exports AND queue workers AND WebSockets co-exist in the same module.
>
> If none of the above is true, use SIMPLE-CRUD. Adding `Domain/Entities/`, `ReadRepositories/`, `AggregateRoot`, `CommandBus`, `UnitOfWork`, or the full Resilience layer to a 5-field CRUD is **overengineering** — auditors must flag it.
>
> **Optionality rules (simple CRUD)** — the DEFAULT is to skip these; create each only when its rule triggers: `Domain/Entities/` (Entity Rule) · `Persistence/Mappers/` (Mapper Rule) · `Domain/Ports/` + `Persistence/Repositories/` (Repository Rule — handlers query the Eloquent model directly by default) · `{Entity}Id` VO (Value Objects Rule) · `Tests/Unit/`. A repository port with exactly one Eloquent implementation, no decorator and no DB-free unit tests is boilerplate, not DIP.
>
> **Response shapes are Spatie Data** (`{Entity}Data::from()` / `::collect()`) for Inertia props AND JSON. There is no `Infrastructure/Http/Resources/` folder in this project — a `JsonResource` subclass in a new module is a FAIL.
>
> **`src/Shared/` is a catalogue, not a scaffold** — only the Day-1 CORE table in `ARCHITECTURE-PHP/SKILL.md` (`Shared/` Optionality Rule) is created up front. Every other adapter (RabbitMQ, SQS, Pusher, S3, AI, Mail, Export, Observability, Resilience, UnitOfWork) waits for its trigger. An adapter with no consumer is dead code.
>
> **Total skill files:** 6 (OWASP + BACKEND-PHP + FRONTEND + 2× ARCHITECTURE-PHP + ARCHITECTURE-VUE) + this router. No redundancy.

# [MUST] CSS / Styles

- Follow `FRONTEND/SKILL.md` §0–§2 strictly.
- NEVER hardcode hex, `bg-red-600`, or `bg-[#hex]`. Use `var(--token)` only.
- All tokens defined in `resources/css/globals.css` (imported by `app.css`).

---

# [MUST] Vue 3 / TypeScript

- Follow `FRONTEND/SKILL.md` strictly.
- Use `<script setup lang="ts">` on every `.vue` file. No Options API in new code.
- **ZERO `any`** — no `any`, no `as any`, no implicit `any`, no `@ts-ignore`, no `@ts-expect-error` without a linked issue. When a type is genuinely unknown use `unknown` + a narrowing guard. No hardcoded colors in components.
- **Backend types are GENERATED, never hand-written.** `php artisan typescript:transform` (spatie/laravel-typescript-transformer v3) emits `resources/js/generated/generated.d.ts` from the Spatie `Data` classes and enums; that file is the single source of truth for Inertia page props and every API/DTO shape. Run it the FIRST time a frontend module is created and again after ANY backend DTO/enum change (it is part of the module finalization pipeline). Hand-writing a TypeScript interface that mirrors a backend `Data` class is a FAIL — it is exactly how `any` and silent drift creep in. `MapOutputName(SnakeCaseMapper::class)` is honored by the generator, so the snake_case contract is preserved automatically.
- Every page declares its layout via `defineOptions({ layout })`. State always explicitly typed via `ref<T>()`, `defineProps<T>()`, `defineEmits<T>()`.
- **Vue 3.5 APIs are mandatory, not optional** (floor is Vue 3.5 stable — see `FRONTEND/SKILL.md` §4.5): `useTemplateRef<T>('name')` for template refs · `useId()` for every generated `id` / `aria-*` link in a reusable component · destructured props with defaults (**`withDefaults` is banned**; use a getter `() => prop` when passing a destructured prop as a reactive source) · `defineModel<T>()` for every custom `v-model` · `onWatcherCleanup()` in any watcher starting a timer/request/subscription · `defineSlots<T>()` for scoped slots. Vue 3.6 Vapor mode stays BANNED until GA.
- Server state → Pinia Colada (`useQuery` / `useMutation`). Client state → Pinia setup stores. Never mix the two. Every query declares BOTH `staleTime` and `gcTime`; paginated lists use `placeholderData: (prev) => prev`; shared filter/draft state uses `defineQuery` / `defineMutation`; optimistic rollbacks are guarded (`context.optimistic === queryCache.getQueryData(key)`) — see `FRONTEND/SKILL.md` §6.
- All UI primitives consumed through **PrimeVue v4 unstyled + Volt** components under `resources/js/volt/` (50+ pre-styled UI primitives, code-ownership via `npx volt-vue add`, Tailwind v4 pass-through, WCAG AA compliant, responsive out-of-the-box, TypeScript) — no other UI library is used. PrimeVue is registered with `{ unstyled: true }` (no theme preset). Data table = PrimeVue `DataTable` server-side `:lazy`. Toasts = PrimeVue `Toast` + `useToast()`. Forms = `@primevue/forms` + Zod v4. Icons = `primeicons` (`pi pi-*`).
- Theme toggling uses ONLY the `.dark` class on `<html>` (light mode = absence of class). Never `data-theme`, never `.light`. The `dark:` variant is bound to `&:is(.dark *)` via `@custom-variant` in `app.css` (unstyled mode has no PrimeVue `darkModeSelector`). `useThemeStore` uses `useColorMode({ modes: { light: '', dark: 'dark' } })` and is the single source of persistence (no `pinia-plugin-persistedstate` on top).

---

# [MUST] Laravel / PHP

- Follow `BACKEND-PHP/SKILL.md` strictly.
- No business logic in Controllers. Artisan/Composer run natively via Herd (`php artisan`, `composer`) — never through Sail/Docker.
- Web routes = primary (Inertia + session). API routes = secondary (mobile/Sanctum only).
- Every input validated by `FormRequest` or Spatie `Data`. Every model declares `$fillable`.
- **N+1 prevention is mandatory** — `Model::shouldBeStrict()` enabled in `AppServiceProvider`; every list query uses `with('rel:id,fk,col')` with explicit columns; counts/sums via `withCount()`/`withSum()`/`withAvg()`; combined relation+filter via `withWhereHas()`. See `BACKEND-PHP/SKILL.md` §4.1.
- **Audit trail available**: `LogsActivity` (spatie/laravel-activitylog ^5.1) is enabled by default on every aggregate Eloquent model with explicit `logOnly([...])` + `logOnlyDirty()` + `dontLogEmptyChanges()`. Never `logAll()`, never log secrets/PII. **v5 namespaces/API — the v4 forms fatal at runtime**: `Spatie\Activitylog\Models\Concerns\LogsActivity` (NOT `…\Traits\LogsActivity`), `Spatie\Activitylog\Support\LogOptions` (NOT `Spatie\Activitylog\LogOptions`), and `dontLogEmptyChanges()` (NOT `dontSubmitEmptyLogs()`).
- **Bulk operations**: when the UI exposes row selection, the module ships BOTH `BulkDelete{Entity}Handler` AND `BulkRestore{Entity}Handler` (soft delete + soft restore over a UUID array), with matching `POST /bulk-delete` and `POST /bulk-restore` routes guarded by `permission:DELETE_*` / `permission:RESTORE_*`.

---

# [MUST] Security (always-on, see `.claude/OWASP/SKILL.md`)

- Authorize every route: `auth` middleware + Spatie `permission:*` or Policy.
- Frontend UI authorization uses `permissions`, never `roles`.
- UUID-bound routes use `->whereUuid('uuid')`.
- Argon2id password hashing (`config/hashing.php`).
- CSP via Spatie `laravel-csp`; security headers via `bepsvpt/secure-headers`.
- File uploads validated (MIME + size + extension); R2 access via signed URLs only.
- Spatie `LogsActivity` with explicit `logOnly([...])` — never `logAll()`, never log secrets/PII.
- `APP_DEBUG=false` in production. No stack traces, no SQL errors, no file paths leaked.

---

# [MUST] File editing & env handling

- For file administration and edits, try filesystem MCP tools first.
- If filesystem MCP does not work, use `write_to_file` only for brand-new files.
- Reserve `apply_patch` only for edits to existing files.
- For `.env` and `.env.example`, if direct modification is not possible, provide only the required environment variable keys/placeholders and continue working.

---

# [SHOULD] General quality

- Mobile-first on every UI component.
- `font-family: var(--font-sans)` everywhere.
- Prefer descriptive names over comments.
