# Technical plan: Video Edit

> Phase 4 · PLAN — defines HOW V1 is built, verified against `research.md`.
> Every decision traces to `spec.md` (US / FR / EX / NFR), `clarify.md` (Q / D) or `research.md` (§).

**Feature ID:** 001-video-edit
**Based on:** spec.md, clarify.md, research.md
**Scope:** V1 implemented. V2/V3 are accounted for architecturally only (spec §4.1, EX-1…EX-9).

---

## 1. Technical summary

**Module shape.** V1 is a new module, `src/Modules/VideoEdits`, built on the **intermediate** hexagonal baseline (`.claude/skills/ARCHITECTURE-PHP/SKILL.md`), not SIMPLE-CRUD. Three promotion triggers from `rules.md` are met:
- **#1 — lifecycle.** An edit moves through a multi-step lifecycle: draft → queued → processing → completed/failed, plus a retry window and a deletion guard.
- **#2 — integrations.** V1 already has two: FFmpeg binaries and Cloudflare R2. V2 and V3 add Whisper and Gemini.
- **#4 — sub-entities.** One aggregate composes sources, cut decisions and applied cuts.

**Request flow.**
1. The browser creates a **draft** edit and receives pre-signed R2 upload URLs.
2. The browser uploads the videos straight to R2.
3. `POST …/submit` verifies the uploaded objects and queues **one job** on the dedicated Redis (Upstash) connection and queue `video-edits`, consumed by a plain `queue:work` worker (no Horizon in V1, Horizon-compatible — P3).

**Pipeline stages.** The job runs a fixed, ordered list of stages (EX-7):

| Stage | What happens |
| --- | --- |
| Download | Sources are copied from R2 to a local workspace |
| Merge | Sources are normalized to the output profile and joined |
| Analysis | **Decision producers** run: V1 has silence detection + manual ranges |
| Plan cuts | Decisions are validated and normalized |
| Render | The keep-ranges are rendered |
| Publish | The result goes to R2; sources are deleted |

**The editing core.** Validation → normalization → render only ever consumes the single `CutDecision` contract (EX-1, EX-3). Adding the V2 speech detectors or the V3 AI analyzer means registering a new producer (EX-2); the core does not change.

**FFmpeg.** It is reached through a `VideoEditorPort`. The adapter uses `pbmedia/laravel-ffmpeg` 8.9 (research §3, §7). The rest of the module is unaware of FFmpeg, so tests use a fake editor. Real-binary tests are opt-in.

---

## 2. Technology stack (verified)

| Component | Choice | Verified version | Source / justification |
| --- | --- | --- | --- |
| Framework / language | Laravel + PHP (Herd local, Railway prod) | 13.26.1 / 8.5 | research §1 |
| Database | PostgreSQL (Supabase); tests on SQLite in-memory (`phpunit.xml`) | 17.6 | research §9.1 |
| FFmpeg wrapper | `pbmedia/laravel-ffmpeg` (wraps `php-ffmpeg/php-ffmpeg`) | 8.9.0 / v1.4.0 | research §1–§3 |
| FFmpeg / FFprobe binaries | Railway: `RAILPACK_DEPLOY_APT_PACKAGES="... ffmpeg"`. Local: manual install; `FFMPEG_BINARIES` / `FFPROBE_BINARIES` env. **Minimum 7.1** (needed for `-/filter_complex`) | Version pinned at install `[UNVERIFIED — exact version that introduced -/option]` | research §4.4, §9.4 |
| Object storage | Cloudflare R2 through the existing `Shared\Domain\Ports\StoragePort` (`temporaryUploadUrl`, `copyToLocal`, `putFromPath`, `temporaryUrl`, `delete`, `exists`) + one new `size()` method | `league/flysystem-aws-s3-v3` 3.35.3 | research §9.2, §9.5 |
| Queue | **Redis on Upstash** (TLS, `phpredis`) in dev AND prod, **separate Upstash databases per environment** (P3). Dedicated queue connection **`video-edits`** (driver `redis`, redis connection `queue`, `retry_after` 3900, `block_for` 5, `after_commit` true), queue **`video-edits`**. Worker: `php artisan queue:work video-edits --queue=video-edits --timeout=3600 --tries=3 --memory=4096`. **No Horizon in V1**; Horizon-compatible (redis connection + job `tags()`) | Laravel 13.26.1 · `redis` ext loaded locally | research §9.3, §10 |
| Redis connection for queues | New `queue` entry in `config/database.php` `redis` block: `REDIS_QUEUE_*` env with fallback to `REDIS_*`, database 0, **env-scoped prefix** `{app}-{APP_ENV}-queue-` as a safety net against dev/prod mixing. Per-connection `options.prefix` support `[UNVERIFIED — confirm PhpRedisConnector merges connection options]` | — | research §10.1 |
| Worker platform (Railway) | `RAILPACK_DEPLOY_APT_PACKAGES="... ffmpeg"`, `RAILPACK_PHP_EXTENSIONS` including `redis` and `pcntl` | — | research §9.4, §10.3 |
| Job attributes | `#[Queue]`, `#[Tries]`, `#[Timeout]`, `#[Backoff]` (`Illuminate\Queue\Attributes`) — project precedent `GenerateInvoicePdfJob` | Laravel 13 | research §9.3 |
| DTOs / validation | `spatie/laravel-data` with `SnakeCaseMapper` in and out | 4.23.0 | `InvoiceData` precedent |
| Generated TS types | `spatie/laravel-typescript-transformer` (Data + enums auto-collected) | 3.3.0 | `TypeScriptTransformerServiceProvider` |
| Permissions | `spatie/laravel-permission`, seeded in `RolePermissionSeeder` | 8.3.0 | scout §4 |
| Audit | Existing `Shared\Domain\Ports\AuditPort` → `SpatieActivityLogAdapter` (`spatie/laravel-activitylog` 5.1.0) | 5.1.0 | `DeleteBackupHandler` precedent |
| API docs | `dedoc/scramble` (return type-hints on controller methods) | 0.13.42 | BACKEND-PHP §9 |
| Tests | Pest | 5.1.1 | scout §9 |
| V3 (later, not built now) | `Shared\Infrastructure\AI\AIClientInterface` / `LaravelAIAdapter` (`laravel/ai` 0.11.0, CircuitBreaker) · `DomPdfExportAdapter` (`barryvdh/laravel-dompdf` 3.1.2) | — | research §9.5 |

---

## 3. Architecture

### 3.1 Layers and the V1 → V3 extension seam

```text
HTTP (VideoEditController, JSON under /data/admin/video-edits)
  │  Spatie Data validation · permission middleware · owner scoping
  ▼
Application / Commands & Queries (final readonly handlers)
  │  CreateVideoEditHandler ─► StoragePort::temporaryUploadUrl
  │  SubmitVideoEditHandler ─► dispatch ProcessVideoEditJob
  ▼
Infrastructure / Queue / ProcessVideoEditJob  (redis connection `video-edits`, queue `video-edits`, 3600 s)
  ▼
Application / Pipeline / VideoEditPipeline   (ordered stages, weighted progress — EX-7)
  ├─ Download   StoragePort::copyToLocal · fingerprint (sha256) · VideoEditorPort::probe
  ├─ Merge      VideoEditorPort::merge (normalize to OutputProfile)
  ├─ Analysis   DecisionProducerRegistry::forMode(mode) ──► CutDecisionProducer[]   ◄── EXTENSION SEAM (EX-2)
  │               V1: SilenceDecisionProducer (VideoEditorPort::detectSilences)
  │               V1: ManualRangeDecisionProducer (stored parameters)
  │               V2: TranscriptionStage + Filler/Stutter/VocalSound producers (NOT built)
  │               V3: ScriptExtraction + AiEditDecisionProducer (NOT built)
  ├─ Plan cuts  Domain: CutDecisionValidator → CutPlanner → CutPlan (applied + rejected)   (EX-1, EX-3)
  ├─ Render     VideoEditorPort::render(keepRanges)            ◄── consumes ONLY CutPlan
  └─ Publish    StoragePort::putFromPath · delete sources (FR-9) · wipe workspace (FR-18)
  ▼
Domain (pure PHP): enums, value objects, CutDecisionValidator, CutPlanner, SilenceCutParser, ports
```

### 3.2 Key design decisions

| # | Decision | Why | Trace |
| --- | --- | --- | --- |
| AD-1 | **Direct browser → R2 upload** with pre-signed PUT URLs; the app never proxies video bytes | No 2 GB request bodies through PHP; matches D1 and FR-19 | research §9.2 |
| AD-2 | **Draft → submit** two-step: create returns upload URLs; submit verifies objects exist and `size() ≤ 2 GB` (a pre-signed PUT does not enforce size) | Only system-issued object keys are accepted (FR-19); size is enforced server-side (D2) | risk R4 |
| AD-3 | **One `CutDecision` contract** (`start, end, reason, origin, ?confidence, ?evidence`) produced by `CutDecisionProducer` implementations, validated per decision into applied/rejected | EX-1, EX-2, EX-3, EX-8; the V3 AI output uses the same path | spec §6.2 |
| AD-4 | **Two-pass encode when cuts follow a merge.** Merge writes a high-quality intermediate (`libx264 -crf 14 -preset veryfast`); render produces the final output (`-crf 20 -preset medium`). Single-source edits and merge-only edits encode once | Silence analysis and manual ranges must run on the merged timeline (D7). A single-pass split+trim graph would buffer frames without bound | research §5 |
| AD-5 | **Render = `trim/atrim + setpts/asetpts + concat` in one filtergraph**, loaded from a file with `-/filter_complex <file>` injected through laravel-ffmpeg `beforeSaving()` | Frame- and sample-accurate (Q4, FR-6). The file avoids the command-length failures from issue #505. `select/aselect` was rejected: audio drifts across many cuts | research §4.4, §5 |
| AD-6 | **No low-memory or chunked render mode** | User decision Q7 (8 GB Railway / 32 GB local). Replaced by a benchmark gate (T-bench) and a user escalation if it fails — never a silent fallback | clarify Q7 |
| AD-7 | **Silence detection with `silencedetect`** through laravel-ffmpeg `getProcessOutput()`, parsed by the pure `SilenceCutParser` (ported from the guide) | No Whisper in V1; covered by the package's raw output API | research §3, §6 |
| AD-8 | **Hard delete, no `SoftDeletes`, no `LogsActivity` on the edit models.** Submissions, retries and deletions are audited through `AuditPort` | Q2/Q5 demand permanent deletion with only audit entries left. `LogsActivity` would leave lifecycle rows per status change. Precedent: `DeleteBackupHandler` | Deviation D-1, D-2 |
| AD-9 | **Atomic status transitions** via conditional update (`UPDATE … WHERE uuid = ? AND status IN (…)`) + a **partial unique index** for one active edit per user | Races between delete, retry, submit and job start are resolved in the database, not in memory (FR-16, D14) | research §9.1 |
| AD-10 | **Repository port returns the Eloquent model**, as `InvoiceRepositoryPort` does | Follows the existing project precedent rather than introducing a Mapper + Entity | scout §7 |
| AD-11 | **No Domain Entity / Mapper.** Lifecycle invariants live in `VideoEditStatus::canTransitionTo()` and pure domain services | Entity Optionality Rule: the state rules fit an enum and services, and the model is 1:1 with the table | ARCHITECTURE-PHP Lean Mode |
| AD-12 | **Workspace on a local ephemeral disk** `video-edit-workspace` (`storage/app/video-edit-workspace/{uuid}`), wiped in `finally` | Local disk is allowed for ephemeral processing only; final storage is R2 | BACKEND-PHP §5 Storage |
| AD-13 | **Stage restart on retry.** Each attempt restarts from Download with a clean workspace | Idempotent and simple; jobs are rare | FR-17 |
| AD-14 | **Stuck-job backstop.** A scheduled sweep fails edits that stay `processing` > 65 min | PCNTL timeouts are not enforced on Windows/Herd, and a worker crash may never call `failed()` | research §9.3, risk R3 |
| AD-15 | **Queue on Redis (Upstash), config-driven.** The job's connection and queue come from `config('video-edit.queue.connection' / '.name')` (defaults `video-edits` / `video-edits`); `phpunit.xml` sets the connection to `sync`. `after_commit = true` so the job is never picked up before the `draft → queued` transaction commits | P3; tests never touch Upstash; no race between submit and worker | research §10.4 |
| AD-16 | **Two-step range validation + correctable retry.** Structural checks at E2; duration-bound checks in the Download stage right after probing, **before** merge/analysis/render. They fail with `failure_code = invalid_cut_ranges` + per-range `failure_details`, and E6 accepts corrected `manual_ranges` for that code only | P1 | spec FR-4, FR-11 |

### 3.3 Typical flow (US-2 + US-3, one source)

1. `POST /data/admin/video-edits` (Create): body is validated → draft row + source row (`storage_path = video-edits/{userUuid}/{editUuid}/sources/{sourceUuid}.{ext}`) → responds with the 60-min upload URL and headers.
2. The browser `PUT`s the file to R2.
3. `POST …/{uuid}/submit` → one `StoragePort::size` call per source → atomic `draft → queued` (the partial unique index turns a second active edit into 409) → `AuditPort::log('video_edit.submitted')` → dispatch the job → 202.
4. The job runs the stages:
   - Download + probe + sha256, then **duration-bound validation** (manual ranges ≤ merged duration, ≥ 1 s output, total ≤ 90 min). Failures stop here with `invalid_cut_ranges` / `invalid_media`, before merge (AD-16).
   - Merge is skipped for a single source.
   - `SilenceDecisionProducer` + `ManualRangeDecisionProducer` produce decisions.
   - `CutDecisionValidator` marks each decision applied or rejected with a reason.
   - `CutPlanner` merges overlaps (union of reasons), applies silence padding, absorbs fragments < 0.25 s and enforces ≥ 1 s of output.
   - Render.
   - Publish to `video-edits/{userUuid}/{editUuid}/result.mp4`.
   - Persist decisions, applied cuts and totals, then `completed`.
   - Delete source objects and null their `storage_path`.
5. The client polls `GET …/{uuid}` for status and progress, then calls `GET …/{uuid}/download-url` for a 15-min signed URL.

---

## 4. Data model (physical schema)

All tables follow the Invoices convention: `id()` bigint PK (hidden) plus a `uuid` column (UUIDv7, unique, public identifier), and `timestamps()`. **No `softDeletes()`** (AD-8). Times are stored as **integer milliseconds**, which is exact enough for frame accuracy (1 frame at 60 fps ≈ 16.7 ms) and avoids float drift.

### `video_edits` (aggregate root)
```
id                    bigint PK
uuid                  uuid UNIQUE
user_id               FK users  cascadeOnUpdate, cascadeOnDelete   (Invoices precedent)
previous_edit_id      FK video_edits NULL  nullOnDelete            (US-7 re-edit link)
mode                  string(20)   enum VideoEditMode  (merge | auto_edit | ai_edit)   (EX-4)
status                string(20)   enum VideoEditStatus (draft | queued | processing | completed | failed)
parameters            json         {silence_removal:{enabled, threshold_seconds}, manual_ranges:[{start_ms,end_ms,note?}]}  (US-7 prefill, EX-9 room for attachments)
effective_settings    json NULL    {noise_floor_db, padding_ms, min_fragment_ms, output_profile:{width,height,fps}}  (snapshot for audit/report)
progress_percent      smallint default 0
current_stage         string(30) NULL   enum ProcessingStage
attempts              smallint default 0
failure_code          string(50) NULL   (e.g. invalid_media, invalid_cut_ranges, processing_timeout, processing_error)
failure_message       string(255) NULL  (user-safe, FR-20)
failure_details       json NULL          (per-range validation errors only — never stderr)
original_duration_ms  bigint NULL
final_duration_ms     bigint NULL
removed_duration_ms   bigint NULL
applied_cut_count     integer default 0
rejected_decision_count integer default 0
warnings              json NULL
result_path           string NULL
result_size_bytes     bigint NULL
sources_expire_at     timestamp NULL    (failed + 24 h — FR-10)
sources_purged_at     timestamp NULL
queued_at / started_at / completed_at / failed_at   timestamp NULL
created_at / updated_at
```
Indexes:
- `(user_id, created_at)` — history, newest first (US-6)
- **partial unique** `video_edits_one_active_per_user` on `(user_id) WHERE status IN ('queued','processing')` — FR-16 (PostgreSQL and SQLite both support partial indexes)
- `(status, updated_at)` — stuck sweep (AD-14)
- `(status, sources_expire_at)` — source purge (FR-10)
- `(status, created_at)` — expired drafts

### `video_edit_sources`
```
id, uuid
video_edit_id         FK video_edits cascadeOnDelete
position              smallint           UNIQUE(video_edit_id, position)
original_name         string(255)        (shown to owner only; never logged)
extension             string(10)
declared_mime         string(100)
declared_size_bytes   bigint
storage_path          string NULL        (NULL once purged)
size_bytes            bigint NULL        (verified at submit)
sha256                char(64) NULL      INDEX — EX-6 transcript reuse key for V2
duration_ms           bigint NULL
width, height         integer NULL
frame_rate            decimal(7,3) NULL
has_audio             boolean NULL
container             string(50) NULL
video_codec, audio_codec  string(30) NULL
created_at / updated_at
```

### `video_edit_cut_decisions` (EX-1, EX-8 — applied AND rejected)
```
id
video_edit_id         FK cascadeOnDelete    INDEX
producer              string(50)   (silence_detector | manual; V2/V3 add more)
reason                string(40)   enum CutReason (silence | manual …)
origin                string(30)   enum DecisionOrigin (system_detection | user …)
start_ms, end_ms      bigint
confidence            decimal(4,3) NULL
evidence              json NULL
outcome               string(10)   enum DecisionOutcome (applied | rejected)
rejection_reason      string(60) NULL
applied_cut_sequence  integer NULL
created_at
```

### `video_edit_applied_cuts`
```
id
video_edit_id         FK cascadeOnDelete
sequence              integer      UNIQUE(video_edit_id, sequence)
start_ms, end_ms      bigint
reasons               json   list<string>
origins               json   list<string>
created_at
```

### Relations
- `VideoEditEloquentModel`: `user()` belongsTo, `sources()`, `cutDecisions()`, `appliedCuts()` hasMany, `previousEdit()` belongsTo.
- **`App\Models\User::videoEdits()`** hasMany plus `@property-read` PHPDoc (mandatory bidirectional FK rule).
- Lists eager-load with explicit columns, e.g. `with('sources:id,video_edit_id,position,original_name,duration_ms')` and `withCount('appliedCuts')`.

### Roadmap tables (NOT created in V1)
- `video_edit_transcripts` (V2, keyed by ordered source `sha256` list).
- `video_edit_script_documents` and `video_edit_ai_runs` (V3).

V1 leaves room for them through `sha256`, `evidence`, `confidence`, `producer` and `parameters`.

---

## 5. API contracts

All routes live in `src/Modules/VideoEdits/Infrastructure/Routes/web.php` under `Route::middleware(['auth','verified'])->prefix('data/admin/video-edits')->name('video-edits.admin.')`. They use per-route `permission:*` (Invoices precedent) and `->whereUuid('uuid')`. Every query is **owner-scoped**: another user's edit returns **404**. JSON is snake_case.

**Not in V1:**
- The Inertia page route (`GET /video-edits`) ships with the frontend spec.
- Sanctum `api.php` — skipped in V1 (P4).

| # | Method & path | Permission | Throttle | Story / FR |
| --- | --- | --- | --- | --- |
| E1 | `GET /` | `VIEW_ANY_VIDEO_EDITS` | — | US-6 · FR-14 |
| E2 | `POST /` | `CREATE_VIDEO_EDITS` | `10,1` | US-1/2/3/7 · FR-1…4, FR-12, FR-19 |
| E3 | `POST /{uuid}/submit` | `CREATE_VIDEO_EDITS` | `10,1` | US-1/2/3 · FR-7, FR-16 |
| E4 | `GET /{uuid}` | `VIEW_VIDEO_EDITS` | — | US-4/5/7 · FR-7, FR-8 |
| E5 | `GET /{uuid}/download-url` | `DOWNLOAD_VIDEO_EDITS` | `30,1` | US-5 · FR-15 |
| E6 | `POST /{uuid}/retry` | `RETRY_VIDEO_EDITS` | `10,1` | US-8 · FR-11 |
| E7 | `DELETE /{uuid}` | `DELETE_VIDEO_EDITS` | `20,1` | US-9 · FR-13 |

Static segments are declared before `/{uuid}`.

### E1 `GET /data/admin/video-edits`
- **Request:** `?page=1&per_page=15` (`per_page` max 100), optional `status`, `mode`. Drafts are excluded.
- **200:** `{ data: VideoEditListItemData[], meta: pagination }`
- `VideoEditListItemData`: `uuid, mode, status, progress_percent, source_count, final_duration_ms, applied_cut_count, created_at, completed_at`.
- **Errors:** 401, 403, 422 (bad filter).

### E2 `POST /data/admin/video-edits` — create draft
- **Request (`CreateVideoEditData`):**
```json
{
  "mode": "auto_edit",
  "silence_removal": { "enabled": true, "threshold_seconds": 1.0 },
  "manual_ranges": [ { "start_ms": 12400, "end_ms": 14800, "note": "retake" } ],
  "sources": [ { "position": 1, "file_name": "take-1.mp4", "mime_type": "video/mp4", "size_bytes": 734003200 } ],
  "previous_edit_uuid": null
}
```
- **Validation:**
  - `mode` must be in `merge | auto_edit`. `ai_edit` → **422** `mode_not_available` (EX-4).
  - `merge` needs 2–10 sources; `auto_edit` needs 1–10.
  - Positions are unique and contiguous from 1.
  - Extension is one of mp4/mov/webm/mkv, with the matching MIME list; `size_bytes` is between 1 and 2 147 483 648.
  - `auto_edit` requires silence removal enabled or ≥ 1 manual range.
  - `threshold_seconds` must be 0.3–10 (default 1.0).
  - `manual_ranges` has at most 500 entries, `start_ms ≥ 0`, `end_ms > start_ms`, and `note` ≤ 120 chars. Checks against the real duration happen at job start (AD-16).
  - `merge` accepts no ranges and no silence settings.
  - `previous_edit_uuid` must be an existing edit owned by the caller.
- **201 (`CreatedVideoEditData`):** `{ edit: VideoEditDetailData (status draft), uploads: [ { source_uuid, position, upload_url, headers: {…}, expires_at } ] }`
- **Errors:** 401, 403, 404 (`previous_edit_uuid` not owned), 422, 429.

### E3 `POST /data/admin/video-edits/{uuid}/submit`
- **Request:** empty.
- **Checks:**
  - The edit is `draft` and owned by the caller.
  - **One `StoragePort::size()` metadata call per source** (a missing object → `source_missing`; no separate `exists()` call, to keep submit < 1 s p95 with 10 sources — Analyze A4).
  - Each size is ≤ 2 GB and matches `declared_size_bytes` within 1 %.
  - Duration limits (≤ 90 min total, D2) are checked at the Download stage, because durations are unknown until probed.
- **202:** `VideoEditDetailData` with status `queued`.
- **Errors:**
  - 404 — not found or not owned.
  - 409 `already_active` — partial unique index hit.
  - 409 `invalid_state` — not a draft.
  - 422 `source_missing` / `source_too_large` (per source uuid).
  - 429.

### E4 `GET /data/admin/video-edits/{uuid}`
- **200 (`VideoEditDetailData`):**
  - `uuid, mode, status, progress_percent, current_stage, attempts`
  - `parameters` (prefill for re-edit, US-7), `previous_edit_uuid`
  - `sources[]: {uuid, position, original_name, duration_ms, width, height, has_audio, available}`
  - `summary: {original_duration_ms, final_duration_ms, removed_duration_ms, applied_cut_count, rejected_decision_count}`
  - `applied_cuts[]: {sequence, start_ms, end_ms, duration_ms, reasons[], origins[]}`
  - `decisions[]: {producer, reason, origin, start_ms, end_ms, confidence, outcome, rejection_reason}`
  - `warnings[]`, `failure: {code, message, details} | null`
  - `retry_available_until` (nullable), `can_retry`, `can_delete`, `can_download`
  - `created_at, queued_at, started_at, completed_at, failed_at`
- **Errors:** 401, 403, 404.

### E5 `GET /data/admin/video-edits/{uuid}/download-url`
- **200 (`DownloadUrlData`):** `{ url, expires_at }` — `StoragePort::temporaryUrl(result_path, now()->plus(minutes: 15))`. Signed URLs are never logged.
- **Errors:** 404; 409 `not_completed`; 429.

### E6 `POST /data/admin/video-edits/{uuid}/retry`
- **Request:** empty, OR — only when `failure_code = invalid_cut_ranges` — `{ "manual_ranges": [ { "start_ms", "end_ms", "note?" } ] }` with the same structural rules as E2. The corrected ranges replace `parameters.manual_ranges` and are re-validated against the real duration when the job starts (AD-16). Sending `manual_ranges` for any other failure code → 422 `ranges_not_correctable`.
- **Checks:** status `failed`, `sources_expire_at > now()`, `sources_purged_at IS NULL`.
- **Effect:** atomic `failed → queued`; resets progress, stage and failure fields; `AuditPort::log('video_edit.retried')`; dispatch.
- **202:** `VideoEditDetailData`.
- **Errors:** 404; 409 `not_retryable` / `already_active`; 429.

### E7 `DELETE /data/admin/video-edits/{uuid}` — hard delete
- **Effect** (in a transaction with a row lock):
  1. Refuse if `processing`.
  2. Delete the result object and remaining source objects via `StoragePort::delete`.
  3. Delete the rows (child rows cascade).
  4. `AuditPort::log('video_edit.deleted', null, {edit_uuid, mode, status_at_deletion, source_count}, causer)` — no file names, paths or URLs.
- A queued edit that gets deleted is skipped by the job, which exits cleanly when the row is missing.
- **204** empty.
- **Errors:** 404; 409 `processing`; 429.

### Error envelope (all 4xx)
`{ "message": "…", "code": "already_active", "errors": { "field": ["…"] } }`

Messages are user-safe. Commands, paths and provider output never reach the client (FR-20).

---

## 6. Folder structure

```
src/Modules/VideoEdits/
├── Providers/VideoEditsServiceProvider.php          ← routes, port bindings, producer registry, commands
├── Domain/
│   ├── Enums/ VideoEditMode · VideoEditStatus · ProcessingStage · CutReason · DecisionOrigin · DecisionOutcome
│   ├── ValueObjects/ TimeRange · SilenceThreshold · CutDecision · AppliedCut · CutPlan · MediaProbe · OutputProfile · ContentFingerprint
│   ├── Services/ CutDecisionValidator · CutPlanner · SilenceCutParser        ← pure; CutPlanner/SilenceCutParser ported from GUIDE
│   ├── Exceptions/ InvalidMediaException · InvalidCutRangesException · VideoEditStateConflictException · VideoProcessingException
│   └── Ports/ VideoEditRepositoryPort · VideoEditorPort · CutDecisionProducer
├── Application/
│   ├── DTOs/ CreateVideoEditData · SilenceRemovalData · ManualRangeData · SourceUploadData · VideoEditFilterData
│   │         VideoEditListItemData · VideoEditDetailData · VideoEditSourceData · AppliedCutData · CutDecisionData
│   │         UploadTargetData · CreatedVideoEditData · DownloadUrlData
│   ├── Commands/ CreateVideoEditHandler · SubmitVideoEditHandler · RunVideoEditHandler · MarkVideoEditFailedHandler
│   │             RetryVideoEditHandler · DeleteVideoEditHandler · PurgeExpiredVideoEditSourcesHandler · SweepStaleVideoEditsHandler
│   ├── Queries/ ListVideoEditsHandler · GetVideoEditHandler · GetVideoEditDownloadUrlHandler
│   └── Pipeline/                                     ← justified by EX-2/EX-7: ordered stages + pluggable producers
│       ├── VideoEditPipeline.php · ProcessingContext.php · ProgressReporter.php · DecisionProducerRegistry.php
│       └── Producers/ SilenceDecisionProducer.php · ManualRangeDecisionProducer.php
├── Infrastructure/
│   ├── Http/Controllers/VideoEditController.php      ← fused JSON controller, explicit return types (Scramble)
│   ├── Persistence/Eloquent/Models/ VideoEditEloquentModel · VideoEditSourceEloquentModel · VideoEditCutDecisionEloquentModel · VideoEditAppliedCutEloquentModel
│   ├── Persistence/Repositories/EloquentVideoEditRepository.php
│   ├── Media/                                         ← FFmpeg adapter (third-party binary integration)
│   │   ├── LaravelFfmpegVideoEditor.php · FilterGraphBuilder.php · VideoEditWorkspace.php
│   ├── Queue/ProcessVideoEditJob.php
│   ├── Console/ PurgeExpiredVideoEditSourcesCommand.php · SweepStaleVideoEditsCommand.php
│   └── Routes/web.php
└── Tests/
    ├── Unit/     TimeRangeTest · SilenceThresholdTest · VideoEditStatusTest · CutDecisionValidatorTest · CutPlannerTest
    │             SilenceCutParserTest · ProgressReporterTest · DecisionProducerRegistryTest · FilterGraphBuilderTest
    ├── Feature/  VideoEditCreateTest · VideoEditSubmitTest · VideoEditAccessTest · VideoEditShowListTest
    │             VideoEditDownloadTest · VideoEditRetryTest · VideoEditDeleteTest · VideoEditPipelineTest
    │             VideoEditCleanupTest · VideoEditRoadmapReadinessTest
    ├── Integration/ LaravelFfmpegVideoEditorTest    ← opt-in: VIDEO_EDIT_FFMPEG_TESTS=1 + binaries present
    └── Support/  FakeVideoEditor.php · InMemoryStorage (or Storage::fake-backed StoragePort)

Outside the module:
- database/migrations/2026_09_xx_create_video_edits_tables.php
- config/video-edit.php                    (limits, silence, cuts, output profile, queue, retention, URL TTLs, workspace)
- config/laravel-ffmpeg.php                (vendor:publish; binaries/timeout/threads/log_channel/temporary_files_root — keys confirmed from the published file)
- config/queue.php                         (+ 'video-edits' connection: driver redis, connection 'queue', queue 'video-edits', retry_after 3900, block_for 5, after_commit true)
- config/database.php                      (+ redis 'queue' connection: REDIS_QUEUE_* → REDIS_* fallback, db 0, env-scoped prefix)
- phpunit.xml                              (+ VIDEO_EDIT_QUEUE_CONNECTION=sync)
- .env.example (placeholders only)         VIDEO_EDIT_QUEUE_CONNECTION=video-edits · VIDEO_EDIT_QUEUE=video-edits · REDIS_QUEUE_HOST= · REDIS_QUEUE_PASSWORD= · REDIS_QUEUE_PORT=6379 · FFMPEG_BINARIES= · FFPROBE_BINARIES= · VIDEO_EDIT_WORKSPACE_ROOT=
- config/filesystems.php                   (+ 'video-edit-workspace' local disk, not served)
- bootstrap/providers.php                  (+ VideoEditsServiceProvider)
- routes/console.php                       (+ schedules: purge hourly, sweep every 5 min, withoutOverlapping)
- database/seeders/RolePermissionSeeder.php (P2: VIDEO_EXPORTS → VIDEO_EDITS + VIDEO_EDIT_ACTIONS)
- database/migrations/2026_09_xx_replace_video_exports_permissions.php (P2: delete `*_VIDEO_EXPORTS` permission rows + pivots)
- app/Models/User.php                      (+ videoEdits() hasMany + PHPDoc)
- src/Shared/Domain/Ports/StoragePort.php + R2StorageAdapter (+ size(string $path): int)
```

**Folders added beyond the default tree, one sentence each:**
- `Application/Pipeline/` — the ordered stage runner and the producer registry are the V2/V3 extension seam (EX-2, EX-7).
- `Infrastructure/Media/` — the FFmpeg adapter is a third-party binary integration, not persistence or HTTP.
- `Infrastructure/Console/` — scheduled cleanups follow the Backups/Campaigns precedent of module commands.
- `Tests/Integration/` — real-binary tests must be separable and opt-in.

---

## 7. Testing strategy

**Pest 5.** Tests live in `src/Modules/VideoEdits/Tests`, use `RefreshDatabase` on SQLite in-memory, seed `RolePermissionSeeder` in `beforeEach`, and use a `SUPER_ADMIN` helper (Invoices precedent). Handlers resolve `VideoEditorPort` → `FakeVideoEditor`; `StoragePort` is swapped for a fake-backed implementation.

| Level | What is covered | Key assertions |
| --- | --- | --- |
| **Unit (domain)** | `TimeRange` invariants; `SilenceThreshold` 0.3–10; `VideoEditStatus::canTransitionTo()` matrix; `CutDecisionValidator` (out of range, start ≥ end, unknown reason, confidence ∉ [0,1]); `CutPlanner` (overlap/adjacent merge with reason union, silence padding 150 ms, fragment < 250 ms absorbed, output < 1 s rejected, totals); `SilenceCutParser` (real `silencedetect` stderr samples incl. trailing silence without `silence_end`); `ProgressReporter` weights sum to 100; `FilterGraphBuilder` output for N keep-ranges | Pure, no DB |
| **Feature (HTTP)** | E1–E7 success paths; the 401/403/404(owner)/409/422/429 matrix per endpoint (OWASP §11 — every endpoint); `ai_edit` → 422 `mode_not_available`; second active edit → 409; submit with a missing or oversized object → 422; download before completion → 409; retry outside the window → 409; retry after `invalid_cut_ranges` with corrected ranges → 202 (ranges replaced), `manual_ranges` on another failure code → 422; submit pushes `ProcessVideoEditJob` on connection/queue `video-edits` (`Queue::fake()->assertPushedOn`); delete while processing → 409 | Response shape snake_case, DB state, `Queue::fake()` dispatch, audit rows |
| **Feature (pipeline)** | Job with `FakeVideoEditor`: merge + silence + manual → decisions persisted (applied + rejected), applied cuts, totals, progress 100, result stored, **source objects deleted** (FR-9), workspace wiped (FR-18) | US-1/2/3/5 acceptance criteria |
| **Feature (failure)** | Editor throws → `failed()` → `failure_code`, safe message, `sources_expire_at = now + 24h`, sources kept; invalid duration-bound ranges → `invalid_cut_ranges` with per-range details; a deleted queued edit → job exits without error | FR-10, FR-17, FR-20, D14 |
| **Feature (cleanup)** | Purge deletes sources of failed edits past 24 h; sweep fails `processing` older than 65 min; expired drafts (> 24 h) are removed with their objects | FR-10, AD-14 |
| **Feature (roadmap readiness)** | A test-only producer emitting `filler`-like decisions with confidence is registered in the container and rendered end-to-end **without touching** validator/planner/render/storage code; a mixed valid/invalid decision set is partially rejected with per-decision reasons | spec §11 roadmap criteria, EX-1…EX-3 |
| **Integration (opt-in, real FFmpeg)** | Fixtures generated at runtime with `lavfi` (`testsrc2` + `sine` with known silent gaps, clips at 720p/30 fps and 1080p/60 fps, one without audio). Asserts: merge duration = Σ ±0.5 s; silences ≥ threshold removed and shorter kept; 50-range render duration = merged − Σcuts ±0.1 s; A/V stream duration delta ≤ 1 frame; `-/filter_complex` accepted by the installed binary | spec §10 success criteria, risk R1 |
| **Benchmark (manual gate, Linux 8 GB)** | T-bench: 20-min 1080p source, 200 cuts, on the Railway worker; record peak RSS (`/usr/bin/time -v`) and wall time | NFR Performance, AD-6, risk R2 |

**Coverage target:**
- Every FR, EX and US acceptance criterion is mapped to ≥ 1 test in the §10 table.
- Domain services reach ≥ 90 % line coverage.
- Closing gate: `php artisan test --compact --filter=VideoEdit`, then the full suite green, then the module finalization pipeline (`rules.md`).

---

## 8. Security and compliance (OWASP baseline)

| # | Item | How it is met |
| --- | --- | --- |
| 1 | Access control + SSRF | `auth` + `verified` + per-route `permission:*_VIDEO_EDITS`; `->whereUuid('uuid')`. Every repository lookup is `where('user_id', auth id)`, so a foreign edit is 404. The server never fetches user-supplied URLs; object keys are server-generated (FR-19) |
| 2 | Authentication | Existing session/Fortify stack; no new auth surface |
| 3 | Injection | Spatie Data validation on every input. FFmpeg arguments are built as **arrays** by the package (Symfony Process), never shell strings. Filtergraphs contain only validated numbers from `CutPlan`, formatted with `sprintf('%.3F')`, and are written to a workspace file. File names never reach FFmpeg: workspace files are renamed to `{sourceUuid}.{ext}` |
| 4 | Cryptography / secrets | R2 credentials stay in env; signed URLs are never logged or stored |
| 5 | Misconfiguration | `set_command_and_error_output_on_exception` stays enabled for **logs**, but exception messages are mapped to user-safe `failure_message` before persistence or response |
| 6 | Supply chain | `pbmedia/laravel-ffmpeg` is pinned `^8.9` with `composer.lock` committed; FFmpeg binary version recorded at install |
| 7 | Insecure design | Threat-modelled flows: upload (size / type / ownership), processing (resource exhaustion), deletion (race with job). Deny by default: `ai_edit` is rejected until V3 |
| 8 | Integrity of uploads | Extension + declared MIME validated at E2; server-side `size()` at E3; **container verified by FFprobe** (`format_name` ∈ mov/mp4/webm/matroska) at Download → `invalid_media`. Videos are re-encoded by render (the output is a fresh encode) |
| 9 | Logging | `AuditPort` for submitted, retried and deleted (edit uuid, mode, counts). Never file names, paths, signed URLs, stderr or transcripts. Structured `Log::warning` on pipeline failures with the edit uuid + failure code only |
| 10 | Exceptional conditions | Typed domain exceptions → `failure_code`; `failed()` on the job; `#[Timeout(3600)]` + `retry_after` 3900 + stuck sweep; workspace wiped in `finally` |
| 11 | Object-level authz | Owner scoping in every handler + Feature tests asserting 404 for another user on E4–E7 |
| 12 | Property-level authz | Responses only through Data DTOs; `$hidden = ['id']` on models; `storage_path`/`result_path` never serialized |
| 13 | Function-level authz | Distinct permissions for view / create / download / retry / delete; tested with a role lacking each one |
| 14 | Resource consumption | Throttles per route (§5); `per_page` ≤ 100; limits of 2 GB/file, 10 files, 90 min, 500 ranges; one active edit per user (DB-enforced) |
| 15 | R2 | Pre-signed PUT (60 min) and GET (15 min, D12) only; private objects |
| +2 | LLM | Not in V1. V3 must route through `AIClientInterface` (OWASP §16), treat AI output as untrusted, and validate through `CutDecisionValidator` (EX-3) |

**Documented deviations** (each needs a `Justified-OWASP-Deviation:` / rules trailer on the commit):
- **D-1:** no `SoftDeletes`. User decision Q2/Q5 requires permanent deletion; precedent `DeleteBackupHandler`.
- **D-2:** no `LogsActivity` on the edit models; explicit `AuditPort` events instead, so only audit entries (no lifecycle rows) survive deletion.
- **D-3:** no bulk delete/restore. V1 has no row selection, and restore is impossible after a hard delete.

The queue complies with the BACKEND-PHP Redis pin (P3). Omitting Horizon is a user decision, not a rules deviation.

---

## 9. Risks and pending decisions

### Risks
| # | Risk | Mitigation |
| --- | --- | --- |
| R1 | laravel-ffmpeg is only tested upstream with FFmpeg 4.4/5.0 (research §2) | Opt-in integration suite against the installed binary (7.1+) is a closing gate; the adapter is isolated behind `VideoEditorPort` |
| R2 | A 200-cut trim/concat graph might exceed 8 GB on Railway (the guide added chunking after OOM kills) | T-bench on the real worker. If it fails, **stop and escalate** to the user (Q7 forbids a silent low-memory mode) with the measured RSS |
| R3 | PCNTL is unavailable on Windows/Herd, so job timeouts are not enforced locally | Stuck sweep every 5 min (AD-14); Linux worker enforces `#[Timeout]` |
| R4 | A pre-signed PUT does not enforce object size | `size()` check at submit (E3); drafts expire after 24 h and their objects are deleted |
| R5 | Workspace disk: intermediate + render ≈ up to 3× input (≤ ~6–10 GB for 2 GB inputs); Railway ephemeral disk size `[UNVERIFIED]` | `video-edit-workspace` root is configurable to a mounted volume; a free-space check before Download fails the edit with `insufficient_workspace` |
| R6 | The `-/filter_complex` option-from-file syntax needs a recent FFmpeg `[UNVERIFIED — introduction version]` | Pin ≥ 7.1; an integration test asserts acceptance. Fallback: `-filter_complex_script` behind the same builder |
| R7 | A long job on the Supabase pooler may see dropped idle connections | No transaction is held across stages; short progress writes; Laravel lost-connection reconnect |
| R8 | Double encode on merge + cuts slightly lowers quality | High-quality intermediate (CRF 14) (AD-4); single-source edits encode once |
| R9 | Upstash per-command billing: an always-on idle worker polls continuously (≈1.5M commands/month estimated `[UNVERIFIED]`) | `block_for = 5` (never 0, which blocks SIGTERM); the dev worker runs only when needed; the prod worker cost is checked against the Upstash dashboard after a week |
| R10 | Upstash eviction enabled on the queue database would silently drop jobs | Setup checklist: eviction disabled on both dev and prod queue databases (T007) |
| R11 | Dev and prod pointed at the same Upstash database would mix jobs | Separate Upstash databases per environment (P3) + env-scoped `queue` prefix (defense in depth) |

### Decisions — resolved by the user on 2026-09-11 (clarify.md P1–P4)
- **P1 — manual ranges beyond the real duration.** The server only learns durations when FFprobe runs at the Download stage, which is after submit.
  - **Accepted:** the frontend pre-validates with the browser's video metadata. The server re-validates at Download (before merge, analysis or render) and fails the edit with `invalid_cut_ranges` and per-range details. **Retry (E6) then accepts corrected `manual_ranges`** for that failure code only.
  - `spec.md` US-3, US-8, FR-4, FR-11 and §11 have been updated (AD-16).
- **P2 — permissions.**
  - The seeder already has `VIDEO_EXPORTS` (`VIEW_ANY`, `CREATE`, `DOWNLOAD`), which no code uses.
  - **Accepted:** replace it with `VIDEO_EDITS` + `VIDEO_EDIT_ACTIONS = ['VIEW_ANY','VIEW','CREATE','DOWNLOAD','RETRY','DELETE']` in `ADMIN_TOOL_MODULES` (SUPER_ADMIN gets all automatically).
  - The stale `*_VIDEO_EXPORTS` rows in existing databases are removed in the module migration.
- **P3 — accepted: Redis (Upstash) in dev and prod**, separate instances per environment, no Horizon in V1 but Horizon-compatible (AD-15, research §10). Worker command: `php artisan queue:work video-edits --queue=video-edits --timeout=3600 --tries=3 --memory=4096` (the `--memory` threshold only restarts the PHP worker after a job; it does not cap FFmpeg, per Q7).
- **P4 — accepted: no Sanctum API routes in V1.** Web session JSON only, as the spec has no mobile client.

---

## 10. Traceability

| Requirement | Covered by (plan) | Verified by (test) |
| --- | --- | --- |
| US-1 / FR-1 merge, output profile | §3.1 Merge stage, AD-4, §4 `effective_settings`, E2 | VideoEditPipelineTest · Integration merge |
| US-2 / FR-2 silence threshold + padding | AD-7, `SilenceDecisionProducer`, `CutPlanner` padding, E2 validation | CutPlannerTest · SilenceCutParserTest · VideoEditCreateTest · Integration silence |
| US-3 / FR-3, FR-4 manual ranges + two-step validation | `ManualRangeDecisionProducer`, `CutDecisionValidator`, E2 (structural), Download stage (duration-bound, AD-16) | CutDecisionValidatorTest · VideoEditCreateTest · VideoEditPipelineTest (invalid_cut_ranges) |
| FR-5 combined normalized cuts | `CutPlanner`, `video_edit_applied_cuts.reasons/origins` | CutPlannerTest |
| FR-6 frame-accurate, A/V ≤ 1 frame | AD-5, `FilterGraphBuilder` | FilterGraphBuilderTest · Integration A/V delta |
| US-4 / FR-7 status, progress, stage | `ProgressReporter`, `current_stage`, E4 | ProgressReporterTest · VideoEditShowListTest |
| FR-8 persistence | §4 schema | VideoEditPipelineTest |
| FR-9 delete sources on success | Publish stage | VideoEditPipelineTest |
| FR-10 / US-8 keep 24 h then purge | `sources_expire_at`, `PurgeExpiredVideoEditSourcesCommand` | VideoEditCleanupTest |
| FR-11 retry (+ corrected ranges) | E6, AD-16 | VideoEditRetryTest |
| P3 Redis queue, Horizon-compatible | AD-15, §2 queue rows | VideoEditSubmitTest (`assertPushedOn`) · T007 setup checklist |
| US-7 / FR-12 re-edit prefill | E4 `parameters`, E2 `previous_edit_uuid` | VideoEditCreateTest |
| US-9 / FR-13 hard delete + audit | E7, AD-8, D-1/D-2 | VideoEditDeleteTest |
| US-6 / FR-14 history | E1 | VideoEditShowListTest |
| US-5 / FR-15 15-min download link | E5 | VideoEditDownloadTest |
| FR-16 one active per user | AD-9 partial unique index, E3/E6 409 | VideoEditSubmitTest |
| FR-17 retries + 60-min timeout | `ProcessVideoEditJob` attributes, AD-14 | VideoEditPipelineTest (failure) · VideoEditCleanupTest |
| FR-18 temp files wiped | AD-12 `VideoEditWorkspace` finally | VideoEditPipelineTest |
| FR-19 only system-issued uploads, no SSRF | AD-1, AD-2, §8 #1 | VideoEditSubmitTest |
| FR-20 no internal output leaked | §5 error envelope, §8 #5 | VideoEditPipelineTest (failure) |
| FR-21 authz + ownership | §5 permissions, §8 #1/#11/#13 | VideoEditAccessTest |
| EX-1 one decision contract | `CutDecision` VO, `video_edit_cut_decisions` | CutDecisionValidatorTest |
| EX-2 pluggable producers | `CutDecisionProducer` port, `DecisionProducerRegistry` | VideoEditRoadmapReadinessTest |
| EX-3 render consumes only validated plan | `VideoEditorPort::render(CutPlan keep ranges)` | VideoEditRoadmapReadinessTest |
| EX-4 modes first-class, AI rejected | `VideoEditMode::isAvailable()`, E2 422 | VideoEditCreateTest |
| EX-5 replaceable providers | Ports only; V2/V3 adapters behind new ports + existing `AIClientInterface` | Design review (Analyze) |
| EX-6 transcript reuse possible | `video_edit_sources.sha256` + position | VideoEditPipelineTest (fingerprint stored) |
| EX-7 extensible stages | `VideoEditPipeline` ordered stages, `ProcessingStage` weights | ProgressReporterTest |
| EX-8 report-ready data | rejected decisions + confidence + evidence persisted | VideoEditPipelineTest |
| EX-9 attachments/instructions room | `parameters` json | Design review (Analyze) |
| NFR Performance (8 GB, no memory caps) | AD-6, P3 worker flags | T-bench (manual gate) |
| NFR Security | §8 | VideoEditAccessTest + per-endpoint 401/403/404/422 |
| NFR Availability | AD-13, AD-14, `failed()` | VideoEditCleanupTest |
| NFR Privacy / retention | FR-9/FR-10 flows, E7, draft expiry | VideoEditCleanupTest · VideoEditDeleteTest |
