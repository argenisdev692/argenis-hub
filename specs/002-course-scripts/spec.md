# Specification: Course Scripts

> Phase 1 · SPECIFY — Defines WHAT is built and WHY. No technical stack here (see `research.md` / `plan.md`).

> **Revision 3 (2026-09-12)** — after the author's third review: (a) the practice-document format now comes from a **real sample**, `GUIDE/MODULE-VIDEOS/Propuestas_Logistica_Heliantia.md/.pdf`; (b) the extended scope (formerly "post-MVP", Phase P) **is in this version**; (c) the independent second review is **opt-in per run with a boolean**. Every decision is traceable in `clarify.md`.

**Feature ID:** 002-course-scripts
**Date:** 2026-09-12
**Status:** In review (clarified, revision 3)

## 1. Summary

**Course Scripts** turns a **video-pill course** (*curso de píldoras de vídeo*) on **any subject** into **everything needed to record it**.

The author uploads three things — nothing else is required:

1. **The course title.**
2. **The index** — the ordered list of videos, optionally grouped into blocks, optionally with durations and a per-video brief.
3. **The content** — the author's notes (*apuntes*): free text inside the index file under each video, free text around it, and/or additional Markdown/PDF content files.

From that, the module **generates the rest**, per video:

| Deliverable | What it is | Reference |
| --- | --- | --- |
| **Recording script** (`Guion_Video_NN`) | Technical header, learning objectives, continuity note, numbered timed sections (with sub-sections and numbered demos), verbatim narration, on-screen prompts, expected results, on-screen actions, material shown on screen, on-screen tables, summary, next-video handoff, technical recording notes, final verification checklist | `Guion_Video_39/43/46` |
| **On-screen prompts sheet** (`Prompts_Video_NN`) | Every prompt the presenter types, in order, paste-ready, each tagged with its section/demo and the practice file to have ready first | script 39 recording notes |
| **Practice document** (`Practica_{Tema}_Guion_NN`) | A practice pack for the video's demos: which **files** to prepare and where to put them, which demo uses them, an **instructor note** explaining the deliberately designed differences, and the **full content of each simulated artifact** — realistic fictional business documents (proposals, reports, emails, meeting notes, datasets…). **Only when the video needs it.** | `Propuestas_Logistica_Heliantia` (video 22) |
| **Practice files** (e.g. `Propuesta_Logistica_ProveedorA_2026.pdf`) | Each simulated artifact also as its **own file**, named as the practice document says, ready to upload to Drive/Slack/etc. or paste | same sample: "Subirlas a Drive como archivos separados con los nombres indicados" |

…each document as **Markdown and PDF**, plus a **course bible** the module proposes itself, all downloadable as **one ZIP**.

**The files in `GUIDE/MODULE-VIDEOS` are the author's manual output** — produced one by one in a Claude web chat. They define the **output format and quality bar**, not the subject.

Pipeline per course: **Ingest** (index + notes) → **Prepare** (bible + subject research) → **Research each video** → **Write** (outline, sections, closing) → **Practice** (plan + artifacts, when warranted) → **Validate** (deterministic gate, plus the independent review **when the run asks for it**) → **Deliver** (Markdown, PDF, practice files, ZIP).

**Source precedence is fixed:** author's notes > index brief > research findings > model knowledge.

The unit of value is a folder a presenter opens, reads the script, uploads or pastes the practice files, pastes the prompts in order, and records — in a sequence where video 46 remembers what video 43 taught.

## 2. Motivation / Business context

Writing recording material by hand is the bottleneck in producing any video course. Each reference script is 3,000–6,000 words; each demonstrative video also needs a practice pack whose artifacts are several pages of believable fictional documents (the video-22 sample is two complete supplier proposals, ~8,000 characters). The author produces these in a chat window, one video at a time. The reference course — *Claude para Usuarios*, 48 videos across 7 blocks — has **3 of 48 scripts written**.

A chat window does not scale:

- **Continuity is lost when the chat rolls over.** Script 46 names videos 43, 44 and 45 and hands off to 47.
- **A shared fiction runs through the course.** `Tecnoform S.A.` recurs in videos 38, 39, 43, 46; `Heliantia Group` is the client in video 22's practice pack. The course needs a registry of fictional organisations so regenerating a video never invents a conflicting one.
- **Scripts and practice packs must match.** Video 22's pack says "Usar en: DEMO 2 (Sección 4 del guión)"; script 39 says "Practica_Reunion_Guion_39.pdf, Bloque A". Those references must resolve.
- **Practice material is designed, not filler.** The sample's instructor note: the proposals "tienen diferencias deliberadas en precio, cobertura y condiciones para que la tabla comparativa generada por Claude resulte informativa y no trivial … Ambas son deliberadamente imperfectas para favorecer una recomendación con matices." The material exists to make the demo's result interesting.
- **A model writing from memory writes stale material; an index alone is thin.** Notes and research supply substance.

## 3. Actors

- **Course author**: uploads title + index + content, optionally edits structure/notes/bible, picks the provider, **decides per run whether to use the second review**, launches generation, downloads.
- **Content writer provider** (OpenAI / Anthropic / Gemini, selectable per run): writes bible, scripts and practice packs.
- **Quality reviewer provider** (a different AI provider by default): scores drafts — **only in runs with the second review turned on**.
- **Web research provider** (Tavily): subject- and video-level research, recency-biased, fail-soft.
- **Page retrieval provider** (Firecrawl): full page content when a snippet is too thin, capped, fail-soft.
- **Background processor**: executes runs, reports progress.
- **Document renderer**: Markdown, PDF, practice files, ZIP.

## 4. Delivery principle

> **The course structure, the author's content, the course bible and the continuity chain are first-class persisted data, independent of any AI provider. Generation is a replaceable step that reads them.**

**Every generated artifact is attributable**: provider, brief and bible revisions, notes excerpts and research sources used, whether the second review ran, and its scores when it did.

## 5. User stories

Delivery order: **Core** (US-1…US-13) first, then **Extended** (US-14…US-16). Both are in scope for this version; the split is sequencing only.

### US-1: Upload title, index and content (Core)
**As a** course author, **I want** to upload my course title, index and notes, **so that** the system knows everything I know about the subject.

- [ ] Given a title and an index file (Markdown or PDF), when I upload, then every video (position, title, and whatever topic, duration, block and brief the index carries) and every block is extracted.
- [ ] Free text under a video that is not a recognised brief field is stored as **that video's notes**.
- [ ] Free text belonging to no video is stored as **course notes**.
- [ ] Additional content files (Markdown/PDF) are stored as course content, optionally assigned to a video.
- [ ] A PDF yields the same result as its Markdown equivalent.
- [ ] A typed title takes precedence over the index's title.
- [ ] Thin or ambiguous videos are flagged, never dropped, never blocking.
- [ ] A file yielding no ordered list of videos is rejected with a reason; nothing is stored.

### US-2: Review and optionally edit the parsed course (Core)
- [ ] I see blocks, videos, durations, brief fields, notes and thin flags.
- [ ] Edits to a brief or notes are used by generation and bump the brief revision.
- [ ] Without edits, generation proceeds with the parsed data.

### US-3: Course bible, proposed automatically (Core)
- [ ] With no bible, preparing the course (or its first run) proposes one from title, index and notes: **fictional organisations** (one primary — the company the audience works in — plus recurring secondary ones: clients, suppliers, partners), recurring characters/roles, audience profile, narration tone, taught tool (if any), forbidden phrasings.
- [ ] I can accept or edit it.
- [ ] The bible is part of every writer input, including practice packs.
- [ ] Changing the bible tells me which scripts predate the change.

### US-4: Research where the notes are thin (Core: Tavily · Extended: Firecrawl)
- [ ] Subject research runs once per course and is reused.
- [ ] Video research runs per scripted video.
- [ ] Research supplements rich notes, never contradicts them.
- [ ] Sources used are recorded per script with URLs.
- [ ] Research outage ⇒ script generated from notes + brief, marked **ungrounded**.
- [ ] *(Extended)* A high-value result with a thin snippet has its full page retrieved, bounded per video.

### US-5: Generate the full recording script (Core)
- [ ] Technical header: duration, block, recording format, position within block.
- [ ] Learning objectives and continuity note.
- [ ] Numbered timed sections summing to the duration within tolerance; optional numbered sub-sections; demonstrations numbered across the script (`DEMO 1`, `DEMO 2`…).
- [ ] Typed segments: narration, on-screen prompt, expected result, on-screen actions, material shown on screen, on-screen table, presenter note.
- [ ] Prompts only when the course teaches a tool; none invented otherwise.
- [ ] Closing: summary, next-video handoff, technical recording notes (preparation — including the practice files to prepare —, during-recording cues, tools/connectors required or "none", continuity, fictional organisations used), final verification checklist.
- [ ] Mandatory content mapped to sections; errors-to-avoid check recorded.
- [ ] A failed generation shows as failed with a reason.

### US-6: On-screen prompts sheet (Core)
- [ ] Every prompt verbatim, in recording order, with section and demo label.
- [ ] Prompts whose demo uses practice files list the files to have ready (by file name).
- [ ] No prompts ⇒ no sheet, reason recorded.
- [ ] Derived from the stored script; never a separate generation.

### US-7: Practice pack, only when warranted (Core)
**As a** course author, **I want** a practice pack in the format I already use (`Propuestas_Logistica_Heliantia`), **so that** every demo has believable material designed to produce an interesting result.

- [ ] A video whose demos need prepared material gets a practice document; the decision and reason are recorded. A conceptual video gets none, with the reason.
- [ ] The document opens with a header: `DOCUMENTO DE PRÁCTICA · VÍDEO NN — {título}`.
- [ ] It states the **files** to prepare (file names), the **setup instruction** (e.g. "Subir ambos documentos a Google Drive", "pegar en el chat"), and **where they are used** (demo label + script section + demo purpose).
- [ ] It contains a **NOTA PARA EL INSTRUCTOR** explaining the **designed contrasts**: the deliberate differences, gaps or imperfections built into the material and what result they are meant to provoke in the demo.
- [ ] It contains the **full content of every artifact**: realistic fictional documents with the structure their genre requires (a proposal has presentation, pricing tables, coverage, penalties, cancellation terms, additional conditions, contact and legal footer; an email has headers and body; meeting notes are deliberately messy when the demo is about structuring them).
- [ ] Artifacts use the bible's fictional organisations; third parties invented for an artifact (e.g. suppliers) are registered in the bible so later videos reuse them consistently.
- [ ] Figures inside an artifact are internally consistent (line items sum to totals).
- [ ] **Every** practice reference in the script (file name, demo label, section) resolves to the practice document, and **every** artifact is used by at least one demo.
- [ ] Each artifact is **also delivered as its own file** (Markdown + PDF) under the file name the document states.
- [ ] Given the system declined, I can force a practice pack for that video (the script is regenerated to use it).

### US-8: Deliverables and ZIP download (Core)
- [ ] Script, prompts sheet, practice document and each practice file as Markdown **and** PDF.
- [ ] PDFs visually distinguish every script part; practice PDFs render artifacts as documents (headings, tables, footers), each artifact starting on a new page; Spanish accents correct.
- [ ] One ZIP per course: bible, README (per-video status, provider, grounded, reviewed), parsed index, and per video its script, prompts sheet, practice document and practice files, organised by block and video.
- [ ] A single video's deliverables are downloadable individually.
- [ ] Generating a video again gives me the newest accepted version; previous versions are retained (US-14).

### US-9: Batch generation with visible progress (Core)
- [ ] Scope: whole course, one block, or a selection. Default: one video.
- [ ] **A "second review" switch (boolean, default off)** on every run.
- [ ] Before starting I see the estimate of AI calls (including review calls only when the switch is on) and research calls, and confirm.
- [ ] While running: current video, completed, failed.
- [ ] Failures don't stop the run; retry only the failed ones (keeping the run's second-review choice).
- [ ] Cancel keeps completed videos. Course order respected. Ceilings stop the run with a reason.

### US-10: Web interface (Core)
- [ ] Course list; upload screen; course screen with structure, bible, notes/content, run launcher (scope, provider, **second review toggle**, estimate), live progress, per-video preview (script, prompts, practice pack, review scores when present), downloads, ZIP.
- [ ] Mobile-first, accessible, dark mode, consistent with the application.

### US-11: Ownership and authorization (Core)
- [ ] Foreign courses are not found; every action needs its permission; files never public; lists and bundles contain only my data.

### US-12: Cost and consumption control (Core)
- [ ] Estimate before, ceilings during, AI and research calls reported after — review calls reported separately from writing calls.
- [ ] Generation endpoints rate-limited per user.

### US-13: Independent second review — opt-in per run (Core)
**As a** course author, **I want** to decide per run whether an independent AI reviews each draft, **so that** I pay for the extra quality only when I want it.

- [ ] Given `with_review = false`, no reviewer call is made; scripts pass through the deterministic gate only and are marked "not reviewed".
- [ ] Given `with_review = true`, every draft (script **and** practice pack) is scored by a provider distinct from the writer on: mandatory-content coverage, duration adherence, format fidelity to the references, continuity correctness, errors-to-avoid compliance, practice pack quality (realism, designed contrasts actually present, figures consistent, every artifact used) and prompt/practice referential integrity.
- [ ] Below threshold, only the failing sections or artifacts are rewritten with the reviewer's objections, bounded.
- [ ] Exhausted iterations ⇒ best draft delivered, marked not-passed, scores visible.
- [ ] A warning is shown when writer and reviewer resolve to the same provider.
- [ ] The choice is stored on the run and on every script version it produced.

### US-14: Regenerate with feedback and versions (Extended)
- [ ] Reject a script with a written instruction and get a new version (with or without second review).
- [ ] List versions, choose the accepted one; deliverables rebuild from it.
- [ ] Later scripts whose continuity referenced a regenerated video are flagged stale — never auto-regenerated.

### US-15: Style references (Extended)
- [ ] Attach existing scripts and practice packs (like `Guion_Video_39`, `Propuestas_Logistica_Heliantia`) as voice/format exemplars used by bible proposal, writer and reviewer.

### US-16: Course listing export (Extended)
- [ ] The course list exports in the application's standard listing formats.

## 6. Functional requirements

### 6.1 Ingestion (US-1, US-2)
- **FR-1**: Accept a Markdown or PDF index, validated by extension, real content type and size.
- **FR-1a**: Accept a typed title that takes precedence over the index's; reject when neither exists.
- **FR-1b**: Accept zero or more content files (Markdown/PDF), each optionally assigned to a video, within count and size limits.
- **FR-2**: Extract an ordered list of videos from whatever unit the index enumerates.
- **FR-2a**: Any subject and structure; blocks supported, never required.
- **FR-3**: Only position and title required per video.
- **FR-4**: Extract any per-video brief (objective, learning areas, audience objectives, mandatory content, errors to avoid, expected result), all optional.
- **FR-4a**: Compensate for absent brief fields with notes and research.
- **FR-4b**: Store free text under a video that is not a brief field as the video's notes.
- **FR-4c**: Store free text outside any video as course notes; content-file text as course content.
- **FR-4d**: Select per video the relevant, size-bounded excerpts of course notes/unassigned content; record which were used.
- **FR-5**: Flag thin or ambiguous videos without blocking them.
- **FR-6**: Every field and note editable before generation; edits used.
- **FR-7**: Reject an upload yielding no ordered list of videos, persisting nothing.
- **FR-8**: Retain uploaded files for the course's lifetime.
- **FR-8a**: Videos without a declared duration use the configured default.

### 6.2 Course bible (US-3, US-15)
- **FR-9**: Hold per course a bible: fictional organisations (exactly one primary, any number of secondary, each with name, role in the fiction and sector), recurring characters/roles (name, role, organisation), audience profile, narration tone, taught tool (optional), forbidden phrasings.
- **FR-10** *(Extended)*: Accept existing scripts and practice packs as style references.
- **FR-11**: Propose a bible from title, index and notes (plus style references when present); never require the author to write one.
- **FR-12**: Include the bible in every writer and reviewer input.
- **FR-13**: Identify accepted scripts predating a bible change.
- **FR-13k**: When a practice pack introduces a new fictional organisation or character, append it to the bible as secondary (bumping the revision) so later videos reuse it.

### 6.3 Research (US-4)
- **FR-13a**: One subject-level investigation per course, persisted.
- **FR-13b**: One video-level investigation per scripted video, persisted.
- **FR-13c** *(Extended)*: Escalate to full page retrieval when a high-value snippet is insufficient, bounded per video.
- **FR-13d**: Bias toward recent sources.
- **FR-13e**: Findings in writer input, below notes and brief in precedence.
- **FR-13f**: Record per script the sources used (URLs) and notes excerpts used.
- **FR-13g**: Research outage ⇒ continue, mark ungrounded.
- **FR-13h**: Retrieved content is untrusted input.
- **FR-13i**: Research calls counted separately from AI calls.
- **FR-13j**: Course-level findings reused across videos.

### 6.4 Generation (US-9)
- **FR-14**: Writer provider selected per run from the supported set.
- **FR-14a**: Every run and every regeneration request carries a boolean **`with_review`**, default `false`, persisted on the run and on each script version produced.
- **FR-15**: Scopes: course, block, selection; default one video.
- **FR-16**: Asynchronous, returning a trackable run.
- **FR-17**: Course order within a run.
- **FR-18**: Per-video outcome recorded.
- **FR-19**: Continue after a video fails.
- **FR-20**: Retry only failed videos, with the run's original `with_review`.
- **FR-21**: Cancel keeps completed videos.
- **FR-22**: Report current video, completed, failed.
- **FR-23**: Stop at the AI-call or research-call ceiling and report why.
- **FR-24**: Record AI calls (writing and review separately) and research calls, per provider.
- **FR-25**: No run without videos; at most one active run per course.

### 6.5 Script content contract (US-5)
- **FR-26**: Technical header: duration, block (or none), recording format, position within block.
- **FR-27**: Learning objectives.
- **FR-28**: Continuity note referencing previous videos by number and content; first-of-block frames the block.
- **FR-29**: Numbered sections with time budgets summing to the duration within tolerance; optional numbered sub-sections within their parent's budget.
- **FR-29a**: Demonstrations carry a label numbered across the script (`DEMO 1`, `DEMO 2`…), a purpose, and the section they belong to.
- **FR-30**: Sections are composed of typed segments: `narration`, `on_screen_prompt`, `expected_result`, `on_screen_actions`, `show_on_screen` (optional read-aloud flag, optional practice file reference), `on_screen_table`, `presenter_note`.
- **FR-30a**: `on_screen_prompt` only when the course teaches a tool; never invented otherwise.
- **FR-30b**: A `comparison` section kind supports incorrect-vs-correct demonstrations.
- **FR-31**: Mandatory content → section mapping recorded.
- **FR-32**: Errors-to-avoid check recorded.
- **FR-33**: Continuity marked provisional when built from briefs.
- **FR-33a**: Accepted scripts store a short "what this video taught" summary for downstream continuity.
- **FR-33b**: Closing parts: summary bullets; next-video handoff when one exists; recording notes (preparation incl. practice files by name, during-recording cues, tools/connectors or explicit none, continuity, fictional organisations used — all present in the bible); final verification checklist.
- **FR-34** *(Extended)*: Scripts whose continuity source was regenerated are flagged stale.

### 6.6 Prompts sheet (US-6)
- **FR-35a**: For scripts with prompts, derive a sheet: every prompt verbatim, in order, with section and demo label, preceded by the practice file names the demo uses.
- **FR-35b**: No prompts ⇒ no sheet, reason recorded.
- **FR-35c**: Deterministic rendering, never a generation.

### 6.7 Practice pack (US-7)
- **FR-35**: Decide per video whether a practice pack is warranted; record decision and reason.
- **FR-36**: A practice document MUST contain: header (video number and title); files summary (file names); setup instruction; usage (demo label, script section, demo purpose); instructor note; and one or more **artifacts**.
- **FR-36a**: Each artifact MUST have: a file name (descriptive, sanitised, unique within the video, with the target extension), a title, a genre (`proposal`, `report`, `email`, `email_thread`, `meeting_notes`, `dataset`, `policy`, `contract`, `chat_transcript`, `context_brief`, `comparison_case`, `other`), the demos that use it, the **designed contrasts** it embodies, and its full content as structured blocks (headings, paragraphs, bullet lists, tables with header rows, key-value lines, footer).
- **FR-36b**: Every practice reference in the script (file name, demo label, section) MUST resolve to the practice document, and every artifact MUST be used by at least one demo.
- **FR-36c**: The instructor note MUST explain every designed contrast declared by the artifacts and the demo result each is meant to provoke.
- **FR-36d**: Numeric tables that declare a total row MUST be arithmetically consistent within rounding tolerance.
- **FR-36e**: Each artifact MUST also be delivered as its own file under its file name, in Markdown and PDF.
- **FR-37**: The practice document is named `Practica_{Tema}_Guion_{NN}`, accents and spaces normalised; artifact file names are proposed by the writer and sanitised.
- **FR-38**: The author can force a practice pack; the script is regenerated to use it.
- **FR-39**: No accepted script may reference a practice document, file or demo that was not produced — enforced at acceptance.
- **FR-39a**: Artifacts use the bible's organisations and characters; newly introduced ones are registered (FR-13k).
- **FR-39b**: Fictional contact data (emails, phones, tax ids, addresses) MUST be plausibly formatted but invented; emails use invented domains derived from fictional organisation names, and the writer is instructed never to use real companies, real people or real contact details.

### 6.8 Quality gates (US-13)
- **FR-44a** *(always)*: Every draft passes the deterministic gate — time budget, coverage, closing completeness (FR-33b), prompt/tool consistency (FR-30a), demo labels (FR-29a), practice integrity (FR-36b), instructor-note coverage of declared contrasts (FR-36c), table arithmetic (FR-36d) — with a bounded structural retry.
- **FR-40**: When `with_review = true`, drafts (script and practice pack) are scored on the dimensions of US-13.
- **FR-40a**: When `with_review = false`, zero reviewer calls are made and versions are stored with `reviewed = false`.
- **FR-41**: The reviewer defaults to a provider distinct from the writer; a warning is returned when they coincide.
- **FR-42**: Failing objections feed a bounded rewrite of only the failing sections or artifacts.
- **FR-43**: No passing iteration ⇒ best draft delivered, marked not-passed.
- **FR-44**: Scores and objections visible to the author.

### 6.9 Deliverables (US-8)
- **FR-45**: Script: Markdown + PDF.
- **FR-46**: Prompts sheet, practice document and each practice file: Markdown + PDF.
- **FR-47**: Script PDFs distinguish every part; practice PDFs render artifacts as documents, one per page break.
- **FR-48**: One ZIP per course: bible, README, parsed index, and per video its script, prompts sheet, practice document and practice files, organised by block and video.
- **FR-48a**: Per-video bundle and individual deliverable downloads.
- **FR-49** *(Extended)*: Superseded versions retained, listable, retrievable.
- **FR-50** *(Extended)*: Deliverables rebuild when another version is accepted.
- **FR-51**: Deliverables served only to the owner through short-lived access.

### 6.10 Interface (US-10)
- **FR-52a**: Screens for course list, upload, course detail (structure, bible, notes, runs with second-review toggle, progress, previews, downloads).
- **FR-52b**: Run progress updates without reload.

### 6.11 Security, authorization and audit
- **FR-52**: Authentication and explicit permission on every route.
- **FR-53**: Owner scoping on every read, generation, download, delete; foreign identifiers are not found.
- **FR-54**: Audit: upload, bible change, run start (with `with_review`), cancel, version acceptance, deletion.
- **FR-55**: Never log keys, prompts with user content, or file contents.
- **FR-56**: Index, notes, content, bible, style references and research are untrusted input.
- **FR-57**: Rate limits on upload, generation, status, download and export.
- **FR-58**: Uploads validated by real MIME, extension and size.

## 7. Non-functional requirements

- **Performance**: Upload/parse within seconds; run launch < 1 s; generation in background; ZIP streamed.
- **Security**: Private content; credentials in configuration only; author content untrusted toward providers.
- **Availability**: Provider outages fail videos, never runs or the read surface; breakers stop hammering.
- **Scalability**: ~100 videos per course; practice packs up to a configured artifact count per video.
- **Cost**: Estimated, capped, reported; the second review is explicit and opt-in because it roughly adds a quarter to a third of a run's AI calls.
- **Usability**: Only title + index (+ optional content) are mandatory.
- **Compliance**: Course content is the author's IP; fictional data never impersonates real entities (FR-39b).

## 8. Data entities (conceptual)

- **Course**: title, language, durations, owner, status, course notes, bible (+ origin, revision), prepared at.
- **Block** (optional), **Video brief** (+ notes, thin flag, brief revision, script status).
- **Source document**: kind (`index`, `content`, `style_reference`), optional video, extracted text.
- **Research finding**: provider, query, URL, title, content, score, full page fetched, dates, course- or video-scoped.
- **Generation run**: scope, writer and reviewer providers, **with_review**, status, progress, AI writing/review and research calls with ceilings.
- **Video outcome**: status, failure reason, attempts, calls.
- **Script version**: all script contract parts, demos, coverage, errors check, grounded, notes excerpts, sources, **reviewed**, review scores/objections/passed, feedback note, practice decision.
- **Practice version**: decided by, reason, document name, header, files summary, setup instruction, usage, instructor note, artifacts (file name, title, genre, used-by demos, designed contrasts, content blocks), review scores when reviewed.
- **Deliverable**: document type (`script`, `prompts`, `practice`, `practice_file`), format, artifact file name when applicable, path, size, checksum.

## 9. Out of scope (this version)

- Audio, voice-over, video, images, diagrams, slide decks.
- Pixel-matching the reference PDFs' visual design (fidelity is to structure and content).
- Translation; multi-user collaboration; URL/LMS import; scheduled re-research; external publishing; OCR.
- Rich in-app editing of generated bodies — authors edit the downloaded Markdown or regenerate with feedback.
- Native Office formats (`.docx`, `.xlsx`) for practice files — Markdown and PDF only.

## 10. Decisions and assumptions

### Revision 3 decisions

- **DEC-9 — Practice pack format from the author's sample (Q8).** `Propuestas_Logistica_Heliantia` replaces the inferred D12 format. A practice document is a header + files summary + setup instruction + usage (demo + section) + instructor note + full simulated artifacts; artifacts are also separate files. Scripts reference **files and demos**, not "Bloque" letters (FR-36…FR-39b).
- **DEC-10 — Second review is a per-run boolean (Q9).** `with_review` (default `false`) on runs and regenerations. Off: deterministic gate only, zero reviewer calls. On: the full US-13 loop over script and practice pack. The deterministic gate always runs.
- **DEC-11 — Extended scope is in this version (Q9).** Former Phase P is delivered in this version: the second review joins the core (it is a run parameter from day one); versions/feedback/staleness, style references, Firecrawl escalation and listing export follow as the extended phases. DEC-8's "post-MVP" framing is superseded; the core/extended split is sequencing only.

### Revision 3 defaults

- **D18 — Fictional organisation registry.** The sample's client (`Heliantia Group`) differs from the recurring `Tecnoform S.A.`; a single-organisation bible cannot represent that. The bible holds one primary and many secondary organisations; practice packs register what they introduce (FR-9, FR-13k).
- **D19 — Invented contact data.** The sample uses realistic-looking emails, phones and CIFs. Kept realistic in format, but always invented (FR-39b) — a generated address may otherwise match a real person or company.
- **D20 — Artifacts as structured blocks.** The sample's Markdown is a lossy PDF conversion (split table cells, lost header "Tipo de incidencia"). Artifacts are stored as structured blocks and rendered cleanly, so the module's output is better-formed than the conversion it learned from.
- **D21 — Review cost.** Per reviewed video: 1 script review + 1 practice review (when a pack exists) + bounded rewrites. The estimate shows review calls only when `with_review` is on.

### Revision 2 decisions still standing

- **DEC-5** on-screen prompts + prompts sheet · **DEC-6** title + index + content in, everything out; notes first-class; automatic bible · **DEC-7** web interface in this version · **D13** expanded script contract · **D14** subject-agnostic segments · **D15** lexical notes matching · **D16** default duration · **D17** ZIP layout (extended by revision 3 with practice files).
- **D12** (inferred "Bloque" practice format) — **superseded by DEC-9**. **DEC-8 / D11** (MVP/post-MVP) — **superseded by DEC-11**.

### Revision 1 decisions still standing

DEC-0 research first-class · DEC-1 local PDF parsing · DEC-2 call ceilings, one-video default · DEC-3 structure first, renderings second · DEC-4 new module reusing Shared · A1–A7 / D1–D9.

## 11. Success criteria (measurable)

- **SC-1**: The reference index parses into 7 blocks and 48 videos; < 5% flagged thin.
- **SC-2**: PDF index matches the Markdown parse for ≥ 95% of fields.
- **SC-3**: A generated script for video 39, 43 or 46 contains every structural part of the reference.
- **SC-4**: ≥ 90% of scripts within 10% of declared duration.
- **SC-5**: 100% of mandatory content items mapped.
- **SC-6**: 0 unresolved practice references (file, demo, section); 0 unused artifacts; 0 inconsistent total rows.
- **SC-7**: 100% of prompts-sheet prompts appear verbatim and in order in the script.
- **SC-8**: A 48-video run continues past failures and reports all 48 statuses.
- **SC-9**: Consumption never exceeds either ceiling.
- **SC-10**: No resource reachable by a non-owner — tested on every route.
- **SC-11**: Title + index + notes → downloadable ZIP with no other input.
- **SC-12**: A non-tool subject produces zero prompts and no prompts sheets.
- **SC-13**: A practice pack generated for video 22 contains every structural part of `Propuestas_Logistica_Heliantia`: header, files, setup instruction, usage with demo and section, instructor note explaining the contrasts, and full artifacts each delivered as its own file.
- **SC-14**: A run with `with_review = false` makes 0 reviewer calls; a run with `with_review = true` reviews 100% of its drafts.
