# Analyze: CV ATS Job Studio (`004-cv-ats-job-studio`)

- **Date:** 2026-09-18 (evening)
- **Phase:** 6 — Analyze (cross-check spec ↔ plan ↔ tasks before any implementation)
- **Inputs:** `spec.md` rev 3 · `clarify.md` Q1–Q20 + R-1…R-16 · `research.md` §1–§17 ·
  `plan.md` §1–§13 · `tasks.md` rev 2
- **Verdict:** **coverage clean; implementation NOT yet allowed.** Six gaps and two overclaims
  were found and fixed in this pass. Q19 (option b) and Q20 (local PG17 + pgvector) were
  decided afterwards. Three items still block Phase 7: the local PostgreSQL set-up (user
  action), V-5 (reading the portals' terms) and T-002 (extension probe).

## 1. Method

1. **Mechanical traceability check.** A throwaway Node script (session scratchpad, not in the
   repository) read `spec.md`, `plan.md` and `tasks.md`. It extracted every `FR-n`, `NFR-n`
   and `SC-n` reference, expanding ranges such as `FR-9…FR-15`. It then reported (a) any ID
   missing from any of the three documents and (b) any task entry with no `Traces:` line.
2. **Manual contradiction review.** Every decision in `clarify.md` was compared with the plan
   section that implements it. Every "supersedes" marker was followed to confirm the newer
   section actually replaces the older one.
3. **Re-examination of `research.md` §10**, as requested ("analiza 10"). Its claims were
   re-checked against the statistics computed in §17.2.

## 2. Coverage result (mechanical, after fixes)

| Check | spec | plan | tasks |
|---|---|---|---|
| FR-1 … FR-52 referenced | ✅ none missing | ✅ none missing | ✅ none missing |
| NFR-1 … NFR-15 referenced | ✅ | ✅ | ✅ |
| SC-1 … SC-17 referenced | ✅ | ✅ | ✅ |
| Task entries without a `Traces:` line | — | — | ✅ none (21 before the fixes in §3) |

A reference is necessary but not sufficient. The real proof is T-102: every requirement must
map to a passing test once the code exists.

## 3. Gaps found and fixed in this pass

| # | Gap | Where it showed | Fix applied |
|---|---|---|---|
| GAP-A1 | FR-1 / plan §3.2 name `CvSourcePort` + `EloquentCvSource` and `ProjectSourcePort` + `EloquentPortfolioProjectSource`, but **no task built them** (T-008 was only a read-contract spike). | FR-1, FR-4 | `tasks.md` **T-068 extended** with both read seams (read-only on `Modules\Cvs` and `Modules\Portfolios`). |
| GAP-A2 | FR-31 (block a run with a missing gate input) was traced only to a migration task (T-018). **No behaviour test.** | FR-31 | **T-043 extended**: `StartRunHandler` refuses with `ProfileGateIncompleteException` naming the input; test included. |
| GAP-A3 | NFR-8 / US-8 require that named people in postings are not retained unless they are the application channel. Plan §3.4 declared a scrubber "a smell". **Posting text and search snippets can incidentally contain a recruiter's name, email or phone, and no task handled it.** | NFR-8 vs plan §3.4 | **New T-149** (minimal minimiser: contact email/phone kept only as the application channel, named-contact lines removed). **Plan §3.4 amended in §13**: the B2B privacy machinery stays absent; the minimiser pattern is present. |
| GAP-A4 | Revisions 2–3 added user-facing actions (unlink resolution, relations inbox, policy edit, neutral mode, own rates) **without routes in plan §5**. | plan §5 | **Plan §13 "Routes added"** table; T-135 implements it. |
| GAP-A5 | SC-7 (a second profile needs no schema change and inherits nothing) **lost its test** when Q11 cut the seeder to fullstack only. | SC-7 | **T-018 extended** with a fixture second profile in the test (not seeded). |
| GAP-A6 | 21 task entries broke the file's own rule "a task with no trace is scope creep". | tasks.md | Traces added to T-086, T-089, T-091, T-093, T-095, T-096, T-102…T-106 and T-136…T-145. |

## 4. Contradictions between documents, and how each is resolved

Each of these is an older statement that a newer, explicitly flagged section replaces. The
older text is **kept as history** so the change stays auditable. The table exists so nobody
implements the older version by mistake.

| # | Older statement | Replaced by | Status |
|---|---|---|---|
| C-A | Plan §4.3 / clarify Q8 / old T-050: `halfvec(1536)` via raw SQL | Plan CHG-7 + CHG-19: native `vector`, verified by measurement (research §17.3); T-050 withdrawn → T-121 | ✅ resolved |
| C-B | Plan CHG-9: channel priors 6.87 % for both `employer_site` and `employer_ats` | Plan CHG-16: the source separates career pages (6.87 %) from ATS-hosted (3.16 %), and the figures are an ordering only (research §17.1) | ✅ resolved — **was a real error** |
| C-C | Plan CHG-9: Beta-Binomial with `m = 30` "learned from outcomes" | CHG-16: evidence gate (≥ 30 applications, ≥ 3 positives), `N0 = 100`, policy values — `m = 30` let one outcome halve a factor (research §17.2) | ✅ resolved — **was a real error** |
| C-D | Clarify Q13: Senior handled with "`q_level ≈ 0.6` prior" | Q13's *decision* stands; the *number* is withdrawn pending Q19 | ⏳ open → Q19 |
| C-E | Plan CHG-9: read-time SQL `ORDER BY … power(2, …)` | CHG-17: materialised `apply_priority`, lazy refresh, portable ordering (tests run on SQLite) | ✅ resolved |
| C-F | Plan CHG-10: boolean `fetch_policy` | CHG-18: `access_mode` enum with five modes, unchecked portals → `link_only` | ✅ resolved |
| C-G | Plan §4 "softDeletes on user-facing aggregates" | CHG-15: `softDeletes` on every table (`BACKEND-PHP` §4 rule) | ✅ resolved |
| C-H | Plan §7 / old T-018: seeder for four profiles | CHG-6 / Q11: fullstack only; SC-7 kept via a fixture profile (GAP-A5) | ✅ resolved |
| C-I | Spec FR-36 "never store credentials for a third-party site" vs ITJobs/InfoJobs API keys | FR-36 reworded: user-session credentials banned; provider-issued API keys allowed as configuration | ✅ resolved |
| C-J | Plan §3.4 "PersonalDataScrubber presence is a smell" vs NFR-8 | Amended in plan §13 (GAP-A3) | ✅ resolved |
| C-K | Research §9 / old T-084 / old T-092: "13 applications, only Cognition replied" | Research §10 (measured from the cache): **16 applications, 1 screening (Coalition Technologies), 1 rejection, 14 silent**; T-084 and T-092 corrected | ✅ resolved — §9 kept as the user's earlier report |
| C-L | Research §12 "direct 6.87 % vs LinkedIn 1.95 %" presented as a channel effect | Research §17.1: self-selected, 78 % LinkedIn, metric differs, magnitudes unstable across cuts. Ordering only | ✅ resolved |

## 5. Research §10 re-examined ("analiza 10")

§10 was the empirical audit of the local pipeline's own 16 applications. Re-checked against
§17.2:

| §10 claim | Holds? | Why |
|---|---|---|
| 33 cached postings, 16 applied, outcomes 1 screening / 1 rejected / 14 silent | ✅ | Measured directly from the cache |
| Top six scores silent; the one positive came from the lowest score (70) | ✅ as a fact | Descriptive, verifiable per row |
| **"The fit score is not the bottleneck"** | ❌ **overclaim — corrected (A-10)** | With one positive in 16, the data cannot show that fit orders outcomes, nor that it doesn't. Fisher p = 1.00 / 0.31 / 0.08. §10, §13's closing line and spec motivation #5 were reworded. |
| LinkedIn + aggregator 0/11 replies vs board + direct 2/5 | ✅ as a count, ⚠️ not significant | p = 0.083 for any reply, 0.312 for positives |
| Senior 0/7 vs non-senior 1/9 | ✅ as a count, ❌ as evidence | p = 1.000 |
| "1/16 ≈ market baseline" | ⚠️ softened | It is inside the published range, but those baselines use a different population and metric (§17.1) |
| Missing `posted_at` makes freshness unmeasurable | ✅ **the strongest finding in §10** | Every dated application was sent the day it was found (0 days in 14 of 14), so the unknown is how old the posting already was. FR-40 / T-109 exist because of this. |

**What §10 supports:** building the *measurement* (posted date, sightings, apply destination,
outcomes) and an Opportunity layer that is **policy, not inference**. It does not support
applying any learned effect today. About 750 applications per group would be needed to detect a
3 % → 6 % difference, which is why CHG-16's evidence gate exists.

## 6. Checks that passed with no change

- Every `clarify.md` decision Q1–Q18 has an implementing plan section. Q19 and Q20 are open
  by design.
- The LinkedIn stance (Q2) is consistent across spec (FR-36, FR-50), plan (CHG-11, CHG-18),
  research (§2, §15.2, §17.5), clarify (R-16) and tasks (T-045, T-117, T-101).
- Rules version 2 (CHG-8) stays monotone with v1 (κ = π = 1 reproduces v1; test in T-059).
- No task introduces a composer package. NFR-10 holds. The PostgreSQL *test* database (Q20)
  is test infrastructure, not a runtime service.
- Fit/opportunity separation (NFR-13) is enforced by two tests (T-131 SC-13, T-132 NFR-13)
  plus an architecture test (T-101).
- Every portal in the user's question has an access mode or is `link_only` by default (FR-52,
  T-010).

## 7. What still blocks Phase 7 (implementation)

| # | Item | Type | Owner | Unblocks |
|---|---|---|---|---|
| B-1 | ~~**Q19** — opportunity policy defaults~~ | Decision | User | ✅ **Resolved 2026-09-18: option (b)** |
| B-2 | **Q20** — decided: local PG17 + pgvector (`pgsql_testing`). **Still open: the set-up itself** (install/start the service, create an empty test DB, confirm `vector` is available) | User action | User | **T-000**, T-002, T-052, T-112, T-121 |
| B-3 | ~~**V-5** — portal terms~~ | Documentation | Claude | ✅ **Done 2026-09-18** (`research.md` §19): T-113/T-114 unblocked; T-115/T-116 not built by default |
| B-4 | **T-002** — probe `pg_trgm` + `unaccent` grants (rolled back, like V-2) | Probe | Claude, needs a go-ahead to touch the dev DB again | T-017 |

Once B-1…B-4 are closed, the first file of module code is **T-000**. It is the test that
permanently re-proves the vector-index behaviour measured in research §17.3.

## 8. Revision 4 addendum — prompt caching and failover (2026-09-18 night)

Re-ran the mechanical check after plan CHG-20, spec NFR-16/NFR-17/SC-18/SC-19, and tasks
T-150…T-156: **FR-1…52, NFR-1…17 and SC-1…19 are referenced in spec, plan and tasks; no task
lacks a trace.**

| # | Check | Result |
|---|---|---|
| GAP-B1 | `match_narrative` was in CHG-20's purpose table, but **no task built its agent** (US-4 needs the "why" narrative) | **Fixed:** new T-156 `NarrateMatchAgent`, narrative from the stored breakdown only, with a test that no number diverges |
| GAP-B2 | Tailoring and translation agents were listed as cache-flagged, but T-077/T-078 did not say so | **Fixed:** both extended to use `AiCallExecutor` + cached layers |
| C-M | Shared `ProviderFailover` docblock: "evaluation judges stay outside failover" vs CHG-20 giving `cv_judge` failover | **No conflict.** That rule protects *an LLM grading another LLM's output* (writer ≠ judge). `cv_judge` grades the candidate's CV, not model output; this module's fabrication check (FR-8) is deterministic provenance, not an LLM judge. Recorded so an auditor does not flag it. |
| C-N | NFR-1 (same inputs → same score) vs failover (a different provider may extract differently) | **Consistent** through provenance (`extracted_by_*`), rescoring from stored rows, and no re-extraction without an explicit action (RK-19) |
| C-O | NFR-2 (LLM never sets a number) vs the narrative degrade path | **Consistent** — the narrative has no numeric fields; failure removes text, never numbers (T-156, T-152 test 3) |
| C-P | Caching economics are grade C (secondary sources) | **Not applied as fact.** No price or TTL is hard-coded; T-154 fills the pricing table from live pricing pages and decides TTL by measurement (SC-18) |
| Reuse | Is new Shared code needed? | **No.** `PromptCachingAIClient`, `UsesPromptCache`, `CacheablePrompt`, circuit breaker and the CourseScripts loop cover it. A per-agent TTL hook is *conditional* on T-154 and would be flagged then. |

The blockers in §7 are unchanged; Phase H0 adds no new blocker.

## 8b. Revision 5 addendum — V-5 and sources as signals (2026-09-18 night)

Mechanical check after spec FR-53/FR-54/SC-20/A-18, plan CHG-21 and tasks T-157…T-160:
**FR-1…54, NFR-1…17 and SC-1…20 are referenced in spec, plan and tasks; no task lacks a trace**
(T-118 grew past the checker's 12-line window; the window was widened to 25 lines, and the
trace was there).

| # | Check | Result |
|---|---|---|
| C-Q | Plan CHG-18 seeded **InfoJobs as `api_feed`** (conditional on V-5) | **Reversed by V-5:** its API terms require a Partners agreement for offer aggregators → `link_only`; T-115 not built (Q22) |
| C-R | Plan CHG-18 listed **JustJoin.it as `sitemap`** (conditional) | **Reversed by V-5:** user terms not readable by permitted means → `link_only` by FR-52's default; T-116 not built |
| C-S | Spec §8 bans circumventing anti-bot measures, but **no task enforced it for the extraction provider** — and V-5 showed Firecrawl's automatic mode *does* pick a stealth proxy | **Gap closed:** T-159 + SC-20 (basic proxy only; a refusal means "not fetchable") |
| C-T | CHG-11 resolved signals only by probing ATS boards by slug; the user's flow adds a **web search for the employer's own posting** and a **permitted fetch of the employer's page** | **Extended:** CHG-21 four-tier flow; T-118 rewritten; a test replays the user's LinkedIn example with zero requests to linkedin.com |
| C-U | US-10 did not say what happens to an unresolved signal, beyond keeping its channel | **Specified:** `status = reference`, unscored, "Open manually" list, optional paste (FR-53, T-158, T-138) |
| C-V | Three sources restrict use to personal / non-commercial, while `PIPELINE-A-B-COMPARATION.md` discusses a white-label product | **Recorded as a constraint (A-18)** with a per-source `usage_restriction`; no conflict for the current personal scope |
| Priority | The user's explicit order "API/ATS/feed → permitted portal → web search → permitted scraping" | Encoded as `resolution_tier` (T-160) and asserted: a lower tier is never used when a higher tier returned the posting |

Blockers now: **B-2** (local PG17 + pgvector set-up — user) and **B-4** (T-002 probe — needs
your go-ahead). Optional decision **Q21** (RemoteRocketship subscription) does not block.

## 9. Files touched by this analysis

`tasks.md` (T-018, T-043, T-068 extended; T-149 added; traces added) · `plan.md` (§13: §3.4
amendment, routes table) · `research.md` (§10 and §13 wording corrected) · `spec.md`
(motivation #5 reworded). No source code, no migrations, no configuration changed.

## 10. Implementation record — Slice 1 + full backend (2026-09-18/19, T-106)

Built in dependency order against this analysis: Phase 0 gate (T-002 probe —
vector 0.8.2 / pg_trgm 1.6 / unaccent 1.1, grants OK, rolled back), 31 tables
across 28 migrations (all `json`, never `jsonb` — T-112/CHG-17), fullstack
profile + skill-relation + source seeders, pure rules-v2 Domain services,
ports + Eloquent repositories (posting, score, profile), queued 9-stage
pipeline, AI seam (AiPurpose × 7, AiCallExecutor, 6 agents), 7 discovery
sources + fetch ladder, opportunity layer, CV lifecycle, PhpWord/DomPDF
exports with extraction verification, applications + insight reports, and
round-2/3 routes (relations, resolve, references+paste, policy).

Deliberate deviations from `plan.md` (auditor note):

- D-1 `WordExportPort` (new, Shared) instead of extending the existing
  `ExportPort` with DOCX: ISP — the CSV/XLSX/PDF adapters must not gain a
  surface they never implement. `PhpWordExportAdapter` honours T-074's path.
- D-2 `TransactionPort` (Domain-owned, thin `DB::transaction()` runner) for
  cross-aggregate demarcation: the Cvs port contract forbids the `DB` facade
  in handlers, and stuffing every aggregate into the two planned repositories
  would violate SRP/ISP. Secondary-aggregate reads stay handler-direct per
  the SIMPLE-CRUD optionality rule; the T-101 arch test pins the boundary.
- D-3 `StudioProfileRepositoryPort` (third repo): profiles own rules
  versioning, gate validation and run bootstrapping — real logic, not CRUD.
- D-4 Shared `FirecrawlClientInterface::scrape()` gained an optional
  `?string $proxy` param (default null = previous behaviour) so T-159's
  basic-only rule is enforceable per call without changing LeadScout.
- D-5 `studio_postings.status` also carries `reference` (FR-53) alongside the
  plan's lifecycle values; `UpdatePostingStatusData` allows it.

Still open after this build: B-2 (local PG17+pgvector set-up — user action;
T-000/T-052-pgsql/T-112-pgsql fail loudly until then, excluded from the
default suite via `--exclude-group=pgsql`), Q21 (RemoteRocketship
subscription — T-157 skipped, source stays `resolve_only`), measurement tasks
(T-031/T-148/T-154 — need live runs; harnesses built, see §12).

## 11. Implementation record — partials, Phase P, Phase M (2026-09-19, T-106)

Closed partials: TailorCvHandler + TranslateCvHandler (cached layers through
`AiCallExecutor`, scope set/cleared per call), ExtractRequirementsHandler
(skip-if-hashed, enum-validated), StoreEmbeddingHandler (write-once per
content hash), EvidenceRetriever (+ pending-only proposals), deterministic
prompt layers (`CvSnapshotLayer` + byte-identical test + ordering rejection),
SlugCandidateGenerator, RefreshVocabularyHandler, ExpirePostingsHandler
(`expiry_days` config), GetOwnRatesHandler + `/budgets/own-rates`,
recompute-from-stored (`RescorePostingHandler::recomputeFromStored`, SC-2
test green), real `HarvestStructuredSourcesJob` (registry walk, pre-flight
spend guard, sightings, counters), per-task state above. T-001: direct
Tavily `/search` → 200, `/research` → 422 (recorded in research §8). T-006
field-contract table recorded as research §21.

Phase P (T-136…T-146): `cv-studio` frontend module (types aliased over
generated DTOs, 7 Pinia Colada composables, 3 badges/chips, query-param
helpers) + 9 pages — Dashboard, Postings Index (lazy table, filters, bulk,
export, references tab + paste dialog, run trigger), Postings Show (match
report, gates, H/S/D, caps, resolve/unlink, dismiss, rescore), Runs Show
(polled stages, funnel, spend), Insights Show (frequency, reach, suppressed
correlation, own rates), Applications Board (status/outcome kanban with
aged-silence badge), CV Audit (verdict + metric answers), CV Structure
(parse + confirm), Versions Index (DOCX/PDF downloads), Settings Profile
(gates, policy editor with grade/source, neutral toggle, relations inbox,
profile creator) — plus the Talent-group nav entry. Backend gained the
versions + applications lists and the dashboard route to serve them.
Frontend gates: tsc clean for all new files, eslint clean; the repo-wide
`vue-tsc` `.form` errors in auth/settings pre-date this build (Wayfinder
artifact drift, untouched).

Phase M (T-092…T-096, T-147): the cache holds URLs + judgements, not posting
objects, so fixtures reconstruct the stated note cases (documented in the
test file, not independent validation) — SC-3 agreement green, v1→v2
monotone green with 100-stays-100, G2 stack-lock green, T-147 descriptive
green with no predictive claim, T-096 four-URL merge green (same-source
collision fixed by dropping the duplicate row). Harnesses built for the
live-only remainder: precision/recall/F1, yield-per-euro per cell,
vocab-trigger evaluation (all unit-tested); live measurement still needs
runs + spend (T-095/T-148/T-154), as does S calibration (provisional
0.30/0.85) and the executor fallback path (response object not fakeable).

## 12. Implementation record — adapters + T-004/T-005 (2026-09-19)

- Adapters: Recruitee, Workable, Teamtailor sitemap walker, RemoteOK,
  Remotive, Himalayas, Jobicy, Adzuna — all fail-soft with `Http::fake`
  tests; WWR stays on the generic RSS adapter by URL. Resolver now holds
  15 adapters; seeder registry extended (SmartRecruiters registered with
  no adapter per T-037).
- T-004/T-005 resolved by live research (recorded as research §22):
  SmartRecruiters has no free public API (covered via Arbeitnow);
  Teamtailor needs per-tenant keys, so discovery walks the public sitemap.
- Found and fixed everywhere: Laravel's HTTP client throws on 4xx when
  `retry()` is configured (proven by probe) — all 14 fetchers now
  try/catch to `[]`, so a dead source degrades instead of killing the run
  (NFR-6).
