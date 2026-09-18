# Clarifications: Course Scripts

> Phase 2 · CLARIFY — Audit trail of every ambiguity raised against `spec.md`, and how it was closed.
> **Q** = answered by the user. **D** = resolved by default (proposed, not blocking).

**Feature ID:** 002-course-scripts
**Date:** 2026-09-12
**Status:** Closed (revision 3) — no high-impact question left open. Revision 2 (Q5–Q7, D11–D17) and revision 3 (Q8–Q9, D18–D21) are at the end of this file.

---

## High-impact questions (asked directly)

### Q1 — How is an uploaded PDF index turned into text? ✅ ANSWERED

**Why it blocked:** The user's request names "índice md o pdf" explicitly, and the reference course ships both (`pildoras_video_claude_usuarios.md` and `CLAUDE-PARA-USUARIOS-RESUMEN.pdf`, plus a `.pdf` next to every reference script). This application has **no PDF text-extraction capability**: `barryvdh/laravel-dompdf` 3.1 writes PDFs and cannot read them, and `composer show --direct` confirms no parser is installed. `specs/001-video-edit` deferred the same problem to its unbuilt V3, so there is no precedent to follow.

**Impact if unresolved:** Decides FR-1 entirely, and with it whether the module needs a new composer dependency (which `CLAUDE.md` forbids me from adding without approval), an outbound network call in the upload path, or a token cost per upload.

**Options presented:** local pure-PHP parser · Firecrawl `/parse` · provider document-input · Markdown-only V1.

**Answer: add `smalot/pdfparser`.** Local, pure-PHP extraction.

**Consequences now binding:**
- One new composer dependency, approved by this answer. It is the **only** dependency this module adds.
- The uploaded index never leaves the application's infrastructure — which also settles the privacy half of FR-55/FR-56 for the ingestion path.
- Extraction is deterministic and offline, so parser behaviour is testable with a real fixture PDF and no HTTP faking.
- Accepted limitation: a **scanned/image-only PDF has no text layer** and this parser will return little or nothing. That is not a silent failure — FR-7 requires rejecting an upload that yields no recognisable structure, so such a file is refused with a reason rather than producing an empty course. OCR is explicitly out of scope.
- `FIRECRAWL_API_KEY` / `FIRECRAWL_BASE_URL`, which the user supplied, are therefore **not consumed by this module**. Noted so a later reader does not assume dead config is an oversight.

---

### Q2 — How is a run's provider consumption bounded? ✅ ANSWERED

**Why it blocked:** The natural unit of use here is "generate the 45 missing scripts", not "generate one". At a writer call plus a reviewer call plus up to N rewrites per video, a full-course run over 48 videos is realistically 150–300 provider calls in one click. Post's existing quality loop has the same shape at a per-post scale; at course scale the same design becomes a budget event.

**Impact if unresolved:** Decides the default scope offered (US-7), the ceiling's unit (FR-23), what "calls consumed" means in FR-24, and whether the iteration cap is per-course configuration or a constant.

**Options presented:** call ceiling + single-video default · video ceiling + block default · spend ceiling · rate-limit only.

**Answer: ceiling in provider calls per run, default scope = single video.**

**Consequences now binding:**
- The ceiling's unit is **provider calls**, counted across writer *and* reviewer, so the quality loop cannot escape it — the failure mode of a video-count ceiling (one video iterating five times costs five times and the ceiling never notices) is closed by construction.
- The default scope presented for a run is **one video**, so quality is validated cheaply before a course-scale commitment. Block and full-course scopes remain available (FR-15) and are gated behind a pre-run **call estimate** shown to the author (US-12 first criterion), not behind a different code path.
- Reaching the ceiling **stops the run and reports how far it got** (FR-23). It does not fail the run: videos already written stay written, consistent with FR-18/FR-19.
- Per-user rate limiting (FR-57) stays in addition to the ceiling, not instead of it. They guard different things — the throttle guards the endpoint, the ceiling guards the wallet.

---

### Q3 — How does the writer return a script? ✅ ANSWERED

**Why it blocked:** A script is two things at once: a document a person reads aloud, and a structured record the system must verify. FR-29 (section time budgets sum to the declared duration) and FR-31 (every mandatory content item maps to a section) are only checkable if the structure survives the provider boundary.

**Impact if unresolved:** Decides whether FR-29/FR-31/FR-40 are verifiable at all, how the Markdown and PDF deliverables are produced, and whether the reviewer scores prose or data.

**Options presented:** structured sections rendered by the system · Markdown parsed back · hybrid.

**Answer: the writer returns schema-validated structured sections; the system renders Markdown and PDF from them.**

**Consequences now binding:**
- The provider contract is a **JSON schema**, matching how every existing AI module in this application already talks to a provider (`AIClientInterface::generateStructured()` returns a `StructuredAgentResponse`). No new transport shape is invented.
- **Markdown and PDF are two renderings of one stored structure**, not two generations. They cannot drift, and regenerating a deliverable never costs a provider call.
- FR-29 and FR-31 become arithmetic and set-membership over stored data — assertable in a unit test with no provider involved.
- The reviewer (FR-40) scores the **structure**, so "did it cover mandatory item 4" is a lookup, and only the genuinely subjective dimensions (narration quality, continuity phrasing) are left to a model's judgement.
- Accepted cost: the rendered Markdown will not be byte-identical to the reference `.md` files. Fidelity is to structure, which §9 of the spec already states as the out-of-scope boundary for the PDF; this extends the same principle to Markdown.

---

### Q4 — Where does the module live? ✅ ANSWERED

**Why it blocked:** The work shares its AI-generation shape with `Post` (async run, writer/reviewer split, progress, versions) and its source material with `VideoEdits` (whose V3 roadmap already names "an optional script (MD/PDF)" as an input). Either could have absorbed it.

**Impact if unresolved:** Decides the namespace, the service provider, routes, permissions and the whole directory tree — i.e. every task in Phase 5.

**Options presented:** new `CourseScripts` module · extend `VideoEdits` · a user-chosen name.

**Answer: a new `CourseScripts` module.**

**Consequences now binding:**
- Namespace `Modules\CourseScripts\…` at `src/Modules/CourseScripts/`, its own `CourseScriptsServiceProvider`, its own route file, its own permission set.
- `VideoEdits` stays a video-rendering module; coupling document authoring to an ffmpeg pipeline would have put two unrelated lifecycles under one aggregate.
- Shared infrastructure is **reused, not re-implemented**: the AI client, the export/PDF port, private storage, and the audit port. A new adapter for any of these in this module is a review failure.
- The `Post` module is the **pattern reference** for the async generation loop, not a base class to inherit. Similarity is deliberate; shared code between the two is not created speculatively.

---

## Resolved by default (proposed, not blocking)

### D1 — Content language
Generated content is produced in the **course's own language**, detected at parse time from the index and stored on the course record. The reference course is Spanish, so the reference course generates Spanish. The application's interface, logs, identifiers and code remain English per the project's ABSOLUTE language rule — that rule governs *this application's own output*, not the user content it produces on the author's behalf.
*Spec impact:* confirms A1.

### D2 — Selectable providers
The writing provider is chosen from the set this application already exposes for content generation — `openai`, `anthropic`, `gemini` — matching `GeneratePostContentData`'s existing validation. The user's `.env` names exactly these three plus their model IDs. No provider is added by this module, and model selection stays configuration, never a request parameter (a user-supplied model string is an injection surface and a billing surface).
*Spec impact:* confirms A2.

### D3 — Reviewer independence
The reviewer defaults to `config('ai.default_for_evaluation')`, which the application already documents as deliberately distinct from the writing default — the user's `.env` sets `AI_PROVIDER=openai` against `AI_EVALUATOR_PROVIDER=anthropic`, so the gate is independent out of the box. FR-41's warning fires only when an author's explicit writer choice collides with that default.
*Spec impact:* confirms A3.

### D4 — Practice-document decision authority
The **writer decides**, per video, whether a practice document is warranted, and must state why — because the reference material shows conceptual and demonstrative videos inside the same block (39 and 43 both warrant one; the evidence does not support a static rule on the brief). The author's override is **one-directional**: forcing generation where the system declined. There is no "suppress" override, because FR-39 forbids a script that references a document which does not exist — suppression would have to rewrite the script, which is a regeneration, not an override.
*Spec impact:* confirms A4, and explains the asymmetry FR-38 states without justifying.

### D5 — Storage
Uploaded indexes and generated deliverables use the application's existing private storage path (`StoragePort` / R2), served through short-lived signed access only, consistent with FR-51 and with how `VideoEdits` already handles user media. No new disk or bucket.
*Spec impact:* confirms A5.

### D6 — Version retention
Every script version is retained for the life of the course; deleting a course deletes its deliverables. A retention cap is configuration, defaulted to unlimited, because the volume is documents and the value of "what did version 2 say" is high while a course is in production.
*Spec impact:* confirms A6.

### D7 — Continuity window
A video's continuity context is the **accepted scripts of its immediate predecessors plus its block position**, summarised — not the full text of every earlier script. Video 48 cannot carry 47 full scripts into a prompt, and the reference scripts show what is actually needed: video 46 names videos 43, 44 and 45 and what each taught, in two sentences. The stored summary per accepted script is what makes this cheap.
*Spec impact:* confirms A7, and adds a requirement the spec left implicit — **each accepted script stores a short "what this video taught" summary** for downstream continuity. Carried into `plan.md` as a field on the script version, and into `tasks.md`.

### D8 — Concurrency on one course
FR-25 forbids a second concurrent run over an overlapping scope. Resolved as: **one active run per course**, not per video-set intersection. Scope-overlap arithmetic is real work to get right and its only payoff is letting an author run block 1 and block 7 simultaneously — which the call ceiling from Q2 discourages anyway.
*Spec impact:* narrows FR-25 to a simpler, testable rule.

### D9 — What "stale continuity" does
FR-34 requires flagging scripts whose continuity source was regenerated. Resolved as: **flag only, never auto-regenerate**. A cascade from one regeneration could silently re-run a course tail and bill for it, which contradicts Q2's whole intent. The author decides what to re-run.
*Spec impact:* makes FR-34 a marker, not a trigger.

### D10 — Bundle format
The single-archive deliverable (FR-48) is a **ZIP**, organised `block-NN/video-NN/` with the Markdown and PDF of the script and, where present, the practice document. ZIP is what PHP writes natively with no dependency.
*Spec impact:* makes FR-48 concrete.

---

## Follow-through (revision 1)

`spec.md` §10 has been updated in place: Q1–Q4 replaced with their decisions, and A1–A7 annotated with the D-number that confirmed them. D7 additionally adds a stored per-script summary field, and D8/D9/D10 narrow FR-25, FR-34 and FR-48 respectively. This file is the audit trail; `spec.md` is the current truth.

---

# Revision 2 — author's second review (2026-09-12)

The author reviewed an analysis comparing revision 1 against the reference material and their intent. Their statement, verbatim:

> *"prompts en pantalla … lo de guide/MODULE-VIDEOS son ejemplos que yo generaba manualmente en claude web, ahora lo quiero en mi sistema que al cargar indice curso pildoras mas titulo quiero que genere el resto. es decir cuando haga pildoras de videos, al subir indice y contenido, modulo debera generar guiones y material practico y prompts, es decir todo lo que se necesite"*

## Answered

### Q5 — What does "prompts" mean? ✅ ANSWERED
**Options:** on-screen prompts the presenter types · prompts for image/video generation tools.
**Answer: on-screen prompts.**
**Consequences:** prompts stay a segment type inside the script (FR-30) and are additionally delivered as a **prompts sheet** per video (FR-35a–c), rendered deterministically from the stored script — zero extra provider calls, cannot drift. Script 39's recording notes ("Prompts de las cuatro fases preparados para copiar y pegar en secuencia sin pausas") are the evidence the sheet is a real need.

### Q6 — What does the author provide, and what is generated? ✅ ANSWERED
**Answer:** the author provides **title + index + content (notes)**; the module generates **everything else** needed to record: scripts, practice material, prompts, bible.
**Consequences:**
- Revision 1 had no notion of notes: `MarkdownIndexParser` discarded every line under a video that was not a recognised brief field. Notes become first-class (FR-4b, FR-4c, FR-4d), with a fixed precedence notes > brief > research > model knowledge (FR-13e).
- Additional content files are accepted (FR-1b). The single-table `course_style_references` generalises to `course_source_documents` with a `kind`.
- The bible can no longer be an author chore: it is **proposed automatically** from title + index + notes (FR-11) — style references become an optional post-MVP input rather than the only input.
- `GUIDE/MODULE-VIDEOS` is confirmed as the author's manual output: the target format and quality bar.

### Q7 — Must V1 be usable from the web interface? ✅ ANSWERED (implied by Q6)
"Quiero que en mi sistema al cargar índice … genere el resto" describes an author workflow, not an API. Revision 1's "JSON endpoints only, no Inertia page" (VideoEdits precedent P4) cannot deliver it.
**Consequence:** US-10 and FR-52a/b — Inertia screens are in V1. Routes follow the `Post` module convention (Inertia pages + JSON actions in one `course-scripts.` group; polling for progress, as `usePostAi` does).

## Resolved by default

### D11 — MVP cut
The analysis showed 94 tasks between the author and a ZIP. Delivery is split:
- **MVP:** ingestion with notes, automatic bible, Tavily research, two-stage writing with closing parts, practice documents, prompts sheet, deterministic validators, runs with ceilings, Markdown/PDF/ZIP, web screens, security.
- **Post-MVP:** independent AI reviewer (US-13), versions/regeneration/staleness (US-14), style references (US-15), listing export (US-16), Firecrawl escalation (FR-13c).
The schema carries post-MVP columns from the first migration, so post-MVP work adds code, not reshaping migrations. FR-44a keeps a deterministic quality gate in the MVP.

### D12 — Practice document format
No practice document exists in the repository (the author's manual `Practica_*` PDFs were not committed). Derived from how scripts reference them: script 39 — "Practica_Reunion_Guion_39.pdf, Bloque A … Bloque B"; script 43 — "los dos escenarios (incorrecto y correcto) del documento de práctica". Format: labelled blocks with title, purpose, using sections, and content that is text, a table, or an incorrect/correct pair. Referential integrity is two-way (FR-36a). A sample supplied later refines the template, not the schema.

### D13 — Script contract expanded to match the references
Revision 1 captured header, objectives, continuity and sections. Script 39 additionally has `MOSTRAR EN PANTALLA` (read-aloud material), `TABLA EN PANTALLA`, sub-sections `2.1/2.2`, `RESUMEN`, `PRÓXIMO VÍDEO`, `NOTAS TÉCNICAS PARA LA GRABACIÓN` (preparación previa, durante la grabación, conectores necesarios, continuidad, empresa ficticia) and `VERIFICACIÓN FINAL`. All added (FR-29, FR-30, FR-33b). Scripts 43/46 remain the header/continuity format target (RK-5); 39 is now also the closing-parts target.

### D14 — Subject-agnostic segments
Revision 1's prompt/result/actions triplet assumed a software course. "Cualquier tema" requires: prompts only when the bible or brief names a tool the presenter types into (FR-30a); a `comparison` section kind for incorrect/correct demos (FR-30b); otherwise narration, on-screen material and tables. SC-12 tests a non-tool subject.

### D15 — Matching course notes to videos
Excerpts of course notes and unassigned content files are chunked by heading/paragraph and scored lexically against a video's title, topic and brief; the top excerpts within a size budget are passed to the writer, and their ids are recorded. No embeddings, no vector store, no new dependency — the author can always assign a content file to a video explicitly.

### D16 — Default duration
Videos without a declared duration use `course-scripts.script.default_video_minutes` (8). Needed because FR-29 is arithmetic over a duration; revision 1's schema made it non-nullable, which contradicted FR-3.

### D17 — ZIP layout
`{curso}/00-curso/` (Biblia, Índice, README with per-video status) plus `bloque-NN-{slug}/video-NN-{slug}/` containing `Guion_Video_NN`, `Prompts_Video_NN`, `Practica_{Tema}_Guion_NN` in `.md` and `.pdf`. File names reuse the author's own naming from `GUIDE/MODULE-VIDEOS`. Supersedes D10's layout.

## Follow-through (revision 2)

`spec.md`, `plan.md`, `tasks.md` and `analyze.md` were rewritten together; `research.md` gained §11 (reference-format analysis). Revision 1 decisions DEC-0…DEC-4 and D1…D9 stand; D10's layout is superseded by D17.

---

# Revision 3 — author's third review (2026-09-12)

Author's statement, verbatim:

> *"si quiero la phase P pero como boolean si quiero enviar true segunda revisa y ya ingrese ejemplo referencia de como elaboraba los ejemplos practicos de guiones Propuestas_Logistica_Heliantia.pdf y Propuestas_Logistica_Heliantia.md"*

## Answered

### Q8 — What does a practice document actually look like? ✅ ANSWERED (sample supplied)
**Evidence:** `GUIDE/MODULE-VIDEOS/Propuestas_Logistica_Heliantia.md` / `.pdf` — the practice document for video 22 ("Integración con Google Workspace: documentos y Drive"). Analysed in `research.md` §12.
**Answer:** DEC-9. It is not a set of "Bloque" snippets (revision 2's D12 inference). It is a **practice pack**: header, files to prepare, setup instruction, where it is used (DEMO 2, Sección 4), an instructor note explaining deliberately designed differences, and complete, realistic fictional business documents.
**Consequences:**
- D12 superseded. The script references practice material by **file name + demo label + section**.
- Artifacts get genres, designed contrasts and structured content blocks (FR-36a); integrity is file/demo based (FR-36b).
- The instructor note is mandatory and must explain every declared contrast (FR-36c).
- Artifacts are also delivered as separate files (FR-36e) — the sample instructs uploading them to Drive "como archivos separados con los nombres indicados".
- Table totals must add up (FR-36d) — the sample's totals are exact (16.280 + 9.072 + 1.704 + 1.800 = 28.856 €).
- Practice pack generation becomes a plan + one call per artifact (the sample is ~8,000 characters for two artifacts).

### Q9 — Is Phase P in scope, and how is the second review controlled? ✅ ANSWERED
**Answer:** Phase P is wanted in this version (DEC-11). The independent second review is controlled by a **boolean per run** — `with_review: true` sends drafts to the second review; `false` (default) does not (DEC-10).
**Consequences:**
- `with_review` on `StartGenerationRunRequest`, on regeneration requests, on `course_generation_runs` and on `course_script_versions` (`reviewed`).
- `CallEstimator` includes review calls only when `true`; review calls are counted separately (FR-24).
- Retry-failed inherits the run's choice (FR-20).
- The deterministic gate (FR-44a) runs in both cases.
- The run launcher UI shows the toggle and the estimate difference.
- Former "post-MVP" items (versions/feedback/staleness, style references, Firecrawl, listing export) are scheduled after the core in the same version; the Firecrawl escalation stays automatic and capped by configuration (not asked to be a toggle).

## Resolved by default

### D18 — Fictional organisation registry
Video 22's pack uses `Heliantia Group` as the client while videos 38/39/43/46 use `Tecnoform S.A.`. A single "fictional organisation" field cannot hold both. The bible holds one primary organisation and a list of secondary ones; any organisation or character a practice pack introduces is appended (FR-9, FR-13k). The deterministic check becomes "every organisation named in recording notes exists in the bible", not "equals the one organisation".

### D19 — Invented contact data
The sample includes emails (`r.alcantara@t-meridional.es`), phones and CIFs that look real. Format stays realistic; content must be invented, with email domains derived from the fictional organisation's name, and the writer instructed never to use real companies, people or contact details (FR-39b). A realistic-but-real address would be a privacy and impersonation problem on a recorded course.

### D20 — Artifact content as structured blocks
The sample's `.md` is a lossy conversion of its PDF: table cells split across rows, one table header lost. Storing artifacts as structured blocks (heading, paragraph, list, table with header row, key-values, footer) lets the renderer output clean Markdown and PDF and lets FR-36d check totals.

### D21 — Review call accounting
Reviewed video = +1 script review, +1 practice review when a pack exists, + rewrites bounded by `runs.max_review_iterations`. For the 48-video course this moves the estimate from ~500 to ~650 AI calls; the default AI ceiling rises to 900 so a full reviewed course fits.

## Follow-through (revision 3)

`spec.md` rewritten (revision 3); `plan.md`, `tasks.md`, `analyze.md` updated; `research.md` gained §12 (practice sample analysis). D12 and DEC-8/D11 are superseded; everything else stands.
