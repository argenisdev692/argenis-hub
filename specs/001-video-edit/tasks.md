# Tasks: Video Edit (V1)

> Phase 5 · BREAK DOWN TASKS — splits `plan.md` into small, verifiable steps.
> Each task leaves the system compiling and testable. `[P]` = parallelizable (no shared files, no dependency).
> `[USER]` = needs an action only the user can take (installs, cloud consoles). Tests are Pest 5; run with `php artisan test --compact --filter=…`.

**Feature ID:** 001-video-edit
**Based on:** plan.md (AD-1…AD-16), spec.md, clarify.md

---

## Phase A — Foundations

- [ ] **T001 [USER]** Install FFmpeg + FFprobe **≥ 7.1** locally (Windows) and record the exact version. Set `FFMPEG_BINARIES` / `FFPROBE_BINARIES` in `.env`. *Verify:* `ffmpeg -version` and `ffmpeg -h full` lists option-from-file support. → plan §2, R1, R6
- [x] **T002** `php artisan vendor:publish --provider="ProtoneMedia\LaravelFFMpeg\Support\ServiceProvider"`. Read the published `config/laravel-ffmpeg.php` and wire `binaries`, `timeout` (3600), `threads`, `log_channel` and `temporary_files_root` to env/config. Resolves the `[UNVERIFIED]` config keys. → plan §2
- [x] **T003 [P]** Create `config/video-edit.php`: limits (10 sources, 2 GB, 90 min, 500 ranges, allowed ext/MIME/containers), silence (default 1.0, 0.3–10, noise −30 dB, padding 150 ms), cuts (min fragment 250 ms, min output 1 s), output profile (1920×1080 max, fps cap 60, x264 CRF 20 medium, intermediate CRF 14 veryfast, AAC 192k 48 kHz stereo), queue (connection/name from env, timeout 3600, tries 3, backoff [60, 300]), retention (failed sources 24 h, drafts 24 h, stuck 65 min), URL TTLs (upload 60 min, download 15 min), workspace root. → clarify D2–D16, AD-15
- [x] **T004 [P]** Redis queue wiring (AD-15, P3). *Verified 2026-09-11: runtime prefix `argenishub-local-queue-` on the `queue` connection vs global `argenishub-database-`. `.env.example` not editable (permission) — keys handed to the user.*
  - `config/database.php` gets a redis `queue` connection (`REDIS_QUEUE_*` → `REDIS_*` fallback, db 0, env-scoped prefix; verify per-connection prefix is honored by phpredis).
  - `config/queue.php` gets the `video-edits` connection (redis, `connection: queue`, `queue: video-edits`, `retry_after: 3900`, `block_for: 5`, `after_commit: true`).
  - `phpunit.xml` gets `VIDEO_EDIT_QUEUE_CONNECTION=sync`.
  - Provide `.env.example` placeholder keys (manually if the file is not editable).
  - *Verify:* `php artisan config:show queue.connections.video-edits`.
- [x] **T005 [P]** `config/filesystems.php`: `video-edit-workspace` local disk (not served, root from `VIDEO_EDIT_WORKSPACE_ROOT`, default `storage/app/video-edit-workspace`). → AD-12
- [x] **T006** Module skeleton: `src/Modules/VideoEdits/Providers/VideoEditsServiceProvider.php` with `registerWebRoutes()` and an empty `Infrastructure/Routes/web.php`, registered in `bootstrap/providers.php`. *Verify:* `php artisan route:list --path=data/admin/video-edits` runs without error.
- [ ] **T007 [USER]** Infra checklist:
  - **Upstash:** separate dev and prod databases; **eviction disabled** on both (R10); credentials per environment (R11).
  - **Railway worker service:** `RAILPACK_DEPLOY_APT_PACKAGES="... ffmpeg"`, `RAILPACK_PHP_EXTENSIONS` including `redis,pcntl`, start command `php artisan queue:work video-edits --queue=video-edits --timeout=3600 --tries=3 --memory=4096`.
  - *Verify:* the worker logs show FFmpeg found and the connection established.
  - → research §9.4, §10

## Phase B — Data model & domain core

- [x] **T008** *(verified on SQLite via `VideoEditPersistenceTest`; running it on Supabase waits for user confirmation)* Migration `create_video_edits_tables`: `video_edits`, `video_edit_sources`, `video_edit_cut_decisions`, `video_edit_applied_cuts` exactly as plan §4, including the **partial unique index** `video_edits_one_active_per_user` (raw statement valid on PostgreSQL + SQLite) and all listed indexes. *Verify:* `php artisan migrate` locally + `php artisan db:table video_edits`.
- [x] **T009** Eloquent models (4) under `Infrastructure/Persistence/Eloquent/Models`:
  - `final`, `@internal`, `$fillable`, `$hidden = ['id']`, `casts()` method (enums, json, datetimes), `HasUuids` on `uuid`, typed relations.
  - **No SoftDeletes / LogsActivity (D-1, D-2).**
  - Add `App\Models\User::videoEdits()` + `@property-read` PHPDoc.
- [x] **T010** Factories for `VideoEdit` (states: draft, queued, processing, completed, failed, failedWithExpiredSources, invalidCutRanges) and `VideoEditSource`.
- [x] **T011 [P]** Domain enums `VideoEditMode` (`isAvailable()`: ai_edit false), `VideoEditStatus` (`canTransitionTo()`, `isActive()`), `ProcessingStage` (weights sum 100), `CutReason`, `DecisionOrigin`, `DecisionOutcome` + unit tests `VideoEditStatusTest`, `ProgressWeightsTest`. → EX-4, EX-7
- [x] **T012 [P]** Domain value objects `TimeRange`, `SilenceThreshold`, `CutDecision`, `AppliedCut`, `CutPlan`, `MediaProbe`, `OutputProfile`, `ContentFingerprint` (readonly, property hooks for invariants) + unit tests `TimeRangeTest`, `SilenceThresholdTest`. → EX-1
- [x] **T013** `CutDecisionValidator` (start ≥ end, negative, beyond duration, confidence ∉ [0,1] → rejected with reason) + `CutDecisionValidatorTest`. An unknown reason cannot reach the validator because `CutReason` is a typed enum; raw V3 AI payloads map strings with `CutReason::tryFrom()` at the producer boundary and reject unknown values there. → FR-4, EX-3
- [x] **T014** `CutPlanner` (ported from `GUIDE/.../CutPlanner`, adapted): merges overlapping and adjacent cuts with the union of reasons and origins, applies silence padding, absorbs fragments < 250 ms, enforces ≥ 1 s output, and computes totals + keep ranges. Test: `CutPlannerTest`. → FR-2, FR-4, FR-5
- [x] **T015 [P]** `SilenceCutParser` (ported from `GUIDE`) + `SilenceCutParserTest` with real `silencedetect` stderr samples (including trailing silence without `silence_end`). → FR-2
- [x] **T016** Ports `VideoEditRepositoryPort` (incl. atomic `transitionStatus(uuid, from[], to, attributes): bool`), `VideoEditorPort`, `CutDecisionProducer`. `EloquentVideoEditRepository` implements the repository port and is bound in the provider. Add `Tests/Support/FakeVideoEditor.php`. → AD-9, AD-10
- [x] **T017 [P]** `StoragePort::size(string $path): int` + `R2StorageAdapter` implementation + test with `Storage::fake('r2')`. → AD-2

## Phase C — US-1 / US-2 / US-3: create draft & submit

- [x] **T018** Request DTOs: `CreateVideoEditData`, `SilenceRemovalData`, `ManualRangeData`, `SourceUploadData`, `RetryVideoEditData`, `VideoEditFilterData`. Response DTOs: `VideoEditListItemData`, `VideoEditDetailData`, `VideoEditSourceData`, `AppliedCutData`, `CutDecisionData`, `UploadTargetData`, `CreatedVideoEditData`, `DownloadUrlData`. All use `SnakeCaseMapper` in and out, with validation attributes per plan §5 E2. → EX-4, EX-9
- [x] **T019** `CreateVideoEditHandler` (draft + source rows + `temporaryUploadUrl` per source, previous-edit ownership) + `VideoEditController::store` + route E2 (`CREATE_VIDEO_EDITS`, `throttle:10,1`). `VideoEditCreateTest` covers: 201 shape; 422 matrix (mode/source counts/ext/MIME/size/threshold/ranges/merge-with-ranges); `ai_edit` → 422 `mode_not_available`; a foreign `previous_edit_uuid` → 404. → US-1/2/3/7, FR-1…4, FR-12, EX-4
- [x] **T020** *(job class exists with final attributes; its `handle()` is completed in T024)* `SubmitVideoEditHandler` (one `size()` metadata call per source — a missing object maps to `source_missing`, no separate `exists()`, atomic `draft → queued`, audit `video_edit.submitted`, dispatch after commit) + route E3. `VideoEditSubmitTest` covers: 202; 422 `source_missing` / `source_too_large`; 409 `already_active` (partial unique index); 409 `invalid_state`; `Queue::fake()->assertPushedOn('video-edits', ProcessVideoEditJob::class)`. → FR-7, FR-16, FR-19, AD-15

## Phase D — Processing pipeline (US-1 / US-2 / US-3 execution)

- [x] **T021** *(implemented as `VideoEditWorkspacePort` + `LocalVideoEditWorkspace`; no separate `ProcessingContext` class was needed)* `VideoEditWorkspace` (per-edit dir, free-space check → `insufficient_workspace`, wipe) + `ProgressReporter` (weighted stages, throttled writes) + `ProcessingContext` + unit tests. → AD-12, FR-7, FR-18, R5
- [x] **T022** `DecisionProducerRegistry` (mode → producers, container-tagged) + `SilenceDecisionProducer` (padding metadata) + `ManualRangeDecisionProducer`, with a `DecisionProducerRegistryTest`. → EX-2
- [x] **T023** `VideoEditPipeline` + `RunVideoEditHandler` + `MarkVideoEditFailedHandler`. Stages: Download (copy, sha256, probe, container check, **duration-bound range validation → `invalid_cut_ranges` with per-range details**) → Merge → Analysis → Plan cuts (persist applied + rejected decisions, applied cuts) → Render → Publish (upload result, delete sources, null `storage_path`) → `completed`; workspace wiped in `finally`. → AD-3, AD-4, AD-13, AD-16, FR-5, FR-8, FR-9
- [x] **T024** `ProcessVideoEditJob` (`#[Tries(3)]`, `#[Timeout(3600)]`, `#[Backoff([60, 300])]`, connection/queue from config, `tags()`, permanent errors → `$this->fail()`, `failed()` → mark failed + `sources_expire_at = now + 24h`, missing row → return). `VideoEditPipelineTest` (FakeVideoEditor) covers:
  - merge + silence + manual success (decisions, applied cuts, totals, progress 100, result stored, sources deleted, workspace wiped)
  - editor failure → failed with safe message and sources kept
  - `invalid_cut_ranges` with per-range details and no merge/render called
  - deleted queued edit → no-op
  - no stderr/paths in `failure_message`
  - → FR-9, FR-10, FR-17, FR-18, FR-20, EX-6, EX-8
- [x] **T025** `FilterGraphBuilder` (normalize/scale/pad/fps/aformat per input; `anullsrc` for inputs without audio; trim/atrim/setpts/concat keep-range graph; `%.3F` numbers only) + `FilterGraphBuilderTest`. → AD-4, AD-5, FR-6
- [ ] **T026** *(BLOCKED on T001: argument ordering of `beforeSaving()`, the X264 default bitrate vs CRF, and `[0:v]` reuse must be verified against a real binary before this adapter is written)* `LaravelFfmpegVideoEditor` implements `VideoEditorPort`:
  - `probe` (FFprobe format/streams — confirm the php-ffmpeg API via Context7 first)
  - `merge` (complex filter + `addFormatOutputMapping`)
  - `detectSilences` (`getProcessOutput()`)
  - `render` (graph written to the workspace file, `-/filter_complex` injected via `beforeSaving()`, `onProgress`)
  - Maps `EncodingException` → `VideoProcessingException` without leaking the command. Bound in the provider.
  - → AD-5, AD-7, research §3
- [ ] **T027** `Tests/Integration/LaravelFfmpegVideoEditorTest` (skipped unless `VIDEO_EDIT_FFMPEG_TESTS=1` and binaries exist). Runtime `lavfi` fixtures. Asserts:
  - merge duration ±0.5 s
  - silence detection vs threshold
  - 50-range render duration ±0.1 s
  - A/V delta ≤ 1 frame
  - a no-audio clip merges
  - `-/filter_complex` is accepted
  - Depends on T001. → spec §11, R1, R6

## Phase E — US-4 / US-5 / US-6: status, result, history

- [x] **T028** `GetVideoEditHandler` + `ListVideoEditsHandler` (owner-scoped, drafts excluded, `per_page` ≤ 100, eager-load explicit columns, `withCount`) + routes E1/E4. `VideoEditShowListTest` covers: shapes incl. `parameters` prefill, `can_retry` / `can_delete` / `can_download`, `retry_available_until`; foreign edit → 404; pagination. → US-4, US-5, US-6, US-7, FR-7, FR-14
- [x] **T029** `GetVideoEditDownloadUrlHandler` (15-min `temporaryUrl`, completed only) + route E5 (`DOWNLOAD_VIDEO_EDITS`, `throttle:30,1`). `VideoEditDownloadTest` covers: 200; 409 `not_completed`; 404 foreign; the URL never stored/logged. → US-5, FR-15

## Phase F — US-7 / US-8: re-edit & retry

- [x] **T030** *(covered by `VideoEditCreateTest` + `VideoEditShowListTest`)* Re-edit linkage: a create with `previous_edit_uuid` stores `previous_edit_id` and leaves the original untouched; the show exposes `previous_edit_uuid` + prefill `parameters`. Tests added to `VideoEditCreateTest`. → US-7, FR-12
- [x] **T031** `RetryVideoEditHandler` (window + sources present, atomic `failed → queued`, resets progress/failure, audit `video_edit.retried`; accepts corrected `manual_ranges` **only** when `failure_code = invalid_cut_ranges`) + route E6 (`RETRY_VIDEO_EDITS`). `VideoEditRetryTest` covers: 202; corrected ranges replace parameters; 422 `ranges_not_correctable`; 409 `not_retryable` (expired/purged/not failed); 409 `already_active`; 404 foreign. → US-8, FR-11, AD-16

## Phase G — US-9: hard delete

- [x] **T032** `DeleteVideoEditHandler` (row lock, 409 when processing, delete result + remaining source objects, delete rows (cascade), audit `video_edit.deleted` without names/paths/URLs) + route E7 (`DELETE_VIDEO_EDITS`, `throttle:20,1`). `VideoEditDeleteTest` covers: 204; storage deletes called; 0 rows in the 4 tables; exactly 1 deletion audit entry with no file names/paths; 409 processing; a deleted queued edit is skipped by the job; 404 foreign. → US-9, FR-13, D-1, D-2

## Phase H — Cross-cutting

- [x] **T033** Permissions (P2):
  - `RolePermissionSeeder` replaces `VIDEO_EXPORTS` / `VIDEO_EXPORT_ACTIONS` with `VIDEO_EDITS` + `VIDEO_EDIT_ACTIONS = ['VIEW_ANY','VIEW','CREATE','DOWNLOAD','RETRY','DELETE']` in `ADMIN_TOOL_MODULES`.
  - Migration deleting `*_VIDEO_EXPORTS` permission rows and pivots.
  - `VideoEditAccessTest`: 401 guest and 403 per missing permission for E1–E7 (OWASP §11/§13).
  - → FR-21
- [x] **T034** Scheduled maintenance:
  - `PurgeExpiredVideoEditSourcesHandler` + command (failed edits past `sources_expire_at` → delete objects, set `sources_purged_at`).
  - `SweepStaleVideoEditsHandler` + command (`processing` > 65 min → failed `processing_timeout`; drafts > 24 h → delete objects + rows).
  - Register in the provider; schedule in `routes/console.php` (purge hourly, sweep every 5 min, `withoutOverlapping`).
  - `VideoEditCleanupTest`.
  - → FR-10, FR-17, AD-14, NFR privacy
- [x] **T035** `VideoEditRoadmapReadinessTest`:
  - A test-only producer emitting `filler`-like decisions with confidence is registered via the container and flows through validation → plan → render → persistence **with zero changes to core classes**.
  - A mixed valid/invalid decision set is partially rejected with per-decision reasons.
  - → spec §11 roadmap criteria, EX-1…EX-3
- [x] **T036** OWASP pass against plan §8. Throttles present on every mutating route. Tests assert responses never contain `storage_path` / `result_path` / signed URLs except E5 / E2 upload URLs. Log lines carry the edit uuid + failure code only. → NFR Security

## Phase I — Closeout

- [ ] **T037 [USER-assisted]** Performance gates on Railway:
  - **(a) T-bench** on the 8 GB worker: 20-min 1080p source, 200 cuts. Record peak RSS + wall time. **If it fails, stop and escalate** (Q7, R2).
  - **(b)** p95 latency of E2 (create draft) and E3 (submit with 10 sources) over 20 requests each; target < 1 s (NFR Performance, Analyze A4).
  - **(c)** Confirm an expired download link is denied by R2 (US-5 AC3, Analyze A6).
  - Record all results in `SSD-SUMMARY.md`.
- [x] **T038** *(2026-09-11: module suite 191/191, 566 assertions; full suite 1223 passed / 6 skipped / 0 failed of 1229, 3851 assertions)* `vendor/bin/pint --dirty --format agent` → `php artisan test --compact --filter=VideoEdit` → `php artisan test --compact` (full suite). Paste the real counts.
- [x] **T039** *(2026-09-12: caches cleared · IDE helpers written · `typescript:transform` emitted the module types · `pint` fixed 2 docblocks and `pint --test` passed · Scramble cleared/exported/cached · module suite re-run 191/191 after the docblock rewrite · code graph re-indexed `mode: full` → 13 863 nodes / 49 648 edges)* Module finalization pipeline (`rules.md`): `optimize:clear` → `ide-helper:generate` → `ide-helper:models --write` (re-run the module filter if docblocks changed) → `typescript:transform` → `pint` → `pint --test` → `scramble:clear` → `scramble:export` → `scramble:cache` → `index_repository(mode: "full")` + `index_status` check.
- [x] **T040** *(traceability table + gaps recorded in `SSD-SUMMARY.md` §7)* Final traceability pass against plan §10 (every FR / US / EX has code + a test). Log gaps as new tasks, then write Phase 8 `SSD-SUMMARY.md`.

---

**Commit convention:** `feat(video-edits): T0XX short description`
**Dependency spine:** T003–T006 → T008–T010 → T011–T017 → T018–T020 → T021–T026 → T028–T032 → T033–T036 → T038–T040. T001/T007/T027/T037 need the binaries and infra.
