---
description: Audits Vue 3 + Inertia.js v3 modules against design tokens, Pinia Colada, Pinia, PrimeVue DataTable, accessibility, security and architecture rules. Auto-fixes violations and re-verifies until 100% score.
---

# FRONTEND AUDIT AGENT — Vue 3 + Inertia.js v3 + Pinia + Pinia Colada + PrimeVue v4 unstyled + Volt + Tailwind v4

## PHASE 1 — AUDIT (produce checklist)

Before starting the audit, you MUST:

1. Read `.claude/FRONTEND/SKILL.md` — single source of truth for frontend rules
2. Read `.claude/OWASP/SKILL.md` — the always-on security baseline (15+1 items mapped to OWASP Top 10:**2025** + API Top 10:2023 + LLM Top 10:2025 when AI is in scope)
3. Call context7 to resolve current docs for: Vue 3 (Composition API + `<script setup>`), Inertia.js v3 (`@inertiajs/vue3`), Pinia v4 (`/vuejs/pinia`), Pinia Colada (`/posva/pinia-colada`), PrimeVue v4 + Volt (`/websites/primevue` or `/primefaces/primevue`), `@primevue/forms`, `tailwindcss-primeui`, Zod v4, Tailwind CSS v4
4. Call tavily to verify latest stable versions of all frontend packages, prioritizing recent/current sources (`time_range: day`, `week`, or `month`); search `site:primevue.org` / `site:volt.primevue.org` for any primitive being audited

Then analyze the indicated module against these rules.
For each item mark ✅ PASS, ❌ FAIL (with file:line and brief description) or ⚠️ WARN.

### Required Checklist

**Styles & Tokens (§0, §1)**

- [ ] Zero hex values or Tailwind palette names in components — only `tailwindcss-primeui` semantic utilities (`bg-surface-100`, `text-color`, `text-muted-color`, `bg-primary`, …) or `var(--token)` for non-semantic project tokens
- [ ] No `bg-red-600`, `text-gray-500`, or `bg-[#hex]` anywhere
- [ ] All new core tokens added to `globals.css` FIRST, with the `tailwindcss-primeui` bridge updated and the `.dark` override added in the same commit when contrast flips
- [ ] Soft-deleted rows use `--deleted-row-bg`, `--deleted-row-border`, `--deleted-row-opacity` via DataTable `rowClass`
- [ ] Tag/severity tones resolve through the PrimeUI bridge (`--p-green-500`, `--p-red-500`, …) — never inline at the call site
- [ ] Cards use the Volt `<Card>` from `@/volt/Card.vue` — no custom `.card` class
- [ ] Buttons use the Volt `<Button>` with `severity`/`outlined`/`text`/`link` props — no `.btn-*` classes, no inline bg styles
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
- [ ] Inertia pages live in `Pages/{Module}/{Index|Show|Create|Edit}.vue`
- [ ] Pages use `defineOptions({ layout })` — no manual layout wrapping per page
- [ ] State ownership is clear: server state in Pinia Colada, client UI state in Pinia, page props from `usePage()`
- [ ] KISS / DRY / Clean Code / SRP applied to components, composables, helpers

**State Management (Pinia + Pinia Colada)**

- [ ] Audit whether Pinia is installed and whether the module truly needs shared client state
- [ ] Pinia stores live in `modules/{context}/stores/` (or `modules/app/stores/` for app-shell) and use `defineStore('id', setupFn)`
- [ ] Stores are explicitly typed and consumed through `storeToRefs` for state
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
- [ ] **Inertia v3 March 2026**: layout chrome (page title, breadcrumbs, action toolbar) flows via `defineOptions({ layout: [Layout, props] })` tuple + `setLayoutProps()` for dynamic overrides — NEVER via Pinia, NEVER via an event bus, NEVER via the removed `useLayoutProps` hook (deleted before v3 stable)
- [ ] **Inertia v3 March 2026**: paginated lists with frequent partial reloads use `Inertia::deepMerge($data)->matchOn('data.uuid')` on the backend
- [ ] `onHttpException` and `onNetworkError` callbacks wired in `app.ts` for global error handling

**Theme Controller (§1.5)**

- [ ] `modules/app/stores/useThemeStore.ts` exists and uses `useColorMode({ attribute: 'class', modes: { light: '', dark: 'dark' } })` from `@vueuse/core`
- [ ] ONLY the `.dark` class is applied to `<html>` — light mode = absence of class. NEVER `<body>`, NEVER `data-theme`, NEVER `.light`
- [ ] FOUC-killer inline script present in `app.blade.php` `<head>` with `nonce="{{ csp_nonce() }}"`, defaulting to `'dark'` (dark-first project policy) and adding `.dark` only when the resolved theme is dark
- [ ] `:root` in `globals.css` defines core tokens AND the `tailwindcss-primeui` bridge in LIGHT values; `.dark` redefines core tokens to dark values plus contrast-flipping bridge overrides (e.g. `--p-primary-contrast-color`)
- [ ] `@media (prefers-color-scheme: dark)` defensive fallback present (matches `html:not(.dark)`) for non-JS contexts
- [ ] No PrimeVue theme preset / `darkModeSelector` configured — PrimeVue runs `{ unstyled: true }`; dark mode is purely the `.dark` class + the `@custom-variant dark` + token overrides
- [ ] `<Toast>` mounted once in `AppLayout`; severity colors resolve from the bridge (no per-instance hardcoded theme)
- [ ] `<ThemeToggle>` component reachable in both authenticated and guest layouts
- [ ] `useThemeStore` is NOT wrapped with `pinia-plugin-persistedstate` — `useColorMode`'s `storageKey: 'app:theme'` is the SINGLE source of persistence (matches the FOUC-killer's read key); no auth tokens or PII persisted alongside

**Pinia Colada (§6) — Server State**

- [ ] All network composables under `modules/{context}/composables/` use `@pinia/colada`
- [ ] `key` is a function returning `[entityName, ...filters]`, with `toValue(...)` for reactive params
- [ ] `queryCache.invalidateQueries({ key: ['{entities}'] })` after every successful write
- [ ] Optimistic mutations: `onMutate` returns BOTH `previous` and `optimistic`; `onError` rolls back ONLY when `context.optimistic === queryCache.getQueryData(key)` (an unguarded rollback discards a concurrent mutation — FAIL); `onSuccess` reconciles with the server payload
- [ ] Every paginated list query sets `placeholderData: (previousData) => previousData` — a manual "last page" ref is a FAIL
- [ ] Shared filter/draft state uses `defineQuery` / `defineMutation` — a Pinia store holding server state is a FAIL
- [ ] PrimeVue `useToast()` `.add({ severity, ... })` on mutation outcomes
- [ ] Every query declares BOTH `staleTime` (lists default 2 min) AND `gcTime` (default 5 min) — missing either is a FAIL
- [ ] No raw `fetch()` calls inside Vue components — only inside composables
- [ ] `@tanstack/vue-query` only present if a documented JSDoc reason exists

**PrimeVue DataTable (§7)**

- [ ] DataTable runs in server-side `:lazy="true"` mode — `:value` from Pinia Colada, `:total-records` = `meta.total`, `:loading` = query `isPending`
- [ ] `dataKey="uuid"` on every DataTable instance
- [ ] `@page` / `@sort` / `@filter` handlers map `DataTableStateEvent` into the reactive `filters` object (no client-side sort/filter)
- [ ] Selection via `v-model:selection` + `<Column selectionMode="multiple">`
- [ ] Soft-deleted rows styled via `rowClass` bound to `--deleted-row-*` tokens
- [ ] `DeleteConfirmModal.vue` + `BulkDeleteConfirmModal.vue` used (Volt `Dialog`) — never `window.confirm()` / `alert()`
- [ ] `RestoreConfirmModal.vue` + `BulkRestoreConfirmModal.vue` used (Volt `Dialog`) — never `window.confirm()` / `alert()`
- [ ] 3 action icons per active row: `pi pi-eye` (View, default) / `pi pi-pencil` (Edit, default) / `pi pi-trash` (Delete, `severity="danger"`) — each with `aria-label` + `v-tooltip` + `<PermissionGuard>` (VIEW/UPDATE/DELETE)
- [ ] 2 action icons per deleted row: `pi pi-eye` (View) / `pi pi-check-circle` (Restore, `severity="success"`) — each with `aria-label` + `v-tooltip` + `<PermissionGuard>` (VIEW/RESTORE). Edit on a soft-deleted row is FAIL.
- [ ] Advanced filter (§7.1): search debounced 300ms, status select (All/Active/Deleted), `DataTableDateRangeFilter.vue` with presets (Today, Yesterday, Last 7/30 days, This/Last month, This year, Custom) + Clear + inline `date_to >= date_from` validation + max-date=today
- [ ] `build{Entity}QueryParams()` helper shared by list query AND export URL — drift between the two is FAIL
- [ ] Date columns use `formatDateShort()`
- [ ] Record counter: `{count} {count === 1 ? 'record' : 'records'} found`
- [ ] Status filter supports: All / Active / Deleted
- [ ] **Bulk delete AND bulk restore both wired** via `v-model:selection` + Pinia Colada `useMutation` against `POST /bulk-delete` / `POST /bulk-restore` (paired — shipping one without the other is FAIL)
- [ ] `DataTableBulkActions.vue` disables bulk-delete when any selected row has `deleted_at !== null`, disables bulk-restore when any selected row has `deleted_at === null`, disables both with tooltip on mixed selection
- [ ] DataTable paginator uses a sliding template (5 page links around current)
- [ ] All cells centered by default (DataTable `:pt` → `text-center`)

**PrimeVue/Volt Primitives (§10)**

- [ ] Every UI primitive consumed via `@/volt/{Component}.vue` — PrimeVue/Volt is the sole UI library
- [ ] `app.ts` registers PrimeVue `{ unstyled: true }` + ToastService + ConfirmationService — no styled theme preset
- [ ] Tones requested via `severity` / `outlined` / `text` / `link` / `size` props — never bespoke utility classes
- [ ] Confirmation dialogs (Delete/Restore) use the Volt `<Dialog>` — never `window.confirm()` and never a hand-rolled modal
- [ ] Side drawer / mobile sidebar uses the Volt `<Drawer>`
- [ ] Date range filter uses Volt `<DatePicker selectionMode="range">` inside Volt `<Popover>`
- [ ] Searchable selects use Volt `<Select filter>` or `<AutoComplete>`
- [ ] Binary toggles use Volt `<ToggleSwitch>`
- [ ] Local edits inside `volt/{Component}.vue` are documented in a top-of-file JSDoc explaining why upstream couldn't be used as-is
- [ ] Customisation done via the component's `pt` (Pass-Through) config — never inline `class` overrides at the call site
- [ ] App-level class composition uses `cn()` from `@/lib/utils` (clsx + tailwind-merge)

**Animation (§11)**

- [ ] Default: Vue's built-in `<Transition>` / `<TransitionGroup>` + CSS transitions on tokens (PrimeVue overlays keep their built-in transitions)
- [ ] If `motion-v` is used: variants/transitions imported from `lib/motion.ts` — never inline
- [ ] No `duration > 0.4s`
- [ ] No `whileHover` scale `> 1.04`
- [ ] No `motion-v` animating bg colors or font sizes
- [ ] Unmounting elements wrapped with `<Presence>` (motion-v) or native `<Transition>`

**Security (§14)**

- [ ] No `v-html` on untrusted input (PrimeVue tooltip/HTML options keep `escape` ON for user-controlled strings)
- [ ] No `eval()` or dynamic code execution
- [ ] No tokens/PII stored in `localStorage`
- [ ] No `console.log()` of sensitive props
- [ ] All restricted UI wrapped in `<PermissionGuard :permission="'VIEW_{MODULE}'">`
- [ ] `router.visit('/login')` + `queryCache.clear()` on logout
- [ ] Authorization checks remain permission-based even if a Pinia store exists

**TypeScript (§13)**

- [ ] `PaginatedResponse<T>` interface used for all list responses
- [ ] Entity list items use `snake_case` keys: `uuid`, `created_at`, `updated_at`, `deleted_at: string | null`
- [ ] No `any` types — strict TypeScript 5 throughout
- [ ] Module `types.ts` defines all domain-specific interfaces
- [ ] `resources/js/generated/generated.d.ts` exists and is current — run `php artisan typescript:transform` and FAIL the audit if the file changes (types were stale)
- [ ] Any hand-written interface mirroring a backend `Data` class is a **FAIL** — cite `file:line` and replace with the generated type
- [ ] Any hand-rolled `PaginatedResponse<T>` is a **FAIL** — use the generated `PaginatedDataCollection<TKey, TValue>`
- [ ] ZERO `any` / `as any` / implicit `any` / `@ts-ignore` / bare `@ts-expect-error` — each occurrence is a FAIL with `file:line`
- [ ] `generated.d.ts` has not been hand-edited (it is a build artifact — fix the PHP `Data` class instead)
- [ ] `defineProps<T>()` and `defineEmits<T>()` use generic syntax — no runtime declarations
- [ ] **Vue 3.5 (§4.5)**: template refs use `useTemplateRef<T>('name')` — a same-name `ref()` bound via `ref="x"` is a FAIL
- [ ] **Vue 3.5 (§4.5)**: generated `id` / `aria-describedby` / `aria-labelledby` in reusable components use `useId()` — `Math.random()`, `crypto.randomUUID()`, module counters, or hardcoded ids are a FAIL
- [ ] **Vue 3.5 (§4.5)**: `withDefaults(...)` in new code is a FAIL — destructured props with defaults instead; a destructured prop passed as a reactive source without a getter is a FAIL
- [ ] **Vue 3.5 (§4.5)**: custom `v-model` uses `defineModel<T>()` — a manual `modelValue` prop + `update:modelValue` emit is a FAIL
- [ ] **Vue 3.5 (§4.5)**: every watcher starting a timer / request / subscription calls `onWatcherCleanup()` synchronously
- [ ] **Vue 3.5 (§4.5)**: every scoped slot is typed with `defineSlots<T>()` — untyped scoped slots violate the no-`any` rule
- [ ] Inertia `PageProps` augmented in `types/inertia.d.ts`

**Forms (§13.1)**

- [ ] Every mutating form uses `@primevue/forms` `<Form>` + `<FormField>` with `zodResolver` from `@primevue/forms/resolvers/zod`
- [ ] Zod v4 schema in `modules/{context}/schemas/`; form type is `z.infer<typeof schema>` (never duplicated as a separate interface)
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

## PHASE 2 — FIX

For each ❌ FAIL, you MUST before writing the fix:

1. Call context7 to confirm the exact API for the affected library (Vue 3, Inertia v3, Pinia, Pinia Colada, PrimeVue v4 DataTable/Forms, `tailwindcss-primeui`, Zod v4)
2. Call tavily to search `site:primevue.org` / `site:volt.primevue.org` IF the failure involves a Volt/PrimeVue primitive, OR to verify current Vue 3 / Pinia Colada best practices if uncertain (prioritize `time_range: day | week | month`)

Then apply the minimal fix following the exact rules in `.claude/FRONTEND/SKILL.md` and the security baseline in `.claude/OWASP/SKILL.md`.
If the audit reveals missing or unnecessary global state management, prefer the simplest valid approach: component `ref` first, Pinia setup store only when state must be shared beyond component boundaries, and Pinia Colada for all server state.

---

## PHASE 3 — VERIFICATION CHECKLIST

After all fixes, you MUST:

1. Call context7 to re-confirm any APIs touched during Phase 2
2. Re-run EVERY item from Phase 1

Expected result:
✅ ALL items PASS
📊 Score: X/Y items — target 100% (10/10)

If any item remains ❌, repeat Phase 2 → Phase 3 until perfect score.
