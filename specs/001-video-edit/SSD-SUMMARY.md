# SDD summary: Video Edit (001-video-edit)

> Phase 8 · CONSOLIDATE — the whole journey in one document. Each section links
> to its source file; nothing here requires opening them.

**Feature ID:** 001-video-edit · **Module:** `src/Modules/VideoEdits`
**Dates:** specified → implemented 2026-09-11 / 2026-09-12
**Status:** V1 backend implemented and green, except the real FFmpeg adapter, which is blocked on FFmpeg being installed (see §7).

---

## 1. Specify → [spec.md](./spec.md)

**What it is.** A backend module that physically edits recorded videos. It merges clips, removes silences and removes an explicit list of time ranges, asynchronously, and keeps a durable record of what was removed and why.

**The product has three modes, delivered in three versions** (spec §1, §4):

| Mode | Does | Version |
| --- | --- | --- |
| Merge | Joins clips. No content analysis. | **V1** |
| Auto Edit | Removes silences + manual ranges (V1); fillers, stutters, vocal sounds from transcription (V2) | **V1** / V2 |
| AI Edit | An AI provider reads the transcript and an optional `.md`/`.pdf` script and returns structured edit decisions; report + PDF | V3 |

**Binding delivery principle** (spec §4.1): *"Implement V1 without Whisper/Gemini, but design the domain and interfaces so that Whisper-based semantic editing and Gemini-based AI editing can be added later without changing the core video editing pipeline."*

**Actors.** Content creator · background processor · scheduled cleanup · (V2) transcription service · (V3) AI provider.

**V1 user stories.** US-1 merge · US-2 silence removal (1 s default) · US-3 manual ranges · US-4 status and progress · US-5 result + summary · US-6 history · US-7 re-edit from previous settings · US-8 retry a failed edit · US-9 permanent delete.
**Roadmap stories.** US-10/11 (V2 speech + transcript reuse) · US-12/13/14 (V3 AI edit, pause markers, report + PDF).

**Requirements.** 21 functional (FR-1…FR-21) plus **9 extensibility requirements (EX-1…EX-9)** that V1 had to satisfy so V2/V3 plug in without touching the core.

---

## 2. Clarify → [clarify.md](./clarify.md)

**User decisions.**

| # | Decision |
| --- | --- |
| Q1 | V1 = merge + silence removal + manual ranges. No Whisper, AI or PDF. |
| Q2 | Edits persist in the database (history, re-edit), with **hard delete** from the frontend. |
| Q3 / Q3b / Q3c | Sources are **not** kept: deleted when the edit finishes; kept **24 h** after a failure so it can be retried. "Re-edit" means a new edit prefilled with the old settings. |
| Q4 | **Frame-accurate** cuts; quality first for spoken content. |
| Q5 | Hard delete removes record, cuts, result and sources; only audit entries remain. |
| Q6 / Q6b | Frontend will use shadcn-vue + Zod 4 + Pinia Colada + TanStack Vue Form. Global rules intentionally left unchanged. |
| Q7 | **No memory limits and no low-memory mode.** Targets: Railway 8 GB, local 32 GB. |
| Q8 | The spec covers V1, V2 and V3; only V1 is built. |
| P1 | Manual ranges are checked against the real duration when the job starts; failures report per range and **retry accepts corrected ranges**. |
| P2 | The unused `VIDEO_EXPORTS` permissions are replaced by `VIDEO_EDITS`. |
| P3 | **Redis (Upstash)** queue in dev and prod, separate instances, no Horizon but Horizon-compatible. |
| P4 | No Sanctum/mobile API in V1. |

**Resolved by default (D1–D17)**, all configurable: direct-to-R2 uploads; ≤ 2 GB per file, 10 files, 90 min; threshold 0.3–10 s; 150 ms speech margin; 250 ms fragment floor; 1 s minimum output; ranges on the merged timeline; one active edit per user; 15-min download links; 2 retries and a 60-min timeout; **drafts never submitted expire after 24 h** (D17, raised during Analyze).

**Open for V2/V3 (R1–R10):** what counts as a "vocal sound", whether wind noise stays, languages, whether AI cuts apply automatically and above what confidence, what to do with "REDUCIR" suggestions, consent before sending content to a provider, and whether raw AI responses are stored.

---

## 3. Research → [research.md](./research.md)

Verified live (Tavily / Context7 / repo inspection; §0 marks the source of each finding).

- **`pbmedia/laravel-ffmpeg` 8.9.0** (2026-02-24) supports Laravel 13 and PHP 8.5; it wraps `php-ffmpeg` 1.4.0. It covers merging, disk I/O, progress, raw process output and typed errors — but has **no multi-range cut API, no silence helper and no test fakes**, and its README still says it was only tested with FFmpeg 4.4/5.0. Issue #505 reports long commands failing.
- **Cutting technique:** `trim`/`atrim` + `setpts` + `concat` re-encodes and is frame- and sample-accurate; `-c copy` snaps to keyframes and can drift. A filtergraph can be loaded from a file (`-/filter_complex`), which avoids the command-length limit.
- **This repo:** PostgreSQL 17.6 (Supabase) — so a **partial unique index** can enforce one active edit per user; `StoragePort` already had `temporaryUploadUrl`; `AuditPort`, the AI client and the PDF adapter already exist for V3.
- **Queue:** Laravel 13 job attributes; `retry_after` must exceed the job timeout; `block_for` must not be 0 (it blocks SIGTERM). Upstash is TLS-only with a single database, bills per command, and needs eviction disabled.
- **Deployment:** Railway installs FFmpeg via `RAILPACK_DEPLOY_APT_PACKAGES` and PHP extensions via `RAILPACK_PHP_EXTENSIONS`. Local PHP has `redis` but **no `pcntl`**, so job timeouts are not enforced on Windows.
- **The old `GUIDE/VideoExport` code:** its pure logic (time ranges, cut inversion, silence parsing) was worth porting; its cache-only job tracking, 554-line process runner and free-text AI review were not.

---

## 4. Plan → [plan.md](./plan.md)

**Shape.** Intermediate hexagonal module (`Domain` / `Application` / `Infrastructure`), justified by a multi-step lifecycle, two integrations and several child tables.

**Flow.** Browser creates a draft → uploads straight to R2 with pre-signed URLs → `submit` verifies the objects and queues one job → the job runs ordered stages:

```
Download → Merge → Analysis → Plan cuts → Render → Publish
                     ↑
        decision producers (the extension seam)
```

**Key decisions (AD-1…AD-16).** Direct uploads, never through PHP · one cut-decision contract for every producer · a high-quality intermediate when a merge is followed by cuts · `trim/atrim + concat` with the graph loaded from a file · **no low-memory mode**, replaced by a benchmark gate · hard delete with `AuditPort` instead of `SoftDeletes`/`LogsActivity` · atomic compare-and-set status changes plus a partial unique index · a config-driven Redis queue · two-step range validation with a correctable retry.

**Endpoints** (`/data/admin/video-edits`, session JSON, per-route permission and rate limit, owner-scoped so a foreign edit is 404): list · create draft · submit · detail · download link · retry · delete.

**Data model.** `video_edits` + `video_edit_sources` + `video_edit_cut_decisions` + `video_edit_applied_cuts`. Times in integer milliseconds; a SHA-256 per source (the V2 transcript-reuse key); rejected decisions stored for the V3 report.

**Documented deviations.** No soft delete; no `LogsActivity` on these models; no bulk actions.

---

## 5. Tasks → [tasks.md](./tasks.md)

| Phase | Tasks | Status |
| --- | --- | --- |
| A Foundations | T001–T007 | T002–T006 done · **T001, T007 need you** |
| B Data & domain | T008–T017 | **done** |
| C Create & submit | T018–T020 | **done** |
| D Pipeline | T021–T027 | T021–T025 done · **T026–T027 blocked on T001** |
| E Status, result, history | T028–T029 | **done** |
| F Re-edit & retry | T030–T031 | **done** |
| G Delete | T032 | **done** |
| H Cross-cutting | T033–T036 | **done** |
| I Closeout | T037–T040 | T038–T040 done · **T037 needs Railway** |

---

## 6. Analyze → [analyze.md](./analyze.md)

The spec ↔ plan ↔ tasks cross-check found **four issues, all fixed before implementation**:

- **A1/A2:** the spec and clarify still said ranges were checked at submit; corrected to job start (P1).
- **A3:** the internal `draft` state and 24 h draft expiry existed in the plan but nowhere in the spec → added as decision D17.
- **A4:** submit made two storage calls per video (up to 20), risking the "under 1 second" target → reduced to one call per video, with a measurement added to T037.

Deferred: showing range errors in the UI belongs to the frontend spec (A5). Accepted: the expired-link check is enforced by R2 (A6), and two extensibility requirements are design-level (A7).

**No orphan tasks and no unresolved contradictions.**

---

## 7. Implement

### What was built

- **Domain:** 7 enums, 11 value objects, 3 services (validating decisions, planning cuts, parsing silence output), 7 exceptions, 5 ports.
- **Application:** 8 command handlers, 3 query handlers, 13 DTOs, the pipeline with its producer registry, progress reporter and two V1 producers.
- **Infrastructure:** Eloquent models and repository, the queue job and dispatcher, the local workspace, the filter-graph builder, the controller, routes and two Artisan commands.
- **Outside the module:** migration (4 tables + partial unique index), a migration removing the obsolete permissions, seeder changes, `config/video-edit.php`, Redis queue and workspace disk config, the `User` relation, `StoragePort::size()`, and schedule entries.

### Tests — 191 in the module, all passing

| Requirement | Covered by |
| --- | --- |
| US-1 merge, output profile | `VideoEditPipelineTest`, `FilterGraphBuilderTest`, `DomainValueObjectsTest` |
| US-2 silence + margin | `CutPlannerTest`, `SilenceCutParserTest`, `DecisionProducersTest`, `VideoEditPipelineTest` |
| US-3 manual ranges, two-step validation | `VideoEditCreateTest`, `CutDecisionValidatorTest`, `VideoEditPipelineTest` |
| US-4 status, progress, timeout | `ProgressReporterTest`, `VideoEditShowListTest`, `VideoEditCleanupTest` |
| US-5 result + summary + link | `VideoEditDownloadTest`, `VideoEditShowListTest` |
| US-6 history | `VideoEditShowListTest` |
| US-7 re-edit | `VideoEditCreateTest`, `VideoEditShowListTest` |
| US-8 retry (+ corrected ranges) | `VideoEditRetryTest` |
| US-9 hard delete + audit | `VideoEditDeleteTest` |
| FR-9/FR-10 source lifecycle | `VideoEditPipelineTest`, `VideoEditCleanupTest` |
| FR-16 one active edit | `VideoEditPersistenceTest`, `VideoEditSubmitTest`, `EloquentVideoEditRepositoryTest` |
| FR-19/FR-20/FR-21 security | `VideoEditSubmitTest`, `VideoEditAccessTest`, `VideoEditPipelineTest` |
| **EX-1…EX-3 roadmap readiness** | `VideoEditRoadmapReadinessTest` — a new producer is added by registration alone and flows through validation, planning, rendering, storage and deletion unchanged |

**Full project suite:** 1,229 tests — 1,223 passed, 6 skipped, 0 failed, 3,851 assertions.

### Finalization (T039)

`optimize:clear` · `ide-helper:generate` · `ide-helper:models --write` (rewrote `User.php` and `CompanyData.php`) · `typescript:transform` (the module's Data classes and enums are in `resources/js/generated/generated.d.ts`) · `pint` (fixed 2 docblocks) · `pint --test` **passed** · `scramble:clear` / `export` (`api.json`) / `cache` · module tests re-run after the docblock rewrite: **191/191**.

Code graph re-indexed with `mode: full` → 13 863 nodes / 49 648 edges (`status: indexed`).

**Notes.**
- `.cbmignore` was missing from the working tree and was restored from git before re-indexing, since it is what keeps build artifacts out of the code graph. A stray deletion, `!values.is_paid`, was left untouched for the owner to decide.
- The indexer reports three of this module's files as *parse_partial*: `Domain/ValueObjects/ValidatedDecision.php:40`, `Tests/Unit/CutPlannerTest.php:119,124` and `Tests/Feature/EloquentVideoEditRepositoryTest.php:99`. Those lines are the PHP 8.5 `clone($this, [...])` and `(void)` constructs that tree-sitter cannot parse yet — the code is valid and tested; only the graph may miss those constructs, so use grep there rather than trusting a negative graph answer.

### Gaps and what is left

| # | Item | Why |
| --- | --- | --- |
| 1 | **T026 — the real FFmpeg adapter** | Needs a binary to verify three things: how `laravel-ffmpeg` orders command arguments, whether `X264` forces a bitrate over CRF, and whether FFmpeg accepts reusing `[0:v]` across trims. Everything around it is finished and tested against a fake editor. |
| 2 | **T027 — integration tests** with generated fixtures | Same dependency (T001). |
| 3 | **T037 — the performance gates** | 20-min 1080p with 200 cuts on the 8 GB Railway worker (risk R2), submit response time, and expired-link behaviour. |
| 4 | **T001 / T007 — install and infrastructure** | FFmpeg ≥ 7.1 locally; separate Upstash databases with eviction off; FFmpeg and the `redis`/`pcntl` extensions on the worker. |
| 5 | **Supabase migration not run** | The tables exist only in the test database. The migration is verified on SQLite; running it against Supabase needs your go-ahead. |
| 6 | **Frontend** | A separate spec (shadcn-vue + Zod 4 + Pinia Colada + TanStack Vue Form), including pre-validating ranges in the browser (Analyze A5). |

### Known risks carried forward

- **R2 — memory:** a 200-cut render has not been measured on 8 GB. Per Q7 there is no silent fallback: if the benchmark fails, it comes back to you as a decision.
- **R1 — FFmpeg version:** upstream only tests 4.4/5.0; our own integration tests are the gate.
- **R3 — no PCNTL on Windows:** job timeouts are not enforced locally; the scheduled sweep is the backstop.
- **R9/R10 — Upstash:** per-command billing with an always-on worker, and eviction must stay disabled.
