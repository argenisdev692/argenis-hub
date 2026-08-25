---
description: Generates a Vue 3 + Inertia.js v3 + Pinia + Pinia Colada + PrimeVue DataTable CRUD module following tokens, a11y, architecture & security rules. 5-line summary.
---

# FRONTEND NEW MODULE AGENT — Vue 3 + Inertia.js v3 + Pinia Colada + PrimeVue v4 unstyled + Volt + Tailwind v4

## PHASE 0 — GENERATE THE BACKEND TYPES (FIRST RUN · MANDATORY)

TypeScript types for Inertia page props and every DTO are **generated from the backend**, never hand-written (`FRONTEND/SKILL.md` §13). Do this BEFORE writing a single `.vue` file.

**One-time setup** — verify `app/Providers/TypeScriptTransformerServiceProvider.php` exists. If it does not:

```bash
php artisan vendor:publish --tag=typescript-transformer-provider
```

Then configure it for THIS project and register it in `bootstrap/providers.php`:

```php
<?php

namespace App\Providers;

use Spatie\LaravelTypeScriptTransformer\LaravelData\LaravelDataTypeScriptTransformerExtension;
use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider as BaseProvider;
use Spatie\TypeScriptTransformer\Formatters\PrettierFormatter;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter;

class TypeScriptTransformerServiceProvider extends BaseProvider
{
    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        $config
            // MANDATORY: laravel-data support (Data classes, Lazy/Optional unwrapping,
            // MapOutputName/SnakeCaseMapper, PaginatedDataCollection aliases)
            ->extension(new LaravelDataTypeScriptTransformerExtension())
            ->transformer(EnumTransformer::class)
            // MANDATORY: modules live in src/, NOT app/ — the stub default misses them
            ->transformDirectories(app_path(), base_path('src'))
            ->outputDirectory(resource_path('js/generated'))
            ->writer(new GlobalNamespaceWriter('generated.d.ts'))
            ->formatter(PrettierFormatter::class);
    }
}
```

**Every run:**

```bash
mkdir -p resources/js/generated          # required: the transformer does NOT create it
php artisan typescript:transform         # → resources/js/generated/generated.d.ts
```

Rules (each one verified against the installed v3.3 — do not "simplify" them):

- The output directory **must already exist**. The transformer throws `Output directory "…" does not exist. Please create it before running the transformer.` instead of creating it.
- `transformDirectories(app_path(), base_path('src'))` is NOT optional — this project autoloads `Modules\` → `src/Modules/` and `Shared\` → `src/Shared/`. The published stub only scans `app_path()` and emits ZERO module DTOs.
- Do NOT reference `Spatie\LaravelData\Support\TypeScriptTransformer\DataTypeScriptTransformer` — that class targets laravel-typescript-transformer **v2** and extends a `DtoTransformer` that does not exist in the installed v3. Use `LaravelDataTypeScriptTransformerExtension` instead.
- **No `#[TypeScript]` attribute is needed anywhere.** `DataClassTransformer::shouldTransform()` matches any `BaseData` implementor, and the registered `EnumTransformer` picks up every backed enum in the scanned directories. Consequence: infrastructure-only enums (e.g. `CircuitBreakerState`) land in the output too — harmless, but do not "fix" it by adding attributes.
- **`resources/js/generated/` must NOT be gitignored.** `PrettierFormatter` shells out to `npx prettier --write`, and Prettier 3 reads `.gitignore` as a default ignore-path — a gitignored output directory makes the formatter exit 0 and silently do nothing. The generated types are committed so CI can type-check without PHP.
- If the module's backend DTOs do not exist yet, STOP and run `/backend-new-crud` (or `/backend-new`) first. A frontend module cannot be typed against DTOs that have not been written.

## PHASE 1 — PLAN NEW CRUD OR MODULE (produce checklist)

Before writing any code, you MUST:

1. Read `.claude/FRONTEND/SKILL.md` and `.claude/skills/ARCHITECTURE-VUE/SKILL.md`
2. Read `.claude/OWASP/SKILL.md` — the always-on security baseline (15+1 items mapped to OWASP Top 10:**2025** + API Top 10:2023 + LLM Top 10:2025 when AI is in scope)
3. Call context7 to resolve current docs for: Vue 3 (Composition API + `<script setup>`), Inertia.js v3 (`@inertiajs/vue3`), Pinia v4 (`/vuejs/pinia`), Pinia Colada (`/posva/pinia-colada`), PrimeVue v4 + Volt (`/websites/primevue` or `/primefaces/primevue`), `@primevue/forms`, `tailwindcss-primeui`, Zod v4, Tailwind CSS v4
4. Call tavily to verify latest stable versions of all frontend packages, prioritizing recent/current sources (`time_range: day`, `week`, or `month`) and official docs; search `site:primevue.org` and `site:volt.primevue.org` for every Volt/PrimeVue primitive used in this module
5. Reason hard about the module structure, token usage, layer boundaries, DataTable lazy shape, composable selection, permission guards, animation constraints, and TypeScript interfaces — before generating a single file

Then generate the indicated module following these rules.
For each generated item mark ✅ DONE, ❌ SKIPPED (with reason) or ⚠️ WARN.

### Required Generation Checklist

**Styles & Tokens (§0, §1)**

- [ ] Zero hex values or Tailwind palette names in components — only `tailwindcss-primeui` semantic utilities (`bg-surface-100`, `text-color`, `text-muted-color`, `bg-primary`, …) or `var(--token)` for non-semantic project tokens
- [ ] No `bg-red-600`, `text-gray-500`, or `bg-[#hex]` anywhere
- [ ] All new core tokens added to `globals.css` FIRST, with the `tailwindcss-primeui` bridge updated and the `.dark` override added in the same commit when contrast flips
- [ ] Soft-deleted rows use `--deleted-row-bg`, `--deleted-row-border`, `--deleted-row-opacity` applied via DataTable `rowClass`
- [ ] Tag/severity tones resolve through the PrimeUI bridge (`--p-green-500`, `--p-red-500`, …) — never inline hex
- [ ] Cards use the Volt `<Card>` from `@/volt/Card.vue` — no custom `.card` class
- [ ] Buttons use the Volt `<Button>` from `@/volt/Button.vue` with `severity`/`outlined`/`text`/`link` props — no `.btn-*` classes, no inline bg styles
- [ ] Toast severity colors sourced from the PrimeUI bridge (no hardcoded hex in `volt/Toast.vue` pass-through)
- [ ] `tailwindcss-primeui` semantic bridge present in `globals.css` (`--p-surface-*`, `--p-primary-*`, `--p-text-*`, `--p-content-*`, `--p-highlight-*`, severity `--p-*-500`, `--p-border-radius`)
- [ ] `app.css` imports `tailwindcss` + `tailwindcss-primeui` + `globals.css`; declares `@custom-variant dark (&:is(.dark *));` and the `@theme inline` block
- [ ] `.dark` override block present in `globals.css` (NOT `[data-theme="dark"]`, NOT `.light`)

**Accessibility (§2)**

- [ ] `@media (prefers-reduced-motion: reduce)` present in `globals.css`
- [ ] `:focus-visible` ring uses `var(--accent-primary)`, not `:focus`
- [ ] Icon-only Volt `<Button>`s have `aria-label` or `:title`
- [ ] Modals (Volt `Dialog`) close on `Escape`, confirm button receives `autofocus`
- [ ] No element flashes > 3×/sec — all animation `duration ≤ 0.4s`
- [ ] Labels use `--text-secondary`, never `--text-disabled`

**Architecture / Layer Rules (§3)**

- [ ] `common/` imports nothing from `modules/` or `Pages/`
- [ ] `modules/` imports nothing from `Pages/`
- [ ] `Pages/` is the ONLY layer using `usePage()` and `useForm()`
- [ ] `volt/` imports nothing from `modules/`, `Pages/`, or `common/`
- [ ] All UI imports go through `@/volt/*` — no other UI library is installed or imported
- [ ] No `components.json` (shadcn artifact); `@` Vite alias points at `resources/js/`; `cn()` exported from `resources/js/lib/utils.ts`
- [ ] File naming: components `PascalCase.vue`, composables `camelCase.ts`, dirs `kebab-case/` or `lowercase/`
- [ ] Inertia pages live in `Pages/{Module}/Index.vue`, `Show.vue`, `Create.vue`, `Edit.vue`
- [ ] Pages use `defineOptions({ layout: AppLayout })` — no manual layout wrapping
- [ ] State ownership is clear: server state in Pinia Colada, client UI state in Pinia, page props from `usePage()`
- [ ] KISS / DRY / Clean Code / SRP applied to components, composables, helpers

**State Management (Pinia + Pinia Colada)**

- [ ] Pinia stores defined with `defineStore('id', setupFn)` (setup style) — no Options-style stores
- [ ] Pinia stores live in `modules/{context}/stores/use{Context}UiStore.ts`
- [ ] Pinia state is explicitly typed with `ref<T>(initial)`
- [ ] Components destructure state via `storeToRefs`, actions via plain destructuring
- [ ] No duplication between Pinia stores and Pinia Colada server state
- [ ] `persist` (or `pinia-plugin-persistedstate`) stores only non-sensitive UI preferences
- [ ] No oversized global store introduced without a concrete DX/complexity justification

**Inertia.js v3 (§5)**

- [ ] `Link` + `router` imported from `@inertiajs/vue3` (never `Inertia.visit()`)
- [ ] `Link` uses `prefetch` on primary nav items
- [ ] Never calling `/api/*` routes from Inertia pages
- [ ] `useRemember` used for filter state persistence
- [ ] All pages have `<Head title="..." />` + `defineOptions({ layout })`
- [ ] No Axios installed — relies on Inertia v3's built-in XHR client
- [ ] `Inertia::optional()` used (never deprecated `Inertia::lazy()`)
- [ ] `router.cancelAll()` used (never deprecated `router.cancel()`)
- [ ] If a non-navigation HTTP call is needed → `useHttp` from `@inertiajs/vue3`
- [ ] `app.ts` registers Pinia + Pinia Colada + PrimeVue (`{ unstyled: true }`) + ToastService + ConfirmationService + tooltip directive — no styled PrimeVue theme preset

**Pinia Colada (§6) — Server State**

- [ ] All network composables under `modules/{context}/composables/` use `useQuery` or `useMutation` from `@pinia/colada`
- [ ] `key` is a function returning `[entityName, ...filters]`, with `toValue(...)` for reactive params
- [ ] `queryCache.invalidateQueries({ key: ['{entities}'] })` after every successful write
- [ ] Optimistic mutations: `onMutate` returns BOTH `previous` and `optimistic`; `onError` rolls back ONLY when `context.optimistic === queryCache.getQueryData(key)`; `onSuccess` reconciles with the server payload
- [ ] Every paginated list query sets `placeholderData: (previousData) => previousData` — no manual "last page" ref
- [ ] Shared filter/draft state uses `defineQuery` / `defineMutation` — never a Pinia store holding server state
- [ ] PrimeVue `useToast()` `.add({ severity: 'success' | 'error', ... })` on mutation outcomes
- [ ] Every query declares BOTH `staleTime` (default 2 min for lists) AND `gcTime` (default 5 min)
- [ ] No raw `fetch()` calls inside Vue components — only inside composables
- [ ] No `@tanstack/vue-query` introduced unless documented reason in JSDoc (`@reason: ...`)

**PrimeVue DataTable (§7)**

- [ ] DataTable runs in server-side `:lazy="true"` mode — `:value` from Pinia Colada, `:total-records` = `meta.total`, `:loading` = query `isPending`
- [ ] `dataKey="uuid"` on every DataTable instance
- [ ] `@page` / `@sort` / `@filter` handlers map `DataTableStateEvent` into the reactive `filters` object (no client-side sort/filter)
- [ ] Selection via `v-model:selection` + `<Column selectionMode="multiple">`
- [ ] Soft-deleted rows styled via `rowClass` returning a class bound to `--deleted-row-*` tokens
- [ ] `DeleteConfirmModal.vue` + `BulkDeleteConfirmModal.vue` used (Volt `Dialog`) — never `window.confirm()` / `alert()`
- [ ] `RestoreConfirmModal.vue` + `BulkRestoreConfirmModal.vue` used (Volt `Dialog`) — never `window.confirm()` / `alert()`
- [ ] 3 action icons per active row: `pi pi-eye` (View, default) / `pi pi-pencil` (Edit, default) / `pi pi-trash` (Delete, `severity="danger"`) — each with `aria-label` + `v-tooltip` + `<PermissionGuard>` (VIEW/UPDATE/DELETE)
- [ ] 2 action icons per deleted row: `pi pi-eye` (View) / `pi pi-check-circle` (Restore, `severity="success"`) — each with `aria-label` + `v-tooltip` + `<PermissionGuard>` (VIEW/RESTORE). NEVER show Edit on a soft-deleted row.
- [ ] Advanced filter (§7.1): search debounced 300ms, status select (All/Active/Deleted), `DataTableDateRangeFilter.vue` with presets (Today, Yesterday, Last 7/30 days, This/Last month, This year, Custom) + Clear + inline `date_to >= date_from` validation + max-date=today
- [ ] `build{Entity}QueryParams()` helper shared by list query AND export URL — no drift
- [ ] Date columns use `formatDateShort()`
- [ ] Record counter: `{count} {count === 1 ? 'record' : 'records'} found`
- [ ] Status filter supports: All / Active / Deleted
- [ ] **Bulk delete AND bulk restore both wired** via `v-model:selection` + Pinia Colada `useMutation` against `POST /bulk-delete` / `POST /bulk-restore` (paired — shipping one without the other is FAIL)
- [ ] `DataTableBulkActions.vue` disables bulk-delete when any selected row has `deleted_at !== null`, disables bulk-restore when any selected row has `deleted_at === null`, disables both with tooltip on mixed selection
- [ ] DataTable paginator uses a sliding template (5 page links around current)
- [ ] All cells centered by default (DataTable `:pt` → `text-center`)

**PrimeVue/Volt Primitives (§10)**

- [ ] Every UI primitive consumed via `@/volt/{Component}.vue` — PrimeVue/Volt is the sole UI library
- [ ] Required primitives added via `npx volt-vue add <Component>` and committed to the repo
- [ ] Tones requested via `severity` / `outlined` / `text` / `link` / `size` props — never bespoke utility classes
- [ ] Confirmation dialogs (Delete/Restore) use the Volt `<Dialog>` from `@/volt/Dialog.vue` — never `window.confirm()` and never a hand-rolled modal
- [ ] Side drawer / mobile sidebar uses the Volt `<Drawer>`
- [ ] Date range filter uses Volt `<DatePicker selectionMode="range">` inside Volt `<Popover>`
- [ ] Searchable selects use Volt `<Select filter>` or `<AutoComplete>`
- [ ] Binary toggles use Volt `<ToggleSwitch>`
- [ ] Local edits inside `volt/{Component}.vue` are documented in a top-of-file JSDoc explaining why upstream couldn't be used as-is
- [ ] Customisation done via the component's `pt` (Pass-Through) config — never inline `class` overrides at the call site
- [ ] App-level class composition uses `cn()` from `@/lib/utils` (clsx + tailwind-merge)

**Animation (§11)**

- [ ] Default approach: Vue's built-in `<Transition>` / `<TransitionGroup>` + CSS transitions on tokens (PrimeVue overlays keep their built-in transitions)
- [ ] If `motion-v` is used: variants/transitions imported from `lib/motion.ts` — never inline
- [ ] No `duration > 0.4s`
- [ ] No `whileHover` scale `> 1.04`
- [ ] No `motion-v` animating bg colors or font sizes (use CSS transitions)
- [ ] Unmounting elements wrapped with `<Presence>` (motion-v) or native `<Transition>`

**Security (§14)**

- [ ] No `v-html` on untrusted input (and PrimeVue tooltip/HTML options keep `escape` ON for user-controlled strings)
- [ ] No `eval()` or dynamic code execution
- [ ] No tokens/PII stored in `localStorage`
- [ ] No `console.log()` of sensitive props
- [ ] All restricted UI wrapped in `<PermissionGuard :permission="'VIEW_{MODULE}'">`
- [ ] `router.visit('/login')` + `queryCache.clear()` on logout

**TypeScript (§13)**

- [ ] `PaginatedResponse<T>` interface used for all list responses
- [ ] Entity list items use `snake_case` keys: `uuid`, `created_at`, `updated_at`, `deleted_at: string | null`
- [ ] No `any` types — strict TypeScript 5 throughout
- [ ] Module `types.ts` defines all domain-specific interfaces
- [ ] `php artisan typescript:transform` was run and `resources/js/generated/generated.d.ts` is current (PHASE 0)
- [ ] Backend-shaped types are IMPORTED from the generated file — zero hand-written interfaces mirroring a `Data` class
- [ ] ZERO `any` / `as any` / implicit `any` / `@ts-ignore` / bare `@ts-expect-error`; `unknown` + narrowing where the type is genuinely open
- [ ] Pagination typed via the generated `PaginatedDataCollection<TKey, TValue>` — no hand-rolled `PaginatedResponse<T>`
- [ ] `defineProps<T>()` and `defineEmits<T>()` use generic syntax — no runtime declarations
- [ ] **Vue 3.5 (§4.5)**: `useTemplateRef<T>('name')` — no same-name `ref()` for template refs
- [ ] **Vue 3.5 (§4.5)**: `useId()` for every generated `id` / `aria-describedby` / `aria-labelledby` in a reusable component
- [ ] **Vue 3.5 (§4.5)**: destructured props with defaults — `withDefaults` BANNED; getters when passing a destructured prop as a reactive source
- [ ] **Vue 3.5 (§4.5)**: `defineModel<T>()` for every custom `v-model` — no manual `modelValue` + `update:modelValue`
- [ ] **Vue 3.5 (§4.5)**: `onWatcherCleanup()` in every watcher starting a timer / request / subscription (debounced search, export polling, theme watcher)
- [ ] **Vue 3.5 (§4.5)**: `defineSlots<T>()` on every component exposing a scoped slot

**Forms (§13.1)**

- [ ] Every mutating form uses `@primevue/forms` `<Form>` + `<FormField>` with `zodResolver` from `@primevue/forms/resolvers/zod`
- [ ] Zod v4 schema in `modules/{context}/schemas/{entity}FormSchema.ts`; form type is `z.infer<typeof schema>` (never duplicated as a separate interface)
- [ ] Field errors rendered via Volt `<Message severity="error">` — not toasts
- [ ] No competing validators installed (`yup`, `joi`, `valibot`, `vee-validate`, `class-validator`)

**Sidebar (§9.1)**

- [ ] Every nav item with permission wrapped in `<PermissionGuard>`
- [ ] Related modules grouped in collapsible sections
- [ ] Group open/closed state persisted via Pinia store (with persist) or `localStorage`
- [ ] Active route auto-expands its parent group
- [ ] Section labels use `text-[10px] font-semibold uppercase tracking-[1.8px]`
- [ ] Nav icons are primeicons string class names (`'pi pi-users'`) — no component refs, no `markRaw()`

---

## PHASE 2 — GENERATE

For each item in the checklist: generate the file following the exact rules in `.claude/FRONTEND/SKILL.md` and the security baseline in `.claude/OWASP/SKILL.md`.
For each Volt/PrimeVue primitive used in the module, call tavily to search `site:primevue.org` / `site:volt.primevue.org` BEFORE composing it (anatomy, slots, pass-through sections, accessibility props, `npx volt-vue add` command).
Reason carefully on each file that involves layer boundaries, composable selection, or permission logic.
Use context7 to confirm the exact API for every library touched (Inertia v3, Pinia Colada, Pinia, PrimeVue v4 DataTable/Forms, `tailwindcss-primeui`, Zod v4).

---

## PHASE 3 — VERIFICATION

After all files are generated, you MUST:

1. Call context7 to re-confirm any APIs touched during Phase 2
2. Re-run EVERY item from Phase 1. Expected result:

✅ ALL items PASS
📊 Score: X/Y items — target 100%

If any item remains ❌, repeat Phase 2 → Phase 3 until perfect score.
Then respond with EXACTLY 5 lines — no extra output, no file listing, no explanation:

```
✅ Module {Name} generated — {N} files across Pages / modules / common.
🎨 Tokens: all via var(--token) / tailwindcss-primeui · globals.css updated · dark override present.
🔐 PermissionGuard applied: VIEW_{X}, CREATE_{X}, UPDATE_{X}, DELETE_{X}.
🧪 DataTable: server-side :lazy · uuid dataKey · Delete/RestoreConfirmModal · sliding paginator.
📊 {score}/{total} rules passed · {N} files verified against FRONTEND/SKILL.md + OWASP baseline.
```
