# Clarify: CV ATS Job Studio (`004-cv-ats-job-studio`)

- **Date:** 2026-09-18
- **Phase:** 2 — Clarify (run after Phase 3 research, which resolved several questions on evidence)
- **Rule:** no high-impact question may remain open when `plan.md` is written.

Each question states the **impact** if left unresolved. Questions resolved by research cite
the finding. Questions resolved by default state the default taken.

---

## High impact — block Phase 4

### Q1 — Is this module separate from the B2B outreach pipeline, or an extension of it?

The user asked this directly: *"tengo otro pipeline para b2b contacto agencias por lo que no
sé si este modulo es distinto"*.

**Evidence gathered.** `src/Modules/LeadScout` is a 190-file B2B outbound pipeline: it
discovers agencies, extracts decision-makers, detects contact channels, generates outreach
drafts, and carries GDPR machinery for processing people — `PersonalDataScrubber`,
`Article14Notice`, `SuppressionGate`, `ScoutPrivacyRequest`, suppression lists. Its domain
noun is a *company to contact*.

This module's domain noun is *a posting to apply to*. It stores employer data, not people
(`spec.md` NFR-8), which `research.md` §2 finding 4 places in a materially lighter legal
bucket. Direction of travel is inbound (I apply) not outbound (I contact).

**Recommendation: separate module, shared infrastructure.** `research.md` §4 shows ~60% of
the plumbing is already reusable as-is (ladder, guards, budget ledger, AI seam, circuit
breaker, Tavily/Firecrawl adapters). Merging them would drag the personal-data obligations
of a B2B outreach tool onto a job-application tool that does not need them, and would put
two unrelated aggregates in one module.

**Impact if unresolved:** determines whether Phase 4 plans a new `src/Modules/{Name}` or an
extension of `LeadScout` — i.e. the entire folder structure, service provider, route file
and test layout. Also determines whether the GDPR components are inherited or deliberately
absent.

**Status: RESOLVED 2026-09-18 — separate module, shared infrastructure.** New
`src/Modules/CvJobStudio`; `LeadScout` is untouched. Reuses the `FetchLadder` pattern,
`BudgetLedger`, the Tavily/Firecrawl adapters, the AI seam, `CircuitBreaker` and
`ExportPort`. The GDPR components of `LeadScout` are deliberately **not** inherited — NFR-8
means this module never processes people.

---

### Q2 — Which discovery sources are in scope for v1, and is the LinkedIn stance accepted?

**Evidence gathered.** `research.md` §2 establishes a defensible three-way split: official
APIs and feeds are sanctioned; a general search API returning a LinkedIn URL involves no
LinkedIn session; fetching LinkedIn pages ourselves is a User Agreement breach that held up
in court (`hiQ`, breach-of-contract limb) with ban exposure on top.

**Proposed stance:** discover via official APIs/feeds + Tavily search; **link** LinkedIn
postings for the human to open; never add LinkedIn to the extraction ladder; exclude all
login-gated access architecturally (`spec.md` FR-36, SC-9).

Candidate v1 source set, in provenance order:

| Source | Auth | Note |
|---|---|---|
| Arbeitnow (EU + new UK endpoint) | none | Already implemented in this codebase; inventory is ATS-direct (Greenhouse, SmartRecruiters, Join.com, TeamTailor, Recruitee, Comeet) |
| Greenhouse / Lever / Ashby board endpoints | none | Straight from the employer; needs a company-slug list, no cross-company discovery |
| WeWorkRemotely RSS | none | `RssFeedSource` already implemented |
| RemoteOK | none | Attribution + link-back required |
| Remotive | none | Attribution required, 24h delay, **no resubmission to third-party sites** |
| Himalayas / Jobicy | none | Salary, seniority, level metadata |
| Adzuna | key | ~1,000 calls/month free, 16 countries |
| Tavily search | key | URL + snippet discovery across everything else, incl. LinkedIn/Indeed |

**Impact if unresolved:** determines the source adapters built in Phase 5, the attribution
fields in the schema (FR-35), and whether a company-slug registry is needed for the ATS
endpoints. Getting this wrong after the fact means a schema migration plus a legal review.

**Status: RESOLVED 2026-09-18 — stance accepted, full source set in v1.** Official APIs and
feeds are primary; Tavily discovers URLs everywhere else including LinkedIn; LinkedIn and
Indeed pages are **linked, never fetched**. Login-gated access is excluded
architecturally. All eight sources in the table above are in v1 scope.

---

### Q3 — Do the formula constants carry over unchanged, and how is the frequency contradiction settled?

Two coupled decisions.

**(a) The constants.** The local pipeline uses `0.45·H + 0.25·S + 0.30·D`, a soft-skill
denominator cap of 15%, caps at 55 / 65 / 69, bands at 85 / 75 / 70 / 60, and the
`D_disc = 0.35·P + 0.25·K + 0.20·R + 0.10·F + 0.10·N` shortlist. `research.md` §1 finding 5
independently validates the *shape* (hard skills dominant, semantic secondary, structural
third) and the 75–85 target band.

**Recommendation: carry them over unchanged in v1**, exposed as configuration (`spec.md`
SC-11). The pipeline reportedly works at ~85%; changing weights and adding real embeddings
in the same step makes any regression unattributable.

**(b) The frequency contradiction (`research.md` C-1).** Vendor guidance says match the
keyword *frequency* the tool indicates. The local rule is "presence beats frequency", chosen
deliberately to resist stuffing. These genuinely conflict.

**Recommendation: keep presence-based credit for the score, and surface frequency as
advice only.** The vendor's frequency guidance optimises *their* number; ours exists to
decide where to spend an application. But adopt the two other 2026 rules research surfaced,
which are not stuffing incentives and which the local pipeline lacks:

- **Position weighting** (§1 R1.2): a skill evidenced in the top third counts more.
- **Keyword-in-context** (§1 R1.3): a skill evidenced inside an accomplishment bullet
  counts more than the same skill appearing only in a skills list.

Both are quality signals, not volume signals. Both change stored score values, so they are
a versioned rules change (`spec.md` NFR-9), not a silent tweak.

**Impact if unresolved:** the core formula is unspecified, so `plan.md` §4 (score storage
shape) and the entire scoring test suite cannot be written. Also determines whether
historical scores from the JSON caches remain comparable.

**Status: RESOLVED 2026-09-18 — (a) constants carry over unchanged; (b) presence-based
credit kept, frequency surfaced as advice only; position weighting and keyword-in-context
both adopted.** The two new rules ship as **rules version 2**, so scores remain
attributable against the local pipeline's version 1.

---

### Q4 — Does the CV become structured data in v1, or stay a text blob?

**Evidence gathered.** `src/Modules/Cvs` stores `file_path` + a `raw_text` longText and has
no structured sections. The user's own notes propose exactly the structured shape:

```json
{ "profile": {}, "experience": [], "skills": [], "projects": [],
  "education": [], "certifications": [], "achievements": [], "languages": [] }
```

— and add *"projects que se actualice desde tabla portfolio"*, i.e. projects sourced from
the existing Portfolios module.

**Why this is high impact.** Four of the spec's requirements are only meaningfully
satisfiable over structured data:

- FR-4 / US-5 — per-offer tailoring that re-orders skills and re-weights sections. Over a
  blob this degrades to string substitution.
- R1.2 position weighting — needs to know what is in the top third, which needs sections.
- R1.3 keyword-in-context — needs to distinguish a skills-list mention from a bullet.
- FR-8 provenance — "which source bullet did this output bullet come from" needs bullets to
  be rows.
- US-2 2-page condensation — dropping the least critical bullets needs addressable bullets.

**Recommendation: yes, structured, in v1.** Parse once from `raw_text` into sections on
import (LLM-assisted, human-confirmed), keep `raw_text` as the immutable source of truth,
and pull project entries from the Portfolios module rather than duplicating them.

**Impact if unresolved:** this is the single largest schema decision in the module. Deciding
it after Phase 5 means rewriting the rewriter, the exporter and the scorer.

**Status: RESOLVED 2026-09-18 — structured in v1.** `raw_text` stays the immutable source of
truth; sections are parsed once on import (LLM-assisted, candidate-confirmed); project
entries are sourced from the Portfolios module rather than duplicated.

---

## Medium impact — proposed defaults, correct me if wrong

### Q5 — Are runs manual only, or also scheduled?

The local pipeline has a Sunday-refresh cadence for the portal and keyword caches, implying
a weekly rhythm already.

**Resolved by default:** v1 is **candidate-triggered only**, with the refresh *triggers*
(cache miss, stale week, low yield, forced) modelled as data so a scheduler can be added
later without redesign. Scheduling is a thin wrapper once the run is a queued job, and the
project already has cron infrastructure. `spec.md` A-9.

**Impact:** low. Adding a schedule later touches one command and one cron entry.

---

### Q6 — Is RAG over the CV corpus a v1 feature?

The user asks about *"estudiar RAG cv principal"*.

**Resolved by default:** embeddings are built in v1 because the semantic score requires
them (FR-17). That is vector *retrieval* infrastructure, so RAG comes nearly free — but as
a **scoring and tailoring input**, not a chat interface. Retrieving the three most relevant
CV bullets for a given posting requirement is directly useful to the rewriter and needs no
extra machinery. A conversational "ask my CV" surface is out of scope for v1
(`spec.md` A-11, §8).

**Impact:** low. The vectors exist either way; only the surface differs.

---

### Q7 — Whose CV is the engine calibrated to?

The local pipeline hard-codes the author's baseline in the scoring tables: Covilhã base, 4+
years PHP/Laravel, BSc Computer Science, ES native / PT resident / EN B1, PHP-Laravel stack
lock, Lisbon-only hybrid.

**Resolved by default:** these become **profile data** (`spec.md` A-8, FR-30) with the
author's current values as the seeded default. They are candidate facts that change — an
English exam result or a fifth year of experience would otherwise require a code edit, and
the reach table exists precisely to inform that kind of decision.

**Impact:** medium-low, but cheap now and expensive later. Contradiction C-3 in
`research.md`.

---

### Q8 — Which embedding model and dimensions?

**Resolved by default:** pin the model in configuration and store model + dimensions on
every vector (`spec.md` §7) so a model change is a re-embed, not a corruption. Default to a
multilingual-capable model at 1536 dimensions stored as `halfvec(1536)`, since the corpus
spans ES, EN and PT. `research.md` §3 requires data and queries to share a model.

**Impact:** low, given the model is config and vectors are re-derivable.

---

### Q9 — What is the ageing period before silence counts as a non-reply?

**Resolved by default:** 21 days, carried over from the local pipeline (`cache-schema.md`),
applied in analysis only and never written over a stored value (`spec.md` FR-29).

**Impact:** low, and it is configuration.

---

### Q10 — Does the module keep the four flows' existing output files?

**Resolved by default:** no migration of the JSON caches and Markdown outputs in
`GUIDE/JOBORA-CLAUDE/` is in scope. They remain as reference and as the held-out validation
set for SC-3 — which is a better use for them than import, since they are the only
independent check on the new engine's agreement with the author's own judgement.

**Impact:** low.

---

## Resolved by research — no question needed

| # | Was uncertain | Resolved by |
|---|---|---|
| R-1 | Whether the stack can do real embeddings without FastAPI/Python | `research.md` §3 — `vector` 0.8.2 available on this Supabase instance, `laravel/ai` ^0.11 generates embeddings. Verified by execution. **FastAPI rejected.** |
| R-2 | Whether `crawl4ai` is needed to enumerate links | No. `research.md` §2 — the primary sources are APIs and feeds, not crawls; `FetchLadder` + Firecrawl already covers page extraction for the rest. |
| R-3 | Whether the user's ATS formatting rules are still correct for 2026 | `research.md` §1 — confirmed correct and complete, plus two additions (position weighting, keyword-in-context). |
| R-4 | Whether GDPR blocks global/contractor job discovery | `research.md` §2 finding 4 — public job postings are employer data. The constraint is personal data and authentication, both excluded by NFR-8 / FR-36. |
| R-5 | Whether `phpoffice/phpword` is wired up | `research.md` §4 — installed, **no consumer in `src/`**. A `PhpWordExportAdapter` behind the existing `ExportPort` is greenfield work, not integration work. |
| R-6 | Whether the `cvs` table was meant to be extended | `research.md` §4 — its migration docblock reads *"Module 1 — CV upload aggregate. AI/RAG/jobs tables land in Module 2 later."* This is Module 2. |

---

# Round 2 — 2026-09-18 (pm)

Raised by the second `/ssd-artifact` request (fullstack "full", formula improvement,
Firecrawl + Context7) and by `research.md` §10–§16. Note: the request referenced
`[Pasted text #2 +19 lines]`, but that paste did not reach this session. Round 1 had
already captured the notes it most likely contained (the structured-CV JSON shape and
"projects que se actualice desde tabla portfolio", Q4). If it carried anything else,
it is not reflected here.

## High impact — block the `plan.md` revision

### Q11 — v1 scope: fullstack only, or all four flows?

**Evidence.** The user now asks for *"fullstack full"*. Round 1 scoped v1 as one engine plus
four profiles (US-7). Reading the JOBORA rules closely, the other flows are **not** pure
configuration of the fullstack engine:

| Flow | Discovers | Blend | Extra gates |
|---|---|---|---|
| fullstack | postings | `0.45·H + 0.25·S + 0.30·D` | G1–G4b |
| video content | **companies** (direct channels, not portals) | `0.30·H + 0.25·S + 0.45·D`, own `D` signals | G2 anti-marketing, G5 pay floor 15 EUR/h, G6 pay model, per-minute rate band, `ROI_app` sort |
| trainer (FUNDAE) | **companies** (training academies) | — | company cache, Spanish-only output |
| gap (a fifth flow found in the GUIDE) | postings | `0.25·H + 0.25·S + 0.50·D` | G4 anti-fraud first, G2b contract model, salary band, salary-desc sort |

**Recommendation:** v1 ships **the fullstack flow end to end**. The engine still stores gates,
weights and baselines as **profile data** (cheap, already designed), so a second
posting-based profile needs no schema change. The company-discovery flows (video, trainer)
are a different pipeline shape and become a later spec.

**Impact:** changes US-7's priority, roughly a third of `tasks.md` (profile seeding, the
other flows' gates, their tests), and whether per-profile blend weights must exist in v1.

**Status: RESOLVED 2026-09-18 — fullstack only.** v1 ships the fullstack flow end to end.
Gates, blend weights and baselines stay profile data so a second posting-based profile
(gap, other) needs no schema change; only the fullstack profile is seeded and tested. Video
content and trainer (company discovery) move to a later spec. US-7 drops to Low / deferred.

### Q12 — Add the Opportunity layer (fit × opportunity) in v1?

**Evidence.** `research.md` §10: the top six fit scores were all silent and the only positive
reply came from the lowest applied score. §12: reply rate varies >3× by channel (direct 6.87 %
vs LinkedIn 1.95 %) and 2–4× by posting age; 19 % of Greenhouse postings in Q2 2026 were
ghosts. The pipeline currently orders the apply list by fit alone.

**Recommendation: yes, v1.** `apply_priority = round(fit × q_channel × q_fresh × q_ghost ×
q_level)`, each factor stored with its reason and sample size, Beta-Binomial learning from
outcomes with a pseudo-count of 30, fit never touched (`research.md` §16.2, spec US-9,
NFR-13).

**Impact:** new columns and tables (sightings, channel baselines, opportunity factors),
`posted_at` capture across every source adapter, the apply list's sort order, and a new
domain service. Adding it after the source adapters exist means revisiting every adapter.

**Status: RESOLVED 2026-09-18 — yes, in v1**, exactly as recommended: four factors, each
stored with value, reason and sample size; Beta-Binomial learning with pseudo-count 30; fit
never modified by outcomes.

### Q13 — "Senior" titles with no years floor: fit cap, opportunity factor, or unseed?

**Evidence.** The formula scores "Senior" with no years number as `D_years = 75` with **no
cap** (JOBORA rule: over-qualification is never a penalty, and a 78 on a Senior posting was
once judged real). Outcome data: 7 Senior-titled applications, 0 positive, 1 rejection;
9 non-senior, 1 positive — directional at this n.

**Options:** (a) handle it in the Opportunity layer as `q_level ≈ 0.6`, learned from
outcomes, fit unchanged — **recommended**, it keeps "fit" honest about the CV-vs-JD match;
(b) add a fit cap (e.g. 69) for Senior titles — rejected by the JOBORA rule it would break;
(c) stop seeding Senior queries — loses postings that do fit.

**Impact:** decides whether the fit formula changes (and therefore rules version and SC-3
calibration), or only the opportunity layer does.

**Status: RESOLVED 2026-09-18 — (a) opportunity factor.** Fit formula unchanged; Senior
titles above the profile band get `q_level` with a 0.6 prior, learned from outcomes. Senior
expansion seeding continues.

## Medium / low impact — resolved by default, correct me if wrong

### Q14 — Vector storage type

**Resolved by default: Laravel 13 native `vector` column** via `$table->vector(…)` and
`whereVectorSimilarTo()` / `orderByVectorDistance()` (`research.md` §14.1), replacing plan
§4.3's raw-SQL `halfvec`. The HNSW index and `hnsw.iterative_scan` stay as raw statements.
**Impact:** low — storage size is immaterial at this scale; the change removes raw SQL.

### Q15 — LinkedIn and aggregators: resolve to source

**Resolved by default: yes.** A posting reached via LinkedIn or an aggregator is probed
against the employer's public ATS endpoints; a match becomes the preferred apply link and
scoring text (spec US-10). LinkedIn is still **never fetched** (Q2 unchanged; the candidate
holds a LinkedIn account, so the account-holder ToS reading applies — `research.md` §15.2).
**Impact:** medium, but strictly additive and within the Q2 stance.

### Q16 — Skill taxonomy for partial credit

**Resolved by default:** a `skill relation` table seeded from the JOBORA alias table plus an
**ESCO ICT/digital subset** (ES/EN/PT labels). Embeddings may propose a relation; the
candidate confirms it once; only confirmed relations grant 0.5 credit (spec FR-44,
`research.md` §13 U-1). The full ESCO import is not needed.
**Impact:** low-medium; the table is small and seedable.

### Q17 — Reranker at the shortlist stage

**Resolved by default: not in v1.** `laravel/ai` Reranking exists and `config/ai.php`
defaults it to Cohere, but `D_disc` is deterministic, explainable and already works. A
reranker may be A/B-tested later through `studio_query_experiments` (plan CHG-5). It never
enters the fit score.
**Impact:** low.

### Q18 — ATS detection

**Resolved by default: detection in v1** (URL patterns → `ats_kind` on the posting), feeding
Q15's resolution. ATS-specific advice stays deferred (plan OD-2).
**Impact:** low.

## Resolved by research — round 2

| # | Was uncertain | Resolved by |
|---|---|---|
| R-7 | Is the formula worth changing, or "fine as is"? | `research.md` §13 — shape and constants stay; three upgrades (U-1 taxonomy partial credit, U-2 requirement-level `S`, U-3 evidence retrieval) fold into rules v2. The larger gain is outside fit (Q12). |
| R-8 | Crawl4AI/FastAPI to "see all links"? | `research.md` §15.4 — no. Firecrawl `map` enumerates site URLs; volume is far below where self-hosting pays. |
| R-9 | Can global/contractor jobs incl. LinkedIn be reached under EU rules? | `research.md` §15.1–2 — yes for posting data via official endpoints and search-discovered links; LinkedIn pages never fetched; AI Act Annex III describes employer-side screening, not this tool (re-assess if ever offered to third parties). |
| R-10 | AIApply / Enhancv internals (was `[UNVERIFIED]`) | `research.md` §11 — verified by live Firecrawl scrape. |
| R-11 | Laravel 13 ORM conventions for the plan | `research.md` §14.5 — `#[Table]` + `#[Fillable]` attributes (the repo standard), `HasUuids` → UUIDv7, `Prunable` for retention. |

---

# Round 3 — verification (2026-09-18, evening)

The user refused to let implementation start on three `[UNVERIFIED]` items. `research.md` §17
verified what could be verified and showed what could not. Two decisions follow; **both block
Phase 7 (implement), neither blocks tasks or analyze.**

**Effect on Q12 and Q13.** The *decisions* stand (Opportunity layer in v1; Senior handled by an
opportunity factor, not a fit cap). The *numbers* attached to them — the channel rates, the
0.6 level prior and the pseudo-count 30 — are **withdrawn**: §17.1 showed the channel rates are
an ordering, not magnitudes (and CHG-9 mis-mapped them); §17.2 showed `m = 30` lets one outcome
halve a factor. Q19 replaces them.

## Q19 — Opportunity policy: which default values? — OPEN, blocks implementation

**Evidence.** No external source gives magnitudes for aggregator, agency or seniority effects;
the one measured dataset (Huntr) supports only the ordering "closer to the employer is better",
with magnitudes that swing 2× between cuts; the candidate's own 16 applications show nothing
significant (Fisher p = 1.00 / 0.31 / 0.08) and ~750 applications per group would be needed to
learn a 3 %→6 % difference (`research.md` §17.1–§17.2).

**Mechanism (not in question):** evidence grades, values stored as editable policy, evidence
gate (≥ 30 applications with outcome and ≥ 3 positives before own data replaces a policy value,
then `N0 = 100`), neutral toggle — spec FR-51, plan CHG-16.

**What is in question — the starting values:**

| Option | Channel (at source · board · aggregator/social) | Freshness | Level +1 · +2 | Ghost max penalty |
|---|---|---|---|---|
| (a) **Neutral start** | 1 · 1 · 1 | off | 1 · 1 | off — signals shown as chips only |
| (b) **Mild directional policy** — recommended | 1.0 · 0.85 · 0.70 | half-life 7 d, floor 0.5 | 0.85 · 0.60 | 0.30 |
| (c) Evidence-shaped (Huntr ordering at its own magnitudes) | 1.0 · 0.45 · 0.30 | half-life 5 d, floor 0.25 | 0.60 · 0.30 | 0.60 |

(b) keeps every direction the evidence supports while staying well inside the magnitudes any
source reports, so no factor can dominate fit. Every value is labelled "your policy" with its
grade and is editable.

**Impact:** the seeder's default values and the SC-13/SC-14 fixtures. Code is identical under
all three.

**Status: RESOLVED 2026-09-18 — option (b), mild directional policy.** Channel 1.0 / 0.85 /
0.70 (grade B) · freshness half-life 7 d, floor 0.5, unknown age 0.7 (grade C) · level +1 0.85,
+2 0.60 (grade C) · ghost max penalty 0.30 (grade D) · evidence gate 30 applications / 3
positives, then `N0 = 100` · neutral toggle available. All values are labelled "your policy"
and editable.

## Q20 — Where do PostgreSQL-only tests run? — OPEN, blocks implementation

**Evidence.** `phpunit.xml` uses SQLite in memory (`research.md` §17.4). The vector test the user
asked to be *the first technical test* (T-000), the real similarity adapter and the extension
migrations need PostgreSQL. The only PostgreSQL visible to this session is the shared Supabase
development database — suitable for a rolled-back probe, **not** for a test suite.

**Options:** (a) a local PostgreSQL 17 with pgvector (e.g. Herd's service or DBngin) —
recommended: free, fast, isolated; (b) a separate Supabase project or branch used only for tests;
(c) run the pgsql group against the development database inside rolled-back transactions only —
not recommended.

**Impact:** decides whether the `pgsql` Pest group can run at all, and the connection name tasks
T-000 / T-002 / T-052 use.

**Status: RESOLVED 2026-09-18 — local PostgreSQL 17 + pgvector**, connection `pgsql_testing`.
**Prerequisite (user action, not code):** a local PostgreSQL 17 instance (Herd's service or
DBngin) with the `vector` extension available, an empty database for tests, and the connection
keys in `.env.testing` (`DB_TEST_PGSQL_HOST`, `DB_TEST_PGSQL_PORT`, `DB_TEST_PGSQL_DATABASE`,
`DB_TEST_PGSQL_USERNAME`, `DB_TEST_PGSQL_PASSWORD` — names final in T-000). Whether pgvector
ships with the chosen local service is **not yet verified** and is the first check of V-4.

## Round 4 — after V-5 (2026-09-18 night)

The user confirmed the model: link-only sites are **discovery signals**, never sources; the
module looks for the employer's own copy, with the explicit priority **official API/ATS/feed →
permitted portal → web search → permitted scraping as a last resort**, and keeps unresolved
discoveries as references to open manually. That is now spec US-10 / FR-53 / FR-54 and plan
CHG-21. Three follow-ups:

### Q21 — Subscribe to the RemoteRocketship API? — OPEN, not blocking

**Evidence:** official paid API (active subscription; 500 requests / 3,000 jobs per day); each
job carries the employer's own URL, dates, experience months, languages, stack and a ghost-job
filter (`research.md` §19.1). Reported price $21/month (secondary). **Without it:**
RemoteRocketship stays `resolve_only` (discovered through search, resolved to the employer),
which still works. **With it:** a clean, dated, ghost-filtered remote feed whose URLs go
straight into resolve-to-source. **Impact:** one optional adapter (T-157); nothing else changes.
**Default until answered:** not subscribed (`resolve_only`).

### Q22 — Join the InfoJobs Partners program? — Resolved by default: no

**Evidence:** InfoJobs requires offer-aggregator apps to join its Partners program and sign an
agreement. **Default:** `link_only`, no adapter (T-115 not built). The candidate can revisit this
by contacting InfoJobs. **Impact:** low; InfoJobs postings are still discovered through search
and resolved to the employer.

### Q23 — Let the candidate paste a posting's text for a reference? — RESOLVED: yes (confirmed by the user, 2026-09-18: "me parece bien lo de open manually")

**Why:** it is the natural end of the user's own flow — *"conservar la referencia para que tú la
abras manualmente"*. The candidate opens LinkedIn/Indeed in their own browser, as a person, and
pastes what they read (the Jobscan pattern). The module never touched the site. **Impact:**
small (FR-53, T-158). Correct me if unwanted.

## Resolved by the verification round

| # | Was uncertain | Resolved by |
|---|---|---|
| R-12 | Does Laravel's vector query use the HNSW index? | `research.md` §17.3 — yes: `<=>` emitted, `Index Scan using …hnsw` in all shapes; filtered queries under-return (2 of 3) without `SET LOCAL hnsw.iterative_scan = strict_order`, which is therefore mandatory. |
| R-13 | Can the connection role create the `vector` extension? | §17.3 — yes (inside a rolled-back transaction). `pg_trgm` / `unaccent` still to probe (T-002). |
| R-14 | Is "6.87 % direct vs 1.95 % LinkedIn" a usable rule? | §17.1 — no. Self-selected tracker users, 78 % LinkedIn, "interview logged" metric, magnitudes unstable across cuts. Usable only as an ordering (grade B). |
| R-15 | Portals or keywords? | §17.5 — both: keywords × portals, each portal with a verified access mode; unchecked portals default to `link_only`. |
| R-17 | V-5 — what do the portals' terms allow? | `research.md` §19: **Net-Empregos** RSS (active job search, non-commercial) → `api_feed`; **ITJobs.pt** API with key (personal needs, non-commercial) → `api_feed`; **InfoJobs** → `link_only` (offer aggregators need a partner agreement, Q22); **Tecnoempleo**, **JustJoin.it**, **Glassdoor**, **LinkedIn**, **Indeed** → `link_only`; **RemoteRocketship** → `resolve_only`, or `api_feed` with a paid subscription (Q21); employer pages → fetch only where `robots.txt` allows, basic proxy only. |
| R-16 | *"¿Los MCP podrán ver link y publicación en LinkedIn, Net-Empregos, ITJobs, Tecnoempleo, JustJoin.it, RemoteRocketship…, o solo el link público?"* | **Two separate things.** (1) **MCP** (Tavily/Firecrawl inside this editor session) is a *research instrument* for these spec documents, never the module's production path (T-046); it follows the same limits as the module, and Firecrawl itself refuses social networks. (2) **What the module sees per portal is its `access_mode`** (plan CHG-18): `api_feed` — **full posting** (Net-Empregos RSS, ITJobs.pt API, InfoJobs API, Greenhouse/Lever/Ashby/…); `sitemap` — link + text only if the portal's terms allow (JustJoin.it, V-5); `link_only` — **URL + title + the snippet the search engine already returns**, the page itself is **never opened by the module**; the candidate opens it in their own browser (LinkedIn, Indeed, Tecnoempleo, Glassdoor); `resolve_only` — the link is used only to find the employer's own copy, whose text comes from the employer (RemoteRocketship, JobLeads, Jobgether). **GDPR vs terms:** GDPR governs *personal* data — the module stores employer data only and does not retain person names or contacts, including any that appear inside a search snippet (NFR-8). The restriction on opening `link_only` pages comes from each site's **terms and robots.txt** (contract), which the module also honours. Both are enforced by test (T-045, T-117, T-120). |
