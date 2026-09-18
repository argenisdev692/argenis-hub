# Clarify: Video Edit

> Phase 2 · CLARIFY — audit trail of every ambiguity raised in `spec.md` and how it was resolved.
> Resolved answers have been written back into `spec.md`.

**Feature ID:** 001-video-edit
**Date:** 2026-09-11
**Status:** All high-impact questions resolved

## 1. Questions answered by the user

| # | Question | Impact if unresolved | Decision |
| --- | --- | --- | --- |
| Q1 | What is the v1 scope? | Defines every user story | **Merge + silence removal + manual time ranges.** No Whisper, AI or PDF in v1. |
| Q2 | Durable history or transient status? | Data model, re-edit, future report | **Persist edits in the database.** History and future re-edit are required, plus **hard delete from the frontend**. |
| Q3 | Keep source videos after the edit? | Storage cost, privacy, re-edit feasibility | **Do not store sources.** Delete them once the export finishes. |
| Q3b | Sources are deleted, so what does "re-edit" mean? (contradiction between Q2 and Q3) | Makes US-6 impossible as first written | **Reuse settings.** A re-edit is a new job prefilled with the previous parameters and cut list; the user uploads the sources again. |
| Q3c | What happens to sources when a job fails after retries? | Retry UX vs "don't store sources" | **Keep them 24 h** so the user can retry without uploading again, then a scheduled cleanup deletes them. |
| Q4 | Frame-accurate or keyframe-aligned cuts? | Processing time vs quality | **Frame-accurate.** Quality first, for spoken content. |
| Q5 | What does hard delete remove? | Privacy, auditability | **Everything + audit.** The edit record, its cuts, the result video and any remaining sources are deleted. Only an audit entry (actor, edit ID, timestamp) remains, with no media or personal data. |
| Q6 | Frontend stack? | Future frontend spec, component choices | **shadcn-vue (reka-ui) + Zod 4 + Pinia Colada + TanStack Vue Form** (+ vue-sonner, already installed). Verified in `package.json` and `resources/js/components/ui/`. |
| Q6b | Update the global rules (which mandate PrimeVue/Volt) to match? | Rule drift | **No.** The user said not to edit the frontend skill or rules. The stack is recorded in this module's spec only. The divergence with `.claude/rules/rules.md` is a known, accepted inconsistency outside this feature. |
| Q3b/Q3c/Q5 | Re-confirmation of re-edit, failed-source retention and hard delete | — | **Confirmed by the user (2026-09-11):** Reuse settings · Keep 24 h to retry · Everything + audit. |
| Q7 | Memory limits on the processing queue? | Render strategy for large cut lists | **No memory limits and no low-memory modes.** Production worker: Railway with 8 GB RAM; local: 32 GB RAM. The guide's `lowMemory` / chunked render is not adopted as a mode. **Risk kept for Plan:** the guide added chunking after OOM kills with very large filtergraphs, so Plan must benchmark a 20-min 1080p / 200-cut render against 8 GB (spec §11). |
| Q8 | Does the spec cover only V1, or the full product? | Throwaway architecture when AI arrives | **Full product.** The spec explicitly covers V1 (merge, silence, manual), V2 (speech transcription and speech detectors) and V3 (AI provider, pause markers, script MD/PDF, JSON decisions, AI report + PDF). Only V1 is implemented now, but V1 MUST satisfy the extensibility requirements EX-1…EX-9 so V2/V3 plug in without changing the core pipeline. |

### Plan-phase decisions (user, 2026-09-11)

| # | Question | Impact | Decision |
| --- | --- | --- | --- |
| P1 | Manual ranges beyond the real duration are only detectable once the uploaded media is inspected (after submit) | US-3 "before any processing", US-8 retry scope | **Accepted.** The frontend pre-validates ranges; the job re-validates against the actual media duration at start, before merge/analysis/render. Invalid ranges fail with per-range errors, and **retry accepts corrected ranges**. Spec US-3, US-8, FR-4, FR-11 and §11 updated. |
| P2 | The unused `VIDEO_EXPORTS` permissions already exist in the seeder | Dead permissions, naming | **Accepted.** Replace with `VIDEO_EDITS` permissions and remove the old `*_VIDEO_EXPORTS` rows. |
| P3 | Queue driver | Infra, deviations | **Redis (Upstash)** in development AND production, preferably with **separate dev/prod instances** so jobs never mix. **No Horizon in V1**: the worker is `queue:work` on the dedicated `video-edits` queue. The design must stay **Horizon-compatible** without requiring it. |
| P4 | Sanctum / mobile API routes | API surface | **Skipped in V1.** Web session JSON only. |
| AI | Roadmap confirmation | Architecture | **Confirmed.** V1 ships without Whisper/Gemini, but keeps the decision-producer architecture so Whisper (V2) and Gemini + MD/PDF script analysis (V3) plug in without changing cut validation, rendering, persistence or deletion. |

## 2. Resolved by default (lower impact)

Each default is configurable and can be revisited in Plan without changing the user stories.

| # | Question | Impact | Resolved by default |
| --- | --- | --- | --- |
| D1 | How do videos arrive? | Upload security, request timeouts | Direct upload to private object storage using short-lived upload URLs issued by the system (the guide's pattern). The system never fetches arbitrary remote URLs. |
| D2 | Upload limits? | Worker memory/disk, abuse | ≤ 2 GB per file, ≤ 10 files per merge, ≤ 90 min total input duration. Allowed containers: MP4, MOV, WebM, MKV. |
| D3 | Output profile? | Merge normalization | MP4, video max 1920×1080 with aspect ratio preserved (padded, never stretched). Constant frame rate taken from the first source, capped at 60 fps. Stereo audio at 48 kHz. |
| D4 | Silence threshold range and detection floor? | Validation, cut quality | Threshold 0.3–10 s (default 1 s). Noise floor is a system setting (default −30 dB), not user-editable in v1. |
| D5 | Speech safety margin around silence cuts? | Clipped words | 0.15 s kept on each side of a removed silence. |
| D6 | Minimum durations? | Degenerate outputs | Output ≥ 1 s; kept segments shorter than 0.25 s are absorbed into the adjacent cut. |
| D7 | Which timeline do manual ranges refer to when several sources are merged? | Wrong cuts on multi-source edits | The **merged timeline** — the video as it exists after merging, before any cut. |
| D8 | Cut reasons when silence and manual ranges overlap? | Summary accuracy | They are merged into one cut that keeps **all** contributing reasons and origins. |
| D9 | Maximum manual ranges per request? | Abuse, command size | 500. |
| D10 | Concurrency? | Worker saturation | One active (queued or processing) edit per user; a second submission is rejected. |
| D11 | Result video retention? | Storage cost | Kept until the user hard-deletes the edit (no automatic expiry in v1). |
| D12 | Download link lifetime? | Link leakage | 15 minutes; a new link can be requested at any time. |
| D13 | Retries and timeout? | Stuck jobs | Up to 2 automatic retries on transient failures; a job exceeding 60 min is marked failed. |
| D14 | Hard delete while an edit is processing? | Orphaned output, race conditions | Rejected while processing. Allowed when queued (processing is skipped), completed or failed. |
| D15 | Re-edit when the re-uploaded source has a different duration? | Out-of-range cuts | The prefilled manual ranges are validated again against the new duration. **Superseded in timing by P1:** the check runs when processing starts (not at submit); invalid ranges fail the edit with per-range errors and are corrected on retry. |
| D16 | How is progress reported? | UX | One 0–100 % value across stages (merge, analysis, render), plus the current stage name. |
| D17 | What happens to an edit created (upload URLs issued) but never submitted? | Orphan uploads holding personal data | It stays in an internal **draft** state, never listed in history, and is deleted together with any uploaded objects after **24 h** by the scheduled sweep (raised in Analyze, A3). |

## 3. Open questions

### V1
None blocking. Any new question raised in Plan will be added here before `plan.md` is finalized.

### V2 / V3 (do NOT block V1; must be resolved before planning their version)

| # | Version | Question | Impact |
| --- | --- | --- | --- |
| R1 | V2 | What exactly are "vocal sounds"? Breaths, mouth clicks, lip smacks, "eh"/"mmm" (overlaps with fillers), laughter? | Detection categories and reason taxonomy |
| R2 | V2 | Wind noise was in the original prompt but not in the V2 list. Drop it, or plan it (audio-based, not transcript-based)? | May need an audio-analysis producer instead of a speech one |
| R3 | V2 | Recording language(s): Spanish only, or multilingual filler lists? | Filler/stutter dictionaries, transcription settings |
| R4 | V2 | Are speech detections applied automatically, or does the user review candidates before render? | Adds a review step (edit waits for approval) |
| R5 | V2 | Transcript reuse key: exact content fingerprint + order of sources (proposed), or also the merged duration? | EX-6 data kept in V1 |
| R6 | V3 | AI decisions: auto-apply (as listed: "eliminación automática") above a confidence threshold, or always review first? What threshold? | Validation outcome rules, UX |
| R7 | V3 | "REDUCIR" actions in `PROMPT-EDICION-VIDEOS-2.md` (shorten a redundant explanation) are not exact cuts. Report-only recommendations, or AI-proposed cuts? | Decision contract (cut vs recommendation) |
| R8 | V3 | Script time budgets and topic audit (e.g. "Intro 1 min") — report findings only, or influence cuts? | Report sections, AI instructions |
| R9 | V3 | Consent: is informing the user enough before sending transcript/script to the AI provider, or is explicit opt-in per edit required? | Compliance, UI |
| R10 | V3 | Is the raw AI response stored (for audit/debug), or only the validated and rejected decisions? | Storage, privacy, hard delete scope |
