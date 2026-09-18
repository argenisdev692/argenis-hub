# Research: Video Edit (physical editing with FFmpeg)

> Phase 3 · RESEARCH — real-time verification of the stack before planning.
> Run early at the user's explicit request ("use tavily + context7 for pbmedia/laravel-ffmpeg, video editing for now").
> Phase 2 (Clarify) is still pending — see §8.

**Feature ID:** 001-video-edit
**Date:** 2026-09-11

## 0. Method and verification legend

| Tag | Meaning |
| --- | --- |
| **[TAVILY]** | Confirmed through `tavily_search` |
| **[C7]** | Confirmed through Context7 docs (`/protonemedia/laravel-ffmpeg`, `/websites/ffmpeg_documentation`) |
| **[WEB]** | NOT verified via Tavily — the first two Tavily calls returned HTTP 429 (rate limit), so these were fetched with WebFetch/WebSearch instead |
| **[LOCAL]** | Read from this repository (`composer.lock`, `config/`, `GUIDE/`) |
| **[UNVERIFIED]** | Reasoned, not backed by a fetched source |

## 1. Installed state in this repo

| Item | Value | Source |
| --- | --- | --- |
| `pbmedia/laravel-ffmpeg` | **8.9.0** — already in `composer.lock`; the `composer.json` line is an uncommitted change | [LOCAL] |
| `php-ffmpeg/php-ffmpeg` (wrapped library) | **v1.4.0** | [LOCAL] |
| `laravel/framework` | v13.26.1 | [LOCAL] |
| `laravel/ai` | v0.11.0 (relevant only for later AI phases) | [LOCAL] |
| `config/laravel-ffmpeg.php` | **Not published** | [LOCAL] |
| `ffmpeg` / `ffprobe` binaries | **Not found** on this Windows machine (`where ffmpeg` / `where ffprobe` → not found) | [LOCAL] |

**Blocker for implementation:** the package is only a PHP wrapper; nothing runs without the FFmpeg + FFprobe binaries installed and configured (`FFMPEG_BINARIES` / `FFPROBE_BINARIES`).

## 2. Package maturity and compatibility

- Latest: **8.9.0, released 2026-02-24**. Requires PHP `^8.2|^8.3|^8.4|^8.5` and `illuminate/contracts ^11|^12|^13`; ~5.8M installs. [WEB] https://packagist.org/packages/pbmedia/laravel-ffmpeg
  - 8.8.0 added PHP 8.5, PHPUnit 12 and Laravel 13 support. 8.9.0 added `keepAllAudioStreams()` for HLS, a PHP 8.4 fix for frame contents, and null-safety fixes. [WEB] https://github.com/protonemedia/laravel-ffmpeg/releases
  - Note: the rendered GitHub releases page showed "2025" dates, which contradicts Packagist. Packagist is authoritative, and Laravel 13 support (8.8.0) cannot predate 2026.
- The repository is not archived. [WEB]
- `php-ffmpeg/php-ffmpeg` v1.4.0 was released 2026-01-19; it supports PHP ^8.0–^8.5 and symfony/process up to ^8.0. [WEB] https://packagist.org/packages/php-ffmpeg/php-ffmpeg
- The README still says **"Tested with FFmpeg 4.4 and 5.0"**. Nothing documents testing against FFmpeg 6/7/8. [TAVILY] https://github.com/protonemedia/laravel-ffmpeg
  → **Risk R1:** current FFmpeg builds (7.x/8.x) are untested by upstream, so our own integration test against the real binary must be the gate.
- Open or closed issues relevant to editing: [WEB] https://github.com/protonemedia/laravel-ffmpeg/issues
  - #505 "ffmpeg failed to execute command after reaching a certain number of characters" — hits long filtergraphs, i.e. many cuts.
  - #525 "ffmpeg failed to execute command" (open).
  - #500 "which ffmpeg version is available" (open, unanswered).

## 3. What the package offers (relevant to video editing)

| Capability | API | Useful for v1 editing? | Source |
| --- | --- | --- | --- |
| Open from a Laravel disk, URL (with headers) or `UploadedFile` | `FFMpeg::fromDisk('x')->open(...)`, `openUrl($url, $headers)`, `open($request->file())` | ✅ Input from R2/S3/local through Flysystem | [C7] |
| Export to any disk, with visibility | `->export()->toDisk('r2')->inFormat(new X264)->withVisibility('private')->save('out.mp4')` | ✅ Output upload without our own upload code | [C7] |
| **Merge / concat** | `->export()->concatWithoutTranscoding()->save()` (same codecs) and `->inFormat(new X264)->concatWithTranscoding($hasVideo, $hasAudio)->save()` | ✅ Covers the MERGE mode directly | [C7] |
| Single-range trim | `new \FFMpeg\Filters\Video\ClipFilter(TimeCode $start, ?TimeCode $duration)` via `->addFilter($clip)` | ⚠️ ONE range per export | [C7][WEB] |
| Complex filtergraph + stream mapping | `->export()->addFilter('[0:v]', 'trim=…', '[v0]')` / closure `ComplexFilters $f->custom($in, $filter, $out)` + `->addFormatOutputMapping(new X264, Media::make('disk','out.mp4'), ['[outv]','[outa]'])` | ✅ Multi-cut render (trim/atrim + concat) in one pass | [C7][WEB] |
| Progress | `->onProgress(fn ($percentage, $remaining, $rate) => …)`; experimental `ProgressListenerDecorator` for pass/time | ✅ Job progress % | [C7] |
| Raw command injection | `->beforeSaving(fn (array $commands) => $commands)` — **not compatible with concat or frame exports** | ⚠️ Escape hatch (e.g. `-/filter_complex file`) | [C7] |
| Raw process output (analysis runs) | `->export()->addFilter(['-filter:a','volumedetect','-f','null'])->getProcessOutput()` → `ProcessOutput::all()/errors()/output()` | ✅ Silence detection (`silencedetect`) without our own process runner | [WEB] README |
| Duration probe | `Media::getDurationInSeconds()` / `getDurationInMiliseconds()` | ✅ | [WEB] |
| Copy container without re-encoding | `->inFormat(new CopyFormat)` | ◻️ Not for frame-accurate cuts | [C7] |
| Errors | `EncodingException::getCommand()`, `getErrorOutput()`; `set_command_and_error_output_on_exception` (default `true` in v8) | ✅ — but disable detail in user-facing messages (OWASP: no command/path leaks) | [WEB][TAVILY] |
| Temp files | `FFMpeg::cleanupTemporaryFiles()`; config `temporary_files_root` | ✅ Wipe per job | [WEB] |
| Logging | Config `log_channel` (replaced `enable_logging` in v8) | ✅ | [TAVILY] |
| Frames / thumbnails / tiles / VTT previews | `getFrameFromSeconds()`, `exportFramesByInterval()`, `exportFramesByAmount()`, `exportTile()` | ◻️ Later (preview strip / timeline UI) | [C7][TAVILY] |
| Watermarks | `addWatermark(fn (WatermarkFactory $w) => …)` | ◻️ Out of scope | [TAVILY] |
| HLS + AES-128 encryption, `keepAllAudioStreams()` | `exportForHLS()->addFormat()` | ◻️ Out of scope (streaming, not editing) | [C7][WEB] |
| Multiple exports chained from one open | `->export()…->save()->export()…->save()` | ◻️ | [C7] |

## 4. What the package does NOT offer (gaps to own ourselves)

1. **No multi-range cut API.** Removing N ranges means building the keep-range filtergraph ourselves: `trim/atrim + setpts/asetpts` per range, then `concat=n=N:v=1:a=1`. [C7][WEB]
2. **No silence detection helper.** You get raw stderr through `getProcessOutput()`, and parsing `silence_start/silence_end` stays our code. The guide's `SilenceCutParser` already does this. [WEB]
3. **No testing fakes or mocks.** Pest tests need our own port plus a fake adapter; real-binary tests must be opt-in. [WEB]
4. **Command-length limit** with many cuts (issue #505). FFmpeg supports loading any option's value from a file by prefixing the option with `/`, e.g. `-/filter_complex graph.txt`. [C7] https://ffmpeg.org/ffmpeg-all.html §5
   The package builds its own `-filter_complex`, so using a file means `beforeSaving()` or a raw runner. The Windows `CreateProcess` 32,767-char limit is [UNVERIFIED].
5. **No memory guard** for huge filtergraphs. The guide already works around OOM with chunked render plus pairwise merge (`FfmpegBinaryRunner::renderChunked`). [LOCAL]

## 5. FFmpeg editing techniques (current guidance)

| Technique | Accuracy | Speed | When | Source |
| --- | --- | --- | --- | --- |
| `trim`/`atrim` + `setpts=PTS-STARTPTS` + `concat` in one `filter_complex` (re-encode) | Frame-accurate, A/V stays in sync | Slowest; memory grows with N segments | Dialogue editing, removing silences/fillers — **the default for this module** | [TAVILY] owlclip.com, mux.com |
| `-ss/-to -c copy -avoid_negative_ts` per segment, then concat demuxer | Snaps to the previous keyframe; audio drift possible | 10–100× faster | Rough cuts only | [TAVILY] ffmpeg-micro.com |
| Extract each segment separately (re-encode), then concat | Frame-accurate | Moderate; bounded memory | Many segments / long videos — the fallback for large cut lists | [TAVILY] mux.com |
| `concat` filter across inputs with different codecs/resolutions (normalize with scale/pad first) | — | Re-encode | Merging heterogeneous clips | [TAVILY] ffmpeg-micro.com |
| `silencedetect=noise=-30dB:d=<threshold>` → parse stderr → invert into keep ranges | Speech-agnostic | One analysis pass | Silence removal without Whisper | [LOCAL] guide + FFmpeg docs |

Latest FFmpeg stable version number: **[UNVERIFIED]**. The Tavily search only surfaced BtbN Windows builds dated 2026-09-10 (https://github.com/btbn/ffmpeg-builds/releases). Pin the exact version at install time and record it.

## 6. Analysis of `GUIDE/VideoExport` (untracked reference implementation)

**What it is:** a hexagonal-style module with MERGE / CLEAN / AI modes, 2,824 PHP lines. [LOCAL]

| Piece | Role | Reuse verdict |
| --- | --- | --- |
| `Domain/ValueObjects/TimeRange`, `WordTimestamp` | Pure VOs | ✅ Reuse as-is |
| `Domain/Services/CutPlanner` (`invertCuts`, `totalDuration`) | Merges cut ranges → keep ranges | ✅ Reuse — pure and unit-testable |
| `Domain/Services/SilenceCutParser` | Parses `silencedetect` stderr | ✅ Reuse |
| `Domain/Services/FillerCutDetector` (302 lines) | Filler/stutter/`PAUSA` detection over Whisper words | ◻️ Later phase (needs transcription) |
| `Infrastructure/Pipeline/FfmpegBinaryRunner` (554 lines) | Raw process runner: merge, render (trim/atrim/concat), chunked render, silence detect, audio extract/replace | ⚠️ Overlaps ~70% with laravel-ffmpeg — replace, keeping only what the package cannot do (§4) |
| `Infrastructure/Pipeline/InputResolver` | Resolves inputs, **SSRF allowlist** + private-host block | ✅ Keep the security logic (OWASP A10) |
| `Application/Services/VideoExportJobStore` | Job status in **cache**, not DB | ⚠️ Decision needed (§8 Q2): no history, no report, no re-edit |
| `VideoExportPipeline::runMerge/runClean` | Orchestrates the flow; **deletes source parts** after success | ⚠️ Decision needed (§8 Q5) |
| `AudioEnhanceChain`, arnndn/HTTP denoise adapters | Audio enhancement | ◻️ Out of scope for now |
| `OpenAiWhisperTranscriber`, `ReviewScriptAgainstTranscriptAgent` | Whisper + AI script review (free-text review, not a JSON contract) | ◻️ Later phase — must be redesigned to return a validated cut-list JSON, as `PROMPT-MODULE-VIDEO-EXPORT.md` asks |

**`PROMPT-MODULE-VIDEO-EXPORT.md` key points:**
- Splits the work into MERGE (FFmpeg only), AUTO EDIT (silencedetect + Whisper) and AI EDIT (Gemini → validated JSON → FFmpeg).
- Whisper is NOT needed for merge or silence removal.
- FFmpeg must be the only component that physically edits.

These points are the basis for this module's boundary: **the editor consumes a validated cut list and never cares who produced it.**

**`PROMPT-EDICION-VIDEOS-2.md`** is a per-video editorial prompt ("Video 6.1": target 10–15 min, a `PAUSA` rule, a topic audit against the script, a cut table). It is user content, a template for a future AI phase, and must not become hardcoded logic. Out of scope for v1.

**Contradiction flagged:** the guide prompt says "do not introduce PrimeVue; keep shadcn-vue / reka-ui, TanStack Vue Form, Lucide, vue-sonner". This project's rules (`.claude/rules/rules.md`) mandate **PrimeVue v4 unstyled + Volt, `@primevue/forms` + Zod, primeicons, PrimeVue Toast**. The project rules win; this affects only the future frontend spec.

## 7. Fit assessment — does laravel-ffmpeg fit this project?

**Yes, as an infrastructure adapter behind a port, not as the domain.**

- ✅ Covers merge, probing, progress, disk I/O (R2/S3 via Flysystem), temp cleanup, logging and typed exceptions. That removes most of the guide's 554-line raw runner.
- ✅ Multi-cut render is achievable through `addFilter` complex graphs + `addFormatOutputMapping`.
- ⚠️ We still own:
  - the keep-range graph builder
  - silence stderr parsing
  - the long-graph fallback (chunked extraction, or `-/filter_complex` file via `beforeSaving`)
  - Pest fakes
- ⚠️ R1: untested upstream on FFmpeg 7/8. Mitigate with an opt-in real-binary integration test (skipped when the binary is absent).
- Design rule [UNVERIFIED — plan decision]:
  - The Domain (`TimeRange`, `CutPlanner`, `CutList` validation) never imports `ProtoneMedia\*` or `FFMpeg\*`.
  - A `VideoEditorPort` (`merge`, `probeDuration`, `detectSilences`, `render(keepRanges)`) gets one `LaravelFfmpegVideoEditor` adapter plus a `FakeVideoEditor` for tests.
  - The port is justified under the Repository/Port rule because a DB-free fake is required: the binary is absent in CI and on this machine.

## 8. Open decisions to carry into Clarify / Plan

| # | Question | Impact |
| --- | --- | --- |
| Q1 | v1 scope: merge + silence removal + explicit cut list? (Whisper/AI/report excluded) | Defines every user story |
| Q2 | Edit jobs persisted in DB (history, re-edit, future report) vs cache-only like the guide | Data model, report, re-edit |
| Q3 | Input: presigned direct upload to R2 (guide pattern) vs upload through the app; max size/duration | Upload security, timeouts, worker memory |
| Q4 | Cut accuracy: frame-accurate re-encode (recommended) vs fast keyframe copy | Processing time vs quality |
| Q5 | Keep source videos after success (enables re-edit) or delete like the guide | Storage cost vs re-edit |
| Q6 | Output profile: fixed 1080p H.264/AAC MP4 (guide) vs keep the source resolution | Merge normalization |
| Q7 | Install FFmpeg locally (version to pin) + queue worker availability on Herd | Implementation blocker |

## 9. Plan-phase verification (2026-09-11)

| # | Finding | Impact on plan | Source |
| --- | --- | --- | --- |
| 9.1 | Database is **PostgreSQL 17.6** (Supabase pooler, connection `pgsql`) | "One active edit per user" (FR-16) can be enforced with a **partial unique index** (`WHERE status IN ('queued','processing')`), not only app-level locks | [LOCAL] `php artisan db:show` |
| 9.2 | `Storage::temporaryUploadUrl($path, $expiresAt)` returns `['url' => …, 'headers' => …]`; supported by S3 (and local) drivers in Laravel 13 | Direct browser → R2 upload (D1). The project already wraps it: `Shared\Domain\Ports\StoragePort::temporaryUploadUrl()` (R2StorageAdapter) | [C7] https://laravel.com/docs/13.x/filesystem · [LOCAL] `src/Shared/Domain/Ports/StoragePort.php` |
| 9.3 | Laravel 13 job attributes `#[Tries]`, `#[Timeout]`, `#[Backoff]` (namespace `Illuminate\Queue\Attributes`); the worker `--timeout` / job timeout **must be shorter than the connection's `retry_after`**, or the job is processed twice; timeouts require the **PCNTL** extension | 60-min render job (D13) needs `retry_after` > 3600 s on its queue connection. PCNTL is unavailable on native Windows (Herd), so locally the timeout is NOT enforced — only on the Linux worker (Railway) [UNVERIFIED for Herd specifically] | [C7] https://laravel.com/docs/13.x/queues |
| 9.4 | Railpack installs runtime system packages via `RAILPACK_DEPLOY_APT_PACKAGES="... ffmpeg"` (the `...` keeps Railpack's own list) or `railpack.json` → `deploy.aptPackages` | FFmpeg + FFprobe on the Railway worker without a custom Dockerfile | [TAVILY] https://railpack.com/guides/installing-packages · https://railpack.com/config/environment-variables |
| 9.5 | Existing reusable Shared pieces: `AuditPort` + `SpatieActivityLogAdapter`; `StoragePort` + `R2StorageAdapter` (`temporaryUrl`, `temporaryUploadUrl`, `copyToLocal`, `putFromPath`, `delete`); `AIClientInterface::generateStructured()` + `LaravelAIAdapter` with `CircuitBreaker` (V3); `DomPdfExportAdapter` (V3 PDF) | V1 creates NO new Shared adapter; V3 reuses the AI + PDF adapters | [LOCAL] `src/Shared/**` |

## 10. Redis (Upstash) queue verification (2026-09-11, decision P3)

| # | Finding | Impact on plan | Source |
| --- | --- | --- | --- |
| 10.1 | The project already targets **Upstash**: `config/database.php` requires TLS on the host (`REDIS_HOST="tls://<endpoint>.upstash.io"`, no separate `scheme` option) and **only database 0** exists. The client is `phpredis` with retry/backoff settings | Dev/prod separation cannot use DB indexes, so it must be **separate Upstash databases per environment**, plus an env-scoped key prefix as a safety net | [LOCAL] `config/database.php` L146–206 |
| 10.2 | Local Herd PHP has the `redis` extension loaded; **`pcntl` is NOT loaded** | Local workers run, but do not enforce `#[Timeout]` (risk R3 stands) | [LOCAL] `php -m` |
| 10.3 | Railpack installs PHP extensions from `composer.json` `ext-*` requirements or from `RAILPACK_PHP_EXTENSIONS` (e.g. `gd,imagick,redis`) | Railway worker: set `RAILPACK_PHP_EXTENSIONS` to include `redis` and `pcntl` (avoids a composer dependency change). Whether `pcntl` is already in Railpack's default PHP image is `[UNVERIFIED]` | [TAVILY] https://railpack.com/languages/php |
| 10.4 | The Laravel 13 Redis queue supports `block_for` (wait for jobs instead of re-polling). **`block_for = 0` blocks indefinitely and prevents SIGTERM handling**; `retry_after` must exceed the job timeout. `queue:work {connection} --queue={name}` | Dedicated `video-edits` connection: `block_for = 5`, `retry_after = 3900`, `after_commit = true` | [C7] https://laravel.com/docs/13.x/queues |
| 10.5 | Upstash bills per command (PAYG ~$0.20 / 100K; free tier 500K/month); fixed-price plans exist | An always-on idle worker still issues commands every loop. Rough estimate ≈ 1.5M commands/month with `block_for = 5` `[UNVERIFIED — depends on how Upstash counts EVAL/BLPOP]`, so dev workers should run only when needed | [TAVILY] https://upstash.com/blog/upstash-vs-redis-cloud-a-2026-comparison · [LOCAL] `config/database.php` note |
| 10.6 | Upstash eviction must be **disabled** on the queue database, or queued jobs can be silently evicted `[UNVERIFIED — Upstash default eviction setting]` | Setup checklist item (task), verified in the Upstash console | model knowledge |
| 10.7 | Horizon works on Redis queue connections defined in `config/queue.php`; the jobs' `tags()` method (as in `GenerateInvoicePdfJob`) is picked up by Horizon | A redis-driver `video-edits` connection + `tags()` keeps V1 Horizon-compatible with zero Horizon code | [LOCAL] precedent · `[UNVERIFIED]` Horizon docs not re-queried (Horizon out of scope) |

## Sources

- https://packagist.org/packages/pbmedia/laravel-ffmpeg [WEB]
- https://github.com/protonemedia/laravel-ffmpeg [TAVILY][C7]
- https://github.com/protonemedia/laravel-ffmpeg/releases [WEB]
- https://github.com/protonemedia/laravel-ffmpeg/issues?q=ffmpeg+7 [WEB]
- https://raw.githubusercontent.com/protonemedia/laravel-ffmpeg/main/README.md [WEB]
- https://packagist.org/packages/php-ffmpeg/php-ffmpeg [WEB]
- https://ffmpeg.org/ffmpeg-all.html (option-from-file `-/option`, complex filtergraphs) [C7]
- https://www.owlclip.com/blog/how-to-trim-a-video-using-ffmpeg [TAVILY]
- https://www.ffmpeg-micro.com/blog/ffmpeg-trim-cut-video [TAVILY]
- https://www.ffmpeg-micro.com/blog/ffmpeg-concat-merge-videos [TAVILY]
- https://www.mux.com/articles/clip-sections-of-a-video-with-ffmpeg [TAVILY]
- https://github.com/btbn/ffmpeg-builds/releases [TAVILY]
