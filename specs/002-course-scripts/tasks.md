# Tasks: Course Scripts

> Phase 5 · BREAK DOWN TASKS — The plan split into small, dependency-ordered, independently verifiable steps.
> `[P]` = parallelizable (no shared files, no dependency on a sibling `[P]` task).
> Every task leaves the system compiling and testable — never half-broken.

> **Revision 3 (2026-09-12)** — practice packs from the author's sample (`Propuestas_Logistica_Heliantia`), per-run `with_review` boolean, former Phase P in scope. **Core = phases A–N** (upload → ZIP from the UI, including the optional second review). **Extended = phases O–S**. **T = final closeout.** Completed tasks keep IDs and checkmarks.

**Feature ID:** 002-course-scripts
**Based on:** `plan.md` (rev. 3)
**Commit convention:** `feat(course-scripts): T0XX short description`

**Standing rules for every task**
- Read `.claude/rules/rules.md` and the skills it routes to before touching a layer (OWASP always; BACKEND-PHP + ARCHITECTURE-PHP for PHP; FRONTEND + ARCHITECTURE-VUE for Vue).
- Context7, scoped to the installed version, before using a framework API not already used in this repo.
- Pest 5 only; `declare(strict_types=1);`; explicit types.
- `vendor/bin/pint --dirty --format agent` after touching PHP.
- A task is checked only after **its own test ran and passed** — quote the real result.
- Herd on Windows; from Git Bash PHP is `C:/Users/Lenovo/.config/herd/bin/php.bat`.

---

# CORE

## Phase A — Foundations

- [x] **T001** `composer require smalot/pdfparser:^2.12`.
- [x] **T002** `config/course-scripts.php`.
- [x] **T003** Module skeleton + provider registered.
- [x] **T004** `*_COURSE_SCRIPTS` permissions in `RolePermissionSeeder`.
- [x] **T005** Fixtures `index-sample.md` + `.pdf`.
- [x] **T005a** Revise `config/course-scripts.php` for rev. 3 — no magic numbers elsewhere:
  - `uploads.max_content_files` (10), `uploads.max_content_total_kb`, `uploads.max_style_references` (10)
  - `script.default_video_minutes` (8), `script.practice_ratio` (0.6)
  - `practice.avg_artifacts_per_pack` (2), `practice.max_artifacts_per_pack` (4), `practice.table_total_tolerance` (0.01), `practice.contact_domain_denylist` (gmail.com, outlook.com, hotmail.com, yahoo.com, google.com, microsoft.com, amazon.com, …)
  - `runs.max_ai_calls_per_run` (**900**, replaces `max_calls_per_run`), `runs.max_research_calls_per_run` (250), `runs.max_step_retries` (1), `runs.max_review_iterations` (3), `runs.expected_rewrite_rounds` (0.5)
  - `review.pass_threshold` (per-dimension minimum + overall, e.g. 7/10), `review.default_with_review` (false)
  - `versions.auto_accept_regenerations` (false)
  - `notes.excerpt_budget_chars` (6000), `notes.max_excerpts` (8), `style.exemplar_budget_chars` (6000)
  - `research.subject_queries` (4), `research.point_queries_max` (2), `research.recency` (`year`), `research.finding_content_max_chars` (4000), `research.max_firecrawl_per_video` (2), `research.firecrawl_enabled` (true)
  - `rate_limits.status` (120)
- [x] **T005b** `[P]` Fixtures: `index-with-notes.md` (+ PDF twin), `content-notes.md`, `index-cooking.md`, and `Tests/Support/CanonicalPracticePackFixture.php` (video 22 plan + two proposal artifacts with the sample's values, totals 28.856 € / 32.252 €). Create missing skeleton folders (`Infrastructure/Research/`, `Infrastructure/Rendering/Documents/`, `Infrastructure/Http/Export/`).

## Phase B — Data model

- [x] **T006** Migration with the **eleven** tables of plan §4 (incl. `with_review`, `reviewer_not_independent`, split write/review call counters, `reviewed` + review columns on script versions, practice pack columns, `document_type = practice_file` + `artifact_file_name` default `''` in the unique key). `php artisan migrate`; `db:table` for `courses`, `course_videos`, `course_generation_runs`, `course_script_versions`, `course_practice_versions`, `course_deliverables`.
- [x] **T007** `[P]` Ten Eloquent models: `@internal`, `HasUuids`, explicit `$fillable`, typed `casts()`, relations; `LogsActivity` (v5 namespaces, explicit `logOnly` incl. `with_review` on runs) on Course and GenerationRun; Course purges stored files on force delete.
- [x] **T008** `[P]` Domain enums (plan §6), TitleCase. Transition matrices on run/video status; `SegmentType::requiresTool()`; `ArtifactGenre` and `ContentBlockType` complete.
- [x] **T009** `CourseScriptsEnumsTest`. **Run it.**
- [x] **T010** `[P]` Owner and filter scopes on Course and Video models; `CourseFilterData`.
- [x] **T010a** `Infrastructure/Persistence/CourseOwnershipResolver` (course, video, run, source document, script version, deliverable) → `CourseNotFoundException`. `CourseOwnershipResolverTest`. **Run it.** *(FR-53)*

## Phase C — Ingestion (US-1, US-2)

- [x] **T011** Parsed value objects + `IndexDocumentParserPort`.
- [x] **T012** `MarkdownIndexParser`.
- [x] **T013** `MarkdownIndexParserTest`.
- [x] **T014** `CourseIndexValidator` + test.
- [x] **T015** `PdfIndexParser`.
- [x] **T016** `IndexDocumentParser` dispatcher.
- [x] **T017** `PdfIndexParserTest`.
- [x] **T017a** Notes extraction: `ParsedPoint::$notes`, `ParsedIndex::$courseNotes`; brief extractors report consumed spans; residue → notes; text between heading/list points → notes; text outside points/TOC → course notes; PDF header/footer noise dropped. *(FR-4b/4c, R11.3)*
- [x] **T017b** Extend parser tests with notes fixtures (per-video notes, course notes, no duplication of brief fields, reference index → null notes, PDF parity). **Run them.**
- [x] **T017c** `DocumentTextExtractorPort` + `DocumentTextExtractor` (Markdown passthrough; PDF via `PdfIndexParser::extractText()` refactor; same exceptions) + test. **Run it.**
- [x] **T018** `StoreCourseRequest/Data/Handler` + `CourseController::create/store` + routes #2, #4 (`throttle:course-scripts-upload`): store files, parse, extract, validate, typed title wins, `0` duration → `null`, one transaction, audit. *(FR-1…FR-8a, FR-58)*
- [x] **T019** Brief/notes/course-notes editing and content attach/detach handlers + routes #6–#9. *(FR-6, FR-1b)*
- [x] **T020** `CourseUploadTest` + `CourseEditingTest`. **Run them.**
- [x] **T021** Verify **US-1**, **US-2**.

## Phase D — Preparation and research (US-3, US-4 core)

- [x] **T022** Tavily optional `timeRange` (omitted ⇒ identical request) + `TavilyRecencyTest`; run `Post`, `Campaigns`, `SocialMedia` suites. **Run them.** *(FR-13d)*
- [x] **T023** `[P]` `ResearchPort` + `LaravelResearchAdapter` (never throws, counts calls) + `FakeResearch`.
- [x] **T024** `[P]` `ResearchQueryFactory` + test. **Run it.**
- [x] **T025** `SubjectResearcher` + `PointResearcher` + findings persistence. *(FR-13a/b/j)*
- [x] **T026** `UntrustedContentBlock` (index, notes, content, bible, style references, research, feedback). *(FR-56)*
- [x] **T027** Bible: `CourseBibleData` with `BibleOrganisationData` (exactly one primary) + `BibleCharacterData`; `BibleProposerPort`, `ProposeCourseBibleAgent`, `LaravelAiBibleProposerAdapter`, `FakeBibleProposer`. *(FR-9, FR-11)*
- [x] **T027a** `[P]` `BibleRegistry` (merge organisations/characters by normalised name, keep one primary, bump revision) + `BibleRegistryTest`. **Run it.** *(FR-13k, D18)*
- [x] **T028** `PrepareCourseHandler` + `PrepareCourseJob` + `UpdateCourseBibleHandler` + `FindStaleScriptsHandler` + `CourseBibleController` + routes #11, #12.
- [x] **T029** `CourseBibleTest` + `ResearchPipelineTest`. **Run them.**
- [x] **T029a** Verify **US-3**, **US-4** (core criteria).

## Phase E — Script contract (US-5)

- [ ] **T030** `[P]` `TimeBudgetValidator` + test. **Run it.** *(FR-29, FR-8a)*
- [ ] **T031** `[P]` `CoverageValidator` + `ErrorsToAvoidChecker` + tests. **Run them.** *(FR-31/32)*
- [ ] **T032** `[P]` `DemoLabelValidator` + test (sequential `DEMO n`, unique, one section each). **Run it.** *(FR-29a)*
- [ ] **T033** `[P]` `PromptToolConsistencyValidator` + `ClosingCompletenessValidator` (organisations_used ⊆ bible ∪ practice plan; next video iff exists; preparation lists practice files when a pack exists) + tests. **Run them.** *(FR-30a, FR-33b)*
- [ ] **T034** `[P]` `ContinuityContextBuilder` + test. **Run it.**
- [ ] **T035** `[P]` `NotesExcerptSelector` + test. **Run it.** *(FR-4d)*
- [ ] **T036** `[P]` `CallEstimator` (write vs review split; review only when `withReview`; practice ratio × avg artifacts; bible call iff null; Firecrawl cap) + test incl. both toggle values. **Run it.** *(US-12, D21)*
- [ ] **T037** `[P]` `DocumentNameFactory` (`Guion_Video_NN`, `Prompts_Video_NN`, `Practica_{Tema}_Guion_NN`, artifact file-name sanitiser preserving descriptive names like `Propuesta_Logistica_ProveedorA_2026`, folder slugs) + test. **Run it.** *(FR-37)*
- [ ] **T038** Spatie Data for generated structure: sections, demos, segments, recording notes, practice plan, artifacts, content blocks, designed contrasts, review data, sources.
- [ ] **T039** `ScriptWriterPort` (`outline`, `section`, `closing`, `artifact`) + agents with `HasStructuredOutput`, flat schemas, constant instructions, course language parameter:
  - `GenerateScriptOutlineAgent` — incl. demos and the full **practice plan** (files summary, setup, usage, artifacts with genres, designed contrasts, shared skeletons, instructor note), modelled on `Propuestas_Logistica_Heliantia`.
  - `GenerateScriptSectionAgent` — typed segments; `show_on_screen.practice_file`.
  - `GenerateScriptClosingAgent`.
  *(targets: scripts 39/43/46)*
- [ ] **T040** `LaravelAiScriptWriterAdapter` (per-call provider, call counting) + `FakeScriptWriter` + `CanonicalScriptFixture`.
- [ ] **T041** `GenerateVideoScriptHandler` steps 1–7, 9, 11 (plan §3.5) — script without practice pack yet; gates A/B with bounded retries; persistence incl. `reviewed = false`.
- [ ] **T042** `ScriptStructureTest` + `NonToolSubjectTest`. **Run them.** *(SC-3/4/5/12)*
- [ ] **T043** Verify **US-5**.

## Phase F — Practice pack and prompts sheet (US-6, US-7)

- [ ] **T044** `[P]` `PracticePlanValidator` (unique sanitised file names, every artifact used, every demo file declared, contrasts reference declared files, count ≤ config) + `PracticeReferenceValidator` (segments ↔ plan, two-way) + tests. **Run them.** *(FR-36b)*
- [ ] **T045** `[P]` `InstructorNoteCoverageValidator` + test (every contrast dimension explained; sample instructor note passes). **Run it.** *(FR-36c)*
- [ ] **T046** `[P]` `TableArithmeticValidator` + test (sample totals pass; altered total fails; `16.280 €`, `7,40 €/envío`, `1,234.50`). **Run it.** *(FR-36d)*
- [ ] **T047** `[P]` `ContactDataValidator` + test (denylisted domains and real-site URLs rejected; invented domains accepted). **Run it.** *(FR-39b)*
- [ ] **T048** `GeneratePracticeArtifactAgent` — one call per artifact; inputs: plan, shared skeleton, contrasts to embody, bible, demo context, style exemplar (when present); outputs content blocks by genre; instructions require internally consistent figures and invented contact data.
- [ ] **T049** Wire step 8 into `GenerateVideoScriptHandler`; persist `course_practice_versions`; `BibleRegistry::merge()` for new organisations in the same transaction.
- [ ] **T050** `ForcePracticeDocumentHandler` + route #22 (`{writer_provider, with_review}`) — single-video run of kind `force_practice`, script regenerated to use the pack. *(FR-38)*
- [ ] **T051** `PromptsSheetDocument` projection (prompts in order with section, demo label and practice file names). *(FR-35a/c)*
- [ ] **T052** `PracticePackTest` (against `CanonicalPracticePackFixture`: header, files, setup, usage DEMO 2 / Sección 4, instructor note, two artifacts, registry merge, not-warranted reason, forced) + `PromptsSheetTest`. **Run them.** *(SC-6, SC-7, SC-13)*
- [ ] **T053** Verify **US-6**, **US-7**.

## Phase G — Second review, opt-in per run (US-13)

- [ ] **T054** `ScriptReviewerPort` (`reviewScript`, `reviewPractice`) + `ReviewScriptAgent` + `ReviewPracticeAgent` (scores per plan §3.5 step 10, objections targeted at `section N` or `artifact file`, reference exemplars embedded as constants/style exemplars) + `LaravelAiScriptReviewerAdapter` (reviewer provider from run) + `FakeScriptReviewer` (scripted verdict sequences, call counter).
- [ ] **T055** `ReviewDraftHandler` — loop: review → targeted rewrite of failing sections/artifacts only → gate B → re-review; `≤ max_review_iterations`; keeps best draft; sets `reviewed`, `review_scores`, `review_objections`, `review_iterations`, `passed_review`; counts `ai_review_calls` separately. Wired as step 10, **executed only when the run's `with_review` is true**.
- [ ] **T056** `SecondReviewToggleTest`: `false` ⇒ 0 reviewer calls and `reviewed=false`; `true` ⇒ script and practice reviewed; failing section rewritten alone; exhaustion ⇒ `passed_review=false` with scores; same-provider ⇒ `reviewer_not_independent=true`; estimate differs by review calls. **Run it.** *(SC-14)*
- [ ] **T057** Verify **US-13**.

## Phase H — Runs, progress, ceilings (US-9, US-12)

- [ ] **T058** `EstimateRunRequest` (`with_review` boolean) + `EstimateRunCallsHandler` + route #13.
- [ ] **T059** `StartGenerationRunRequest/Data` (`with_review` `boolean`, default from config) + `StartGenerationRunHandler` (plan §3.4) + route #14; audit includes `with_review`.
- [ ] **T060** `GenerateVideoScriptJob` — `SkipIfBatchCancelled`, `#[Tries(1)]`, timeout from config; catches all; outcome + counters (write/review/research) + `current_video_id`; ceilings ⇒ cancel + `stopped_at_ceiling`; video and course status transitions.
- [ ] **T061** Run status (route #15), cancel (#16), retry-failed (#17, inherits `with_review`).
- [ ] **T062** `GenerationRunLifecycleTest`, `GenerationRunFailureTest`, `GenerationRunCancelTest`, `GenerationCallCeilingTest` (review calls count toward the AI ceiling). **Run them.** *(SC-8, SC-9)*
- [ ] **T063** Verify **US-9**, **US-12**.

## Phase I — Rendering and ZIP (US-8)

- [ ] **T064** `RenderVocabulary` (es/en: script headings + `DOCUMENTO DE PRÁCTICA`, `Archivos`, `Usar en`, `NOTA PARA EL INSTRUCTOR`) + `ScriptDocument`, `PracticeDocument`, `PracticeFileDocument` view models + `ContentBlockMarkdown`.
- [ ] **T065** `[P]` Markdown renderers: script, prompts sheet, practice document (sample layout), practice file.
- [ ] **T066** `[P]` Blade: `course-script`, `course-prompts`, `course-practice` (header page + artifact per page break), `course-practice-file`, `partials/course-content-blocks` — `@extends('exports.pdf.layout')`, no `{!! !!}` · `PdfDocumentRenderer`.
- [ ] **T067** `BuildDeliverablesHandler` — script, prompts, practice, **each practice file**, md + pdf; upsert deliverables.
- [ ] **T068** `CourseBundleBuilder` (D17 + `archivos/`, README incl. reviewed/passed and "datos ficticios" notice) + routes #23–#25.
- [ ] **T069** `DeliverableRenderTest` + `CourseBundleTest`. **Run them.**
- [ ] **T070** Verify **US-8**.

## Phase J — Queries and page props

- [ ] **T071** `ListCoursesHandler`, `GetCourseHandler`, `GetScriptVersionHandler` (accepted or `?version=`), `CourseController::index/show`, route #18 — no N+1.
- [ ] **T072** `DeleteCourseHandler` + route #26.
- [ ] **T073** `CourseScriptsPagesTest`. **Run it.**

## Phase K — Security

- [ ] **T074** Rate limiters upload/generate/status/download/export from config.
- [ ] **T075** Sanitized exception rendering.
- [ ] **T076** Audit events (metadata only).
- [ ] **T077** `CourseScriptsAccessTest` — every core route 401/403/404-foreign. **Run it.** *(SC-10)*
- [ ] **T078** `PromptInjectionDefenceTest` + `BladeEscapingTest`. **Run them.**
- [ ] **T079** Verify **US-11**.

## Phase L — Types handoff

- [ ] **T080** `php artisan typescript:transform`; verify generated types; regenerate Wayfinder.

## Phase M — Frontend (US-10)

> Read `FRONTEND/SKILL.md` + `ARCHITECTURE-VUE/SKILL.md`; follow the `posts` module pattern; tokens only; `<script setup lang="ts">`; zero `any`; Vue 3.5 APIs; permissions not roles.

- [ ] **T081** `types.ts`, `helpers/coursePresentation.ts`, Zod schemas (upload, brief, bible with organisations/characters, run launcher with `with_review`, regenerate).
- [ ] **T082** Composables: `useCourses`, `useCourseMutations`, `useGenerationRun` (estimate reacts to scope + `with_review`; polled status with terminal stop and ceiling), `useScriptPreview`.
- [ ] **T083** `Index.vue`.
- [ ] **T084** `Create.vue` + `CourseUploadForm` + `ContentFileList`.
- [ ] **T085** `Show.vue` — Estructura, Biblia (organisations + characters editor), Notas y contenido, **Generación** (`RunLauncher` with provider, scope, **"Segunda revisión" ToggleSwitch** showing the added review calls, same-provider warning; `RunProgress`), **Entregables** (`ScriptPreview`, `PromptsSheetPreview`, `PracticePackPreview` with instructor note, contrasts table and artifacts, `ReviewScores` when reviewed, `ResearchSourcesList`, `DeliverableDownloads` incl. practice files and ZIP).
- [ ] **T086** Sidebar entry + permission-gated actions.
- [ ] **T087** `npm run types:check`, `lint:check`, `format:check`, `build` — clean. Manual run with the author: reference index + notes → video 22 with `with_review=false`, then with `true`; download the ZIP.
- [ ] **T088** Verify **US-10**, **SC-11**, **SC-13**, **SC-14**.

## Phase N — Core closeout

- [ ] **T089** `php artisan db:show` (eleven tables) + module suite green, counts quoted.
- [ ] **T090** Full suite green.
- [ ] **T091** Traceability review for core FRs/US/SCs.
- [ ] **T092** Finalization pipeline (rules.md): `optimize:clear` → `ide-helper:generate` → `ide-helper:models --write` → `typescript:transform` → `pint` → `pint --test` → `scramble:clear` → `scramble:export` → `scramble:cache`.
- [ ] **T093** `index_repository(mode: "full")` + `index_status` verification.

---

# EXTENDED

## Phase O — Versions, feedback, staleness (US-14)

- [ ] **T094** `RegenerateScriptRequest/Data/Handler` (`feedback_note` delimited as untrusted, `writer_provider`, `with_review`) — single-video run of kind `regeneration`; new unaccepted version unless `versions.auto_accept_regenerations`. Route #20.
- [ ] **T095** `ListScriptVersionsHandler` (route #19) + `AcceptScriptVersionHandler` (route #21: flip acceptance, rebuild deliverables, expose new `taught_summary`, audit).
- [ ] **T096** `[P]` `StaleContinuityMarker` + test; invoked on acceptance changes; flag only. **Run it.** *(FR-34)*
- [ ] **T097** `ScriptVersioningTest` (regenerate with/without review, list, accept rebuilds, staleness flags without cascade). **Run it.**
- [ ] **T098** Frontend: `useScriptVersions`, `ScriptVersionList` (accept, stale badge), `RegenerateDialog` (feedback + second-review toggle). Types/lint/build clean.
- [ ] **T099** Verify **US-14**.

## Phase P — Style references (US-15)

- [ ] **T100** `AttachStyleReferenceRequest/Handler` + route #10 (scripts or practice packs, md/pdf).
- [ ] **T101** `[P]` `StyleExemplarSelector` + test (script exemplar, practice exemplar when a pack is planned, budget). **Run it.**
- [ ] **T102** Pass exemplars to bible proposal, outline, artifact and review agents (delimited). `StyleReferenceTest`. **Run it.**
- [ ] **T103** Frontend `StyleReferenceList` in "Notas y contenido". Verify **US-15**.

## Phase Q — Firecrawl escalation (FR-13c)

- [x] **T104** `config/services.php` `firecrawl` block; `FirecrawlClientInterface` + `FirecrawlScrapeAdapter` (breaker, fail-soft, `maxAge`) bound in `SharedServiceProvider`; `FirecrawlScrapeAdapterTest`. **Run it.**
- [ ] **T105** `ResearchEscalator` (snippet → Tavily raw → Firecrawl, `max_firecrawl_per_video`, `firecrawl_enabled`) + unit test proving no Firecrawl call when the snippet suffices; `full_page_fetched` persisted; research counters. **Run it.**
- [ ] **T106** Verify **US-4** extended criterion.

## Phase R — Listing export (US-16)

- [ ] **T107** `CourseExportTransformer`, `ExportCoursesRequest`, `CourseExportController` (CSV/XLSX/PDF) reusing `CourseFilterData`, `course-scripts.blade.php`, route #3 before `/{uuid}`.
- [ ] **T108** `CourseScriptsExportTest` (formats, owner-only). **Run it.** `ExportButton` on Index. Verify **US-16**.

## Phase S — Extended security pass

- [ ] **T109** Extend `CourseScriptsAccessTest` to routes #3, #10, #19–#21; extend `PromptInjectionDefenceTest` to feedback notes and style references. **Run them.**

---

# Phase T — Final closeout

- [ ] **T110** Module suite + full suite green, counts quoted; `db:show`.
- [ ] **T111** Traceability review against plan §10 for **every** FR, US and SC.
- [ ] **T112** Finalization pipeline (as T092); re-run T110 if model docblocks changed.
- [ ] **T113** Delete unused skeleton folders (e.g. `Persistence/Repositories/`), then `index_repository(mode: "full")` + `index_status`.
- [ ] **T114** `specs/002-course-scripts/SSD-SUMMARY.md`.

---

## Dependency notes

- **A → B → C → D → E → F → G** sequential in their consuming tasks (validators marked `[P]` can be built early).
- **H** needs E–G. **I** needs E–F (parallel with G/H). **J** needs B–I. **K** touches all. **L** before **M**. **N** closes the core.
- **O, P, Q, R** are independent of each other after N. **S** after O–R. **T** last.

## Parallelization summary

| Wave | Tasks |
| --- | --- |
| 1 | T005a, T005b |
| 2 | T007, T008, T010 |
| 3 | T023, T024, T027a |
| 4 | T030–T037, T044–T047 (pure Domain) |
| 5 | T065, T066 |
| 6 | Phase I alongside G/H |
| 7 | Phases O, P, Q, R |
