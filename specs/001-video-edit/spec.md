# Specification: Video Edit

> Phase 1 · SPECIFY — Defines WHAT is built and WHY. No technical stack here (see `research.md` / future `plan.md`).
> Updated after Phase 2 · CLARIFY — every decision is traceable in `clarify.md` (Q = user answer, D = resolved by default).

**Feature ID:** 001-video-edit
**Date:** 2026-09-11
**Status:** In review (clarified)

## 1. Summary

**Video Editor** is a backend module that produces edited videos from recorded clips. The product has **three editing modes**, delivered in three versions:

| Mode | What it does | Delivered in |
| --- | --- | --- |
| **Merge** | Joins several clips into one video. No content analysis. | **V1** |
| **Auto Edit** | Removes unwanted parts detected automatically: silences and manual ranges in V1; fillers, stutters, repetitions and vocal sounds from speech transcription in V2. | **V1** (silence + manual), **V2** (speech) |
| **AI Edit** | An AI provider analyses the transcription and an optional script (MD/PDF) and returns structured edit decisions (pause markers, errors, off-script content), which are validated and then applied. Includes a decision report and a PDF. | **V3** |

All modes end in the same place: a validated list of cut decisions is rendered by one editing core into the final video, together with a record of what was removed and why.

**V1** implements Merge and the silence/manual part of Auto Edit. It also keeps edit history, lets the user re-edit from previous settings, retry failed edits, and permanently delete edits. Source recordings are not kept; they are deleted once the edit finishes.

## 2. Motivation / Business context

Course and tutorial recordings are captured in several takes and contain long silences, filler words, false starts and explicit "PAUSA" markers the speaker says aloud to flag a retake. Today this cleanup is manual in a desktop editor, which is slow and inconsistent.

A reference implementation exists (`GUIDE/VideoExport`), but it mixes transcription, AI review, audio enhancement and rendering in one pipeline, keeps no durable edit record, and its AI step returns free text instead of verifiable decisions.

The product's end goal is the AI Edit mode. Building V1 as an isolated editing core that consumes validated decisions means V2 (speech) and V3 (AI) add new **decision producers** instead of rewriting the editor.

## 3. Actors

- **Content creator** (authenticated user with the relevant edit permissions): uploads videos, chooses the mode and parameters, follows progress, downloads results, browses history, re-edits from previous settings, and hard-deletes edits. From V3, also uploads a script and reads the AI decision report.
- **Background processor** (system): executes edits outside the request cycle, reports status, progress and outcome, and cleans up source files.
- **Scheduled cleanup** (system): deletes sources of failed edits once their retry window expires.
- **Speech transcription service** (external, **V2**): turns spoken audio into text with segment and word timestamps. It must be replaceable.
- **AI analysis provider** (external, **V3**; Gemini planned): analyses the transcript and script and returns structured edit decisions. It must be replaceable without changing the editor.

## 4. Product roadmap and delivery principle

### 4.1 Delivery principle (binding for V1)

> **Implement V1 without Whisper/Gemini, but design the domain and interfaces so that Whisper-based semantic editing and Gemini-based AI editing can be added later without changing the core video editing pipeline. The AI version is part of the product roadmap and must be explicitly accounted for in the architecture/specification.**

"V1 has no AI" does **not** mean "design now, figure out AI later". The requirements in §6.2 (EX-*) are V1 requirements, even though they exist to serve V2 and V3.

### 4.2 Conceptual pipeline (all versions)

```text
                         VIDEO EDITOR
                              │
          ┌───────────────────┼───────────────────┐
          ↓                   ↓                   ↓
        MERGE             AUTO EDIT            AI EDIT
          │                   │                   │
          │        V1: silence detection    V3: AI provider
          │        V1: manual ranges            (+ script MD/PDF)
          │        V2: speech transcription       │
          │            → speech detectors         │
          │                   │                   │
          │                   └─────────┬─────────┘
          │                             ↓
          │               CUT DECISIONS (one contract)
          │                             ↓
          │               VALIDATION & NORMALIZATION
          │                             ↓
          └──────────────────────→   RENDER   ←── the only step that edits the video
                                        ↓
                            FINAL VIDEO + EDIT SUMMARY
                                        ↓
                            V3: AI DECISION REPORT + PDF
```

### 4.3 Versions

| Version | Content | Status |
| --- | --- | --- |
| **V1** | Merge; silence removal (configurable threshold); manual ranges; async processing with progress; history; re-edit from settings; retry of failed edits; hard delete; edit summary | **Specified and to be implemented now** |
| **V2** | Speech transcription with segment and word timestamps; detection of filler sounds (muletillas), filler words, stutters/repetitions and vocal sounds; automatic editing from those detections; transcript stored for reuse | **Implemented** (2026-09-12) — OpenAI Whisper behind `TranscriptionPort`, `SpeechDecisionProducer` registered on the V1 producer tag. Resolves R1 (vocal sound = bracketed non-verbal audio), R3 (Spanish default, config-driven dictionaries), R4 (auto-applied), R5 (fingerprint + order). R2 (wind noise) remains out of scope — it needs an audio-analysis producer, not a speech one. |
| **V3** | AI provider (Gemini planned, replaceable); detection of spoken pause markers ("PAUSA", "PAUSA AQUÍ", "PAUSA ACÁ", …); script analysis from `.md` and `.pdf`; script ↔ transcript comparison; structured JSON edit decisions validated before use; automatic removal of the decided segments; report of what the AI decided; PDF of the report | **Implemented** (2026-09-12) — `gemini-3.7-flash` through the Shared `AIClientInterface`, behind `AiEditAnalysisPort`. **Hybrid by necessity:** Gemini reports video positions as `MM:SS` at 1 FPS, too coarse for a frame-accurate cut (FR-6), so the model reasons over the V2 transcript and answers in WORD INDICES, which are resolved to exact milliseconds from Whisper's timings. Resolves R6 (only `pause_marker`/`retake` auto-apply, at ≥ 0.8; editorial findings are report-only), R7 ("REDUCIR" is a recommendation, never a cut), R9 (explicit consent required before anything is sent), R10 (only validated decisions and recommendations stored, never the raw response). |

## 5. User stories

### 5.1 V1 — implemented now

#### US-1: Merge several videos into one (Priority: High)
**As a** content creator, **I want** to join several recorded clips in a chosen order, **so that** I get one continuous video without a desktop editor.

**Acceptance criteria:**
- [ ] Given 2–10 valid videos in a defined order, when I request a merge, then the request is accepted immediately with an edit identifier and the final video contains every clip in that order with its audio.
- [ ] Given clips with different resolutions or frame rates, when they are merged, then the result uses one uniform output profile (max 1920×1080, aspect ratio preserved and padded, constant frame rate from the first clip capped at 60 fps) and no image is stretched. (D3)
- [ ] Given a clip that has no audio track, when it is merged with clips that do, then the result keeps continuous audio (silence during that clip) and the edit does not fail.
- [ ] Given an input that is not a valid video, exceeds 2 GB, or makes the total exceed 90 minutes, when I submit, then the request is rejected (or the edit fails) with a readable reason and no output is published. (D2)

#### US-2: Remove silences longer than a threshold (Priority: High)
**As a** content creator, **I want** silences longer than a threshold removed automatically, **so that** the video goes straight to the point.

**Acceptance criteria:**
- [ ] Given no threshold is specified, when I request silence removal, then silences of **1 second or longer** are removed.
- [ ] Given I specify a threshold between 0.3 s and 10 s (e.g. 3 s), when the edit runs, then only silences at least that long are removed and shorter pauses are kept. (D4)
- [ ] Given a threshold outside 0.3–10 s, when I submit, then the request is rejected with a validation error before any processing.
- [ ] Given a video with no qualifying silence, when the edit runs, then the edit completes, the output duration matches the original within tolerance, and the summary reports 0 cuts.
- [ ] Given a silence is removed, when the result is played, then 0.15 s of the silence is kept on each side so speech next to the cut is not clipped. (D5)

#### US-3: Remove a manual list of time ranges (Priority: High)
**As a** content creator (and, from V2/V3, an automated decision producer), **I want** to submit a list of time ranges to remove, **so that** any source of edit decisions drives the same editor.

**Acceptance criteria:**
- [ ] Given up to 500 valid ranges, when the edit runs, then exactly those ranges are removed frame-accurately and the rest of the video is kept in order with audio and video in sync. (Q4, D9)
- [ ] Given several sources, when I submit ranges, then the ranges refer to the merged timeline (the video after merging, before any cut). (D7)
- [ ] Given a range with start ≥ end, a negative time, or more than 500 ranges, when I submit, then the whole request is rejected with per-range validation errors before any processing.
- [ ] Given the interface can read the local videos' durations, when I enter ranges, then ranges beyond that duration are flagged before submission. (P1)
- [ ] Given a range whose end is beyond the real merged duration (known only once the uploaded media is inspected), when processing starts, then the edit fails **before any merging, analysis or rendering** with a per-range error, and I can correct the ranges and retry (US-8). (P1)
- [ ] Given overlapping or adjacent ranges, when the edit runs, then they are merged into one cut that keeps every contributing reason and origin. (D8)
- [ ] Given ranges that would leave less than 1 s of output, when I submit, then the request is rejected. Kept fragments shorter than 0.25 s are absorbed into the adjacent cut. (D6)
- [ ] Given silence removal and manual ranges are requested together, when the edit runs, then both sets are combined into one set of cuts.

#### US-4: Follow the status and progress of an edit (Priority: High)
**As a** content creator, **I want** to see the state and progress of my edit, **so that** I know when the result is ready or why it failed.

**Acceptance criteria:**
- [ ] Given a submitted edit, when I check its status, then I see one of: queued, processing, completed, failed; while processing I also see a single 0–100 % progress value and the current stage. (D16)
- [ ] Given a failed edit, when I check it, then I see a user-safe reason with no internal commands, file paths or stack traces.
- [ ] Given I already have an edit queued or processing, when I submit another, then it is rejected until the first one finishes. (D10)
- [ ] Given an edit that exceeds 60 minutes of processing, when the limit is reached, then it is marked failed; transient failures are retried automatically up to 2 times first. (D13)
- [ ] Given an edit owned by another user, when I request it, then I get "not found" (its existence is not revealed).

#### US-5: Get the result and an edit summary (Priority: High)
**As a** content creator, **I want** to download the final video and see what was removed, **so that** I can trust the automatic edit.

**Acceptance criteria:**
- [ ] Given a completed edit, when I request the result, then I get a download link valid for 15 minutes, and I can request a new one at any time. (D12)
- [ ] Given a completed edit, when I open its summary, then I see:
  - mode, parameters and number of sources
  - original (merged) duration, final duration, total removed time and number of cuts
  - every cut: start, end, duration, reasons, origins
  - warnings
- [ ] Given the download link has expired, when I use it, then access is denied.

#### US-6: Browse my edit history (Priority: Medium)
**As a** content creator, **I want** a list of my past edits, **so that** I can find a result or reuse its settings.

**Acceptance criteria:**
- [ ] Given I have edits, when I open my history, then I see only my own edits, newest first and paginated, each with mode, status, final duration, number of cuts and creation date.
- [ ] Given a completed edit in my history, when I open it, then its summary (US-5) is available for as long as the edit exists. (D11)

#### US-7: Re-edit from a previous edit's settings (Priority: Medium)
**As a** content creator, **I want** to start a new edit prefilled with a previous edit's mode, parameters and cut list, **so that** I don't have to configure it again. (Q3b)

**Acceptance criteria:**
- [ ] Given a previous edit, when I choose re-edit, then I get its mode, silence threshold and manual ranges as a starting point, and I must upload the source videos again.
- [ ] Given the re-uploaded sources have a different duration, when processing starts, then the prefilled ranges are validated again against the real duration; any out-of-range range fails the edit with a per-range error and can be corrected on retry (US-8). (D15, P1)
- [ ] Given a re-edit is submitted, when it runs, then it creates a new edit linked to the original, and the original edit and its result stay unchanged.

#### US-8: Retry a failed edit without re-uploading (Priority: Medium)
**As a** content creator, **I want** to retry a failed edit within 24 hours, **so that** a transient problem doesn't force me to upload again. (Q3c)

**Acceptance criteria:**
- [ ] Given an edit failed less than 24 hours ago, when I retry it, then processing starts again with the retained sources and the same parameters.
- [ ] Given an edit failed because of invalid manual ranges less than 24 hours ago, when I retry it, then I can submit corrected manual ranges; they are validated again and the retained sources are reused. (P1)
- [ ] Given an edit failed more than 24 hours ago, when the scheduled cleanup runs, then its sources are deleted and retry is no longer offered (re-edit, US-7, remains available).

#### US-9: Permanently delete an edit (Priority: Medium)
**As a** content creator, **I want** to permanently delete an edit, **so that** its video and data no longer exist. (Q2, Q5)

**Acceptance criteria:**
- [ ] Given a completed, failed or queued edit that I own, when I confirm deletion, then the edit record, its cuts and detections, the result video and any remaining source files are permanently removed; a queued edit is never processed.
- [ ] Given an edit that is processing, when I request deletion, then it is rejected until processing ends. (D14)
- [ ] Given an edit is deleted, when anyone looks for it, then it cannot be found or restored; only audit entries remain (the deletion entry — who deleted which edit ID, and when — plus earlier submission/retry entries), none containing media, file names or personal data.
- [ ] Given an edit owned by another user, when I request deletion, then I get "not found".

### 5.2 V2 — roadmap (speech-based Auto Edit). Not implemented in V1; V1 must not block it.

#### US-10: Auto Edit from speech (Priority: V2)
**As a** content creator, **I want** filler sounds, filler words, stutters/repetitions and vocal sounds removed automatically, **so that** the speech sounds clean without manual work.

**Acceptance criteria (roadmap level):**
- [ ] Given Auto Edit with speech detection enabled, when the edit runs, then the audio is transcribed with segment and word timestamps.
- [ ] Given a transcript, when detection runs, then candidate cuts are produced for fillers (e.g. "eh", "este", "mmm"), filler words, stutters/repetitions and vocal sounds, each with reason, origin `transcription` and confidence when available.
- [ ] Given candidate cuts, when they are applied, then they go through the same validation, normalization and render as V1 cuts.
- [ ] Given I choose which detection categories to apply, when the edit runs, then only those categories become cuts, and the others are still listed in the summary as not applied.

#### US-11: Reuse a stored transcript (Priority: V2)
**As a** content creator, **I want** a re-edit of identical sources to reuse the stored transcript, **so that** I don't pay for or wait on transcription again.

**Acceptance criteria (roadmap level):**
- [ ] Given a re-edit whose re-uploaded sources are identical in content and order to a previous edit's, when it runs, then the stored transcript is reused and no transcription is requested.
- [ ] Given different sources, when it runs, then a new transcript is produced.

### 5.3 V3 — roadmap (AI Edit). Not implemented in V1; V1 must not block it.

#### US-12: AI Edit with an optional script (Priority: V3)
**As a** content creator, **I want** an AI to analyse my recording's transcript and, optionally, my script (`.md` or `.pdf`), and decide which segments to remove, **so that** the final video follows the script and is free of errors and retakes.

**Acceptance criteria (roadmap level):**
- [ ] Given AI Edit, when I upload a script in `.md` or `.pdf` (validated type and size), then its text is extracted and used as context. The script is user content, never hardcoded rules.
- [ ] Given optional editorial instructions (e.g. a target duration or a style of prompt like `PROMPT-EDICION-VIDEOS-2.md`), when the edit runs, then they are passed as user-provided context, not as built-in logic.
- [ ] Given the transcript (and script), when the AI analyses them, then it returns **only structured edit decisions** (start, end, reason, confidence, evidence), never free-form editing instructions.
- [ ] Given the AI response, when it is received, then every decision is validated against the cut-decision contract. Invalid decisions are rejected and reported, and only valid decisions reach render.
- [ ] Given the AI provider is replaced by another, when AI Edit runs, then validation, render, summary and report behave identically.

#### US-13: Remove spoken pause markers and retakes (Priority: V3)
**As a** content creator, **I want** every spoken "PAUSA" / "PAUSA AQUÍ" / "PAUSA ACÁ" marker removed together with the failed take before it, **so that** retakes disappear without manual work.

**Acceptance criteria (roadmap level):**
- [ ] Given a spoken pause marker in the transcript, when AI Edit runs, then a decision with reason `pause_marker` covers the marker and a decision with reason `retake` covers the preceding erroneous take, both with timestamps and confidence.
- [ ] Given script ↔ transcript comparison, when the AI finds errors, wrong words or off-script content, then it produces decisions with the corresponding reason and evidence.

#### US-14: AI decision report and PDF (Priority: V3)
**As a** content creator, **I want** a report of what the AI decided and why, viewable in the app and downloadable as PDF, **so that** I can audit the edit.

**Acceptance criteria (roadmap level):**
- [ ] Given a completed AI Edit, when I open the report, then I see:
  - original and final video, mode, original and final duration, removed time, number of removed segments
  - every decision: timestamps, reason, confidence, evidence, and applied or rejected with the rejection reason
  - script coverage findings
  - errors and warnings
- [ ] Given the report, when I request the PDF, then I get a downloadable PDF with the same content, generated from stored data without reprocessing the video.

## 6. Functional requirements

### 6.1 V1 behaviour

- **FR-1**: The system MUST merge 2–10 videos in a caller-defined order into one video with the uniform output profile (D3).
- **FR-2**: The system MUST detect silences at least as long as a threshold (default 1 s, allowed 0.3–10 s) and remove them, keeping a 0.15 s margin on each side.
- **FR-3**: The system MUST remove a manual list of up to 500 time ranges expressed on the merged timeline.
- **FR-4**: The system MUST validate cut ranges in two steps.
  - **On submission:** start < end, start ≥ 0, at most 500 ranges.
  - **When processing starts, against the real media duration, before any merging, analysis or rendering:** end ≤ merged duration and at least 1 s of output left. Overlapping ranges are merged and fragments < 0.25 s are absorbed.
  - Invalid ranges fail the edit with per-range errors. (P1)
- **FR-5**: The system MUST combine cut decisions from every producer into one normalized set that keeps every contributing reason and origin.
- **FR-6**: The system MUST cut frame-accurately and keep audio and video synchronized within one frame.
- **FR-7**: The system MUST process edits asynchronously, exposing status (queued, processing, completed, failed), a single progress percentage and the current stage.
- **FR-8**: The system MUST persist every edit: owner, mode, parameters, source metadata, original and final duration, removed time, every cut decision, warnings, failure reason, and the result location.
- **FR-9**: The system MUST delete source files as soon as an edit completes successfully.
- **FR-10**: The system MUST retain the sources of a failed edit for 24 hours to allow retry, then delete them automatically.
- **FR-11**: The system MUST let the owner retry a failed edit within its 24-hour window. When the failure was caused by invalid manual ranges, the retry MUST accept corrected ranges and validate them again. (P1)
- **FR-12**: The system MUST let the owner start a new edit prefilled with a previous edit's mode, parameters and cut list, requiring fresh source uploads and re-validating the ranges.
- **FR-13**: The system MUST let the owner permanently delete an edit that is not processing. This removes the record, all its decisions, the result video and any remaining sources, and leaves only an audit entry.
- **FR-14**: The system MUST list the owner's edit history, newest first and paginated.
- **FR-15**: The system MUST deliver results only through owner-scoped download links that expire after 15 minutes.
- **FR-16**: The system MUST allow at most one queued or processing edit per user.
- **FR-17**: The system MUST retry transient processing failures up to 2 times and mark an edit failed after 60 minutes of processing.
- **FR-18**: The system MUST remove all temporary working files when processing ends, whether it succeeded or failed.
- **FR-19**: The system MUST accept only uploads to storage locations it issued. It MUST NOT fetch arbitrary remote URLs.
- **FR-20**: The system MUST NOT expose internal commands, file paths, provider responses or low-level error output to users.
- **FR-21**: The system MUST restrict every operation (upload, submit, view, download, retry, re-edit, delete) to authorized users, and each user sees and acts only on their own edits.

### 6.2 Extensibility requirements — V1 MUST satisfy these so V2/V3 need no change to the core

- **EX-1 · One decision contract.**
  - Every cut, whatever produced it, has the same shape:
    - start and end on the merged timeline
    - reason from an extensible taxonomy
    - origin
    - optional confidence (0–1)
    - optional evidence (e.g. transcript excerpt, script reference, detection rule)
    - validation outcome (applied, or rejected with a reason)
  - V1 produces reasons `silence` and `manual`; the taxonomy is open for V2 (`filler`, `filler_word`, `stutter`, `repetition`, `vocal_sound`) and V3 (`pause_marker`, `retake`, `script_error`, `off_script`, `redundant`, …).
- **EX-2 · Pluggable decision producers.** Silence detection and manual ranges are the V1 producers. Adding a producer (speech detectors in V2, AI analysis in V3) MUST NOT change validation, normalization, render, result storage or deletion.
- **EX-3 · Render consumes only validated decisions.** The editing core never receives raw detector output, transcripts or AI responses, only a validated, normalized decision set. V1 manual ranges already take this path, so V3 AI decisions reuse it unchanged.
- **EX-4 · Modes are first-class.**
  - Merge, Auto Edit and AI Edit exist as modes in the edit record from V1.
  - A mode defines which producers run.
  - V1 accepts Merge and Auto Edit (silence + manual) and rejects AI Edit as "not available yet".
- **EX-5 · Replaceable external providers.** Transcription (V2) and AI analysis (V3) are reached through provider-neutral contracts. Replacing a provider MUST NOT change detectors, validation, render or reporting.
- **EX-6 · Transcript reuse is possible.**
  - V1 records a content fingerprint and media properties for every source, plus the source order.
  - V2 can then recognise identical re-uploaded sources (US-11) even though source files are deleted.
- **EX-7 · Stages are extensible.**
  - Processing is an ordered list of stages with weighted progress. V1: merge, silence analysis, render.
  - V2 adds audio extraction, transcription and speech detection; V3 adds script extraction, AI analysis and decision validation.
  - Adding a stage MUST NOT change existing stages or the status contract.
- **EX-8 · Report-ready data.**
  - The stored edit data includes every decision, applied and rejected, with reason, origin, confidence, evidence and warnings.
  - The V3 report and PDF can then be produced from stored data alone, without reprocessing the video.
- **EX-9 · Edit inputs accept attachments.**
  - The edit request model allows optional attached documents (V3 script `.md`/`.pdf`) and optional free-text editorial instructions as user data.
  - V1 does not expose them.

## 7. Non-functional requirements

- **Performance / resources** (Q7):
  - Submitting an edit responds in < 1 s p95; processing always happens in the background.
  - **No artificial memory limits and no low-memory processing modes** on the processing workers.
  - Target hosts: production worker with **8 GB RAM**, local development with **32 GB RAM**.
  - A 20-minute 1080p recording with up to 200 cuts MUST complete on the 8 GB production host (to be benchmarked in Plan).
- **Security**:
  - Authenticated users with explicit per-operation permissions.
  - Uploads validated by type (MP4, MOV, WebM, MKV), size (≤ 2 GB) and extension.
  - No user-supplied value reaches a system command unvalidated.
  - Private storage with signed, expiring access.
  - Audit trail of submissions and hard deletions, with no media or personal data in logs.
  - From V3, AI output is treated as untrusted input and validated before use, and scripts are treated as untrusted content (prompt-injection resistant).
- **Availability**:
  - A job never stays "processing" beyond its timeout.
  - A worker crash leaves a failed, retryable edit, never a silently lost one.
  - From V2/V3, an unavailable external provider fails the edit with a user-safe reason; it never produces a partially edited video.
- **Scalability**: single-developer project with low volume — tens of edits per week over the next 6–12 months. One active edit per user.
- **Compliance / privacy**:
  - Recordings may contain the creator's voice and face (personal data).
  - Sources are held only while needed: until success, or 24 h after failure.
  - Results live until the owner hard-deletes them, and deletion is permanent.
  - From V2/V3, sending audio, transcripts or scripts to external providers requires the user to be informed, and hard delete also removes stored transcripts, detections and AI outputs.

## 8. Data entities (conceptual)

### V1
- **Video edit**: one requested edit and its history record. It has:
  - an owner, mode (merge, auto edit, AI edit) and parameters (e.g. silence threshold)
  - status (an internal draft state while sources are uploading, then queued, processing, completed or failed; drafts never appear in history and expire after 24 h — D17), progress, current stage, attempt count, timestamps and a user-safe failure reason
  - original and final duration, removed time, warnings, and the result video location
  - an optional link to the edit it was re-edited from
- **Edit source**: a video uploaded for one edit, with original file name, content fingerprint, duration, basic media properties, order position and a transient file location. The metadata stays with the edit; the file is deleted per FR-9/FR-10.
- **Cut decision**: a time interval proposed by a producer (EX-1): start, end, reason, origin, optional confidence and evidence, and validation outcome. It belongs to one edit.
- **Applied cut**: a normalized, merged interval actually removed from the result, referencing the decisions that formed it.
- **Audit entry**: a record of submissions and hard deletions (actor, edit identifier, action, timestamp), without media or personal data. It outlives a deleted edit.
- **Output profile**: the uniform delivery format (D3), a system setting.

### Roadmap (V1 must leave room for them; not created in V1)
- **Transcript** (V2): text with segment and word timestamps for a set of sources, identified by their fingerprints and order, and linked to the transcription provider used. Reusable across edits.
- **Script document** (V3): an uploaded `.md`/`.pdf` attached to an edit, with its extracted text.
- **AI analysis run** (V3): one provider call for an edit, with provider, model, instruction version, request context summary, validated decisions, rejected decisions and errors.
- **Edit report** (V3): the report view of an edit (derived from stored data) and its generated PDF.

## 9. Out of scope for V1 (planned)

| Item | Planned in |
| --- | --- |
| Speech transcription, segment/word timestamps | V2 |
| Filler sounds, filler words, stutters/repetitions, vocal sounds detection | V2 |
| Transcript reuse | V2 |
| AI provider integration, AI Edit mode | V3 |
| Spoken pause marker ("PAUSA", …) and retake detection | V3 |
| Script upload (`.md`/`.pdf`), extraction and script ↔ transcript comparison | V3 |
| Structured AI decision JSON + validation | V3 (the validation path itself exists from V1 — EX-3) |
| AI decision report and PDF | V3 |
| Audio enhancement/denoise, HLS streaming, thumbnails, watermarks | Not planned |
| Soft delete / restore of edits | Not planned (deletion is permanent — Q2/Q5) |

Frontend UI is a separate frontend spec. Its decided stack (Q6) is **shadcn-vue (reka-ui) + Zod 4 + Pinia Colada + TanStack Vue Form + vue-sonner**. The global project rules are intentionally not modified (Q6b).

## 10. Assumptions and open decisions

- **V1:** all high-impact questions resolved — see `clarify.md` §1. Lower-impact decisions resolved by default — see `clarify.md` §2.
- **V2/V3:** open questions that do NOT block V1 are tracked in `clarify.md` §3. They must be resolved before their version is planned.
- Assumption: the video processing binaries are installed on the processing hosts. They are **not** installed on the current development machine (see `research.md` §1). This blocks implementation, not planning.
- Assumption: the guide's pure domain logic (time ranges, cut inversion, silence parsing, filler detection) is a valid starting point. Its cache-only job tracking, immediate source deletion on every outcome, low-memory render mode, and free-text AI review are NOT adopted (FR-8, FR-10, Q7, EX-3).

## 11. Success criteria (measurable)

### V1
- Merging 3 test clips produces a video whose duration equals the sum of the inputs ±0.5 s, with A/V offset ≤ 1 frame.
- On a fixture with known silences, 100% of silences ≥ threshold are removed and 0 silences < threshold are removed.
- A manual cut list of 50 ranges produces an output duration equal to merged duration − Σ(merged ranges) ±0.1 s.
- 100% of invalid cut lists are rejected before any merging, analysis or rendering starts, with a per-range error.
- A 20-minute 1080p recording with 200 cuts completes on an 8 GB host.
- 0 source files remain after a successful edit; 0 source files older than 24 h remain for failed edits.
- 0 edits remain in "processing" after their timeout, and 0 temporary files remain after processing ends.
- After a hard delete, 0 edit/source/decision/cut rows and 0 stored files remain for that edit, exactly 1 deletion audit entry exists, and no remaining audit entry contains media, file names, paths or signed URLs.

### Roadmap readiness (verified in Analyze / code review of V1)
- A test-only fake decision producer (e.g. one emitting `filler` decisions with confidence) can be added and rendered end-to-end **without modifying** validation, normalization, render, result storage or deletion code.
- A decision set containing invalid entries (out of range, unknown reason, confidence outside 0–1) is partially rejected with per-decision reasons, and only valid decisions are applied — the same path V3 AI output will use.
