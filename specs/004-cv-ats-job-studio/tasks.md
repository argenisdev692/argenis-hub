# Tasks: CV ATS Job Studio (`004-cv-ats-job-studio`)

- **Date:** 2026-09-18 · **Revision:** 2 (after `research.md` §10–§17, `plan.md` §12–§13)
- **Phase:** 5 — Task breakdown
- **Status:** ⛔ **DOCUMENTED ONLY — NO IMPLEMENTATION STARTED.** Not one line of module code
  exists. Q19, Q20 and V-5 are done; Phase 7 stays blocked by the rest of the **Phase 0
  verification gate** (V-4 set-up, T-002 probe).
- **Inputs:** `spec.md` rev 3 (FR-1…FR-52, NFR-1…NFR-15, SC-1…SC-17) · `clarify.md` Q1–Q20 ·
  `research.md` §1–§17 · `plan.md` §1–§13 (§13 wins over §12, §12 over §1–§11).
- **Scope:** fullstack flow only (`clarify.md` Q11).

## How to read this

- **`[P]`** — parallelisable: touches no file another `[P]` task in the same phase touches.
- **`Traces:`** — every task names the FR / NFR / SC / CHG / clarify item it serves. A task with
  no trace is scope creep.
- **`Gate:`** — a phase with a gate is not entered until the condition holds.
- Every task leaves the system compiling and testable. **Tests are part of the task** — a task
  is done when its test passes on a real run (Pest 5, `php artisan test --compact --filter=CvJobStudio`).
- IDs from revision 1 are **kept** so references in `plan.md` and `research.md` stay valid. New
  tasks are **T-107…T-149** and **T-000**; they sit in the phase where they belong, not at the end.

## Changes from revision 1

| Kind | Tasks | Why |
|---|---|---|
| **New gate** | Phase 0: V-1…V-5 | The user blocked coding on three `[UNVERIFIED]` items (`research.md` §17) |
| **New — first test** | **T-000** | The user: the vector-index check "debería ser el primer test técnico" |
| **Withdrawn** | ~~T-050~~ (`halfvec` migration) | Replaced by T-121 — native `vector` (plan CHG-7, CHG-19) |
| **Modified** | T-002, T-006, T-010, T-011, T-016, T-018, T-023, T-029, T-032, T-043, T-044, T-045, T-052, T-059, T-060, T-084, T-092, T-101, T-106 | Scope (Q11), native vector, access modes, portability, corrected data (16 applications, not 13) |
| **New** | T-107…T-149 | US-9, US-10, FR-38…FR-52, CHG-6…CHG-19, frontend (NFR-15); T-149 and the extensions to T-018 / T-043 / T-068 come from `analyze.md` |
| **New (revision 4)** | Phase H0: T-150…T-155; T-156 (match narrative); T-021, T-023, T-057, T-077, T-078 extended | Prompt caching + failover per AI purpose (plan CHG-20, NFR-16, NFR-17, SC-18, SC-19) |
| **Revision 5 (V-5)** | V-5 ✓; T-157…T-160 new; T-113/T-114 unblocked; ~~T-115~~/~~T-116~~ not built by default; T-045, T-118, T-138 extended | Sources as signals + four-tier priority (plan CHG-21, FR-53, FR-54, SC-20, A-18) |

## ⚠️ Review these first — cheap now, expensive after Phase C

| # | Decision | Where | Why it matters |
|---|---|---|---|
| 1 | **Opportunity policy defaults** | `clarify.md` Q19, plan CHG-16 | The only numbers in the module that are *not* measured. Choose them knowingly. |
| 2 | **PostgreSQL test database** | `clarify.md` Q20, plan CHG-17 | Without it the vector, trigram and extension tests cannot run at all. |
| 3 | **~27 tables, `softDeletes` on all** | plan §12 CHG-15, §13 | Migration-with-backfill if changed later. |
| 4 | **Fingerprint dedup + resolve-to-source** | CHG-2, CHG-11 | One job has up to 4 URLs; wrong identity corrupts every count and every channel factor. |
| 5 | **Rules v2 shifts stored scores** | plan §1, CHG-8 | Versioned and measurable (T-066, T-094) but not reversible in place. |

---

## Phase 0 — Verification gate (the user's condition for writing any code)

**Purpose:** nothing that depends on an unverified fact is built. Each item ends in a recorded
decision or a measured result, never in production code.

**Gate:** Phase C (the first migration) does not start until **V-1 decided, V-4 decided, V-5
done for every portal adapter that will be built, T-002 green, and T-000 green**.

- [x] **V-1 Opportunity priors — decide the policy defaults.**
      Status: **DECIDED 2026-09-18 — option (b), mild directional** (`clarify.md` Q19). External magnitudes do not exist
      for aggregator, agency or seniority effects; the Huntr dataset supports an *ordering*
      only; own data shows nothing significant (Fisher p = 1.00 / 0.31 / 0.08) and would need
      ~750 applications per group; CHG-9's `m = 30` let one outcome halve a factor.
      Output: the chosen option (a/b/c) written into `clarify.md` Q19 and into the seed for
      `studio_profiles.rules.opportunity` (T-018, T-125).
      *Traces: `research.md` §17.2 · FR-51 · CHG-16 · user blocker 1.*

- [x] **V-2 Vector index behaviour — measured.** Rolled-back probe on PostgreSQL 17.6:
      Laravel emits `<=>`; `Index Scan using …hnsw` in all four shapes; filtered query returned
      **2 of 3** rows without iterative scan and 3 with `SET LOCAL hnsw.iterative_scan =
      strict_order`; extension not persisted. Remaining work is **T-000** (make it a permanent
      test), which needs V-4.
      *Traces: `research.md` §17.3 · CHG-19 · user blocker 2.*

- [x] **V-3 External figures — source verified and demoted.** Huntr primary source read: 1.24 M
      self-reported tracker applications, 78 % LinkedIn, "interview logged" metric, magnitudes
      unstable across cuts, remote boards not ranked; 6.87 % is career pages, ATS-hosted is
      3.16 %. Decision: ordering only (grade B), never a rule; CHG-9's mapping withdrawn.
      *Traces: `research.md` §17.1 · CHG-16 · user blocker 3.*

- [ ] **V-4 PostgreSQL test database — decided, set-up pending.**
      Decision: **local PostgreSQL 17 + pgvector, connection `pgsql_testing`** (`clarify.md` Q20).
      Remaining before T-000: the user installs/starts the local service and creates an empty
      test database; then verify `CREATE EXTENSION vector` succeeds there (pgvector availability
      on the chosen local service is not yet verified). Never the shared Supabase database.
      *Traces: `research.md` §17.4 · CHG-17 · RK-17.*

- [x] **V-5 Portal terms — read (2026-09-18, `research.md` §19).** Terms pages were read only where
      `robots.txt` allows it. Outcome: **Net-Empregos** RSS → `api_feed` (active job search, no
      commercial use) · **ITJobs.pt** API + key → `api_feed` (personal needs, no commercial
      use) · **InfoJobs** → `link_only` (offer aggregators need a Partners agreement — Q22) ·
      **JustJoin.it** → `link_only` (user terms not readable by permitted means) ·
      **RemoteRocketship** → `resolve_only`, or `api_feed` with a paid subscription (Q21) ·
      Tecnoempleo, Glassdoor, LinkedIn, Indeed → `link_only`. Also surfaced: Firecrawl's
      automatic mode chose a stealth proxy once → hard rule "basic proxy only" (T-159).
      *Traces: `research.md` §17.5 · FR-52 · FR-35 · CHG-18 · user question 4.*

---

## Phase A — Verification spikes (throwaway probes; output is a recorded decision)

- [ ] **T-001 `[P]` Re-verify the Tavily direct API.** `api.tavily.com/search` and `/research`
      with the project's `TAVILY_API_KEY` (`config/services.php:68`): HTTP status, quota
      headers, latency, whether `/research` exists on this plan. **Print status codes only —
      never the key.** Result into `research.md` §8.
      *Traces: `research.md` §8 · NFR-4.*

- [ ] **T-002 Extension grants — remaining part.** `vector` creation is **already proven**
      (V-2). Probe `pg_trgm` and `unaccent` the same way (one rolled-back transaction) and record
      versions. `halfvec` is no longer needed (CHG-7).
      *Traces: `plan.md` §4.1 · CHG-19 · RK-8.*

- [ ] **T-003 `[P]` PhpWord DOCX spike.** Single-column, table-free, Helvetica 9–11 pt, standard
      headings via `phpoffice/phpword` 1.4.0 — **never executed in this codebase**. Confirm the
      text extracts cleanly.
      *Traces: RK-7 · FR-22 · SC-6.*

- [ ] **T-004 `[P]` SmartRecruiters company identifier** — find the real format or mark the
      source out of scope with a reason.
      *Traces: `research.md` §7 · FR-9.*

- [ ] **T-005 `[P]` Teamtailor access** — public feed or `requires_api_key = true` + deferral.
      *Traces: `research.md` §7 · CHG-4.*

- [ ] **T-006 Field contract per confirmed source** — Greenhouse, Lever, Ashby, Recruitee,
      Workable, **plus Net-Empregos RSS, ITJobs.pt, InfoJobs** (after V-5): title, employer,
      location, remote flag, **which field means *first published*** (Greenhouse `updated_at`
      does not — RK-14), full-text field, apply URL. **Output: a mapping table in `research.md`.**
      *Traces: CHG-1 · CHG-10 · FR-9 · FR-14 · FR-40 · RK-14.*

- [ ] **T-007 `[P]` Embeddings smoke test + cost.** One real CV + one real JD through
      `Embeddings::for([...])->dimensions(1536)->cache()->generate()`: model, dimensions,
      latency, cost, cache effectiveness.
      *Traces: FR-17 · FR-33 · RK-9 · OD-1.*

- [ ] **T-008 `[P]` Portfolios read contract** — which fields map to `studio_cv_entries`
      (`kind = project`). Read-only.
      *Traces: `clarify.md` Q4 · FR-4.*

---

## Phase B — Source registry, identity and provenance

**Gate:** T-013 + T-014 + T-107 before any Phase E adapter writes a posting.

- [ ] **T-009 ATS-detection probe design.** Given a company name, test each ATS pattern (a 404
      means "not a customer") and record the hit. Budget-guarded, rate-limited, negative results
      cached 30 days. **Used by resolve-to-source (T-118).**
      *Traces: `research.md` §7 · CHG-11 · FR-9 · FR-33.*

- [ ] **T-010 Seed the source registry from `portal-tiers-cache.json` v4** — the 125
      `site_query` entries over **67 distinct domains**, tiers 1–4, `geography_policy`,
      `multinationals` locale hosts, `rotation_index` — **and an `access_mode` per domain from
      `research.md` §17.5, defaulting to `link_only` for every domain whose terms are
      unchecked.** Idempotent re-runnable seeder.
      *Traces: `research.md` §7, §17.5 · CHG-4 · CHG-18 · FR-32 · FR-52.*

- [ ] **T-011 `studio_sources` + `studio_source_locales` migrations and models** — CHG-4's
      resolution columns, the terms columns, and **`access_mode`** (replaces CHG-10's
      `fetch_policy`). `#[Table]`/`#[Fillable]`, `$hidden = ['id']`, `softDeletes`.
      *Traces: CHG-4 · CHG-15 · CHG-18 · FR-9 · FR-35 · FR-52.*

- [ ] **T-012 `studio_source_companies` slug registry** — + `status` (`found|not_found`) and
      `verified_at` for the resolve-to-source negative cache.
      *Traces: CHG-4 · CHG-11 · FR-9.*

- [ ] **T-013 `CanonicalPostingUrl` value object + unit tests** — fragment, tracking params,
      trailing slash; must collide with the local pipeline's `CanonicalUrlNormalizer`.
      *Traces: FR-10.*

- [ ] **T-014 `PostingFingerprint` value object + unit tests** — read
      `LeadScout/Domain/ValueObjects/PostingFingerprint` first; reuse or extend.
      *Traces: CHG-2 · FR-10.*

- [ ] **T-015 `studio_posting_sources` + `DeduplicatePostingsHandler`** — one posting, many
      sources; the same job from Greenhouse + Arbeitnow + WWR + Tavily → 1 posting, 4 sources.
      *Traces: CHG-2 · FR-10 · FR-13.*

- [ ] **T-016 Liveness and expiry** — `is_expired`, `expired_at`, `last_alive_check_at`,
      `alive_check_method`; **expiry derived from sightings: absent for N consecutive harvests
      (config, default 2)**. Expired: keeps its score, leaves the apply list, stays in insights.
      *Traces: CHG-3 · CHG-10 · FR-42 · `research.md` §9.*

- [ ] **T-107 `access_mode` enforcement — `NeverFetchHostPolicy`.** Domain policy over
      `access_mode ∈ {link_only, resolve_only}`; injected into every fetcher, probe and ladder
      step. Unit test per mode.
      *Traces: CHG-18 · FR-50 · FR-52 · SC-16.*

- [ ] **T-108 `discovery_channel` + `apply_destination` + `ats_kind`.**
      `ApplyDestinationClassifier` and `AtsDetector` (pure; URL-pattern table in config:
      `boards.greenhouse.io`, `jobs.lever.co`, `jobs.ashbyhq.com`, `*.myworkdayjobs.com`,
      `*.icims.com`, `*.recruitee.com`, `apply.workable.com`, `*.teamtailor.com`,
      `*.smartrecruiters.com`, `*.personio.*`). Table-driven unit tests.
      *Traces: CHG-10 · CHG-16 · FR-43 · FR-46.*

- [ ] **T-160 `resolution_tier` + `usage_restriction` on `studio_sources`** — seeded per plan
      CHG-21's matrix (tier 1 official API/ATS/feed · 2 permitted portal · 3 web search · 4
      permitted scraping); `usage_restriction` seeded `personal_non_commercial` (Net-Empregos,
      ITJobs.pt) and `partner_agreement_required` (InfoJobs). Test: a
      `partner_agreement_required` source has no enabled adapter; `SourceResolver` never uses a
      lower tier while a higher tier returned the posting.
      *Traces: FR-54 · A-18 · CHG-21.*
- [ ] **T-109 `posted_at` contract + `studio_posting_sightings`.** `PostingSourcePort` must
      return a first-published date **or an explicit unknown** with `posted_at_source`; one
      sighting row per observation (`Prunable` after 180 days). Test: no posting is persisted
      with a silently blank date.
      *Traces: CHG-10 · FR-40 · FR-41 · SC-17 · RK-14.*

---

## Phase C — Data model

**Gate:** Phase 0 gate satisfied. Review items 1–5 signed off.

- [ ] **T-000 — THE FIRST TECHNICAL TEST: `VectorIndexUsageTest` (Pest group `pgsql`).**
      Written before any other module file, independent of module migrations. Inside one
      rolled-back transaction on `pgsql_testing`: create a temp table + HNSW
      `vector_cosine_ops` partial index; assert (1) `orderByVectorDistance` /
      `whereVectorSimilarTo` SQL contains `<=>`; (2) `EXPLAIN` contains `Index Scan using`
      the HNSW index; (3) with a selective filter and `SET LOCAL hnsw.iterative_scan =
      strict_order`, `LIMIT k` returns exactly `k` rows; (4) `minSimilarity = 1 − distance`.
      Fails loudly (not skipped) when `pgsql_testing` is configured but unreachable (RK-17).
      *Traces: `research.md` §17.3 · CHG-19 · V-2 · user blocker 2.*

- [ ] **T-017 Extensions migration** — `Schema::ensureVectorExtensionExists()`, `pg_trgm`,
      `unaccent`; guarded by `DB::getDriverName() === 'pgsql'` so SQLite tests still migrate.
      *Traces: `plan.md` §4.1 · CHG-7 · CHG-17.*

- [ ] **T-018 `studio_profiles` + model + factory + seeder — fullstack profile only.** Gates,
      stack lock, geography, languages, seniority band, protected block, candidate baseline, and
      **`rules` jsonb** (blend weights, caps, bands, D tables, **opportunity policy from V-1**).
      **SC-7 test with a fixture second profile** (e.g. gap-style `0.25/0.25/0.50` weights,
      different gates) created without any schema or code change, whose run does not inherit the
      fullstack gates — only the fullstack profile is *seeded*. *(Added by `analyze.md` GAP-A5.)*
      *Traces: FR-30 · FR-31 · FR-51 · SC-7 · A-8 · CHG-6 · CHG-16 · Q11.*

- [ ] **T-019 `[P]` CV structure tables** — `studio_cv_structures`, `studio_cv_entries`,
      `studio_cv_bullets`, `studio_cv_skills` (`evidence`, ordinals).
      *Traces: FR-4 · Q4.*

- [ ] **T-020 `[P]` Audit / version / export tables** — `studio_cv_audits` (+
      `parse_recovery_ratio`, `content_issues`), `studio_metric_answers`,
      `studio_cv_versions`, `studio_exports`.
      *Traces: FR-2 · FR-3 · FR-5 · FR-6 · FR-8 · FR-24 · FR-39 · CHG-13.*

- [ ] **T-021 `[P]` Run / posting tables** — `studio_runs`, `studio_postings` (+ fingerprint,
      liveness, `posted_at`, `posted_at_source`, `discovery_channel`, `apply_destination`,
      `ats_kind`, `preferred_source_id`), `studio_posting_texts` (`ladder_step` incl.
      `source_api`, `resolution_note`), `studio_requirements` (+ `source_text_hash`,
      `extracted_by_provider`, `extracted_by_model`, `prompt_version`), `studio_gate_results`.
      *Traces: FR-9…FR-15 · FR-40 · FR-43 · NFR-16 · NFR-17 · CHG-1…CHG-3 · CHG-10 · CHG-16 · CHG-20.*

- [ ] **T-022 `[P]` Scoring / outcome tables** — `studio_scores` (+ `s_components` per
      responsibility, `opportunity_static`, `o_components`, `apply_priority`,
      `priority_computed_at`), `studio_skill_matches`, `studio_applications`,
      `studio_insight_reports`.
      *Traces: FR-16…FR-21 · FR-25…FR-29 · FR-45…FR-48 · CHG-8 · CHG-9 · CHG-16 · CHG-17.*

- [ ] **T-023 `[P]` Spend / vector tables** — `studio_budgets`, `studio_provider_calls`
      (`Prunable`; token and cache columns per CHG-20 / T-153), `studio_embeddings` with **native `$table->vector('embedding', dimensions:
      1536)` on pgsql and a text stand-in on SQLite**; owner types `cv_bullet | cv_title |
      posting_title | posting_responsibility | requirement`.
      *Traces: FR-17 · FR-33 · FR-45 · CHG-7 · CHG-17.*

- [ ] **T-024 Bidirectional relations + `User` inverse** — every `user_id` gets `hasMany` on
      `App\Models\User` with generic PHPDoc; factories for all models; **`softDeletes` on every
      table**.
      *Traces: `ARCHITECTURE-PHP` identifier rules · CHG-15 · NFR-7.*

- [ ] **T-110 `studio_skill_relations` + seeder** — confirmed rows from the JOBORA alias table
      (JavaScript ⇄ JS/ES6/ECMAScript, TypeScript ⇄ TS, Vue.js ⇄ Vue/VueJS/Vue 3, Node.js ⇄
      Node/NodeJS, PostgreSQL ⇄ Postgres, REST APIs ⇄ REST/RESTful/API REST; family CI/CD ⇄
      GitHub Actions). Unique `(user_id, from_skill, to_skill, kind)`.
      *Traces: CHG-12 · FR-44 · Q16.*

- [ ] **T-111 `studio_channel_baselines`** — `bucket_kind`, `bucket`, `policy_value`, `grade`,
      `source`, `positives`, `trials`, `applied_from`, `gate_passed_at`; seeded from the V-1
      policy.
      *Traces: CHG-16 · FR-48 · FR-51.*

- [ ] **T-112 Migration test on both drivers** — the full module schema migrates and rolls
      back on SQLite (default suite) and on `pgsql_testing` (`pgsql` group).
      *Traces: CHG-17 · RK-17.*

---

## Phase D — Query and keyword strategy

- [ ] **T-025 `studio_query_templates`** — country, language, title variation, stack synonym,
      seniority, modality, regional term.
      *Traces: CHG-5 · FR-32.*
- [ ] **T-026 `[P]` Country / language configuration** — prefer/allow/deny locales; Asia and
      Middle East denials hold.
      *Traces: FR-30 · `research.md` §7.*
- [ ] **T-027 `[P]` Vocabulary from `keyword-cache.json`** — mid-first, junior/senior on
      expansion, regional terms (`desarrollador`/`programador`/`desenvolvedor`), `never_seed`
      blocks Nest/Angular pivots.
      *Traces: FR-32.*
- [ ] **T-028 Refresh triggers as data** (cache-miss, stale-week, low-yield, forced).
      *Traces: FR-32 · A-9 · Q5.*
- [ ] **T-029 `SeedStrategyBuilder` — keywords × portals by access mode.** `api_feed` sources
      are harvested whole and filtered locally (keywords are filters, not queries);
      `search_scoped`/`link_only` portals get `include_domains` searches; `sitemap` sources are
      walked. Pure, unit-tested.
      *Traces: CHG-5 · CHG-18 · FR-32 · FR-52 · user question 4.*
- [ ] **T-030 `studio_query_experiments` + metric** — relevant-and-deduplicated yield per euro,
      recorded per **(portal × template × locale)** cell.
      *Traces: CHG-5 · CHG-18 · NFR-4.*
- [ ] **T-031 Experiment: curated/localised seeds vs generic queries** — both arms, same window,
      T-030 metric, result into `research.md`.
      *Traces: CHG-5 · SC-3.*

---

## Phase E — Discovery: structured sources first, then portals, then resolution

**Gate:** Phase B identity + T-107 green.

- [ ] **T-032 `PostingSourcePort` + `SourceHarvestResult`** — carries full-text flag, **posted_at
      or explicit unknown**, `discovery_channel`.
      *Traces: NFR-12 · CHG-1 · CHG-10 · FR-9 · FR-40.*
- [ ] **T-033 `[P]` `GreenhouseBoardSource`** (`?content=true`, extraction skipped).
      *Traces: `research.md` §7 · CHG-1 · FR-9 · FR-14.*
- [ ] **T-034 `[P]` `LeverBoardSource`** (`additionalPlain`; 404 = not a customer).
      *Traces: `research.md` §7 · FR-9.*
- [ ] **T-035 `[P]` `AshbyBoardSource`** (2.17 MB payloads — page or stream).
      *Traces: `research.md` §7 · FR-9.*
- [ ] **T-036 `[P]` `RecruiteeBoardSource` + `WorkableBoardSource`.**
      *Traces: `research.md` §7 · FR-9.*
- [ ] **T-037 `[P]` `SmartRecruitersSource` / `TeamtailorSource`** — conditional on T-004/T-005;
      else registered without an adapter.
      *Traces: CHG-4 · FR-9.*
- [ ] **T-038 `[P]` `ArbeitnowSource` + `RssFeedSource`** — adapt the LeadScout implementations.
      *Traces: `research.md` §4, §7 · FR-9.*
- [ ] **T-039 `[P]` Remote-board feeds** — RemoteOK, Remotive, Himalayas (cursor), Jobicy, WWR;
      attribution terms carried.
      *Traces: `research.md` §2, §7 · FR-35.*
- [ ] **T-040 `[P]` `AdzunaSource`** — key, budget category `search`.
      *Traces: FR-9 · FR-33.*
- [ ] **T-041 `TavilySearchSource` over the DIRECT API** — never MCP; gap-filling and
      unknown-employer discovery.
      *Traces: `research.md` §8 · FR-9.*
- [ ] **T-113 `[P]` `NetEmpregosRssSource`** — ISO-8859-1 decoding, `access_mode = api_feed`,
      `usage_restriction = personal_non_commercial`, modest polling (once per run). V-5 ✓.
      *Traces: CHG-18 · CHG-21 · FR-9 · FR-52 · A-18 · `research.md` §19.1.*
- [ ] **T-114 `[P]` `ItJobsApiSource`** — provider-issued read-only key in `config/services.php`
      (**user action: request it by email at `itjobs.pt/api`**), 1 request/second
      (`Crawl-delay: 1`), `usage_restriction = personal_non_commercial`. V-5 ✓.
      *Traces: CHG-18 · CHG-21 · FR-9 · FR-36 · FR-52 · A-18.*
- [ ] ~~**T-115 `InfoJobsApiSource`**~~ — **not built by default** (Q22): InfoJobs requires offer
      aggregators to sign a Partners agreement. The source is registered `link_only` +
      `partner_agreement_required` with no adapter (the T-037 pattern). Revisit only if the
      candidate joins the program.
      *Traces: CHG-21 · FR-52 · A-18 · `research.md` §19.1.*
- [ ] ~~**T-116 `JustJoinSitemapSource`**~~ — **not built by default**: JustJoin.it's user terms
      are not readable by permitted means, so it stays `link_only` (FR-52). Enable only if the
      candidate reads the terms and they permit sitemap discovery.
      *Traces: CHG-21 · FR-52 · `research.md` §19.1.*
- [ ] **T-157 `[P]` `RemoteRocketshipApiSource` — conditional on Q21 (subscription).** `POST
      /api/openclaw/jobs`, Bearer key, ≤ 50 jobs/request, **daily cap 500 requests / 3,000 jobs**
      enforced client-side, `hideGhostJobsFilter: "true"`, `includeJobDescription` only for
      shortlisted jobs. The returned employer `url` is handed to resolve-to-source (tier 1) — the
      application destination is never RemoteRocketship. Without Q21 the source stays
      `resolve_only` and this task is skipped.
      *Traces: CHG-21 · FR-9 · FR-49 · FR-54 · `research.md` §19.1.*
- [ ] **T-117 Search-scoped discovery for `link_only` portals** — Tavily `include_domains` per
      portal × template for LinkedIn, Indeed, Tecnoempleo, Glassdoor; stores URL + title +
      snippet exactly as the search API returned them + whatever date the snippet carries;
      **never fetches**. Person names and contact details inside a snippet are **not retained**
      (NFR-8) — test with a snippet naming a recruiter. The candidate opens the page in their own
      browser.
      *Traces: CHG-18 · FR-50 · FR-52 · NFR-8 · US-10 · `clarify.md` R-16.*
- [ ] **T-042 `SourceResolver` + `HarvestStructuredSourcesJob`** — walks `resolution_priority`,
      skips disabled/unhealthy/over-budget sources, respects `access_mode`; names no provider in
      code.
      *Traces: CHG-1 · CHG-4 · CHG-18 · FR-9 · NFR-12.*
- [ ] **T-118 Resolve to source — the four-tier flow of plan CHG-21.**
      `ResolvePostingSourceHandler` + `ResolveToSourceJob` + `SlugCandidateGenerator` (pure) +
      **`TrigramSimilarity` (pure PHP, pg_trgm-compatible)**:
      **tier 1** probe ATS boards (via T-009) → title ≥ 0.55 + compatible scope;
      **tier 3** Tavily search `"{employer}" careers "{title}"` with `exclude_domains` = every
      `link_only`/`resolve_only` host → `AtsDetector` → back to tier 1 with the discovered slug,
      or **tier 4** for the employer's own domain (robots-allowed `FetchLadder`, basic proxy).
      Refuse ambiguity (two matches within 0.05); on a match attach the `employer_ats` /
      `employer_site` source, set `preferred_source_id`, record each step in `ladder_step` +
      `resolution_note`, re-extract requirements, upgrade `apply_destination` to `at_source`;
      otherwise set `status = reference`. Per-run caps: 20 probes, 10 resolution searches.
      Tests: SC-15 fixture; **the user's example** (a LinkedIn signal resolved through a
      Greenhouse board found by tier-3 search) with **zero requests to linkedin.com**.
      *Traces: CHG-11 · CHG-17 · CHG-21 · FR-49 · FR-54 · SC-15 · SC-16 · Q15.*
- [ ] **T-119 `[P]` Unlink a wrong resolution** — handler restores the original source as
      preferred and records the unlink.
      *Traces: CHG-11 · RK-13.*
- [ ] **T-158 References + paste JD** — `ListReferencesHandler` (unresolved `status = reference`,
      unscored, showing link, title, snippet, source and date) and `PasteJobTextHandler` (candidate-
      supplied text → `ladder_step = candidate_pasted` → T-149 minimiser → normal pipeline: gates,
      requirements, fit, opportunity). The pasted text is untrusted input (same containment as
      any JD). Tests: a reference is never scored; a pasted reference is scored and labelled
      "supplied by you"; no request is made to the reference's host at any point.
      *Traces: FR-53 · US-10 · NFR-8 · Q23 · CHG-21.*
- [ ] **T-043 Pipeline wiring** — Harvest → CanonicalizeAndDedupe (+ sightings) → DiscoverViaSearch
      → **ResolveToSource** → Gate → Shortlist → Extract (conditional) → Score → Report.
      Idempotent per `(run_id, posting_id)`; no double count, no double spend. **`StartRunHandler`
      refuses a profile missing a required gate input with `ProfileGateIncompleteException`
      naming the input — test included.** *(FR-31 test added by `analyze.md` GAP-A2.)*
      *Traces: CHG-1 · CHG-11 · NFR-5 · NFR-6 · FR-31 · FR-33.*
- [ ] **T-044 Conditional extraction + `PostingFetchLadder`** — never runs when `text_source =
      source_api` **or `access_mode ∈ {link_only, resolve_only}`**; `RobotsTxtPolicy` +
      `OutboundUrlGuard` on every allowed fetch. Test: zero fetches for a Greenhouse posting and
      for a Tecnoempleo link.
      *Traces: CHG-1 · CHG-18 · FR-14 · FR-34 · FR-50.*

---

## Phase F — Guards and restrictions

- [ ] **T-045 Never-fetch test for every `link_only` / `resolve_only` host** (was LinkedIn only):
      no source, fetcher, probe or ladder step issues a request to LinkedIn, Indeed,
      Tecnoempleo, Glassdoor, InfoJobs, JustJoin.it, RemoteRocketship (web), JobLeads, Jobgether —
      including when it is the only copy of a posting, and including tier-3 resolution searches
      (they pass these hosts in `exclude_domains`).
      *Traces: FR-36 · FR-50 · SC-9 · SC-16.*
- [ ] **T-046 MCP-is-not-production test.**
      *Traces: `research.md` §8 · NFR-12.*
- [ ] **T-047 `[P]` Outbound guard + robots policy** with a denied-target test.
      *Traces: FR-34 · SC-9.*
- [ ] **T-048 `[P]` Per-source rate limits + circuit breaker** (ITJobs `Crawl-delay: 1` included).
      *Traces: NFR-6 · FR-34.*
- [ ] **T-049 `[P]` Attribution enforcement** (Remotive no-resubmission + 24 h delay).
      *Traces: FR-35.*
- [ ] **T-120 `[P]` Robots-conformance test** — a fetcher asked for a path its site disallows for
      `*` (e.g. `tecnoempleo.com/alertas-empleo-rss.php`) refuses before any request.
      *Traces: FR-34 · FR-52 · `research.md` §17.5.*
- [ ] **T-149 `[P]` Incidental personal-data minimisation in posting text.** Before
      `studio_posting_texts.text` and snippets are stored: e-mail addresses and phone numbers are
      kept **only** when they are the posting's stated application channel, otherwise replaced by
      a placeholder; named-contact lines ("contact Maria García, Talent Partner") are removed when
      detectable. Reuse the pattern of LeadScout's `PersonalDataScrubber` — **not** its Art. 14
      notice / suppression machinery, which stays absent (plan §3.4 as amended). Fixture test with
      a JD naming a recruiter and an application e-mail. *(Added by `analyze.md` GAP-A3.)*
      *Traces: NFR-8 · US-8 · FR-50 · `clarify.md` R-16.*
- [ ] **T-159 `[P]` Basic-proxy-only rule for Firecrawl.** `FirecrawlPostingFetcher` (and any
      Firecrawl use in the module) always sends `proxy: "basic"`; a config guard rejects any other
      value at boot; a refusal in basic mode marks the page *not fetchable* and is never retried in
      `auto`/`stealth`/`enhanced`. Tests: the outgoing request carries `basic`; a simulated
      basic-mode refusal leaves the posting as a reference (**SC-20**).
      *Traces: SC-20 · CHG-21 · spec §8 · `research.md` §19.2.*

---

## Phase G — Embeddings and similarity

**Gate:** T-000 green.

- ~~**T-050 `halfvec(1536)` migration**~~ — **withdrawn**, replaced by T-121 (CHG-7, CHG-19).
- [ ] **T-121 HNSW index** — raw statement `USING hnsw (embedding vector_cosine_ops) WITH (m =
      16, ef_construction = 64) WHERE deleted_at IS NULL`, pgsql-guarded, non-concurrent.
      *Traces: CHG-7 · CHG-19 · FR-17.*
- [ ] **T-051 `[P]` `EmbeddingPort` + `LaravelAiEmbeddingAdapter`** — model + dims per vector;
      `embedding` budget category.
      *Traces: FR-17 · FR-33 · NFR-12.*
- [ ] **T-052 `SimilaritySearchPort` + `PgVectorSimilaritySearch` + `InMemorySimilaritySearch`.**
      Real adapter: transaction with `SET LOCAL hnsw.iterative_scan = strict_order` + `ef_search
      = 100`, `whereNull('deleted_at')`, `orderByVectorDistance`. `pgsql`-group test proves the
      **filtered count equals `k`**; default-suite tests use the in-memory fake.
      *Traces: CHG-17 · CHG-19 · RK-1 · FR-17.*
- [ ] **T-053 `[P]` Write-once per content hash** — same text twice charges once.
      *Traces: RK-9 · FR-33.*
- [ ] **T-054 `[P]` `StudioReembedCommand`.**
      *Traces: OD-1 · NFR-9.*
- [ ] **T-122 `[P]` Embedding coverage** — bullets, CV titles, posting titles, responsibilities
      and requirements embedded once per content hash after parse/extract.
      *Traces: FR-45 · CHG-8 U-2 · CHG-7.*

---

## Phase H0 — LLM cost and resilience: prompt caching + failover (plan CHG-20)

**Purpose:** every LLM call in the module goes through the existing Shared AI layer
(`PromptCachingAIClient`, `UsesPromptCache`, `CacheablePrompt`, circuit breaker), with failover
and caching decided **per purpose** and proven by measurement. **No new Shared class.**
**Gate:** before T-057 (the first LLM consumer).

- [ ] **T-150 `AiPurpose` enum + `ai.purposes` config** — per purpose: provider, fallbacks,
      model per provider, timeout, `max_input_chars`, `max_output_tokens`, `prompt_cache`
      on/off, `prompt_version`. Seeded with the plan CHG-20 decision table: cache **on** for
      `requirement_extraction`, `match_narrative`, `tailor`, `translate`; **off** for
      `cv_rewrite`, `cv_judge`, `cv_structure_parse`.
      *Traces: CHG-20 · NFR-16 · NFR-17 · `research.md` §18.4.*
- [ ] **T-151 Deterministic prompt layers** — `CvSnapshotLayer` (confirmed structure by ordinal,
      sorted keys, no timestamps/ids) and `ExtractionRubricLayer` (sorted vocabulary,
      `prompt_version`), with `cacheKey = cvjs:{purpose}:{content_hash}`. Agents `use
      UsesPromptCache`. **Test: identical input → byte-identical layers.** Test: a long-lived
      layer after a short-lived one is rejected (`CacheablePrompt` invariant).
      *Traces: CHG-20 · NFR-16.*
- [ ] **T-152 `AiCallExecutor` + `FailoverPolicy`** — the CourseScripts loop over `[provider,
      ...fallbacks]` through `PromptCachingAIClient`, with `SpendGuardPort::ensure()` per attempt.
      Logs the exception class only. `FailoverPolicy::isRetryable()`: connection errors, 429,
      502/504/520/522/524, usage limits — **not** invalid structured output or 4xx. Tests:
      (1) primary fails at connection level → the fallback answers and the provider/model are
      recorded (**SC-19**); (2) a schema-validation error does **not** fail over (RK-20);
      (3) all providers down → `AllProvidersFailedException`, and `match_narrative` degrades to
      no narrative with the numbers intact; (4) **embedding failure never switches model**
      (SC-19).
      *Traces: CHG-20 · NFR-6 · NFR-17 · SC-19 · RK-19 · RK-20.*
- [ ] **T-153 `[P]` Token accounting** — `studio_provider_calls` + `purpose`, `attempt_no`,
      `model`, `input_tokens`, `output_tokens`, `cache_read_input_tokens`,
      `cache_creation_input_tokens` from `laravel/ai` `Usage`; cost from a per-provider/model
      **pricing table in config** (input · cached input · cache write · output). Extends T-098.
      *Traces: CHG-20 · NFR-4 · NFR-16 · FR-33.*
- [ ] **T-154 Cache-effectiveness measurement (real run, recorded in `research.md`)** — for each
      cache-flagged purpose: whether calls after the first show `cache_read_input_tokens > 0`
      (Anthropic silently skips prefixes below the model minimum); reads vs writes; 5-minute vs
      1-hour TTL cost (`ai.prompt_cache.long_ttl` is `1h` today, about 2× on writes); whether
      parallel fan-out causes duplicate writes (→ one serial warm-up call). Also: fill the pricing
      table from the providers' **live pricing pages**; verify whether `store: false` can be sent
      per call for OpenAI. Outcome: flags switched off where there are no reads (**SC-18**);
      per-agent TTL override proposed to Shared **only if** 1 h loses.
      *Traces: CHG-20 · SC-18 · RK-18 · `research.md` §18.3.*
- [ ] **T-155 `[P]` `JdTextTrimmer`** (pure) — strips benefits, EEO and legal boilerplate by an
      ES/EN/PT heading lexicon, collapses whitespace, caps at `max_input_chars`. Fixture test: a
      real JD loses its boilerplate and keeps every requirement line.
      *Traces: CHG-20 · NFR-16.*

---

## Phase H — Fit scoring, rules version 2

**Gate:** Phase G green. **Gate:** Phase H0 green (T-057 is the first LLM consumer).

- [ ] **T-055 Ruleset in `studio_profiles.rules`** with fullstack defaults in
      `config/cv-job-studio.php` — every weight, cap, band, sub-score table, credit value,
      κ/π factors, calibration constants. No scoring literal in a class.
      *Traces: SC-11 · NFR-9 · CHG-6.*
- [ ] **T-056 `[P]` `RemoteScopeClassifier` + `GateEvaluator` (G1/G2/G3).**
      *Traces: FR-11 · FR-12 · SC-1.*
- [ ] **T-057 `RequirementExtractorPort` + `LaravelAiRequirementExtractor`** — `laravel/ai`
      `HasStructuredOutput` schema; posting text is untrusted input; each requirement stored with
      `raw_text`; responsibilities extracted separately with must-do / nice-to-have. **Runs through
      `AiCallExecutor` (T-152) with the cached `ExtractionRubricLayer` (T-151) on JD text trimmed
      by T-155; skipped entirely when requirements already exist for the same
      `source_text_hash`; stores `extracted_by_provider`, `extracted_by_model` and
      `prompt_version`.** Test: the same text is extracted exactly once.
      *Traces: FR-15 · FR-45 · NFR-2 · NFR-16 · NFR-17 · RK-3 · CHG-20 · `research.md` §14.3.*
- [ ] **T-058 `[P]` `RequirementWeigher`** — 1.0 / 0.6 / 0.25; soft cap 15 %.
      *Traces: FR-16 · Q3(a).*
- [ ] **T-059 `HeuristicSkillScorer`** — `c_r` from a **confirmed** `SkillRelationSet` (1.0
      alias / 0.5 family / 0), × κ × π. With κ = π = 1 reproduces v1 exactly (test). A pending
      relation never grants credit (test).
      *Traces: FR-16 · FR-44 · CHG-8 U-1 · Q3(b).*
- [ ] **T-060 `SemanticScorer` — requirement-level MaxSim** over a `SimilarityMatrix` VO:
      `S = 100·(0.4·S_title + 0.6·S_resp)`, calibrated `norm()`; per-responsibility best bullet
      stored in `s_components`. Test that `(x+1)/2` would collapse the range (RK-1).
      *Traces: FR-17 · FR-45 · CHG-8 U-2 · RK-1 · SC-4.*
- [ ] **T-061 `[P]` `DeterministicScorer`** — 40/30/15/15 renormalised; `D = 94` case.
      *Traces: FR-18.*
- [ ] **T-062 `[P]` `CapLadder`** — 55 / 55·65 / 69; `69.55 → 70` case.
      *Traces: FR-19 · FR-20 · FR-27.*
- [ ] **T-063 `MatchScoreCalculator` + determinism test** — recompute from stored components with
      all ports faked.
      *Traces: FR-19 · FR-20 · NFR-1 · SC-2.*
- [ ] **T-064 `[P]` `DiscoveryScorer` — `D_disc`** — 0.8825 case; runs over the subset lacking
      text; measure (do not change) F's weight.
      *Traces: FR-13 · CHG-1 · CHG-3.*
- [ ] **T-065 `[P]` `BandClassifier`** — 85/75/70/60.
      *Traces: FR-21.*
- [ ] **T-066 `RescorePostingHandler` + `StudioRescoreCommand`** — zero provider calls.
      *Traces: FR-37 · NFR-9 · SC-2.*
- [ ] **T-067 `[P]` `DismissRequirementHandler`.**
      *Traces: `research.md` §5 · RK-3.*
- [ ] **T-156 `NarrateMatchAgent` + `NarrateMatchHandler`** — the 2–4-sentence "why / gaps"
      narrative for a scored posting (≥ 60), built **only** from the stored breakdown and ✓/✗
      requirements; structured output with no numeric fields (a test asserts that no number in the
      narrative differs from the stored breakdown). Uses the cached `CvSnapshotLayer` through
      `AiCallExecutor`. If every provider fails, the posting is shown without narrative and its
      numbers are unchanged. *(Added by `analyze.md` GAP-B1.)*
      *Traces: US-4 · FR-15 · NFR-2 · NFR-3 · NFR-16 · NFR-17 · CHG-20.*
- [ ] **T-123 `EvidenceRetriever` (U-3)** — top-3 bullets per requirement and responsibility from
      the MaxSim pass; an embedding hit on an unrelated skill creates a **pending** relation
      proposal, never credit.
      *Traces: FR-44 · FR-45 · CHG-8 U-3 · A-11.*
- [ ] **T-124 `[P]` Relations inbox handlers + optional ESCO import** — confirm / reject pending
      relations; `studio:import-esco-skills {csv}` creates `origin = esco`, `status = pending`
      only, ESCO version recorded.
      *Traces: CHG-12 · FR-44 · RK-15 · Q16.*

---

## Phase H2 — Opportunity layer (US-9)

**Gate:** V-1 decided (Q19). Fit (Phase H) green.

- [ ] **T-125 `OpportunityPolicy` value object** — parsed from `studio_profiles.rules.opportunity`;
      rejects any factor outside `[0.1, 1]` or missing `grade`/`source`; `neutral` mode ⇒ Q = 1.
      *Traces: CHG-16 · FR-51 · NFR-14.*
- [ ] **T-126 `[P]` `FreshnessDecay`** — `max(floor, 2^(−age/h))`, unknown age → policy value;
      injected `Clock`; boundary tests.
      *Traces: CHG-9 · CHG-17 · FR-46.*
- [ ] **T-127 `[P]` `GhostSignalDetector`** — no date · age > 30 d · re-listed from sightings ·
      evergreen wording in **ES / EN / PT** · intermediary-only; capped by policy.
      *Traces: CHG-9 · CHG-16 · FR-46.*
- [ ] **T-128 `[P]` `TitleLevelClassifier`** — EN/ES/PT seniority lexicon (junior/júnior,
      mid/pleno, senior/sénior, lead, staff, principal, head, architect/arquiteto/arquitecto) →
      step relative to the profile band.
      *Traces: CHG-9 · FR-46 · Q13.*
- [ ] **T-129 `[P]` `BetaBinomialEstimator` + evidence gate** — below `n < 30` or `k < 3` returns
      the policy value with a 90 % credible interval flagged "not applied"; above, `N0 = 100`.
      Test with the real seniority data (0/7 vs 1/9) proves the factor stays at policy.
      *Traces: CHG-16 · FR-48 · FR-51 · `research.md` §17.2.*
- [ ] **T-130 `OpportunityCalculator`** — builds `o_components` (value, grade, source,
      `applied_from`, n, k, reason) and `opportunity_static`.
      *Traces: CHG-9 · CHG-16 · FR-46 · NFR-14.*
- [ ] **T-131 `RefreshApplyPriorityHandler`** — materialises `apply_priority` when
      `priority_computed_at` is older than 6 h; portable `ORDER BY apply_priority`. **SC-13:**
      neutral mode reproduces pure fit order exactly.
      *Traces: CHG-17 · FR-47 · SC-13.*
- [ ] **T-132 `RecomputeChannelBaselinesHandler`** — triggered by `RecordOutcomeHandler`; never
      writes `total_score`, `raw_score` or any fit component (NFR-13 test). **SC-14:** loading the
      16 historical applications leaves every factor at policy.
      *Traces: CHG-16 · FR-48 · NFR-13 · SC-14.*

---

## Phase I — CV structure, audit, rewrite (US-1, US-2)

- [ ] **T-068 `CvStructureParserPort` + `LaravelAiCvStructureParser`** (structured output),
      **plus the read seams it depends on: `CvSourcePort` + `EloquentCvSource` (reads
      `Modules\Cvs`: `raw_text`, `is_primary`, `user_id`) and `ProjectSourcePort` +
      `EloquentPortfolioProjectSource` (from T-008's contract).** Read-only; neither module is
      modified. *(Added by `analyze.md` GAP-A1.)*
      *Traces: FR-1 · FR-4 · Q4 · plan §3.2.*
- [ ] **T-069 `ConfirmCvStructureHandler`** — unusable until confirmed.
      *Traces: FR-4 · RK-6.*
- [ ] **T-070 `[P]` `AtsStructureChecker`** — columns, tables, images, headers/footers, fonts,
      headings, `MM/YYYY`, page count, selectable text.
      *Traces: FR-2 · FR-6 · SC-6.*
- [ ] **T-071 `CvJudgePort` + `LaravelAiCvJudge`** — verdict with reasons, ≤ 6 metric questions.
      *Traces: FR-2 · NFR-3.*
- [ ] **T-072 `[P]` `SubmitMetricAnswersHandler`.**
      *Traces: FR-3.*
- [ ] **T-073 `CvRewriterPort` + `LaravelAiCvRewriter`** — protected block, 2-page ceiling,
      provenance, no absent skill, front-loading, skills echoed in bullets; fed by T-123 evidence.
      *Traces: FR-5…FR-8 · R1.2 · R1.3.*
- [ ] **T-133 `[P]` Audit extensions** — tailored headline title, age-bias dates, gaps > 6 months,
      career progression, skill evidenced in an accomplishment (deterministic); credibility
      narrative (LLM, unscored); `parse_recovery_ratio` measured as token recall of the
      re-extracted export vs intended text, shown apart from `content_issues`.
      *Traces: CHG-13 · FR-38 · FR-39.*

---

## Phase J — Tailoring and export, ES / EN / PT

- [ ] **T-074 `PhpWordExportAdapter`** behind the Shared `ExportPort` — first consumer of
      `phpoffice/phpword` here.
      *Traces: FR-22 · RK-7.*
- [ ] **T-075 `[P]` `CvVersionPdfExport`** via `DomPdfExportAdapter` +
      `resources/views/exports/pdf/cv_job_studio.blade.php`.
      *Traces: FR-22.*
- [ ] **T-076 PDF text-extraction verification** (`smalot/pdfparser`) → `text_extraction_verified`.
      *Traces: FR-6 · SC-5.*
- [ ] **T-077 `TailorCvHandler`** — re-order/re-weight, nothing absent, linked to the posting.
      `TailorCvAgent` through `AiCallExecutor`, with the cached `CvSnapshotLayer` + protected
      block; the posting's requirements and evidence form the tail.
      *Traces: FR-8 · FR-22 · FR-24 · NFR-16 · CHG-20.*
- [ ] **T-078 `[P]` es / en / pt-PT variants** + untranslatable-terms list. `TranslateCvAgent`
      with the terms list + version content as the long-lived layer and the target language as
      the tail, so the three languages share one cached prefix.
      *Traces: FR-22 · FR-23 · NFR-16 · CHG-20.*
- [ ] **T-079 `[P]` Structural assertions** on every DOCX and PDF in all three languages.
      *Traces: SC-6 · FR-6.*

---

## Phase K — Applications and market insights (US-6)

- [ ] **T-080 `[P]` Status and outcome handlers** — separate fields; never guess.
      *Traces: FR-25.*
- [ ] **T-081 `[P]` `StudioAgeOutcomesCommand`** — 21-day ageing for analysis only.
      *Traces: FR-29.*
- [ ] **T-082 `RequirementFrequencyCounter`** — every posting read incl. capped/skipped, excl. G2.
      *Traces: FR-26.*
- [ ] **T-083 `[P]` `ReachCalculator`.**
      *Traces: FR-27 · SC-10.*
- [ ] **T-084 `OutcomeCorrelator`** — suppressed below 8 usable outcomes. **Corrected data: 16
      applications, 1 positive (screening), 1 rejection, 14 silent** (`research.md` §10) — the
      first report must say the sample is insufficient.
      *Traces: FR-28 · NFR-3 · `research.md` §10.*
- [ ] **T-085 `BuildInsightReportJob`** — every run, zero-match included; never proposes an
      absent skill.
      *Traces: FR-26…FR-28 · SC-1.*
- [ ] **T-134 `[P]` Own-rate panel** — per channel / level bucket: applications, positives,
      rate with 90 % credible interval, and whether the evidence gate is passed. Display only.
      *Traces: FR-48 · FR-51 · NFR-14.*

---

## Phase L — HTTP surface

- [ ] **T-086 `CvJobStudioServiceProvider`** — `registerWebRoutes()` + `registerApiRoutes()`,
      port bindings (incl. `SimilaritySearchPort`), rate limiters; `bootstrap/providers.php`.
      *Traces: `ARCHITECTURE-PHP` provider rules · NFR-12 · plan §5.*
- [ ] **T-087 `[P]` Spatie `Data` DTOs** — no `JsonResource`; heuristic label on every score;
      grade + source on every opportunity factor.
      *Traces: NFR-3 · NFR-14.*
- [ ] **T-088 `[P]` Controllers + FormRequests** per `plan.md` §5; `/export` before `/{uuid}`;
      `whereUuid`; `auth` + `permission:*`.
      *Traces: NFR-7.*
- [ ] **T-089 `[P]` Bulk delete / restore.**
      *Traces: project bulk-operations rule · plan §5.*
- [ ] **T-090 `[P]` Permissions, policies, cross-user test.**
      *Traces: NFR-7 · SC-9.*
- [ ] **T-091 `[P]` `LogsActivity`** (v5 namespaces) on profile, posting status, outcome,
      relation confirmation, **policy edits**.
      *Traces: plan §8 · CHG-15 · FR-51 · project audit rule.*
- [ ] **T-135 `[P]` Round-2/3 routes** — relations inbox (confirm/reject), unlink resolution,
      opportunity policy update (validated by `OpportunityPolicy`), neutral toggle.
      *Traces: CHG-11 · CHG-12 · CHG-16 · FR-51.*

---

## Phase P — Frontend (NFR-15; plan CHG-14)

**Rules:** `FRONTEND/SKILL.md` + `ARCHITECTURE-VUE/SKILL.md` read first; Volt primitives only;
tokens only; `<script setup lang="ts">`; zero `any`; mobile-first; WCAG AA; Pinia Colada for
server state with `staleTime` + `gcTime`.

- [ ] **T-136 Generate types** — `php artisan typescript:transform` → `resources/js/generated/`.
      No hand-written mirrors of backend `Data`.
      *Traces: project TypeScript rule · NFR-15.*
- [ ] **T-137 `[P]` `cv-studio/Dashboard`** — KPI tiles incl. reply rate **with n and interval**;
      next best applications.
      *Traces: US-6 · US-9 · NFR-14 · FR-33.*
- [ ] **T-138 `[P]` `cv-studio/Postings/Index`** — lazy `DataTable` (desktop) / cards (mobile);
      `FitScoreBadge`, `OpportunityChips` (grade + source tooltip), filters, bulk actions; a second
      tab **"Open manually"** listing references (link opens in the candidate's own browser) with a
      **Paste JD** dialog (T-158).
      *Traces: FR-21 · FR-47 · FR-53 · NFR-14 · NFR-15.*
- [ ] **T-139 `[P]` `cv-studio/Postings/Show`** — match report: ✓/~/✗ checklist with evidence
      quotes, H/S/D bars, opportunity factors with grade/n, caps + uncapped, **Apply direct**,
      unlink, dismiss requirement.
      *Traces: US-4 · US-9 · US-10 · FR-20 · FR-45 · FR-49 · NFR-3.*
- [ ] **T-140 `[P]` `cv-studio/Cvs/Audit` + `Cvs/Structure`** — two-tier audit; metric questions;
      structure confirmation.
      *Traces: US-1 · FR-2 · FR-3 · FR-4 · FR-38 · FR-39.*
- [ ] **T-141 `[P]` `cv-studio/Versions/Diff`** — per-bullet diff + provenance; accept/reject;
      export `docx|pdf` × `es|en|pt-PT`.
      *Traces: US-2 · US-5 · FR-8 · FR-22 · FR-23.*
- [ ] **T-142 `[P]` `cv-studio/Applications/Board`** — kanban; aged-silence badge; sent version.
      *Traces: US-6 · FR-24 · FR-25 · FR-29.*
- [ ] **T-143 `[P]` `cv-studio/Runs/Show`** — stage progress (polling), funnel, spend.
      *Traces: NFR-4 · NFR-5.*
- [ ] **T-144 `[P]` `cv-studio/Insights/Show`** — frequency `n/N`, reach table, correlation
      (suppressed < 8), own-rate panel (T-134).
      *Traces: US-6 · FR-26 · FR-27 · FR-28 · FR-48.*
- [ ] **T-145 `[P]` `cv-studio/Settings/Profile`** — gates, baseline, protected block, **policy
      editor showing grade and source per value**, neutral toggle, relations inbox.
      *Traces: US-7 · US-9 · FR-30 · FR-44 · FR-51.*
- [ ] **T-146 Frontend audit pass** — `/frontend-audit` until 100 %.
      *Traces (phase): NFR-15 · NFR-3 · NFR-14 · US-1 · US-5 · US-6 · US-9 · US-10 · CHG-14.*

---

## Phase M — Validation and calibration

- [ ] **T-092 Historical corpus as fixtures** — 33 postings and **16 applications** from the
      fullstack `job-search-cache.json`; held-out test set, not production data (Q10).
      *Traces: SC-3 · SC-14 · Q10 · `research.md` §10.*
- [ ] **T-093 Band agreement (SC-3)** — real number; every disagreement explained from the stored
      breakdown.
      *Traces: SC-3 · NFR-1.*
- [ ] **T-094 Rules v1 → v2 delta (SC-4)** + calibrate `S` floor/ceiling (replaces 0.30/0.85).
      *Traces: SC-4 · RK-1 · OD-1.*
- [ ] **T-095 `[P]` Requirement-extraction precision/recall** (RK-3).
      *Traces: RK-3 · FR-15.*
- [ ] **T-096 `[P]` Dedup validation** — one job, many sources, no false merges.
      *Traces: CHG-2 · FR-10 · T-015.*
- [ ] **T-147 `[P]` Fit order vs priority order on the 16 applications** — **descriptive only**:
      how each policy option (Q19 a/b/c) re-orders them, with no claim of predictive validity
      (n = 16 cannot support one — `research.md` §17.2).
      *Traces: SC-13 · RK-16 · `research.md` §17.2.*
- [ ] **T-148 Portal × keyword yield experiment** — extends T-031 per access mode: unique,
      gate-passing, ≥ 70 postings per euro per portal cell; result into `research.md`.
      *Traces: CHG-18 · FR-52 · user question 4 · `research.md` §17.5.*

---

## Phase N — Observability and budget

- [ ] **T-097 `StudioBudgetLedger`** — modelled on LeadScout's `BudgetLedger`.
      *Traces: FR-33 · SC-8.*
- [ ] **T-098 `[P]` `studio_provider_calls`** — cost and status, never payloads.
      *Traces: NFR-4.*
- [ ] **T-099 `[P]` Run progress and spend surface.**
      *Traces: NFR-4 · NFR-5.*
- [ ] **T-100 `[P]` Budget refusal under concurrency.**
      *Traces: SC-8.*

---

## Phase O — Closeout

**Gate:** every task above checked, or its gap logged.

- [ ] **T-101 `CvJobStudioArchTest`** — layer imports; no `JsonResource`; no scoring or
      opportunity literal outside `rules`/config; **no request path to any `link_only` /
      `resolve_only` host**; no MCP reference.
      *Traces: SC-9 · SC-11 · SC-16.*
- [ ] **T-102 Traceability sweep (SC-12)** — every FR/NFR/SC → passing test; gaps become tasks.
      *Traces: SC-12 · `analyze.md`.*
- [ ] **T-103 Module tests, real result** — `php artisan test --compact --filter=CvJobStudio`,
      plus the `pgsql` group on `pgsql_testing`. Not the full suite.
      *Traces: project test-enforcement rule · CHG-17.*
- [ ] **T-104 Finalization pipeline** — `optimize:clear` → `ide-helper:generate` →
      `ide-helper:models --write` → `typescript:transform` → `pint` → `pint --test` →
      `scramble:clear` → `scramble:export` → `scramble:cache`; re-run module tests if docblocks
      changed.
      *Traces: project module-finalization rule.*
- [ ] **T-105 Re-index the code graph** — `index_repository(mode: "full")`, verify `index_status`.
      *Traces: project code-graph rule.*
- [ ] **T-106 `SSD-SUMMARY.md` (Phase 8)** — `analyze.md` (Phase 6) is already written; update it
      if implementation surfaces new gaps.
      *Traces: SDD workflow Phase 8.*

---

## Coverage of what the user asked

| Ask | Tasks |
|---|---|
| Verify priors before coding | **V-1**, T-125, T-129, T-132, T-147 |
| Vector index as the first technical test | **V-2**, **T-000**, T-052, T-121 |
| Treat external figures as evidence, not rules | **V-3**, T-125, T-134, T-087 |
| Portals (Tecnoempleo, Indeed, Net-Empregos, LinkedIn, RemoteRocketship…) or keywords | **V-5**, T-010, T-029, T-107, T-113…T-117, T-120, **T-148** |
| Full formula (fit) | T-055…T-067, **T-059**, **T-060**, T-123 |
| Opportunity layer | **Phase H2** |
| Apply at source | **T-118**, T-119, T-139 |
| LinkedIn / GDPR restrictions | T-045, T-107, T-117, T-120, T-101 |
| ES / EN / PT export (PhpWord) | T-003, **T-074**, T-078, T-079 |
| UX / UI like Jobscan / AIApply / Enhancv | **Phase P** |
| Laravel AI SDK prompt caching + failover "si amerita" to save tokens | **Phase H0** (T-150…T-155), T-057 |
| LinkedIn / Indeed / Glassdoor / Tecnoempleo as signals → official copy; priority API → portal → search → scraping | **T-118**, T-158, T-160, T-159, T-045, V-5 |

## Sequencing

```
Phase 0 (verification gate: V-1 · V-2 ✓ · V-3 ✓ · V-4 · V-5)
    │
    ▼
A (spikes) ──► B (registry · identity · provenance) ──► C: T-000 FIRST ──► migrations
                                                            │
      ┌─────────────────────────────┬───────────────────────┼─────────────────┐
      ▼                             ▼                       ▼                 ▼
 D (keywords × portals)      E (discovery + resolve)    G (similarity)    I (CV audit)
      │                             │                       │                 │
      └──────────► F (guards) ◄─────┘                       ▼                 ▼
                                                  H0 (AI cache+failover)       │
                                                            ▼                 ▼
                                                        H (fit) ──► H2 (opportunity)
                                                            │                 │
                                     J (export) ◄───────────┘                 │
                                         │                                    │
                                         ▼                                    ▼
                                     K (insights) ──► L (HTTP) ──► P (frontend)
                                                                      │
                                                  M (validation) ◄────┘ ──► N ──► O
```

Critical path: **Phase 0 → C (T-000) → G → H0 → H → H2 → M.**

## Explicitly NOT in this plan

- Trainer, video-content and other flows (Q11 — later spec).
- Automatic application submission, or any user-session login to a third-party site.
- Fetching any `link_only` or `resolve_only` host — LinkedIn, Indeed, Tecnoempleo, Glassdoor,
  aggregators (T-045 enforces).
- MCP on any production path (T-046).
- A reranker in the fit score or shortlist (Q17).
- Full ESCO import as confirmed data (Q16 — pending only).
- Per-ATS advice (OD-2). A conversational "ask my CV" surface (Q6). A scheduler (Q5).
- Any opportunity magnitude presented as a statistic (FR-51).

---

**⛔ Stop here.** 160 tasks (T-000 … T-160; T-050 withdrawn; T-115/T-116 not built by default)
+ 5 verification items (V-1, V-2, V-3, V-5 done), no code. Remaining gate: **V-4 set-up** (local
PG17 + pgvector) and **T-002** (`pg_trgm`/`unaccent` probe) — then Phase A, and the first line of
module code is **T-000**.
