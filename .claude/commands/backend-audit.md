---
description: Audits a Laravel 13 / PHP 8.5 module against architecture, security, audit & test rules. Generates a FAIL/PASS checklist, auto-fixes violations, then re-verifies until 100% score.
---

# BACKEND AUDIT AGENT — PHP 8.5 + Laravel 13

## PHASE 0 — CLASSIFY THE MODULE

Before starting the audit, you MUST classify the module complexity:

- **Simple CRUD baseline**: one aggregate, around 3 to 8 fields, standard CRUD + restore, low business complexity, no files/exports/queues/WebSockets/external integrations unless explicitly requested
- **Intermediate baseline**: multi-step business flow, richer invariants, integrations, files, exports, events, or cross-module orchestration

Then read the correct architecture file:

- Simple CRUD → `.claude/skills/ARCHITECTURE-PHP/SKILL-SIMPLE-CRUD.md`
- Intermediate / complex → `.claude/skills/ARCHITECTURE-PHP/SKILL.md`

### Classification Rules

Classify as **Intermediate** immediately if any of these are true:

- More than one aggregate root or multiple coordinated entities inside the same use-case flow
- Files, signatures, media processing, cloud storage, or storage adapters are required
- Excel/PDF exports are part of the declared module scope
- Queue workers, listeners, domain events, async processing, notifications, or scheduled side-effects are required
- Reverb/WebSockets or real-time broadcasting are part of the module scope
- External APIs, third-party SDKs, or cross-module orchestration are required
- Multi-step workflow, approval flow, state machine, or non-trivial lifecycle transitions exist
- Dedicated read models, projections, read repositories, or denormalized query paths are justified

Classify as **Simple CRUD** only when all of these are true:

- One main entity / one aggregate root
- Around 3 to 8 persisted fields
- Standard list, show, create, update, delete, restore flow
- Low business complexity with shallow invariants
- No requirement for files, exports, queues, events, WebSockets, integrations, or orchestration

Tie-breaker rule:

- If there is doubt, prefer **Intermediate** only when there is a concrete requirement already present in the module or explicitly requested by the user
- If the extra layers exist only because of template inertia, classify as **Simple CRUD** and flag the extra layers as overengineering

The audit output MUST start by stating:

- Selected baseline: `Simple CRUD` or `Intermediate`
- 2 to 5 concrete reasons taken from the actual module files or request scope
- Which architecture skill was used as the audit baseline

## PHASE 1 — AUDIT (produce checklist)

Before starting the audit, you MUST:

1. Read `.claude/BACKEND-PHP/SKILL.md` — single source of truth for backend rules
2. Read `.claude/OWASP/SKILL.md` — the always-on security baseline (15+1 items mapped to OWASP Top 10:**2025** + API Top 10:2023 + LLM Top 10:2025 when AI is in scope)
3. Call context7 to resolve current docs for: Laravel 13, filesystem / storage disks, events & listeners, Spatie Laravel Data 4.x, Spatie Permission 8.x, Spatie Activitylog, Pest 5
4. Call tavily to verify the latest stable versions of all packages, prioritizing recent/current sources (`time_range: day`, `week`, or `month`) and official docs; avoid historical years unless the task explicitly asks for them

Then analyze the indicated module against these rules.
For each item mark ✅ PASS, ❌ FAIL (with file:line and brief description), ⚠️ WARN, or ➖ N/A (with reason when the item is outside the module scope).

### Required Checklist

**PHP 8.5 — applies to ALL layers including simple CRUD AND exports (see `BACKEND-PHP/SKILL.md` §5.1 + §8)**

- [ ] `declare(strict_types=1)` in EVERY .php file
- [ ] `final readonly class` on every stateless DI class (Handlers, VOs, Adapters, Controllers, ExcelExport, PdfExport)
- [ ] Pipe `|>` used in sequential transformations (no nested calls) — MANDATORY in ExportTransformer + any normalization chain inside Handlers
- [ ] `clone($obj, [...])` in wither methods (no manual `get_object_vars()` boilerplate)
- [ ] `array_first()` / `array_last()` (never `reset()`/`end()`)
- [ ] `#[\NoDiscard]` on transformer statics, Command Handlers returning IDs, sanitization methods
- [ ] `Uri\Rfc3986\Uri` or `Uri\WhatWg\Url` (never `parse_url()`)
- [ ] `FILTER_THROW_ON_FAILURE` in `filter_var` validations inside Value Objects
- [ ] `match` expression over chained `if/elseif` (Controller `expectsJson()` branch, ExportController format branch, state transitions)
- [ ] Constructor property promotion on every class with injected dependencies
- [ ] `casts()` **method** (Laravel 11+) on every Eloquent model — NEVER the legacy `$casts` array
- [ ] PSR-12: explicit return type on EVERY method
- [ ] Simple CRUD is NOT an exception — same 8.5 features apply

**Architecture (selected baseline)**

- [ ] Audit baseline was classified correctly before judging complexity-sensitive items
- [ ] Audit report states the selected baseline, concrete evidence, and the skill file used for the baseline
- [ ] Module lives in `src/Modules/{Name}/` with Domain / Application / Infrastructure
- [ ] Hexagonal Architecture respected: Domain/Application depend on ports, Infrastructure implements adapters
- [ ] SOLID respected overall, especially dependency inversion through ports and cohesive interfaces
- [ ] SRP respected: handlers, services, adapters, listeners, policies, and mappers each have one clear reason to change
- [ ] Domain imports nothing from Infrastructure or Laravel
- [ ] Mapper is the ONLY contact point between Domain and Eloquent WHEN it exists. Mapper is OPTIONAL — mark `➕ N/A` when Eloquent casts/accessors cover the translation and the criteria in the Mapper Optionality Rule are not met (do NOT mark FAIL)
- [ ] Domain Entity is OPTIONAL — mark `➕ N/A` when the Eloquent model is 1:1 with the aggregate, no invariants live in Domain, and no methods exist beyond getters (Entity Optionality Rule)
- [ ] ReadRepository is OPTIONAL — mark `➕ N/A` when query handlers reuse the write Eloquent Repository and there is no projection / read model / external read store (ReadRepository Optionality Rule)
- [ ] **Simple CRUD only** — Repository port + Eloquent repository are OPTIONAL: mark `➕ N/A` when handlers query the Eloquent model directly and none of the Repository Optionality Rule triggers apply (2nd implementation, decorator, non-Eloquent source, DB-free handler unit tests). Conversely, a port with exactly ONE Eloquent implementation, no decorator and no DB-free unit tests is **overengineering — FAIL**
- [ ] **Simple CRUD only** — `{Entity}Id` Value Object is OPTIONAL: mark `➕ N/A` when the model uses `HasUuids` + `->whereUuid('uuid')` and the ID carries no invariant of its own
- [ ] **Intermediate only** — repository port in `Domain/Ports/` is MANDATORY (DIP mechanism). If the module was just promoted from simple CRUD, extracting the port is a promotion task, not a pre-existing FAIL
- [ ] Response shapes are Spatie Data (`{Entity}Data::from()` / `::collect()`). NO `Infrastructure/Http/Resources/` folder, no `JsonResource` / `ResourceCollection` subclass anywhere in the module — **FAIL** if present (Response Shape Rule)
- [ ] `src/Shared/` contains no adapter without a live consumer — every path outside the Day-1 CORE table must have its trigger met (`ARCHITECTURE-PHP/SKILL.md` → `Shared/` Optionality Rule). An opt-in path existing without its trigger is **overengineering — FAIL**; cite the unsatisfied trigger row
- [ ] Controllers stay thin: no business rules, orchestration delegated to handlers/services
- [ ] Controller fusion is allowed: a single resourceful `{YourEntity}Controller` serving Inertia + JSON is PASS by default. Splitting into `Api/` + `Web/` is only required when the criteria of the Controller Fusion Rule are met
- [ ] DDD present: ubiquitous language, domain invariants and business rules live in Domain layer
- [ ] Value Objects are used where business concepts deserve invariants, are `readonly`, and validate themselves
- [ ] For simple CRUD modules, primitive fields are not wrapped in speculative Value Objects without a real invariant
- [ ] For simple CRUD modules, application structure stays flat enough to trace list/create/update flows quickly
- [ ] For simple CRUD modules, folders like `Storage`, `Queue`, `WebSocket`, `Export`, `ExternalServices`, listeners, or projections do not exist unless the scope truly requires them
- [ ] For intermediate modules, optional folders (`Domain/Entities/`, `Persistence/Mappers/`, `Persistence/ReadRepositories/`, `WebSocket/`, `Queue/`, `Storage/`, `ExternalServices/`, `Application/Listeners/`, `Domain/Events/`, `Domain/Services/`) only exist when their concrete use-case is documented; their absence is `➕ N/A`, not FAIL
- [ ] DTOs extend `Spatie\LaravelData\Data` and are NOT `readonly`
- [ ] Commands/Queries follow strict CQRS separation (basic or advanced), with side-effect free queries
- [ ] `Application/Commands/` and `Application/Queries/` are FLAT (one Handler per file). Subfolders per use-case are flagged as overengineering unless a use-case requires multiple supporting classes (Saga, Strategy, sub-handlers)
- [ ] Repository: port defined in Domain, implementation in Infrastructure
- [ ] Event Driven Architecture is present when business flow requires it; otherwise marked `N/A` instead of forced
- [ ] For intermediate modules, extra adapters, listeners, policies, or subfolders are justified by concrete use-cases and not by template copy-paste
- [ ] KISS preserved: no speculative abstraction or generic layers without a real second use-case
- [ ] DRY preserved: duplicated business or mapping logic is centralized in the correct layer
- [ ] Clean Code preserved: naming, method size, branching, and exception semantics remain readable
- [ ] DX preserved: directory layout, naming, contracts, and errors are predictable for maintainers
- [ ] Code Review readiness: no dead code, hidden side effects, commented-out blocks, or convention drift
- [ ] ServiceProvider registered in `bootstrap/providers.php`

**Storage / File Administration**

- [ ] If the module manages files, it uses a dedicated Storage Port / adapter instead of raw `Storage` calls in Domain/Application
- [ ] `config/filesystems.php` default disk was reviewed against the project policy
- [ ] `.env.example` and storage-related config do not drift from the intended default disk policy
- [ ] Cloudflare R2 rule present when cloud file storage is required: `r2` disk configured or equivalent S3-compatible adapter documented
- [ ] If project policy requires R2 by default, `FILESYSTEM_DISK` / config fallback align to `r2`; otherwise deviation is explicitly justified
- [ ] File adapters use `Storage::disk('r2')` or configured disk from Infrastructure only
- [ ] Uploaded files/signatures/images are validated before persistence
- [ ] Public/temporary URLs are generated through adapter methods, not hardcoded string concatenation

**Design & Code Quality**

- [ ] SOLID respected overall, with explicit focus on SRP and dependency inversion
- [ ] SRP: each Handler / Service / Adapter has one clear responsibility
- [ ] KISS: no unnecessary abstraction, indirection, or speculative generic layers
- [ ] DRY: duplicated business logic extracted to the correct layer
- [ ] Clean Code: clear naming, small methods, minimal branching complexity
- [ ] DX: module is easy to navigate, naming/routes/contracts are predictable, errors are descriptive
- [ ] Repository Pattern is used consistently for aggregate persistence
- [ ] Code Review readiness: no dead code, debug leftovers, commented-out blocks, or inconsistent conventions

**Audit / Observability (§11)**

- [ ] Model uses `LogsActivity` trait with explicit `logOnly([...])`
- [ ] `logOnlyDirty()` + `dontLogEmptyChanges()` both present
- [ ] `AuditPort` called manually in CommandHandlers only when meaningful business actions justify explicit audit beyond model lifecycle logging
- [ ] NEVER `logAll()`, never log passwords/tokens/PII
- [ ] Structured logging via OTEL (never bare `Log::error('string')`)

**Security (§10)**

- [ ] No raw SQL with user input
- [ ] No `unserialize()` on external input
- [ ] `->whereUuid('uuid')` on UUID routes
- [ ] Permissions defined: `VIEW_X`, `CREATE_X`, `UPDATE_X`, `DELETE_X`, `RESTORE_X`
- [ ] `DELETE_X` guards both `delete` and `bulk-delete`; `RESTORE_X` guards both `restore` and `bulk-restore`
- [ ] `forgetCachedPermissions()` called BEFORE creating permissions

**Bulk Operations + Filters + N+1 + Audit**

- [ ] When the UI exposes row selection, `BulkDelete{Entity}Handler` AND `BulkRestore{Entity}Handler` BOTH exist (paired). Shipping bulk-delete without bulk-restore is FAIL — it creates a UX dead-end.
- [ ] `BulkDelete{Entity}Request` + `BulkRestore{Entity}Request` validate the UUID array is non-empty and capped (`max:500`).
- [ ] Routes `POST /bulk-delete` and `POST /bulk-restore` exist symmetrically under the admin data group.
- [ ] `{Entity}FilterData` follows the canonical shape in `BACKEND-PHP/SKILL.md` §5.2 (`search`, `status`, `date_from`, `date_to`, `sort_field`, `sort_order`, `page`, `per_page`) with `#[BeforeOrEqual('date_to')]` / `#[AfterOrEqual('date_from')]` validation.
- [ ] Single Eloquent scope `scopeApplyFilters({Entity}FilterData $f)` used by BOTH `List{Entities}Handler` and Excel/PDF exports — no duplicated `->when()` chains.
- [ ] Date range uses `whereBetween` with `CarbonImmutable::parse(...)->startOfDay()` / `->endOfDay()` for inclusive boundaries; single-bound (only `date_from` OR only `date_to`) supported via separate branches.
- [ ] Composite DB index covering `[deleted_at, created_at]` or `[status, created_at]` exists for the filter pattern.
- [ ] N+1 prevention verified per `BACKEND-PHP/SKILL.md` §4.1: `Model::shouldBeStrict()` enabled, list queries use `with('rel:id,fk,col')`, aggregates use `withCount`/`withSum`/`withAvg`.
- [ ] Activitylog availability verified: `LogsActivity` trait present on aggregate models with explicit `logOnly([...])` + `logOnlyDirty()` + `dontLogEmptyChanges()`.

**Exports — see `BACKEND-PHP/SKILL.md` §8 (single source of truth, no duplicated rules)**

- [ ] Export flow follows `BACKEND-PHP/SKILL.md` §8 exactly (spatie/simple-excel streaming via `->lazy()->each(...)`, DomPDF + Blade in `resources/views/exports/pdf/`, ExportTransformer with `|>` chain, `Status` derived ONLY from `deleted_at` as `Active`/`Suspended` — never `Inactive`, `/export` route BEFORE `/{uuid}`, shared FilterDTO for Excel + PDF)

**Tests (§7)**

- [ ] Feature — full HTTP CRUD flow for the declared module scope (MANDATORY)
- [ ] Unit/Domain — OPTIONAL: required only when custom Value Objects or domain invariants exist; absent VOs → `➕ N/A`, not FAIL
- [ ] Unit/Application — OPTIONAL: required only when handler logic is non-trivial (orchestration, branching, multi-step); plain CRUD orchestration → `➕ N/A`
- [ ] Integration — OPTIONAL: required only when Mapper, custom casts, scopes, or persistence rules are non-trivial; plain Eloquent persistence → `➕ N/A`
- [ ] `Tests/Unit/` directory absence is `➕ N/A` when no VOs / domain invariants / mappers / pure domain services exist (Tests/Unit Optionality Rule)
- [ ] Tests cover critical business invariants for Value Objects / Domain rules / storage flows when applicable

**OpenAPI (§9) — only if the module exposes API endpoints**

- [ ] `dedoc/scramble` installed and configured in `AppServiceProvider`
- [ ] Every API controller method has an explicit return type-hint (`: JsonResponse`, `: BinaryFileResponse`, etc.)
- [ ] FormRequests injected for all write endpoints (Scramble reads `rules()`)
- [ ] Spatie Data used for response shapes (Scramble reads property types)
- [ ] No manual `@OA\*` annotations present (conflict with Scramble auto-generation)

---

## PHASE 2 — FIX

For each ❌ FAIL: apply the minimal fix following the exact rules in `.claude/BACKEND-PHP/SKILL.md` and the security baseline in `.claude/OWASP/SKILL.md`.
Use context7 to confirm the correct package API if unsure.
Use tavily to look up current best practices or CVEs if a security item is flagged.
When fixing storage concerns, verify Laravel filesystem disk usage, review `config/filesystems.php` + `.env.example` alignment, and keep all cloud storage concerns inside Infrastructure adapters.
When fixing architecture concerns, prefer the smallest change that restores hexagonal boundaries, SRP, DRY, and CQRS separation.
If the module was misclassified, correct the baseline first instead of forcing simple CRUDs into intermediate architecture or stripping necessary complexity from intermediate modules.

---

## PHASE 3 — VERIFICATION CHECKLIST

After all fixes, re-run EVERY item from Phase 1. Expected result:

✅ ALL items PASS
📊 Score: X/Y items — target 100%

If any item remains ❌, repeat Phase 2 → Phase 3 until perfect score.

---

## PHASE 4 — RUN THE TESTS (Pest · MANDATORY)

A 100% checklist score is NOT an audit pass. The audit closes only after the module's **Pest 5** suite runs green.
Runtime is **Laravel Herd (native Windows)** — no Docker, no Sail, no WSL. Run these yourself in this shell, from the project root, and read the real output:

```bash
composer dump-autoload
php artisan migrate
php artisan db:seed --class=RolePermissionSeeder
vendor/bin/pint --dirty --format agent
php artisan test --compact --filter={Module}
php artisan test --compact
```

Rules:

- Missing coverage is itself a FAIL: add the missing Pest tests with `php artisan make:test --pest {Name}` (Pest syntax — `it()` / `expect()`, never PHPUnit class-style) and re-run.
- If a test fails, fix the code (or the assertion when the expectation itself is wrong) and re-run until green.
- NEVER report the audit as passed on a red suite or on a suite you did not actually execute — quote the real pass/fail counts.

---

## PHASE 5 — FINALIZE (MANDATORY · runs only once Phase 4 is green)

The audit does NOT close until this pipeline is executed, in this order, from the project root, with the real output read:

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

Rules:

- `vendor/bin/pint --dirty` (modified files only) is the fast in-flight formatter used while fixing violations; the full `vendor/bin/pint` + `vendor/bin/pint --test` pair is the closing gate here.
- A non-zero exit from `vendor/bin/pint --test` is itself a FAIL — run `vendor/bin/pint` again and re-check until clean.
- `ide-helper:models --write` edits model docblocks in place — re-run `php artisan test --compact --filter={Module}` if it touched anything, and confirm still green.
- `scramble:clear` MUST precede `scramble:export`/`scramble:cache`, otherwise the exported `api.json` is stale.
- Report the audit as passed only after every command above has actually run.
