# Analysis: Course Scripts

> Phase 6 · ANALYZE — Cross-check of `spec.md` ↔ `clarify.md` ↔ `research.md` ↔ `plan.md` ↔ `tasks.md`.

**Feature ID:** 002-course-scripts
**Date:** 2026-09-12
**Revision:** 3 — after the practice-pack sample (`Propuestas_Logistica_Heliantia`) and the `with_review` / Phase P decision.
**Result:** **7 revision-2 assumptions invalidated by the sample or the author's answer, all corrected.** **5 residual issues found while cross-checking revision 3, all fixed in place.** No open contradictions. Implementation continues at **T005a**.

---

## 1. Method

1. **Sample pass** — every part of `Propuestas_Logistica_Heliantia` (research §12) against the practice requirements.
2. **Toggle pass** — `with_review` traced through request → estimate → run → job → version → UI → audit → tests.
3. **Forward pass** — FR/US/SC → plan → task.
4. **Backward pass** — tasks → plan → FR.
5. **Consistency pass** — decisions DEC-0…DEC-11, D1…D21, project rules, and code already built (T001–T017, 26 tests green).

---

## 2. Revision 2 assumptions corrected by revision 3

| # | Revision 2 assumption | What the evidence showed | Correction |
| --- | --- | --- | --- |
| R2-A1 | Practice document = "Bloque A/B" paste snippets (D12, inferred) | The sample is a practice **pack**: header, files, setup instruction, usage (DEMO 2 / Sección 4), instructor note, full simulated documents | DEC-9 · FR-36…FR-36e · T039, T044–T052 |
| R2-A2 | Script references practice material by block label | The sample references **file names + demo + section** | FR-29a, FR-36b · `DemoLabelValidator`, `PracticeReferenceValidator` |
| R2-A3 | One fictional organisation per course | `Heliantia Group` (video 22) ≠ `Tecnoform S.A.` (videos 38–46); suppliers invented per artifact | D18 · FR-9, FR-13k · `BibleRegistry` (T027a) |
| R2-A4 | Practice material is generic filler content | The instructor note states material is **designed** with deliberate contrasts to make the demo result non-trivial | FR-36a/36c · designed contrasts + `InstructorNoteCoverageValidator` |
| R2-A5 | One practice call per video | Two artifacts ≈ 8,000 chars; 3–4 would crowd one structured call | R12.5 · one call per artifact · estimator `practice_ratio × avg_artifacts` |
| R2-A6 | Practice pack is one file | The sample instructs uploading each proposal as a **separate file** | FR-36e · `practice_file` deliverables · ZIP `archivos/` |
| R2-A7 | AI reviewer post-MVP, always on once built | Author: Phase P wanted, review **opt-in per run via boolean** | DEC-10/11 · FR-14a, FR-40a · Phase G · SC-14 |

Superseded and marked as such: D12 (by DEC-9), DEC-8/D11 (by DEC-11), revision 2's `course_practice_versions.blocks` column (by the pack columns).

---

## 3. `with_review` trace

| Layer | Where | Task |
| --- | --- | --- |
| Spec | FR-14a, FR-20, FR-40, FR-40a, FR-41, US-9, US-13, SC-14 | — |
| Config | `review.default_with_review`, `runs.max_review_iterations`, `runs.expected_rewrite_rounds`, `review.pass_threshold` | T005a |
| Request | `StartGenerationRunRequest`, `EstimateRunRequest`, `RegenerateScriptRequest`, `ForcePracticeRequest` — `boolean` | T058, T059, T094, T050 |
| Estimate | `CallEstimator` splits write/review; review = 0 when false | T036 |
| Run row | `with_review`, `reviewer_provider`, `reviewer_not_independent`, `ai_review_calls_consumed` | T006, T059 |
| Job | step 10 executes iff true; review calls count toward the AI ceiling | T055, T060, T062 |
| Retry | inherits the run's value | T061 |
| Version | `reviewed`, scores, objections, iterations, `passed_review` | T006, T055 |
| Bundle | README `reviewed` / `passed review` columns | T068 |
| UI | `RunLauncher` toggle with estimate difference and same-provider warning; `ReviewScores`; `RegenerateDialog` toggle | T085, T098 |
| Audit | `run_started {with_review}` | T059, T076 |
| Tests | `SecondReviewToggleTest`, `CallEstimatorTest`, `GenerationCallCeilingTest` | T036, T056, T062 |

Complete — no layer reads or writes the flag without a task and a test.

---

## 4. Forward pass

| Requirement | Plan | Tasks | Verdict |
| --- | --- | --- | --- |
| FR-1…FR-8a | §3.2 | T011–T021 | ✅ |
| FR-9, FR-11…FR-13, FR-13k | §3.3 | T027, T027a, T028, T049 | ✅ |
| FR-10 | §3.9 | T100–T103 | ✅ |
| FR-13a/b/d/e/f/g/h/i/j | §3.3, §3.5 | T022–T026, T041 | ✅ |
| FR-13c | §3.5 step 3 | T104–T106 | ✅ |
| FR-14, FR-14a, FR-15…FR-25 | §3.4, §3.6 | T036, T058–T063 | ✅ |
| FR-26…FR-33b | §3.5 | T030–T043 | ✅ |
| FR-29a | §3.5 step 5 | T032 | ✅ |
| FR-34 | §3.8 | T096 | ✅ |
| FR-35a…c | §3.7 | T051, T065 | ✅ |
| FR-35, FR-36…FR-39b | §3.5 steps 4, 5, 8, 9 | T039, T044–T052 | ✅ |
| FR-40…FR-44, FR-40a | §3.5 step 10 | T054–T057 | ✅ |
| FR-44a | §3.5 gates | T030–T033, T044–T047 | ✅ |
| FR-45…FR-48a, FR-51 | §3.7 | T064–T070 | ✅ |
| FR-49, FR-50 | §3.8 | T094, T095 | ✅ |
| FR-52a/b | §6 | T081–T088 | ✅ |
| FR-52…FR-58 | §8 | T010a, T074–T079, T109 | ✅ |
| US-16 | §3.10 | T107, T108 | ✅ |
| SC-1…SC-14 | §7 | T013, T017b, T042, T052, T056, T062, T077, T087 | ✅ |

---

## 5. Residual issues found in revision 3 — fixed

### ISSUE-1 — Second review placed after the UI
The first draft of `tasks.md` kept the reviewer in the extended phases while `with_review` was already a core request field — a core build would have accepted `true` and silently ignored it.
**Fix:** reviewer moved to core **Phase G**, before runs (H) and UI (M); spec US-13 relabelled Core; plan §1 delivery order updated.

### ISSUE-2 — Nullable column inside a unique key
`course_deliverables UNIQUE(script_version, document_type, artifact_file_name, format)` would not deduplicate script/prompts/practice rows if `artifact_file_name` were `NULL` (NULLs are distinct in unique indexes).
**Fix:** plan §4 note and T006 — store `''` for non-file documents.

### ISSUE-3 — Regeneration acceptance contradicted auto-accept
Revision 2's MVP rule "newest passing version accepted" would make US-14's explicit acceptance meaningless and could overwrite a version the author chose.
**Fix:** plan §3.8 — first generation auto-accepts; regenerations create unaccepted versions unless `versions.auto_accept_regenerations` (default false). T094/T095.

### ISSUE-4 — Ceiling too low once review exists
~490 write + ~175 review calls exceed revision 2's 700 for the reference course.
**Fix:** `runs.max_ai_calls_per_run` 900 (T005a); review calls counted inside it (T062).

### ISSUE-5 — Realistic contact data risk
The sample's realistic emails/phones/CIFs, reproduced by a model, may match real people or companies.
**Fix:** FR-39b + D19 + `ContactDataValidator` (T047) + "datos ficticios" notice in the ZIP README (T068). Residual risk recorded as RK-11 — a denylist cannot prove a domain is unregistered.

---

## 6. Backward pass

Every task traces to a plan section and a requirement. Rule-mandated tasks without an FR: T005a/T005b (config/fixtures), T022 (Shared change regression), T080 (generated types), T092/T093/T112/T113 (finalization, re-index), T114 (summary). No task implements an out-of-scope item (§9: audio/images/translation/collaboration/URL import/OCR/in-app rich editing/Office formats).

---

## 7. Consistency pass

| Decision | Honoured |
| --- | --- |
| DEC-0…DEC-4 | ✅ unchanged |
| DEC-5 prompts sheet | ✅ now lists practice file names per demo |
| DEC-6 title + index + content | ✅ |
| DEC-7 web UI | ✅ Phase M + extended UI tasks |
| DEC-9 practice pack format | ✅ schema, agents, validators, renderers, fixture |
| DEC-10 `with_review` boolean | ✅ §3 trace |
| DEC-11 Phase P in scope | ✅ core G + extended O–R |
| D13–D17 | ✅ (D17 extended with `archivos/`) |
| D18–D21 | ✅ T027a, T047, T064–T066, T036 |
| Repository Rule | ✅ no repository ports; resolver without port |
| Spatie Data only | ✅ T038 |
| Generated TS types | ✅ T080 before UI |
| Shared Optionality Rule | ✅ only Tavily change and Firecrawl adapter (with a consumer) |

### Tensions recorded

- **Structured blocks vs. the sample's Markdown.** Output Markdown will be cleaner than the sample's lossy conversion (D20) — intentionally not byte-faithful.
- **PDF not visually inspected.** The sample PDF could not be rasterised in this environment; layout decisions follow the Markdown's order and the spec's structure-fidelity principle. If the PDF's visual style matters, the author can point out specifics and only the Blade templates change.
- **Review cost is explicit.** Default `with_review=false` keeps the one-click cost at ~10 AI calls per video; turning it on is visible in the estimate before confirming.

---

## 8. Implementation state

| Phase | State |
| --- | --- |
| A | T001–T005 ✅ · T005a/b pending |
| C | T011–T017 ✅ (26 tests, 90 assertions green) · T017a+ pending |
| B, D–N (core), O–S (extended), T | pending |

**Next: T005a → T005b → T006.**
