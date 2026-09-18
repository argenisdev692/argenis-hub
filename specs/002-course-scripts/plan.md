# Technical plan: Course Scripts

> Phase 4 · PLAN — Defines HOW it is built. Every decision traces to `spec.md` (a requirement) or `research.md` (a verified finding).

> **Revision 3 (2026-09-12)** — practice packs modelled on the author's sample `Propuestas_Logistica_Heliantia` (research §12, DEC-9); independent second review is a per-run boolean `with_review` (DEC-10); the extended scope (former Phase P) is in this version (DEC-11).

**Feature ID:** 002-course-scripts
**Based on:** `spec.md` (rev. 3), `clarify.md`, `research.md`
**Governed by:** `.claude/rules/rules.md` · `.claude/BACKEND-PHP/SKILL.md` · `.claude/skills/ARCHITECTURE-PHP/SKILL.md` · `.claude/FRONTEND/SKILL.md` · `.claude/skills/ARCHITECTURE-VUE/SKILL.md` · `.claude/OWASP/SKILL.md`

---

## 1. Technical summary

`CourseScripts` is a hexagonal module at `src/Modules/CourseScripts/` with Inertia screens under `resources/js/pages/course-scripts/` and `resources/js/modules/course-scripts/`.

**Input:** title + index + optional content files.
**Output per video:** `Guion_Video_NN`, `Prompts_Video_NN`, `Practica_{Tema}_Guion_NN` and each practice file (e.g. `Propuesta_Logistica_ProveedorA_2026`), all `.md` + `.pdf`. **Per course:** bible, README, parsed index. One ZIP.

Pipelines:

1. **Ingestion** — deterministic, offline: index → videos/blocks/brief/notes; content files → text.
2. **Preparation** — once per course: bible proposal (organisations registry) + subject research.
3. **Generation** — async batch/chain; per video: continuity → notes excerpts → research (+ Firecrawl escalation) → outline (incl. practice plan) → gate → sections → closing → practice artifacts → full gate → **second review loop iff `with_review`** → persist → render.

**Everything checkable is stored structure** (DEC-3). Markdown, PDF, prompts sheet and practice files are renderings.

**Delivery order (DEC-11):** **Core** (phases A–N in `tasks.md`) delivers upload → ZIP from the UI, including the `with_review` second review. **Extended** (phases O–S) adds versions/feedback/staleness, style references, Firecrawl escalation and listing export. Both ship in this version. The schema is complete from the first migration.

**Dependencies:** one new (`smalot/pdfparser`, installed). Shared changes: Tavily recency argument; Firecrawl adapter.

**Architecture tier:** intermediate `ARCHITECTURE-PHP/SKILL.md` (state machine; ≥ 2 integrations; > 15 fields across sub-entities; exports + queues).

---

## 2. Technology stack (verified)

| Component | Choice | Version | Source |
| --- | --- | --- | --- |
| Runtime / framework | PHP 8.5.9 · Laravel ^13.17 | — | research §0 |
| PDF text extraction | `smalot/pdfparser` ^2.12 | v2.12.5 | research §1 |
| Orchestration | `Bus::batch()` + one nested chain + `allowFailures()` + `SkipIfBatchCancelled` | 13.x | research §2 |
| LLM transport | `AIClientInterface::generateStructured()` over `laravel/ai`, per-call `provider:` | ^0.11.0 | research §3 |
| Web research | `TavilyClientInterface` (exists) + optional recency | — | research §10 |
| Page retrieval | new `FirecrawlClientInterface` + `FirecrawlScrapeAdapter` | `/v1` | research §10.5 |
| PDF rendering | `ExportPort::pdf()` → DomPDF, `exports.pdf.layout` (DejaVu Sans, UTF-8) | ^3.1 | research §5 |
| ZIP / MIME | `ZipArchive` · `fileinfo` | present | research §6 |
| DTOs / TS | `spatie/laravel-data` ^4.23 → `typescript:transform` | — | rules.md |
| Authz / audit / storage | laravel-permission ^8.3 · activitylog ^5.1 via `AuditPort` · `StoragePort` (R2, signed) | — | `[LOCAL]` |
| Frontend | Vue 3.5 · Inertia v3 · Pinia Colada · PrimeVue v4 unstyled/Volt · Zod v4 · Wayfinder | `package.json` | rules.md |
| Listing export | existing export stack (CSV/XLSX/PDF) as used by other modules | — | BACKEND-PHP §8 |
| Tests | Pest 5 | — | rules.md |

---

## 3. Architecture

### 3.1 Layers

```
Domain (pure PHP)
  Enums · ValueObjects · Ports · Services (validators, continuity, notes excerpts,
  query factory, call estimation, naming, table arithmetic)
        ▲
Application
  DTOs (Spatie Data) · Commands/ · Queries/
        ▲
Infrastructure
  Parsing/ · Research/ · Ai/ · Rendering/ · Persistence/ · Queue/ · Http/ · Routes/
```

### 3.2 Ingestion (US-1, US-2)

```
POST /course-scripts   multipart: title, index, contents[], content_video_numbers[]
  → StoreCourseRequest
  → StoreCourseHandler
      ├─ StoragePort::put()                    index + content files, private          (FR-8)
      ├─ IndexDocumentParserPort::parse()      ParsedIndex{title, language, groups,
      │                                         points[brief…, notes], courseNotes}       (FR-4b/4c)
      ├─ DocumentTextExtractorPort::extract()  content files → text                     (FR-1b)
      ├─ CourseIndexValidator                  → UnrecognisableIndexException           (FR-7)
      ├─ title = typed ?: parsed ?: reject                                               (FR-1a)
      ├─ DB::transaction → Course + Blocks + Videos + SourceDocuments
      └─ AuditPort::log('course_scripts.course_uploaded')
```

Parser extension (research R11.3): brief extractors report consumed spans; detail-body residue → video notes; text outside points/TOC → course notes; for heading/list strategies, text between points → notes; repeated short lines (PDF headers/footers) dropped.

### 3.3 Preparation (US-3, US-4)

```
PrepareCourseHandler   (first job of a run when prepared_at is null, or POST /{uuid}/prepare)
  ├─ bible null → BibleProposerPort::propose()          1 AI call
  │     output: organisations[{key, name, role, sector, is_primary}], characters[{name, role,
  │             organisation_key}], audience, tone, taught_tool?, forbidden_phrasings[]
  │     bible_origin = proposed · bible_revision = 1
  ├─ SubjectResearcher   ≤ subject_queries Tavily calls, recency from config
  └─ prepared_at = now
```

`BibleRegistry` (Domain service) merges organisations/characters introduced by practice artifacts into the bible (dedupe by normalised name), bumping `bible_revision` — FR-13k.

### 3.4 Generation run (US-9, US-12, US-13)

```
POST /course-scripts/{uuid}/runs
  { scope, block_uuid?, video_uuids?, writer_provider, with_review: bool = false,
    confirmed_estimate: { ai_write_calls, ai_review_calls, research_calls } }
  → StartGenerationRunHandler
      ├─ one active run per course                                  → 409
      ├─ reviewer_provider = with_review ? config('ai.default_for_evaluation') : null
      ├─ reviewer_not_independent = with_review && reviewer_provider === writer_provider
      ├─ CallEstimator::estimate(scope, withReview)                  (FR-14a, D21)
      ├─ estimate must match confirmation and fit ceilings           → 422
      ├─ persist run (with_review, providers, estimates) + outcomes in course order
      ├─ Bus::batch([[ PrepareCourseJob?, GenerateVideoScriptJob(v1), … ]])->allowFailures()->dispatch()
      └─ store batch_id; audit course_scripts.run_started {with_review, scope, provider}
```

### 3.5 Per-video pipeline — `GenerateVideoScriptHandler`

```
1. ContinuityContextBuilder     predecessors' taught_summary (window) or briefs (provisional);
                                block position; next video
2. NotesExcerptSelector         video notes + assigned docs + top-k lexical excerpts (ids recorded)
3. PointResearcher              Tavily (recency) → ResearchEscalator (snippet → raw → Firecrawl, capped)
                                outage ⇒ is_grounded = false
4. ScriptWriterPort::outline()  1 call →
     technical_header, learning_objectives, continuity_note, uses_tool,
     sections[{number, parent_number?, title, kind, minutes, purpose,
               demos[{label: "DEMO 2", purpose, practice_files[]}]}],
     coverage_map, taught_summary,
     practice_plan?{ warranted, reason, topic,
                     files_summary, setup_instruction,
                     usage[{demo_label, section_number, purpose}],
                     artifacts[{file_name, title, genre, used_by_demos[], skeleton_key?,
                                organisations[], designed_contrasts[]}],
                     designed_contrasts[{dimension, values_by_file{}, intended_effect}],
                     shared_skeletons[{key, outline[]}],
                     instructor_note }
5. Gate A (outline)             TimeBudgetValidator · CoverageValidator · ErrorsToAvoidChecker
                                DemoLabelValidator (sequential, unique, each in one section)
                                PracticePlanValidator (unique sanitised file names, every artifact used
                                  by ≥ 1 demo, every demo file declared, contrasts reference declared files,
                                  artifact count ≤ config)
                                InstructorNoteCoverageValidator (every contrast dimension mentioned)
                                → retry outline with violations, ≤ max_outline_attempts
6. ScriptWriterPort::section() ×S   typed segments; show_on_screen may carry practice_file
7. ScriptWriterPort::closing()      summary, next_video, recording_notes{preparation[] (incl. practice
                                    files), during_recording[], tools_required[] | none_reason,
                                    continuity, organisations_used[]}, verification_checklist[]
8. ScriptWriterPort::artifact() ×A  per planned artifact → content blocks
                                    [heading{level,text} | paragraph | list{items} |
                                     table{header[], rows[][], total_row?} | key_values{pairs} |
                                     footer{text}]
                                    inputs: plan, shared skeleton, contrasts, bible, demo context
9. Gate B (full)                PromptToolConsistencyValidator · ClosingCompletenessValidator
                                (organisations_used ⊆ bible ∪ plan organisations; next video iff exists)
                                PracticeReferenceValidator (segments' practice_file ∈ plan files; all used)
                                TableArithmeticValidator (es/en number formats, tolerance)
                                ContactDataValidator (email domains not in a denylist of common real
                                  providers/brands from config; no URLs to real sites in artifacts)
                                → re-run only the offending step once; still failing ⇒ video failed
10. if with_review:             ScriptReviewerPort::reviewScript()   1 call (reviewer provider)
                                ScriptReviewerPort::reviewPractice() 1 call when a pack exists
                                scores{coverage, duration, format_fidelity, continuity, errors_to_avoid,
                                       practice_realism, designed_contrasts_present, integrity},
                                objections[{target: section N | artifact file, text}]
                                below threshold → rewrite ONLY targeted sections/artifacts with
                                objections → Gate B → review again; ≤ max_review_iterations;
                                exhausted ⇒ best draft, passed_review = false
                                else: reviewed = false, passed_review = null — zero reviewer calls
11. persist (one transaction)   ScriptVersion (reviewed, scores…) + PracticeVersion + sources +
                                BibleRegistry::merge(new organisations) ; newest passing version accepted
                                unless the course has manual acceptance on a regenerated video (§3.8)
12. BuildDeliverablesHandler    Guion, Prompts (if any), Practica, each practice file — md + pdf
13. counters: ai_write_calls, ai_review_calls, research_calls on outcome and run;
    ceiling reached ⇒ batch()->cancel(), stopped_at_ceiling
```

**The job never throws** (R2.2).

### 3.6 Call accounting (US-12, D21)

```
S  = clamp(round(minutes / minutes_per_section), min_sections, max_sections); minutes = declared ?: default
A  = practice_ratio × avg_artifacts_per_pack           (0.6 × 2 = 1.2, estimate)
aiWriteCallsPerVideo  = 1 outline + S + 1 closing + A (+ retries, not estimated)
aiReviewCallsPerVideo = with_review ? (1 + practice_ratio) × (1 + expected_rewrite_rounds) + rewrite calls : 0
                        expected_rewrite_rounds from config (0.5)
aiCallsPerCourse      = 1 bible proposal iff bible is null
researchCallsPerVideo = 1–2 Tavily + 0–max_firecrawl_per_video
researchCallsPerCourse= ≤ 4 Tavily
```

| Scope | Videos | AI write | AI review (`with_review=true`) | Research |
| --- | --- | --- | --- | --- |
| One video (default) | 1 | ~10 | ~3–5 | ~2–6 |
| One block | 6–10 | ~60–100 | ~20–45 | ~10–40 |
| Reference course (9 min) | 48 | **~490** | **~150–200** | ~52–150 |

Config: `runs.max_ai_calls_per_run` **900** (writing + review), `runs.max_research_calls_per_run` 250.

### 3.7 Rendering and bundle

```
Rendering/Documents/   ScriptDocument · PromptsSheetDocument · PracticeDocument · PracticeFileDocument
Rendering/             RenderVocabulary (es/en headings)
                       MarkdownScriptRenderer · PromptsSheetRenderer · MarkdownPracticeRenderer
                       MarkdownPracticeFileRenderer · PdfDocumentRenderer · CourseBundleBuilder
```

Practice document Markdown mirrors the sample:

```
## DOCUMENTO DE PRÁCTICA · VÍDEO 22 — Integración con Google Workspace: documentos y Drive
Archivos: Propuesta_Logistica_ProveedorA_2026.pdf y Propuesta_Logistica_ProveedorB_2026.pdf
Subir ambos documentos a Google Drive · Usar en: DEMO 2 (Sección 4 del guión) — Comparativa de propuestas
NOTA PARA EL INSTRUCTOR: …
## {artifact 1 content blocks}
## {artifact 2 content blocks}
```

PDF: header page + one artifact per page break. Each artifact is also rendered alone (`PracticeFileDocument`) under its file name.

ZIP (D17 extended):

```
{curso-slug}/
├── 00-curso/  README.md · Biblia.md · Indice.md
└── bloque-04-conectores/
    └── video-22-google-workspace/
        ├── Guion_Video_22.md / .pdf
        ├── Prompts_Video_22.md / .pdf
        ├── Practica_Google_Workspace_Guion_22.md / .pdf
        └── archivos/
            ├── Propuesta_Logistica_ProveedorA_2026.md / .pdf
            └── Propuesta_Logistica_ProveedorB_2026.md / .pdf
```

README columns: video, status, provider, grounded, reviewed, passed review, practice files.

### 3.8 Versions, feedback, staleness (US-14)

- `RegenerateScriptHandler` — `{ feedback_note, with_review }`; runs the per-video pipeline as a single-video run, feedback delimited as untrusted and placed in writer inputs.
- Acceptance rule: first generation of a video auto-accepts; a **regeneration** creates an unaccepted version that the author accepts via `AcceptScriptVersionHandler` (rebuilds deliverables, updates `taught_summary` exposure). Configurable `versions.auto_accept_regenerations` (default `false`).
- `StaleContinuityMarker` — when a video's accepted version changes, flag later accepted versions whose `continuity_source_video_ids` contain it. Flag only (D9).

### 3.9 Style references (US-15)

Upload as `course_source_documents.kind = style_reference` (scripts or practice packs). `StyleExemplarSelector` picks ≤ N excerpts (a script exemplar and, when a pack is planned, a practice exemplar), truncated by config, delimited as untrusted, passed to bible proposal, outline, artifact and review agents.

### 3.10 Listing export (US-16)

`CourseExportController` (CSV/XLSX/PDF) reusing `CourseFilterData` + `scopeApplyFilters`, `course-scripts.blade.php`, owner-scoped, route before `/{uuid}`.

---

## 4. Data model (physical schema)

UUIDv7 PKs (`HasUuids`). `LogsActivity` (explicit `logOnly`) on Course and GenerationRun. **Eleven tables.**

```
courses
- id, user_id FK cascade
- title, language string(8), declared_duration_minutes nullable, default_video_minutes smallint
- status enum(draft, ready, generating, partially_generated, completed)
- course_notes longtext nullable
- bible json nullable      ← {organisations[{key,name,role,sector,is_primary}], characters[{name,role,
                              organisation_key}], audience, tone, taught_tool?, forbidden_phrasings[]}
- bible_origin enum(proposed, author) nullable, bible_revision unsigned int default 0
- prepared_at nullable, timestamps, soft deletes
INDEX (user_id, status), (user_id, created_at)

course_blocks
- id, course_id FK cascade, number, title, declared_duration_minutes nullable, position, timestamps
UNIQUE (course_id, number)

course_videos
- id, course_id FK cascade, course_block_id FK nullable nullOnDelete
- number, title, topic nullable, declared_duration_minutes nullable
- objective, expected_result text nullable
- learning_areas, audience_objectives, mandatory_content, errors_to_avoid json
- notes longtext nullable, needs_review bool, brief_revision unsigned int default 1
- script_status enum(not_started, generating, generated, failed)
- timestamps
UNIQUE (course_id, number); INDEX (course_id, script_status), (course_block_id, number)

course_source_documents
- id, course_id FK cascade, course_video_id FK nullable nullOnDelete
- kind enum(index, content, style_reference)
- original_name, path, mime, size_bytes, checksum(64), extracted_text longtext nullable, timestamps
INDEX (course_id, kind)

course_research_findings
- id, course_id FK cascade, course_video_id FK nullable cascade
- provider string(32), query string(500), url string(2048), title string(500), content text
- score decimal(5,4) nullable, full_page_fetched bool, published_at nullable, gathered_at, timestamps
INDEX (course_id, course_video_id)

course_generation_runs
- id, course_id FK cascade, user_id FK
- scope enum(course, block, selection), scoped_block_id uuid nullable
- kind enum(generation, regeneration, force_practice) default generation
- writer_provider string, reviewer_provider string nullable
- with_review bool default false                                   ← FR-14a
- reviewer_not_independent bool default false                      ← FR-41
- status enum(queued, running, completed, partially_failed, cancelled, stopped_at_ceiling, failed)
- batch_id nullable
- estimated_ai_write_calls, estimated_ai_review_calls, estimated_research_calls unsigned int
- ai_call_ceiling, research_call_ceiling unsigned int
- ai_write_calls_consumed, ai_review_calls_consumed, research_calls_consumed unsigned int default 0
- videos_total, videos_completed, videos_failed unsigned int default 0
- current_video_id uuid nullable, stop_reason nullable, started_at, finished_at nullable, timestamps
INDEX (course_id, status)

course_video_outcomes
- id, run FK cascade, video FK cascade
- status enum(pending, running, completed, failed, skipped), failure_reason string(500) nullable
- attempts, review_iterations unsigned smallint default 0
- ai_write_calls, ai_review_calls, research_calls unsigned int default 0
- started_at, finished_at nullable, timestamps
UNIQUE (run, video)

course_script_versions
- id, course_video_id FK cascade, course_generation_run_id FK nullable nullOnDelete
- version, is_accepted bool
- writer_provider, brief_revision, bible_revision
- technical_header json, learning_objectives json
- continuity_note text, continuity_is_provisional bool, continuity_source_video_ids json, continuity_stale bool
- sections json                ← [{number, parent_number, title, kind, minutes, purpose,
                                    demos[{label, purpose, practice_files[]}], segments[]}]
- uses_tool bool, taught_summary text
- summary_points json, next_video json nullable, recording_notes json, verification_checklist json
- coverage_map json, errors_check json
- is_grounded bool, notes_excerpt_ids json, prompts_sheet_reason string nullable
- practice_warranted bool, practice_decision_reason text
- reviewed bool default false                                  ← FR-40a
- reviewer_provider string nullable
- review_scores json nullable, review_objections json nullable, review_iterations smallint default 0
- passed_review bool nullable
- feedback_note text nullable
- timestamps
UNIQUE (course_video_id, version); INDEX (course_video_id, is_accepted)

course_script_sources
- course_script_version_id FK cascade, course_research_finding_id FK cascade
PRIMARY (both)

course_practice_versions
- id, course_script_version_id FK cascade
- decided_by enum(system, author_forced), decision_reason text
- document_name string                    ← Practica_{Tema}_Guion_NN
- header_title string                     ← DOCUMENTO DE PRÁCTICA · VÍDEO NN — …
- files_summary text, setup_instruction text
- usage json                              ← [{demo_label, section_number, purpose}]
- instructor_note text
- designed_contrasts json                 ← [{dimension, values_by_file{}, intended_effect}]
- artifacts json                          ← [{file_name, title, genre, used_by_demos[], organisations[],
                                              content_blocks[]}]
- review_scores json nullable, review_objections json nullable
- timestamps
UNIQUE (course_script_version_id)

course_deliverables
- id, course_script_version_id FK cascade
- document_type enum(script, prompts, practice, practice_file)
- artifact_file_name string nullable      ← only for practice_file
- format enum(md, pdf), path, size_bytes, checksum(64), timestamps
UNIQUE (course_script_version_id, document_type, artifact_file_name, format)
```

> `artifact_file_name` is part of a unique key; MySQL/PostgreSQL treat `NULL`s as distinct, so the migration stores `''` (not `NULL`) for non-file documents to keep the key effective.

---

## 5. Routes and contracts

`Route::middleware(['web','auth'])->prefix('course-scripts')->name('course-scripts.')` (the `Post` convention). `permission:*` on every route; `whereUuid`; static paths before `/{uuid}`; Spatie Data responses; Wayfinder on the client.

| # | Method & path | Kind | Story | Permission · throttle |
| --- | --- | --- | --- | --- |
| 1 | `GET /` | Inertia `course-scripts/Index` | US-10 | `VIEW_ANY_COURSE_SCRIPTS` |
| 2 | `GET /create` | Inertia `course-scripts/Create` | US-1 | `CREATE_COURSE_SCRIPTS` |
| 3 | `GET /export` | file | US-16 | `EXPORT_COURSE_SCRIPTS` · export |
| 4 | `POST /` | redirect | US-1 | `CREATE_COURSE_SCRIPTS` · upload |
| 5 | `GET /{uuid}` | Inertia `course-scripts/Show` | US-2, US-10 | `VIEW_COURSE_SCRIPTS` |
| 6 | `PUT /{uuid}/videos/{videoUuid}` | JSON | US-2 | `UPDATE_COURSE_SCRIPTS` |
| 7 | `PUT /{uuid}/course-notes` | JSON | US-2 | `UPDATE_COURSE_SCRIPTS` |
| 8 | `POST /{uuid}/contents` | JSON | US-1 | `UPDATE_COURSE_SCRIPTS` · upload |
| 9 | `DELETE /{uuid}/contents/{documentUuid}` | JSON | US-1 | `UPDATE_COURSE_SCRIPTS` |
| 10 | `POST /{uuid}/style-references` | JSON | US-15 | `UPDATE_COURSE_SCRIPTS` · upload |
| 11 | `PUT /{uuid}/bible` | JSON | US-3 | `UPDATE_COURSE_SCRIPTS` |
| 12 | `POST /{uuid}/prepare` | JSON | US-3, US-4 | `GENERATE_COURSE_SCRIPTS` · generate |
| 13 | `POST /{uuid}/runs/estimate` | JSON | US-12 | `GENERATE_COURSE_SCRIPTS` |
| 14 | `POST /{uuid}/runs` | JSON | US-9, US-13 | `GENERATE_COURSE_SCRIPTS` · generate |
| 15 | `GET /runs/{runUuid}` | JSON (polled) | US-9 | `VIEW_COURSE_SCRIPTS` · status |
| 16 | `POST /runs/{runUuid}/cancel` | JSON | US-9 | `GENERATE_COURSE_SCRIPTS` |
| 17 | `POST /runs/{runUuid}/retry-failed` | JSON | US-9 | `GENERATE_COURSE_SCRIPTS` · generate |
| 18 | `GET /{uuid}/videos/{videoUuid}/script` | JSON (accepted or `?version=`) | US-5, US-14 | `VIEW_COURSE_SCRIPTS` |
| 19 | `GET /{uuid}/videos/{videoUuid}/versions` | JSON | US-14 | `VIEW_COURSE_SCRIPTS` |
| 20 | `POST /{uuid}/videos/{videoUuid}/regenerate` | JSON `{feedback_note, writer_provider, with_review}` | US-14 | `GENERATE_COURSE_SCRIPTS` · generate |
| 21 | `PUT /{uuid}/videos/{videoUuid}/versions/{versionUuid}/accept` | JSON | US-14 | `UPDATE_COURSE_SCRIPTS` |
| 22 | `POST /{uuid}/videos/{videoUuid}/practice` | JSON `{writer_provider, with_review}` | US-7 | `GENERATE_COURSE_SCRIPTS` · generate |
| 23 | `GET /deliverables/{deliverableUuid}/download` | signed redirect | US-8 | `DOWNLOAD_COURSE_SCRIPTS` · download |
| 24 | `GET /{uuid}/videos/{videoUuid}/bundle` | ZIP | US-8 | `DOWNLOAD_COURSE_SCRIPTS` · download |
| 25 | `GET /{uuid}/bundle` | ZIP | US-8 | `DOWNLOAD_COURSE_SCRIPTS` · download |
| 26 | `DELETE /{uuid}` | redirect | US-11 | `DELETE_COURSE_SCRIPTS` |

### POST /course-scripts/{uuid}/runs
- **Request:** `scope` · `block_uuid?` · `video_uuids?` · `writer_provider: openai|anthropic|gemini` · **`with_review: boolean` (default false)** · `confirmed_estimate{ai_write_calls, ai_review_calls, research_calls}`
- **200:** `GenerationRunData` incl. `with_review`, `reviewer_provider`, `reviewer_not_independent`
- **409** `run_in_progress` · **422** empty scope / stale estimate / exceeds ceiling · **404**

### POST /course-scripts/{uuid}/runs/estimate
- **Request:** same scope fields + `with_review`
- **200:** `CallEstimateData{ai_write_calls, ai_review_calls, research_calls, ai_ceiling, research_ceiling, fits}` — `ai_review_calls = 0` when `with_review = false`

### GET /course-scripts/{uuid}/bundle
- **200** `application/zip` (D17 layout with `archivos/`) · **409** `nothing_generated` · **404**

---

## 6. Folder structure

### Backend

```
src/Modules/CourseScripts/
├── Providers/CourseScriptsServiceProvider.php
├── Domain/
│   ├── Enums/          CourseStatus · VideoScriptStatus · GenerationRunStatus · GenerationRunKind
│   │                   GenerationScope · VideoOutcomeStatus · SourceDocumentKind · BibleOrigin
│   │                   SectionKind · SegmentType · ContentBlockType · ArtifactGenre
│   │                   PracticeDecisionOrigin · DeliverableDocumentType · DeliverableFormat
│   ├── ValueObjects/   ParsedIndex · ParsedGroup · ParsedPoint [exist, +notes]
│   │                   ContinuityContext · CallEstimate · NotesExcerpt · ValidationViolations
│   │                   ReviewVerdict
│   ├── Exceptions/     [4 exist] · RunInProgressException · EstimateMismatchException
│   │                   NothingGeneratedException · ScriptValidationException · VersionNotFoundException
│   ├── Ports/          IndexDocumentParserPort [exists] · DocumentTextExtractorPort · ResearchPort
│   │                   BibleProposerPort · ScriptWriterPort · ScriptReviewerPort
│   └── Services/       CourseIndexValidator [exists]
│                       TimeBudgetValidator · CoverageValidator · ErrorsToAvoidChecker
│                       DemoLabelValidator · PracticePlanValidator · PracticeReferenceValidator
│                       InstructorNoteCoverageValidator · TableArithmeticValidator · ContactDataValidator
│                       PromptToolConsistencyValidator · ClosingCompletenessValidator
│                       ContinuityContextBuilder · NotesExcerptSelector · StyleExemplarSelector
│                       ResearchQueryFactory · CallEstimator · DocumentNameFactory · BibleRegistry
│                       StaleContinuityMarker
├── Application/
│   ├── DTOs/           StoreCourseData · CourseListItemData · CourseDetailData · CourseFilterData
│   │                   BlockData · VideoBriefData · UpdateVideoBriefData · UpdateCourseNotesData
│   │                   SourceDocumentData · CourseBibleData · BibleOrganisationData · BibleCharacterData
│   │                   CallEstimateData · EstimateRunData · StartGenerationRunData · GenerationRunData
│   │                   VideoOutcomeData · ScriptVersionData · ScriptVersionListItemData
│   │                   ScriptSectionData · ScriptDemoData · ScriptSegmentData · RecordingNotesData
│   │                   PracticeVersionData · PracticeArtifactData · ContentBlockData
│   │                   DesignedContrastData · ReviewData · DeliverableData · ResearchSourceData
│   │                   RegenerateScriptData · ForcePracticeData
│   ├── Commands/       StoreCourseHandler · UpdateVideoBriefHandler · UpdateCourseNotesHandler
│   │                   AttachContentDocumentHandler · DetachContentDocumentHandler
│   │                   AttachStyleReferenceHandler · UpdateCourseBibleHandler · PrepareCourseHandler
│   │                   StartGenerationRunHandler · GenerateVideoScriptHandler · ReviewDraftHandler
│   │                   CancelGenerationRunHandler · RetryFailedVideosHandler
│   │                   RegenerateScriptHandler · AcceptScriptVersionHandler · ForcePracticeDocumentHandler
│   │                   BuildDeliverablesHandler · DeleteCourseHandler
│   └── Queries/        ListCoursesHandler · GetCourseHandler · GetGenerationRunHandler
│                       GetScriptVersionHandler · ListScriptVersionsHandler · EstimateRunCallsHandler
│                       FindStaleScriptsHandler
└── Infrastructure/
    ├── Parsing/        IndexDocumentParser · MarkdownIndexParser · IndexVocabulary · PdfIndexParser [exist]
    │                   DocumentTextExtractor
    ├── Research/       LaravelResearchAdapter · SubjectResearcher · PointResearcher · ResearchEscalator
    ├── Ai/             ProposeCourseBibleAgent · GenerateScriptOutlineAgent · GenerateScriptSectionAgent
    │                   GenerateScriptClosingAgent · GeneratePracticeArtifactAgent
    │                   ReviewScriptAgent · ReviewPracticeAgent
    │                   LaravelAiBibleProposerAdapter · LaravelAiScriptWriterAdapter
    │                   LaravelAiScriptReviewerAdapter · UntrustedContentBlock
    ├── Rendering/      Documents/{ScriptDocument, PromptsSheetDocument, PracticeDocument, PracticeFileDocument}
    │                   RenderVocabulary · MarkdownScriptRenderer · PromptsSheetRenderer
    │                   MarkdownPracticeRenderer · MarkdownPracticeFileRenderer · ContentBlockMarkdown
    │                   PdfDocumentRenderer · CourseBundleBuilder
    ├── Persistence/    CourseOwnershipResolver
    ├── Persistence/Eloquent/Models/  (10 models)
    ├── Queue/          PrepareCourseJob · GenerateVideoScriptJob
    ├── Http/Controllers/ CourseController · CourseContentController · CourseBibleController
    │                   GenerationRunController · ScriptVersionController · DeliverableController
    │                   CourseExportController
    ├── Http/Requests/  StoreCourseRequest · UpdateVideoBriefRequest · UpdateCourseNotesRequest
    │                   AttachContentDocumentRequest · AttachStyleReferenceRequest · UpdateCourseBibleRequest
    │                   EstimateRunRequest · StartGenerationRunRequest · RegenerateScriptRequest
    │                   ForcePracticeRequest · ExportCoursesRequest
    ├── Http/Export/    CourseExportTransformer
    └── Routes/web.php

resources/views/exports/pdf/
├── course-script.blade.php · course-prompts.blade.php · course-practice.blade.php
├── course-practice-file.blade.php · partials/course-content-blocks.blade.php
└── course-scripts.blade.php                       (listing export)
database/migrations/2026_09_12_XXXXXX_create_course_scripts_tables.php
```

No repository ports (Repository Rule). `Persistence/Repositories/` removed at closeout if empty.

**Shared:** `TavilyClientInterface`/`TavilyResearchAdapter` (+recency) · `FirecrawlClientInterface` + `FirecrawlScrapeAdapter` · `config/services.php` `firecrawl` block.

### Frontend

```
resources/js/pages/course-scripts/
├── Index.vue       lazy DataTable, filters, export menu, upload CTA
├── Create.vue      title + index + content files
└── Show.vue        tabs: Estructura · Biblia · Notas y contenido · Generación · Entregables

resources/js/modules/course-scripts/
├── components/
│   CourseStatusBadge · VideoStatusBadge · CourseUploadForm · ContentFileList · StyleReferenceList
│   CourseStructureTable · VideoBriefDrawer · CourseBibleEditor (organisations + characters)
│   CourseNotesEditor · RunLauncher (scope, provider, second-review ToggleSwitch, estimate diff)
│   RunProgress · ScriptPreview · PromptsSheetPreview · PracticePackPreview (header, usage,
│   instructor note, contrasts table, artifacts rendered from content blocks) · ReviewScores
│   ScriptVersionList (accept, stale badge) · RegenerateDialog (feedback + second-review toggle)
│   ResearchSourcesList · DeliverableDownloads
├── composables/    useCourses · useCourseMutations · useGenerationRun · useScriptVersions · useScriptPreview
├── schemas/        courseUpload · videoBrief · courseBible · runLauncher · regenerate
├── helpers/        coursePresentation.ts
└── types.ts        re-exports generated types only
```

---

## 7. Testing strategy

Pest 5. `php artisan test --compact src/Modules/CourseScripts`.

### Unit (pure Domain)

| Test | Covers |
| --- | --- |
| `MarkdownIndexParserTest` [ext.] · `CourseIndexValidatorTest` [exists] | notes, course notes |
| `CourseScriptsEnumsTest` | transitions |
| `TimeBudgetValidatorTest` · `CoverageValidatorTest` · `ErrorsToAvoidCheckerTest` | FR-29/31/32 |
| `DemoLabelValidatorTest` | sequential/unique labels (FR-29a) |
| `PracticePlanValidatorTest` · `PracticeReferenceValidatorTest` | files/demos two-way (FR-36b) |
| `InstructorNoteCoverageValidatorTest` | every contrast dimension explained (FR-36c) |
| `TableArithmeticValidatorTest` | sample totals 28.856 € / 32.252 € pass; off-by-one fails; es/en formats (FR-36d) |
| `ContactDataValidatorTest` | denylisted domains / real URLs rejected (FR-39b) |
| `PromptToolConsistencyValidatorTest` · `ClosingCompletenessValidatorTest` | FR-30a/33b, organisations ⊆ bible |
| `ContinuityContextBuilderTest` · `NotesExcerptSelectorTest` · `StyleExemplarSelectorTest` | |
| `ResearchQueryFactoryTest` · `CallEstimatorTest` (with/without review) | D21 |
| `DocumentNameFactoryTest` · `BibleRegistryTest` · `StaleContinuityMarkerTest` | FR-37, FR-13k, FR-34 |

### Feature

| Test | Covers |
| --- | --- |
| `PdfIndexParserTest` [ext.] · `CourseUploadTest` · `CourseEditingTest` | ingestion |
| `CourseBibleTest` · `TavilyRecencyTest` · `FirecrawlScrapeAdapterTest` · `ResearchPipelineTest` | preparation/research |
| `ScriptStructureTest` · `NonToolSubjectTest` | SC-3/4/5/12 |
| `PracticePackTest` | fixture plan modelled on `Propuestas_Logistica_Heliantia`: header, files, setup, usage, instructor note, 2 proposal artifacts, separate files, new organisations registered (SC-13) |
| `PromptsSheetTest` | SC-7 |
| `SecondReviewToggleTest` | `with_review=false` ⇒ 0 reviewer calls, `reviewed=false`; `true` ⇒ script + practice reviewed, rewrite only targeted items, exhaustion ⇒ not passed, same-provider warning, retry inherits flag (SC-14) |
| `GenerationRunLifecycleTest` · `GenerationRunFailureTest` · `GenerationRunCancelTest` · `GenerationCallCeilingTest` | SC-8/9 |
| `ScriptVersioningTest` | regenerate with feedback, list, accept rebuilds, staleness flags |
| `StyleReferenceTest` | upload, exemplar selection, delimiting |
| `DeliverableRenderTest` · `CourseBundleTest` | incl. `archivos/` |
| `CourseScriptsExportTest` | CSV/XLSX/PDF owner-scoped |
| `CourseScriptsAccessTest` | all 26 routes: 401/403/404 (SC-10) |
| `PromptInjectionDefenceTest` · `BladeEscapingTest` | FR-56, no `{!! !!}` |
| `CourseScriptsPagesTest` | Inertia props |

### Doubles and fixtures

`FakeScriptWriter`, `FakeScriptReviewer` (scripted verdict sequences), `FakeBibleProposer`, `FakeResearch`. Fixtures: `index-sample`, `index-with-notes` (+pdf), `content-notes.md`, `index-cooking.md`, `CanonicalScriptFixture` (video 39 shape), **`CanonicalPracticePackFixture`** (video 22 shape, values copied from the sample).

---

## 8. Security and compliance

| Control | Implementation | Ref |
| --- | --- | --- |
| Access control | `auth` + `permission:*`; `CourseOwnershipResolver` everywhere; 404 for foreign ids | FR-52/53 |
| Function-level authz | generate (spends money) separate from update/download/export | FR-52 |
| Input validation | FormRequest + Data; `with_review` `boolean`; provider `Rule::in(config)`; model never a parameter | FR-58, A2 |
| Uploads | ext + real MIME + size + count; in-process PDF parsing | FR-1, FR-1b |
| Storage | `StoragePort`, signed URLs; ZIP temp file deleted after send | FR-51 |
| LLM01 | index, notes, content, bible, style references, research, **feedback notes** delimited by `UntrustedContentBlock`; instructions constant; no tools | FR-56 |
| LLM02 | schema-validated output; Blade escaping; content blocks rendered as text, never HTML; no `v-html` | OWASP §16 |
| LLM09 / impersonation | FR-39b instructions + `ContactDataValidator` denylist (config) | FR-39b |
| Logging | audit metadata only (`with_review`, provider, counts); sanitized failure codes | FR-54/55 |
| LLM10 | throttles; AI (write+review) and research ceilings; breakers; review opt-in | FR-23, FR-57 |
| Exceptions | `renderable()` sanitized responses | OWASP §10 |
| Property-level authz | Data allowlists; no paths/checksums to the client | FR-51 |

---

## 9. Risks

| Risk | Mitigation |
| --- | --- |
| RK-3 call volume (~490 write, +~175 review for 48 videos) | estimate + confirmation; review opt-in; ceilings 900/250 |
| RK-4 Spanish quality | deterministic gate always; second review on demand |
| RK-5 few exemplars | 39/43/46 scripts + video-22 practice pack; style references for more |
| RK-7 practice realism varies | shared skeletons, declared contrasts, arithmetic + instructor-note validators; review scores realism when on |
| RK-8 notes matching | video notes in full; explicit assignment; excerpt ids visible |
| RK-9 PDF noise in notes | normalisation + PDF-twin tests |
| RK-11 invented contact data colliding with real entities | invented-domain instruction + denylist validator; cannot be fully guaranteed — documented in README of each ZIP ("datos ficticios") |
| RK-12 artifact count blows the budget | `practice.max_artifacts_per_pack` (4); plan validator rejects above it |
| RK-13 review rewrite loops burn calls | only targeted sections/artifacts rewritten; `max_review_iterations`; review calls inside the AI ceiling |
| RK-2 smalot maintenance | isolated in one class |

---

## 10. Traceability

| Requirement | Plan | Test |
| --- | --- | --- |
| FR-1…FR-8a | §3.2 | parser tests · `CourseUploadTest` · `CourseEditingTest` |
| FR-9, FR-11…FR-13, FR-13k | §3.3 | `CourseBibleTest` · `BibleRegistryTest` |
| FR-10 | §3.9 | `StyleReferenceTest` |
| FR-13a…FR-13j | §3.3, §3.5 step 3 | `ResearchPipelineTest` · `TavilyRecencyTest` · `FirecrawlScrapeAdapterTest` |
| FR-14, FR-14a, FR-15…FR-25 | §3.4, §3.6 | run tests · `SecondReviewToggleTest` · `CallEstimatorTest` |
| FR-26…FR-33b | §3.5 steps 4–9 | `ScriptStructureTest` · validator unit tests |
| FR-29a | §3.5 step 5 | `DemoLabelValidatorTest` |
| FR-34 | §3.8 | `StaleContinuityMarkerTest` · `ScriptVersioningTest` |
| FR-35a…c | §3.7 | `PromptsSheetTest` |
| FR-35…FR-39b | §3.5 steps 4, 5, 8, 9; §4 | `PracticePackTest` · practice validator tests |
| FR-40…FR-44, FR-40a | §3.5 step 10 | `SecondReviewToggleTest` |
| FR-44a | §3.5 steps 5, 9 | validator unit tests |
| FR-45…FR-48a, FR-51 | §3.7 | `DeliverableRenderTest` · `CourseBundleTest` · `CourseScriptsAccessTest` |
| FR-49, FR-50 | §3.8 | `ScriptVersioningTest` |
| FR-52a/b | §6 frontend | `CourseScriptsPagesTest` + manual run |
| FR-52…FR-58 | §8 | access, injection, escaping, upload tests |
| US-16 | §3.10 | `CourseScriptsExportTest` |
| SC-1…SC-14 | §7 | tests above |
