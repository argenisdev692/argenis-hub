# Research: CV ATS Job Studio (`004-cv-ats-job-studio`)

- **Date:** 2026-09-18
- **Phase:** 3 — Real-time research (§1–§6 on 2026-09-18; §7–§9 appended the same day after
  the source-hierarchy question was raised)
- **Tooling note:** `mcp__tavily__tavily_research` (deep mode) returned **HTTP 432 — plan
  usage limit exceeded** on **three** attempts, including one after the user reported the
  Tavily plan had been updated and one on the cheaper `mini` model. All Tavily findings
  here come from `mcp__tavily__tavily_search` (`search_depth: advanced`). The **direct**
  Tavily API was not probed — see §8 and task T-001. Findings marked **[UNVERIFIED]** must
  carry that label into `plan.md`.
- **Local facts** (§3, §6) were verified by executing against this machine and this
  database, not from documentation. **§7's endpoint table was produced by calling every
  endpoint directly with `curl`** — it is measurement, not documentation.

---

## §1 — How ATS matching actually works in 2026

### Findings

1. **Real ATS platforms have split into two behaviours.** Workday has moved parts of its
   matching pipeline to transformer-based semantic scoring; exact-string matching remains
   the safer assumption on iCIMS, Lever and Taleo. The practical consequence is that a
   paraphrase *partially* matches on some platforms and not at all on others.
   — <https://resumeoptimizerpro.com/blog/how-to-optimize-your-resume-for-ats>
2. **The AI screening pipeline is a three-step shape:** extract text → structure into
   fields (contact, titles, dates, employers, skills, education) → compare, where the
   comparison maps résumé and posting "into a shared high-dimensional embedding space" and
   ranks by closeness. Non-standard labels or unusual layouts land data in the wrong field
   or drop it entirely — i.e. the structural checks matter *before* the semantic ones.
   — <https://www.jobscan.co/blog/blog-ai-resume-screening>
3. **Positional weighting is real and new to our model.** The top third of the résumé is
   weighted more heavily, because the model samples early tokens with a positional bias
   and uses them to form the document summary vector.
   — <https://resumeoptimizerpro.com/blog/how-to-optimize-your-resume-for-ats>
4. **Keywords in context beat naked skill lists.** Every keyword listed as a skill should
   also appear inside an accomplishment bullet; "naked skill lists score lower". The
   stated sweet spot is 10–15 posting keywords, and the failure mode of most weak résumés
   is a *vocabulary* gap, not a skill gap.
   — same source
5. **Vendor target band: 75–80%, and vendors discourage chasing 100%.** Jobscan's own
   video guidance says 80%+ before applying; the 2026 write-ups converge on 75% for
   competitive roles, and warn that gains past a point come from keyword stuffing, which
   costs recruiter trust.
   — <https://jobshinobi.com/blog/jobscan-resume-scanner-match-rate-how-to-improve>,
     <https://jobscan.build/video-resume-match-report>
6. **What Jobscan's Match Rate compares:** job titles, hard skills, soft skills, education
   level, plus "other information", with hard skills explicitly prioritised over soft
   because hard skills are what recruiters use as search terms. Jobscan's help guidance
   tells users to match the *frequency* the tool indicates, not merely include the term.
   — <https://jobscan.build/video-resume-match-report>,
     <https://www.jobscan.co/blog/top-resume-keywords-boost-resume>
7. **Human-in-the-loop is the vendors' stated posture.** Jobscan's AI Optimize proposes
   edits and requires user sign-off on each one before anything is produced.
   — <https://www.jobscan.co/blog/can-ats-detect-ai-resume>

### What this changes in our model

| # | Finding | Consequence for the plan |
|---|---|---|
| R1.1 | ATS comparison is embedding-based on at least one major platform | The semantic component must become real vector similarity. Confirms `spec.md` A-3. |
| R1.2 | Positional bias — top third weighted more | **New rule.** Skill coverage must be position-weighted, and the rewriter must front-load. Not present in the local pipeline. |
| R1.3 | Keyword-in-context beats a skill list | **New rule.** A skill found only in a skills list should count less than the same skill evidenced in an accomplishment bullet. The local pipeline scores both as 1.0. |
| R1.4 | Structure errors drop fields entirely | Structural checks gate, they do not merely shade — matches the local pipeline's intent, now with per-check granularity (FR-2). |
| R1.5 | Exact-match still dominant on iCIMS/Lever/Taleo | Keep exact/alias matching as the primary component. Do **not** let semantics replace it. Validates the existing 45/25/30 shape. |
| R1.6 | Target band 75–85, stuffing beyond that is counterproductive | Keep the existing band table and the "don't chase >90" guidance. No change. |
| R1.7 | Frequency guidance exists but conflicts with our "presence beats frequency" rule | **Open contradiction.** Recorded in `clarify.md` Q3. Vendor guidance is about *their* score; our rule was a deliberate anti-stuffing choice. |

### The user's own ATS formatting rules — verdict

The rules the user reports applying (single column, no tables/graphics/icons/skill
bars/photos, standard headings, Helvetica 9–11pt, `MM/YYYY` dates, selectable-text PDF
verified by text extraction, 2 pages for a 4+ year profile) are **confirmed correct and
complete for 2026** against findings 2, 4 and the cited checklists. Two additions the
research supports and the current rules do not cover:

- **Front-load** the top third (R1.2).
- **Echo every skills-list keyword inside a bullet** (R1.3).

The verification step by text extraction is the right instinct and becomes FR-6/SC-5 —
an automated assertion rather than a manual check.

---

## §2 — Posting sources: what is legal, and what is available

### Findings

1. **`hiQ v. LinkedIn` is routinely misquoted.** hiQ prevailed narrowly on the point that
   scraping publicly visible data is not "unauthorized access" under the CFAA
   (9th Cir., reaffirmed 2022). **LinkedIn prevailed on breach of contract** — the User
   Agreement prohibition held, with a 2022 ruling against hiQ and a settlement. So public
   scraping is not a federal crime *and* remains a terms-of-service violation with ban
   exposure. Three separate questions: legality, terms compliance, ban risk.
   — <https://linkedrent.com/is-scraping-linkedin-legal-saas>,
     <https://linkedapi.io/guides/how-to-scrape-linkedin>
2. **`Meta v. Bright Data` (N.D. Cal., Jan 2024)** held that terms of service prohibit
   *logged-in* scraping only; logged-off public scraping after account termination was not
   a breach. — <https://cloro.dev/blog/website-scraping-legal>
3. **GDPR applies to personal data regardless of public visibility.** Scraping a public
   profile of an EU resident triggers GDPR; `CNIL v. KASPR` confirms publicly visible
   personal data is still regulated, and legitimate-interest has been rejected for
   large-scale automated collection without the subject's awareness.
   — <https://www.promptcloud.com/blog/is-web-scraping-legal>,
     <https://www.octoparse.com/blog/is-web-crawling-legal-well-it-depends>
4. **Public job postings are named as ordinarily-scrapeable public data**, in the same
   bucket as product listings and open business directories — because a posting is
   employer data, not personal data. The four boundaries that matter are: authentication,
   personal data, copyright, rate limiting.
   — <https://www.promptcloud.com/blog/is-web-scraping-legal>,
     <https://cloro.dev/blog/website-scraping-legal>
5. **Free, unauthenticated, terms-clean API sources exist and are plentiful:**
   - **Per-company ATS board endpoints** — Greenhouse `boards-api`, Lever `postings`,
     Ashby `job-board`. Unauthenticated JSON, straight from the employer. Limitation:
     one company per call, no cross-company discovery, so a company slug list is needed.
   - **Arbeitnow** — no API key; Europe/remote; and notably its inventory is itself
     powered by Greenhouse, SmartRecruiters, Join.com, TeamTailor, Recruitee and Comeet,
     i.e. ATS-direct data. A separate UK endpoint launched in 2026.
   - **Remotive** — free public feed, **but** its terms require linking back and naming
     Remotive as source, forbid resubmitting its jobs to third-party sites, and delay
     jobs 24h. Breach terminates access.
   - **RemoteOK** — free public feed, attribution with a link-back required.
   - **Himalayas** (salary/seniority metadata), **Jobicy** (industry/level tagging),
     **WeWorkRemotely** (public RSS).
   - **Adzuna** — API key, ~1,000 calls/month free, 16 countries.
   - **Jooble**, **Careerjet**, **Findwork**, **The Muse**, **USAJOBS**, **Arbeitsamt**
     (German federal, OAuth).
   - **JSearch** (RapidAPI) aggregates Google-for-Jobs downstream copies of LinkedIn/
     Indeed/ZipRecruiter/Glassdoor — prototype-sized free cap, marketplace markup, and
     a scrape-of-a-scrape in provenance terms.
   — <https://jobspipe.dev/free-jobs-api>,
     <https://www.arbeitnow.com/blog/job-board-api>,
     <http://remotive.io/remote-jobs/api>,
     <https://www.freeapisforyou.in/best-free-job-search-apis>

### What this changes in our model

The user asked directly whether contractor/global job links **including LinkedIn** can be
reached without breaching EU rules. The research gives a clean, defensible split:

| Access pattern | Verdict | Use it? |
|---|---|---|
| Official job-board / ATS APIs and RSS (Arbeitnow, Greenhouse/Lever/Ashby, Adzuna, RemoteOK, Himalayas, Jobicy, WWR) | Sanctioned by the provider's own terms | **Yes — make these the primary sources.** Terms per source, attribution honoured (FR-35). |
| A general search API (Tavily) returning a LinkedIn/Indeed posting URL in its results | We query a search engine, not LinkedIn. No LinkedIn session, no LinkedIn ToS acceptance. | **Yes — for discovery of the URL and its snippet.** |
| Fetching a LinkedIn posting page ourselves, logged out | Not a CFAA issue (finding 1–2), but LinkedIn's User Agreement prohibition has held in court, plus ban/blocking exposure | **No.** Store the URL for the human to open. Do not add LinkedIn to the extraction ladder. |
| Anything behind a login, or circumventing rate limits/anti-bot | Squarely outside every safe harbour in findings 1–4 | **No. Hard architectural exclusion** (FR-36, SC-9, spec §8). |
| Retaining named recruiters / employees from postings | Personal data under GDPR regardless of public visibility (finding 3) | **No** (NFR-8). This is the bright line between this module and the B2B pipeline. |

**The GDPR question resolves favourably**, and for a specific reason worth stating plainly:
this module stores *employer* data — company, title, requirements, location, salary — which
finding 4 places in the ordinarily-scrapeable bucket. The B2B pipeline's heavy GDPR
machinery (Article 14 notices, suppression lists, personal-data scrubbing) exists because
it processes *people*. This module should not need it, provided NFR-8 holds. That is the
sharpest argument for keeping the two modules separate (`clarify.md` Q1).

---

## §3 — Can this stack do it without FastAPI? (verified locally)

The prior analysis in `GUIDE/JOBORA-CLAUDE/ANALISIS-PIPELINE.txt` recommended rebuilding on
FastAPI + Python, on the grounds that PHP cannot do real embeddings and that Laravel's AI
ecosystem is weak. **Both premises are false on this machine.** Verified by execution:

| Fact | How verified | Result |
|---|---|---|
| Database engine | `php artisan db:show` | PostgreSQL **17.6** (Supabase, `eu-central-1`), 120 tables |
| `pgvector` availability | `SELECT … FROM pg_available_extensions` | **`vector` 0.8.2 available**, not yet enabled. One `CREATE EXTENSION` away. |
| `pg_trgm` availability | same query | **1.6 available** — trigram fuzzy matching for skill aliasing |
| `unaccent` availability | same query | **1.1 available** — accent-folding for ES/PT keyword matching |
| Embeddings in PHP | Context7 `/laravel/ai` | `laravel/ai` **^0.11** is installed and generates embeddings natively |
| PHP / framework | `php -v`, `artisan --version` | PHP **8.5.9**, Laravel **13.26.1** |
| Word export | `composer.json` | `phpoffice/phpword` **^1.4** installed (currently **no consumer** in `src/`) |
| PDF export | `composer.json` + existing adapter | `barryvdh/laravel-dompdf` ^3.1, wired through `Shared/Infrastructure/Export/DomPdfExportAdapter` |
| CV text extraction | `composer.json` | `smalot/pdfparser` ^2.12 installed |

`laravel/ai` embeddings API, from Context7 (`/laravel/ai`):

```php
use Laravel\Ai\Embeddings;

$response = Embeddings::for(['Text one', 'Text two'])
    ->dimensions(1536)
    ->cache()          // built-in caching — direct cost control
    ->generate();      // ->generate(Lab::OpenAI) to pin a provider

foreach ($response->embeddings as $embedding) { /* … */ }
```

It accepts `Document` objects (PDFs) alongside strings, and `->cache()` is a first-class
cost lever, which matters for NFR-4/FR-33.

### `pgvector` 0.8 golden path (for `plan.md` §4)

Converged guidance across four independent 2026 sources:

- Column type **`halfvec(N)`**, not `vector(N)` — 50% smaller storage and index, minimal
  recall loss.
- Index **HNSW**, not IVFFlat: `USING hnsw (embedding halfvec_cosine_ops)` with
  `m = 16, ef_construction = 64`. HNSW can be built on an empty table (no training step).
- Query with the matching operator **`<=>`** (cosine). A `halfvec_cosine_ops` index
  requires `<=>`; a mismatched operator silently skips the index.
- Recall tuning via `SET hnsw.ef_search = 100` (≈2× slower than 40, materially better
  recall). Tune `ef_search` before touching `m`.
- **Filtered vector search is the trap.** HNSW terminates early under a `WHERE` clause and
  returns too few rows. On 0.8+ use `SET hnsw.iterative_scan = strict_order`; the old
  overshoot-CTE workaround loses results when the filter is selective. **We are on 0.8.2,
  so `iterative_scan` is available** — and our queries are always filtered by profile and
  candidate, so this is not optional.
- Partial indexes for hot subsets, e.g. `WHERE deleted_at IS NULL`.
- Always embed data and queries with the same model — hence storing model + dimensions
  per vector (spec §7 *Embedding*).
— <https://skills.sh/timescale/pg-aiguide/pgvector-semantic-search>,
  <https://fastmcp.me/skills/pgvector-semantic-search>,
  <https://lobehub.com/tr/skills/pmq9-agent-skills-pgvector-embeddings>,
  <https://explainx.ai/skills/timescale/pg-aiguide/pgvector-semantic-search>

### Verdict on the FastAPI recommendation

**Rejected, on evidence.** The recommendation's four load-bearing claims:

| Claim in `ANALISIS-PIPELINE.txt` | Status |
|---|---|
| "PHP has no equivalent to Sentence-Transformers → no real embeddings" | **False in effect.** `laravel/ai` generates embeddings; `pgvector` 0.8.2 stores and searches them with cosine distance in-database. The proxy can be replaced without Python. |
| "You'd depend on the OpenAI embeddings API → more expensive, less control" | True but immaterial at this scale, and `Embeddings::…->cache()` plus a stored-vector-per-posting design means each posting is embedded once, ever. |
| "Async: Laravel can't parallelise the scraping cascade" | Not a real blocker. The work is queue-shaped, and the sibling module already runs a five-stage queue pipeline. Laravel's HTTP client also pools concurrent requests. |
| "Vercel AI SDK is TypeScript, not PHP" | Obsolete — `laravel/ai` is the Laravel-native equivalent and is already installed and used by six modules here. |

The genuine cost of going to FastAPI is not in that table: a second runtime, a second
deploy target, a second auth story, and a split of the CV data away from the module that
already owns it — against NFR-10. **Local-model inference is the only thing Python would
buy**, and nothing in the spec needs it.

Two capabilities Python was credited with, available here instead:

- **Fuzzy/alias skill matching** → `pg_trgm` 1.6 similarity, in SQL, no service.
- **Accent-insensitive ES/PT matching** → `unaccent` 1.1, in SQL. Relevant because the
  pipeline searches in Spanish and Portuguese.

---

## §4 — Reusable infrastructure already in this codebase

Mapped by a graph scout pass over `src/`. This is why the Laravel answer is not merely
viable but cheap — roughly 60% of the plumbing exists:

| Need | Existing asset | Reuse |
|---|---|---|
| Search provider | `Shared/Infrastructure/Research/TavilyResearchAdapter` + `TavilyClientInterface`; `LeadScout/Infrastructure/Search/TavilySearchAdapter` | **Direct** |
| Page extraction | `Shared/Infrastructure/Research/FirecrawlScrapeAdapter` + interface | **Direct** |
| Extraction ladder | `LeadScout/Infrastructure/Fetching/FetchLadder` (`DirectHttpPageFetcher` → `FirecrawlPageFetcher`), with `RobotsTxtPolicy` + `OutboundUrlGuard` | **Pattern + guards** — satisfies FR-34, SC-9 |
| Job-board sources | `LeadScout/Infrastructure/JobSources/ArbeitnowApiSource`, `RssFeedSource` | **Direct** — already the two source kinds §2 recommends |
| LLM calls | `Shared/Infrastructure/AI/AIClientInterface` + `LaravelAIAdapter`, `ProviderFailover`, `PromptCache/PromptCachingAIClient` | **Direct** — covers NFR-6 and prompt-cost control |
| Spend guard | `LeadScout/Infrastructure/Budgets/BudgetLedger` (euro-micros, per period+category, `lockForUpdate`, pre-flight `ensure()` throwing `BudgetExceededException`) | **Pattern** — satisfies FR-33, SC-8 |
| Resilience | `Shared/Infrastructure/Resilience/CircuitBreaker/*` | **Direct** |
| Explainable scoring | `LeadScout/Domain/Services/ScoringEngine` + `ScoutScoreResult`/`ScoutScoreReason` (per-reason rows), weights in `config/lead-scout.php` | **Pattern** — the config-driven, per-reason-row shape is exactly FR-20/SC-11 |
| CV source | `src/Modules/Cvs/` — `cvs` table: `uuid`, `user_id`, `title`, `niche`, `is_primary`, `file_path`, `file_type`, `raw_text`; ports `CvRepositoryPort`, `CvTextExtractorPort` | **Build on** — and see below |
| PDF export | `Shared/Domain/Ports/ExportPort` + `DomPdfExportAdapter`, Blade templates in `resources/views/exports/pdf/` incl. `cvs.blade.php` | **Direct** |
| Word export | — | **Gap.** `phpoffice/phpword` is installed with no consumer; a `PhpWordExportAdapter` behind `ExportPort` is greenfield. |
| Vector storage | — | **Gap.** `vector` extension not yet enabled; no migration uses it. |

**The decisive find:** the migration that created the `cvs` table carries this docblock —
*"Module 1 — CV upload aggregate. AI/RAG/jobs tables land in Module 2 later."* This module
**is** that Module 2. The extension point was designed in.

Also relevant: the `cvs` table stores a file path plus a `raw_text` blob. It has **no
structured sections**, which is why `spec.md` FR-4 and `clarify.md` Q4 exist — per-offer
tailoring over a text blob can only be string substitution.

---

## §5 — Competitor UX (Jobscan / AIApply / Enhancv)

Drawn from §1's sources rather than a separate sweep, since the vendor material covers it.

| Pattern | Evidence | Adopt |
|---|---|---|
| Match Report as the primary artifact — one headline score, then per-section breakdown, hard skills above soft, each keyword clickable to its posting context | Jobscan match report walkthrough | **Yes.** Matches FR-15/FR-16 output shape. |
| Let the user delete an irrelevant extracted keyword and rescan | same | **Yes** — cheap, and it directly improves the denominator of the hard-skill component. Worth a requirement. |
| Editable diff with per-edit sign-off, never silent rewriting | Jobscan AI Optimize, explicit human-in-the-loop posture | **Yes** — aligns with FR-8 and the reviewer actor. |
| Show missing keywords as a checklist with "add to CV" affordances | Jobscan, Resume Optimizer Pro checklist framing | **Partly** — surface the gap, but never offer to add a skill the candidate lacks (FR-8, US-6 last criterion). |
| Point-lift framing: each recommended move priced in score points | Resume Optimizer Pro "ranked by point-lift" | **Yes** — we can compute this exactly, since we own the formula. Stronger than the vendors, who estimate. |
| ATS-platform-specific advice (Workday vs iCIMS behave differently) | §1 findings 1 and 5 | **Defer** — real, but needs per-employer ATS detection. Note as a later enhancement. |

**[UNVERIFIED]** AIApply's and Enhancv's specific 2026 scoring internals and UI details —
the searches returned Jobscan-centric material. Any AIApply/Enhancv-specific claim must not
enter `plan.md` without its own source.

---

## §6 — Net recommendation for Phase 4

1. **Stay on Laravel.** §3 settles it on verified local facts, not preference.
2. **Enable `vector`, `pg_trgm` and `unaccent`;** store `halfvec` + HNSW per the §3 golden
   path, including `iterative_scan` because every query is filtered.
3. **Replace the semantic proxy with real cosine similarity** via `laravel/ai` embeddings
   (`->cache()`, stored once per posting, model + dimensions recorded).
4. **Add the two 2026 rules the local pipeline lacks:** position weighting (R1.2) and
   keyword-in-context credit (R1.3).
5. **Primary sources are official APIs and feeds**, Tavily for URL discovery; LinkedIn
   pages are linked, never fetched. Login-gated access is excluded architecturally.
6. **Reuse, do not rebuild:** the ladder, guards, budget ledger, AI seam, circuit breaker,
   export port and Tavily/Firecrawl adapters all exist (§4).
7. **Two real gaps to build:** a `PhpWordExportAdapter` behind the existing `ExportPort`,
   and the structured-CV representation that per-offer tailoring requires (FR-4).
8. **Carry forward unchanged:** the 45/25/30 blend, the cap ladder, the band table, the
   soft-skill denominator cap, and the `D_disc` shortlist — §1 validated the shape, and
   keeping the constants keeps historical scores comparable (`clarify.md` Q3).

### Open contradictions to resolve before `plan.md`

| # | Contradiction | Goes to |
|---|---|---|
| C-1 | Vendor guidance says match keyword *frequency*; the local pipeline rule is "presence beats frequency" as an anti-stuffing measure | `clarify.md` Q3 |
| C-2 | Prior analysis recommends FastAPI; §3 rejects it on verified local capability | Resolved here — recorded so the reversal is traceable |
| C-3 | Local pipeline hard-codes Covilhã, EN B1, 4+ years, PHP/Laravel; spec §A-8 makes them profile data | `clarify.md` Q4/Q7 |

---

## §7 — Source hierarchy: measured, not assumed (added 2026-09-18)

The user posed the architecture question as a binary and asked for it to be decided
empirically rather than by intuition:

- **A:** `Tavily → everything else` (search first, then scrape what it finds)
- **B:** `Known structured sources → Tavily discovery → crawling fallback`

### Method

Every candidate endpoint was called directly with `curl` from this machine — no
documentation, no MCP, no intermediary. Recorded: HTTP status, payload size, response
shape, and crucially **whether the payload already contains the full job description**.

### Results (executed 2026-09-18)

| Provider | Endpoint pattern | Status | Payload | Full JD text in payload? |
|---|---|---|---|---|
| **Greenhouse** | `boards-api.greenhouse.io/v1/boards/{slug}/jobs?content=true` | **200** | 866 KB (60 KB without `content`) | **Yes.** Plus `absolute_url`, `location.name`, `updated_at`, `requisition_id`, `company_name`, `metadata[]`, and `data_compliance[]` carrying explicit GDPR consent flags |
| **Lever** | `api.lever.co/v0/postings/{slug}?mode=json` | **200** (`spotify`) | 695 KB | **Yes** — `additionalPlain` is plain-text JD |
| **Ashby** | `api.ashbyhq.com/posting-api/job-board/{slug}` | **200** | 2.17 MB | Yes |
| **Recruitee** | `{slug}.recruitee.com/api/offers/` | **200** (`hygraph`) | 47.8 KB | Yes — `offers[]` with `country`, `location`, `position` |
| **Workable** | `apply.workable.com/api/v1/widget/accounts/{slug}?details=true` | **200** | envelope valid | Pattern confirmed; test company had zero openings |
| **Arbeitnow** | `www.arbeitnow.com/api/job-board-api` | **200** | 2.11 MB | Yes |
| **WeWorkRemotely** | `weworkremotely.com/remote-jobs.rss` | **200** | 861 KB | Yes (RSS) |
| **RemoteOK** | `remoteok.com/api` | **200** | 612 KB | Yes; payload embeds its own ToS string requiring attribution |
| **Remotive** | `remotive.com/api/remote-jobs` | **200** | 150 KB | Yes; payload warns the main domain moved |
| **Himalayas** | `himalayas.app/jobs/api` | **200** | 25.9 KB | Yes; payload notes **cursor pagination added 21/08/2026** |
| **Jobicy** | `jobicy.com/api/v2/remote-jobs` | **200** | 25.9 KB | Yes; `apiVersion 2.2.16` |
| **SmartRecruiters** | `api.smartrecruiters.com/v1/companies/{id}/postings` | **200** | 50 B | **Envelope works, `totalFound: 0`** on `bosch`, `Visa`, `Ubisoft`, `McDonalds` — the company identifier is not a friendly name. Format unresolved → **task T-004** |
| **Teamtailor** | `{slug}.teamtailor.com/jobs.json` | **404** | 0 B | **No public unauthenticated JSON.** Needs an API key → **task T-005** |

Negative controls confirming slug-dependence rather than endpoint failure: `Lever/lever` →
`200 []` (valid customer, no openings); `Lever/plaid`, `Lever/brex` → `404` (not Lever
customers). So a 404 means "not on this ATS", not "endpoint broken" — which is itself the
signal an ATS-detection probe needs (task T-009).

### Conclusion: **B wins, and for a stronger reason than efficiency**

The decisive finding is not that structured sources are cheaper — it is that
**Greenhouse and Lever return the full job description as structured text**. For any
employer on a known ATS, path A is not merely wasteful, it is **strictly worse data**:

```
A:  Tavily query -> SERP snippet -> fetch page -> parse HTML -> lossy JD text
                    (2 provider calls, HTML noise, brittle selectors)

B:  GET boards-api/.../jobs?content=true -> authoritative JD text from the employer
                    (1 call, no parsing, no scraping, employer-sanctioned)
```

So the hierarchy is adopted as the user drew it, with one refinement: the extraction stage
becomes **conditional** — skipped entirely when the source already supplied full text.

```
                        JOB DISCOVERY
                              |
        +---------------------+---------------------+
        v                     v                     v
   ATS / API             Feeds / RSS          Global job boards
 Greenhouse, Lever      WWR, RemoteOK        (125 site queries from
 Ashby, Recruitee,      Remotive,             portal-tiers-cache v4)
 Workable, SmartRec     Himalayas, Jobicy
 (+ Arbeitnow, itself
  ATS-aggregated)
        |                     |                     |
        +---------------------+---------------------+
                              v
                   CANONICALIZE + FINGERPRINT
                      (cross-source dedup)
                              v
                   full JD text already present?
                         +----+----+
                      yes|         |no
                         v         v
                   SKIP EXTRACT   Tavily discovery (gap-fill only)
                         |         v
                         |      FetchLadder (direct HTTP)
                         |         v
                         |      Firecrawl fallback
                         +----+----+
                              v
                        GATES -> SCORE
```

**Tavily's role is redefined, not demoted.** It stops being the entry point and becomes:
(a) discovery of employers and postings **outside** the known feeds — genuinely what it is
best at; (b) the gap-filler when a board gives only a title and a link; and (c) the source
of the weekly vocabulary/portal refresh. Querying Tavily for a posting we could have
fetched from its employer's own ATS is the anti-pattern this section exists to prevent.

The §2 legal analysis is unchanged and reinforced: path B leans on employer-sanctioned
endpoints, so the terms question largely disappears for the primary sources.

### The `portal-tiers-cache.json` list is present and usable

The user's global job-board list **was found in the repo**:
`GUIDE/JOBORA-CLAUDE/fullstack cv/portal-tiers-cache.json`, **version 4**,
`populated: true`, last refreshed 2026-09-11. It contains **125 `site_query` entries**, plus:

- `tier_definitions` 1–4 (mega employment · specialized remote/tech · long-tail/agency · freelance)
- `geography_policy` with `candidate_base`, `prefer_locale_codes` (`pt`, `es`, `uk`, `us`,
  `global`), `allow_locale_codes` (+ `eu`, `de`, `fr`, `nl`, `ie`, `it`, `ca`), and
  `deny_regions` (Asia, Middle East…)
- `multinationals` with per-brand locale hosts (LinkedIn, Indeed, InfoJobs, Glassdoor,
  Monster, Randstad, StepStone…)
- `discovery.rotation_index` for expansion rotation

This is a **seedable source registry**, not something to re-derive. It becomes the seed for
`studio_sources` + `studio_source_locales` (task T-010). Its `deny_regions` and
`prefer_locale_codes` map onto `studio_profiles.geography_deny` / `geography_prefer`,
already specified in `plan.md` §4.2.

---

## §8 — Tavily: direct API vs MCP as the production path (added 2026-09-18)

### Evidence

| Path | Attempts | Result |
|---|---|---|
| Tavily **MCP** `tavily_search` | many | Working. Every finding in §1, §2, §3, §5 came from it. |
| Tavily **MCP** `tavily_research` | **3** on 2026-09-18 — including one after the user reported the plan was updated, and one on the cheaper `mini` model | **HTTP 432 — "This request exceeds your plan's set usage limit"** on all three. Definitive for the MCP path. |
| Tavily **direct API** (`api.tavily.com`, project's own `TAVILY_API_KEY`) | probed 2026-09-19 (T-001, status codes only, key never printed) | **`/search` → 200; `/research` → 422** (endpoint exists, needs different params/plan). `tavily_search` over the direct API is the load-bearing capability, and it is proven. |

### Conclusion: direct API is the production path; MCP is development tooling only

This holds on architecture grounds independent of the 432; the 432 merely illustrates it.

1. **MCP is not reachable from a queue worker.** Discovery runs inside
   `DiscoverPostingsJob` under a queue worker. MCP servers are a client-side developer-tool
   transport attached to this editor session. A production job cannot call one. This alone
   settles the question.
2. **The adapter already exists.** `Shared/Infrastructure/Research/TavilyResearchAdapter` +
   `TavilyClientInterface`, configured at `config/services.php:68-73` with `TAVILY_API_KEY`,
   `TAVILY_SEARCH_URL`, `TAVILY_SEARCH_DEPTH`, `TAVILY_MAX_RESULTS`. That config's own
   docblock records it is fail-soft — an empty key returns null without a request.
3. **Separate quotas, separately observable.** The MCP connection hit a plan cap while the
   project key's state is independent. Production spend must be metered by our own
   `BudgetLedger` against our own key (FR-33), which is impossible through a transport we
   do not meter.
4. **Determinism and testability.** `TavilyClientInterface` can be faked in Pest. An MCP
   tool call cannot.

**Therefore:** *MCP-based sources must never become the production path merely because they
are available* — adopted verbatim as an architectural constraint, asserted by test
(task T-046). MCP stays what it is here: a research instrument for these spec documents.

**On `research`/deep mode:** it is capped and therefore `[UNVERIFIED]` as a production
capability. The plan must not depend on it. Its plausible niche — the weekly portal and
vocabulary refresh, where one deep call might replace several searches — is worth measuring
**only after** T-001 confirms the direct API allows it, and even then it is an optimisation
of a weekly job, never a pipeline dependency. `tavily_search` over the direct API is the
load-bearing capability, and it is proven.

---

## §9 — Outcome data supplied by the user (added 2026-09-18)

Reported directly: of the 13 recorded applications, **only Cognition Technology replied;
the rest were total silence.** Several cached postings **had already expired** by the time
they were revisited.

| Implication | Consequence |
|---|---|
| 1 usable outcome out of 13 | Below the 8-outcome minimum the correlation table requires (`spec.md` FR-28). The report must **say so** rather than render a table that looks authoritative on n=1. This is the guardrail working, not a gap. |
| 12 silences, ~8 already past 21 days | The ageing rule (FR-29) has material input immediately: analysis treats aged silence as non-reply, the stored value stays `unknown`. |
| Postings expired before being acted on | **New requirement, not previously captured.** A posting can die between discovery and application, so scoring a dead posting wastes the entire pipeline and pollutes the reach table. Needs liveness re-checking and an expiry state. Drives the `plan.md` §4.2 change flagged as **CHG-3** and task **T-016**. |
| Freshness is worth more than the current `F` weight implies | `D_disc` gives freshness only 0.10. With confirmed expiry in the corpus, this is a candidate for re-weighting — but as a **measured** change under rules version 2 (task T-064), not an assumed one. |

---

# Research round 2 — 2026-09-18 (afternoon)

Triggered by the second `/ssd-artifact` request: *"quiero es fullstack full incluyendo formula
matematica y algoritmica best match score ats … funciona al 85% … use tavily si se puede
mejorar mas o asi esta bien"*, with Firecrawl and Context7 explicitly requested.

**Tooling this round:** `mcp__tavily__tavily_search` (advanced, `time_range` week/month/year) ·
`mcp__firecrawl__firecrawl_scrape` (AIApply, Enhancv — live pages) · `mcp__context7__query-docs`
(`/websites/laravel_13_x`, `/laravel/ai`) · local measurement over
`GUIDE/JOBORA-CLAUDE/fullstack cv/job-search-cache.json` and `composer.lock`.
`tavily_research` was not retried (§8: HTTP 432 three times). Vendor blogs are marked
**[VENDOR]** — directional evidence, not independent measurement.

---

## §10 — Empirical audit of the local pipeline's own outcomes (measured)

Computed with `node` over the fullstack cache (33 entries, 6 runs, 2026-07-30 → 2026-09-11):

| Measure | Value |
|---|---|
| Postings cached | 33 — `applied` 16 · `skipped` 11 · `new` 6 |
| Applied → outcome | **screening 1** (Coalition Technologies, score **70**) · **rejected 1** (Quental, 88) · **no_reply 14** |
| Positive-outcome rate | 1/16 = **6.25 %**, Wilson 95 % CI ≈ **1.1 % – 28.3 %** |
| Highest-scored applications | 90, 89, 87, 85, 84, 84 → **all silent** |
| Caps recorded | `lang_cap_b2` ×4 · `years_cap` ×2 · `evidence_cap` ×1 |

Applied postings split by **how they were reached**:

| Apply channel | Applied | Any reply | Positive |
|---|---|---|---|
| LinkedIn listing (Tavily-discovered) | 4 | 0 | 0 |
| Aggregator / repost (JobLeads ×2, Jobgether, RemoteRocketship ×2, Remotive, MotorsportJobs, HotRemoteJobs) | 7 | 0 | 0 |
| Job board with an employer-posted ad (WeAreDevelopers, JustJoin.it ×2) | 3 | **2** | **1** |
| Employer's own site (jobs.secureit.ch, mailerlite.com) | 2 | 0 | 0 |

Split by **title seniority**: 7 "Senior"-titled applications → 0 positive (1 rejection);
9 non-senior → 1 positive.

### What this does and does not show

- **n = 16 cannot rank anything statistically.** Every row above is directional — and §17.2
  quantifies it: Fisher exact p = 1.00 (seniority), 0.31 and 0.08 (channel).
- **Corrected 2026-09-18 (evening) by `analyze.md` A-10:** the original bullet here read *"the
  fit score is not the bottleneck"*. That was an overclaim. The single positive came from the
  lowest applied score and the top six were silent, but with **one** positive in 16 the data
  can show neither that fit orders outcomes nor that it does not. What *is* supported: nothing
  in these 16 contradicts the fit formula, and nothing in them validates it as a predictor of
  replies either — it was never designed as one.
- **1/16 sits inside the range published baselines report** (§12, §17.1: 2–3 % average
  response; interview rates 1.95–6.87 % by channel in a self-selected tracker population whose
  metric differs from ours). It is not evidence of a failing pipeline; it is also not enough to
  call it a success.
- **The cache has no `posted_at`** on entries, so posting age at apply time cannot be
  measured retroactively. The module must capture it (→ spec FR-40).

## §11 — Competitor UX, verified live (resolves §5's `[UNVERIFIED]`)

### AIApply (`aiapply.co`, Firecrawl scrape 2026-09-18)

- Job card = logo · title · company · **salary · location · seniority badge** · one paragraph
  **"Why this is a good fit"** · qualifications list with **✓ / ✗ per requirement** ·
  **"89/100 Excellent fit"**.
- Products: resume builder, cover letter, **Auto-Apply** (credit packs; applies on the user's
  behalf — reviews celebrate "100 jobs in under an hour"), mock interview, live "Interview
  Buddy", resume translator, ATS scanner, 20M+ job board. Export PDF or Word; share link.
- **Posture: volume.**

### Enhancv (`enhancv.com/resources/resume-checker/`, Firecrawl scrape 2026-09-18)

- **Two-tier score:** (1) share of content the parser could interpret, (2) content-quality
  issues. States explicitly *"there's no such thing as an ATS score"*.
- **27 checks in 7 categories:** ATS essentials (file format/size, design, email, header
  links, file name, date/link consistency) · sections (essential sections, contact, order) ·
  content (parse rate, quantified impact, repetition, spelling, bullet length) · job
  tailoring (hard skills, soft skills, action verbs, **tailored job title**) · recruiter red
  flags (credibility, interview risk, peer benchmark, **LinkedIn profile match**) · **bias &
  discrimination (age/date bias, employment gaps)** · seniority & impact (career
  progression, skills evidence, leadership signals).
- Claims double-column résumés parse as well as single-column, and PDF beats Word.
- AI job search over 1M+ ads with a match score per listing; job tracker keeping the résumé
  and cover-letter version per role; 20+ languages.

### Jobscan (Tavily; sources dated 2026-06 → 2026-08)

- Match Rate report, 30+ checks; **detects the employer's ATS per posting** (Workday,
  Greenhouse, Taleo…) and tailors advice to it; LinkedIn optimizer; free job tracker.
- New **credit-based Auto Apply, review-gated** — nothing submits without user review;
  2 credits/month included; sources jobs from Lever, Workable and 20+ ATS platforms.
- Main 2026 criticism: match-score chasing encourages keyword stuffing.
  — <https://www.resumly.ai/best/best-ai-job-search-tools>,
  <https://www.finalroundai.com/blog/jobscan-review-pros-cons>,
  <https://www.jobscan.co/blog/greenhouse-ats-what-job-seekers-need-to-know>

### Adopt / reject

| Pattern | Source | Verdict |
|---|---|---|
| Posting card with ✓/✗ per requirement + fit narrative + score | AIApply, Jobscan | **Adopt** — `studio_skill_matches` already holds it; the LLM writes the narrative, never the number |
| Two-tier audit: *parse rate we measured* + *content issues* | Enhancv | **Adopt** — our parse rate is real (we re-extract our own PDF, FR-6), not estimated |
| Checks: tailored job title, date/age bias, employment gaps, career progression, skills evidence | Enhancv | **Adopt** into US-1's audit |
| Per-posting ATS detection + ATS-specific advice | Jobscan | **Adopt detection now** (URL patterns, cheap); per-ATS advice stays deferred (plan OD-2) |
| Tracker holding the exact CV + letter version sent per role | Enhancv, Jobscan | **Adopt** — already FR-24; render as a board by status |
| Mass Auto-Apply | AIApply | **Reject** — contradicts §12 (direct, early, targeted wins) and spec §8 |
| "Double-column parses fine" | Enhancv | **Reject for us** — tested on Enhancv's own builder output; §1 finding 2 and exact-parse ATSs still favour single-column. Contradiction **C-4** |

## §12 — What actually drives a reply: channel, freshness, ghost jobs

1. **Channel.** Across **1.2 M tracked applications (Oct 2024 – Jul 2026)**, interview rate by
   where the candidate applied: company career page **6.87 %** · Google Jobs 6.60 % ·
   Wellfound 3.75 % · Indeed 2.92 % · Glassdoor 2.89 % · **LinkedIn 1.95 %** · ZipRecruiter
   1.12 %. Direct converts at >3× LinkedIn. Stated reasons: smaller pool, stronger intent
   signal, and board integrations sometimes submit a stored profile instead of the file.
   — <https://www.uppl.ai/job-application-response-rate> **[VENDOR]**
2. **Freshness.** Applying within 24 h of posting: **2–4× higher response rate** than a week
   later; average response 2–3 %; Easy Apply 1–2 %; direct 3–5 %.
   — <https://www.loopcv.pro/it/guides/job-application-response-rate> **[VENDOR]**
3. **Ghost jobs are measured, not anecdotal.** **Greenhouse's own data: 19 % of postings in
   Q2 2026 showed no meaningful hiring activity after receiving applications** (WSJ, via
   Yahoo Finance). ~27 % of active US LinkedIn postings estimated as likely ghosts. Ontario
   disclosure rules effective 2026-01-01; Pennsylvania, California and New Jersey bills
   pending; Texas AG investigating LinkedIn over fake job ads.
   — <https://finance.yahoo.com/small-business/articles/40-companies-posted-fake-job-153000925.html>,
     <https://www.fromdayone.com/stories/2026/9/9/ghost-jobs-are-about-to-get-expensive-for-employers>
4. **AI application volume is the noise source.** 67 % of HR leaders say AI-generated
   applications are slowing hiring (Robert Half, via From Day One, 2026-09-09).

### Consequence

The local pipeline optimises **fit** and treats every ≥70 posting as equally worth an
application. §10 + §12 say the order *within* the ≥70 band should come from the
**probability that anyone reads the application**: channel, age, ghost risk, title level.
That is a different quantity from fit and must stay separate — the JOBORA rule *"outcomes
never revise `match_score`"* is correct and is kept. → new **Opportunity layer**, §16 and
spec US-9.

## §13 — Matching algorithms: state of the art 2026

1. **TalentCLEF 2026** (CLEF shared task on job–person and job–skill matching): the strongest
   systems were **hybrid and modular** — multi-stage retrieval + reranking; semantic
   representations combined with **LLM-generated structured views** (extracted skills,
   tasks, experience); **ESCO-graph calibration**; weighted Reciprocal Rank Fusion; BM25 +
   BGE-M3; rerankers such as Cohere-rerank-4. Single-model retrieval lost.
   — <https://arxiv.org/pdf/2606.31692>
2. **JobMatchAI (2026):** white-box reranking through a **multi-factor utility decomposed
   into skill fit, experience, location, salary and semantic similarity**, with **the LLM
   narrating pre-computed scores, never producing them**; hybrid BM25 + ANN + skill graph
   reached NDCG@10 0.81 (+7 % over BM25) — on a 20-query gold set the authors call small.
   — <https://arxiv.org/html/2603.14558v2>
3. **ESCO taxonomy** (European Commission): open data + API, **multilingual (ES, PT and EN
   among 27+ languages)**, hierarchical skill relations. Multilingual skill extraction
   aligned to ESCO measurably improves vacancy–seeker matching.
   — <https://aclanthology.org/2025.genaik-1.15.pdf>
4. **Caveat — pure-embedding taxonomy linking is weak:** linking free-text skills to ESCO
   skills by embeddings alone scores MRR ≈ 0.12–0.22 (arXiv 2512.03195). Embeddings are a
   *candidate generator* for aliasing, not a judge.
   — <https://arxiv.org/html/2512.03195v1>

### Verdict on "can the formula be improved, or is it fine?"

**The shape is right and stays.** Gates → `0.45·H + 0.25·S + 0.30·D` → caps, with an LLM
that extracts and explains but never scores, is the architecture TalentCLEF 2026's winners
and JobMatchAI converged on. Nothing found argues for new weights.

**Three algorithmic upgrades are justified**, folded into rules version 2 *before* any code
exists (v2 is unimplemented, so no extra version bump):

| # | Upgrade | Replaces | Evidence |
|---|---|---|---|
| U-1 | **Partial credit (0.5) decided by a skill-relation table**, seeded from the JOBORA alias table + an ESCO ICT subset; an embedding may *propose* a relation, the candidate confirms it once, after which it is deterministic | LLM judgement on every run | §13.1, §13.4 |
| U-2 | **`S` at requirement level (MaxSim):** each JD responsibility is matched to its best CV bullet, calibrated and weighted — instead of one whole-document cosine | Whole-doc cosine (plan §1) | §13.1 structured views; plan RK-1 |
| U-3 | **Evidence retrieval = the RAG over the main CV:** the same MaxSim pass returns the top-k CV bullets per requirement, feeding the rewriter and the ✓ evidence text | A separate retrieval step | spec A-11 / clarify Q6 |

The remaining gains the evidence points to lie **outside** the fit formula — where and when to
apply (§12, §16) — as an *ordering* supported by external data, not as a measured effect for
this candidate (wording corrected by `analyze.md` A-10; see §17.1–§17.2).

## §14 — Laravel 13 + `laravel/ai`, verified via Context7 (supersedes part of §3)

Installed (read from `composer.lock`): `laravel/framework` **v13.26.1** · `laravel/ai`
**v0.11.0** · `phpoffice/phpword` **1.4.0** · `smalot/pdfparser` v2.12.5 ·
`barryvdh/laravel-dompdf` v3.1.2 · `spatie/laravel-data` 4.23.0. No `pgvector/pgvector`, no
Scout — neither is needed (below).

1. **Native vector support in Laravel 13** (`/websites/laravel_13_x`, docs `ai-sdk`,
   `queries`, `search`):
   - `Schema::ensureVectorExtensionExists();` and `$table->vector('embedding', dimensions: 1536);`
   - `whereVectorSimilarTo('embedding', $vectorOrString, minSimilarity: 0.4)` — also accepts a
     **string** and embeds it on the fly; `order: false` keeps a custom order.
   - `selectVectorDistance()`, `whereVectorDistanceLessThan()`, `orderByVectorDistance()`.
   - Eloquent cast `'embedding' => 'array'`; HNSW index recommended; composes with ordinary
     `where('user_id', …)` scoping.

   **Consequence:** plan §4.3's raw-SQL `halfvec(1536)` path is **superseded**. At this
   module's scale (thousands of rows, not millions) `halfvec`'s 50 % storage saving is
   immaterial, while the native builder gives idiomatic, testable queries. Use `vector` via
   the schema builder; add the HNSW index and the `hnsw.iterative_scan` session setting by
   raw statement (the builder does not cover those). → contradiction **C-5**.
2. **`laravel/ai` Reranking:** `Reranking::of($docs)->limit(5)->rerank('query')`. This repo's
   `config/ai.php` already sets `'default_for_reranking' => 'cohere'` and
   `'default_for_embeddings' => 'openai'`. Usable at the shortlist stage but **must not enter
   the fit score** — it is a provider-dependent relevance model, not a stated formula.
3. **`laravel/ai` structured output:** agents implement `HasStructuredOutput` with
   `schema(JsonSchema $schema)`; responses are array-accessible; queued runs via
   `broadcastOnQueue()`. This is the seam for requirement extraction and CV parsing
   (plan §3.2) — the JSON schema *is* the prompt-injection containment boundary.
4. **Embedding caching is config-driven** (`caching.embeddings.cache/store/individually` in
   `config/ai.php`) on top of `->cache()`.
5. **Eloquent conventions, Laravel 13 and this repo:** attribute-style `#[Table]` (51 models
   here) and `#[Fillable([...])]` (58 models here vs 3 legacy `$fillable`); `HasUuids`
   generates **UUIDv7**; `Prunable` for retention (expired postings, provider-call logs). The
   plan must use the attribute style.

## §15 — Legal and tooling update, September 2026

1. **EU AI Act — Digital Omnibus on AI, Regulation (EU) 2026/1744**, published 2026-07-24,
   in force 2026-07-27: Annex III high-risk obligations — which cover AI used **by
   employers** to recruit, filter applications or evaluate candidates — move from
   2026-08-02 to **2027-12-02**. Prohibited practices and AI-literacy duties already apply;
   Art. 50 transparency applies from 2026-08-02.
   — <https://smartersearch.nl/insights/eu-ai-rules-hiring-2026>,
     <https://nalgo.co.uk/blog/digital-omnibus-ai-act-delay>

   **Reading for this module:** it evaluates *job postings* for the candidate's own use; it
   does not evaluate natural persons on behalf of an employer, so Annex III does not
   describe it. If it were ever offered to third parties as a service, re-assess.
   **[not legal advice]**
2. **LinkedIn, Sept 2026:** courts draw the line at login — logged-out public access is not
   bound by the ToS the way an account holder is; logged-in, fake-account or session-riding
   collection is squarely enforceable. **LinkedIn sued ProAPIs on 2025-10-03** (N.D. Cal.)
   over an "industrial-scale fake account mill". GDPR applies to any identifiable EU person's
   data regardless of login state.
   — <https://gtm-api.com/is-scraping-linkedin-legal>,
     <https://inboundlabs.app/blog/is-linkedin-scraping-legal>

   **The candidate holds a LinkedIn account**, so the account-holder reading applies. The §2
   stance stands: **LinkedIn URLs are discovered through search and linked, never fetched.**
   New this round: **resolve to source** (§16) — find the *same* posting on the employer's own
   ATS endpoint and use that: legal, and the higher-reply channel (§12.1).
3. **Firecrawl:** social-media scraping is **blocked** by Firecrawl itself; v2.11.0
   (2026-06-19) added a `deterministicJson` format that caches a per-site extractor so repeat
   scrapes skip the LLM — relevant for recurring career-page extraction cost. Scrape, crawl
   and map bill 1 credit per page.
   — <https://scrape.do/blog/firecrawl-alternatives>,
     <https://devtoollab.com/blog/best-web-scraping-apis-for-ai>
4. **Crawl4AI re-checked:** Apache-2.0 Python library, self-hosted (~$20–40/month VPS), no GA
   managed cloud. Its value is unmetered crawling at volume. Firecrawl's `map` endpoint
   already enumerates a site's URLs ("ver todos los enlaces"). The same 2026 comparisons
   advise staying on Firecrawl below ~1,000 pages/month.
   — <https://www.capsolver.com/blog/AI/crawl4ai-vs-firecrawl>,
     <https://roundproxies.com/blog/best-firecrawl-alternatives>

   **Verdict unchanged: no FastAPI, no Crawl4AI.** §7 made structured sources primary, so
   page crawling is a minority path; the volume that would justify self-hosting is absent.

## §16 — Net recommendations of round 2

1. **Keep the fit formula's shape and constants** (§13 verdict). Fold U-1, U-2 and U-3 into
   rules version 2.
2. **Add an Opportunity layer, separate from fit:**

   ```
   fit             = MatchScore (0–100, rules v2)            — meaning unchanged
   Q               = q_channel · q_fresh · q_ghost · q_level  — each in [floor, 1]
   apply_priority  = round(fit · Q)                           — orders the ≥70 apply list
   ```

   - `q_channel` — prior from §12.1 relative to direct (LinkedIn ≈ 0.28, Indeed ≈ 0.43,
     Google Jobs ≈ 0.96, direct = 1.0); channels absent from that dataset (niche board,
     aggregator repost, staffing agency) get provisional priors **[UNVERIFIED]**.
   - `q_fresh = max(floor, 2^(−age_days / h))`, half-life `h` provisionally **5 days**
     (§12.2 implies 3.5–7).
   - `q_ghost = 1 − min(0.6, Σ signal weights)` over: no posted date · age > 30 d · same
     fingerprint re-listed ≥ 3 times over ≥ 30 d · "talent pool / general application"
     wording · aggregator-only presence with no employer copy found.
   - `q_level` — title seniority vs the profile's band (one level above ≈ 0.6 prior).
   - **Learning without lying:** each `q_*` bucket is a Beta-Binomial posterior — prior
     `Beta(m·r, m·(1−r))` from the published rate `r` with pseudo-count `m` (default 30),
     updated with the candidate's own positive outcomes (`screening|interview|offer`).
     Sixteen applications move a prior only slightly — the intended shrinkage at small `n`.
     Fit is **never** touched by outcomes.
3. **Resolve to source.** For any posting reached via LinkedIn or an aggregator, probe the
   employer's ATS endpoints (§7 table) for a title/location match (`pg_trgm` similarity); on
   a hit, attach it as a source, take the JD from the API, and surface **"Apply direct"**.
4. **Detect the ATS per posting** from URL patterns (`boards.greenhouse.io`, `jobs.lever.co`,
   `jobs.ashbyhq.com`, `*.myworkdayjobs.com`, `*.icims.com`, `*.recruitee.com`,
   `apply.workable.com`, `*.teamtailor.com`, `*.smartrecruiters.com`, `*.personio.*`, …).
5. **Capture `posted_at`, liveness and re-listing history** on every posting — the
   Opportunity layer is impossible without them (§10's missing field).
6. **Use Laravel 13's native vector API**, drop `halfvec` (§14.1).
7. **The audit (US-1) gains Enhancv's missing categories:** tailored job title, date/age
   bias, employment gaps, career progression, skills evidence — plus a measured parse rate.
8. **No auto-apply — now on evidence** (§11, §12.4), not only on principle.

### New contradictions

| # | Contradiction | Goes to |
|---|---|---|
| C-4 | Enhancv: double-column parses fine · §1 and the local rule: single-column only | Resolved here — keep single-column (Enhancv tested its own builder output; exact-parse ATSs remain) |
| C-5 | Plan §4.3 `halfvec` raw SQL · Laravel 13 native `vector` API | `clarify.md` Q14 — resolved by default to native `vector` |
| C-6 | JOBORA treats every ≥70 posting as equally apply-worthy · §10/§12 show reply probability varies >3× by channel and 2–4× by age | `clarify.md` Q12 — Opportunity layer |
| C-7 | Spec v1 scope = four flows · the user now asks for the fullstack flow "full" | `clarify.md` Q11 |

---

# §17 — Verification round (2026-09-18, evening)

The user blocked implementation on three `[UNVERIFIED]` items — *"yo no dejaría que empiece a
programar sin verificarlas"* — and added a fourth question: whether the module must search
specific portals (Tecnoempleo, Indeed, Net-Empregos, LinkedIn, RemoteRocketship…) or whether
keywords are enough. Each item below states **what was checked, how, the result, and the
decision it forces**. No module code was written; the only execution was a throwaway probe in
the session scratchpad, inside a transaction that was rolled back (§17.3).

## §17.1 — V-3: the "1.2 M applications" figure (primary source verified)

**How.** Firecrawl scrape of the secondary source (`uppl.ai/job-application-response-rate`,
updated 2026-08-29), which names its source; then Firecrawl scrape of the primary source,
**Huntr, "The Best Job Boards of 2026, Ranked by Interview Rate"** (published 2026-07-19),
including its methodology section.
— <https://huntr.co/research/best-job-boards>

**What the primary source actually says**

| Point | Detail |
|---|---|
| Population | **1,244,654 applications logged by users of the Huntr tracker**, 2024-10-01 → 2026-07-01. Self-reported. The authors: *"Rates reflect the behavior of Huntr users, not a controlled experiment; boards differ in the roles, regions, and applicant habits they attract."* uppl adds: tracker users "tend to be more organised than average — treat the absolute numbers as indicative and the relative ranking as the useful signal". |
| Metric | **"at least one interview logged"** — not "any reply". Our positive outcome (`screening\|interview\|offer`) is broader at the screening end. |
| Composition | **LinkedIn = 970,190 applications = 78 %** of the dataset. |
| The 6.87 % | **Not in the 25-board ranking.** It is a separate grouping: company **career page** 6.87 % · applications **straight into an ATS "such as Workday or iCIMS" 3.16 %** · job boards + aggregators 1.95 %. |
| Stability | LinkedIn: 1.95 % overall · 2.94 % (Q1-2026 trailing twelve months) · 3.1 % (2025 annual) · **4.00 % (Q2 2026 alone)**. Google Jobs: 6.60 % · 7.12 % · 11.3 % · 7.72 %. Metric definitions differ between those reports. **The ordering holds in every cut; the magnitudes do not.** |
| Coverage gaps | Remote-only boards (We Work Remotely, Remote OK, FlexJobs) **did not reach the 1,000-application bar and are not ranked**. No Spanish or Portuguese portal appears (regional coverage is LatAm, France, UK, ANZ). |
| Secondary claims | uppl's *"apply within 24–48 h → 2–3× callbacks"* **cites no source**. Huntr's own tailored-résumé cut: 5.7 % vs 3.1 % (same population caveats). |

**Consequences**

1. The user's objection is correct: this is an **observational, self-selected, US-heavy,
   LinkedIn-dominated** sample measuring a different outcome. It cannot be a system rule.
2. **Plan error found:** CHG-9 mapped both `employer_site` and `employer_ats` to 6.87 %. The
   source separates career pages (6.87 %) from ATS-hosted applications (3.16 %), and that
   split is itself confounded — "Workday or iCIMS" means large enterprises with large applicant
   pools, while the Greenhouse/Lever/Ashby boards this module prefers belong mostly to smaller
   companies. **Neither number maps cleanly onto our destinations.** CHG-9's channel table is
   withdrawn (→ plan CHG-16).
3. What survives as evidence is **ordinal**: *the closer the application lands to the employer,
   the better* — replicated across every Huntr cut. That is **grade B** evidence for an
   ordering, and grade C at best for any magnitude.

## §17.2 — V-1: the provisional priors (aggregator, agency, seniority)

**External search** (Tavily, advanced; queries on aggregator/repost reply rates, staffing-agency
postings vs direct, seniority/over- and under-qualification callback rates):

| Prior | What exists | Grade |
|---|---|---|
| Aggregator repost | **No rate data found.** Qualitative only: RemoteRocketship scrapes employer career pages without their consent (a founder: *"we were immediately flooded with bad applications"*); Huntr: aggregators keep the application in their own pipeline, search engines route it to the source. — <https://trustedremotejobboards.com/blog/is-remote-rocketship-legit> | D (magnitude) |
| Staffing agency | **No rate data found.** Only vendor explainers on agency vs direct hire. | D |
| Seniority (+1 level) | **Direction only:** uppl, qualitatively, "the higher the seniority, the lower the response rate"; Pin.com (500 K rejections, vendor): in engineering the top merit rejection reason is "not enough experience" (16.3 %), then missing skills (15.4 %). — <https://www.pin.com/blog/top-rejection-reasons-modern-hiring> **No magnitude anywhere.** | C (direction) |

**Internal analysis — can the candidate's own outcomes set them instead?** (computed with
`node` over the fullstack cache; exact tests, not approximations)

| Question | Result |
|---|---|
| Senior-titled vs other, positive outcome (0/7 vs 1/9) | Fisher exact **p = 1.000** — no evidence either way |
| LinkedIn + aggregator vs board + direct, positive (0/11 vs 1/5) | **p = 0.312** |
| Same split, any reply (0/11 vs 2/5) | **p = 0.083** — suggestive, not significant |
| Applications per group to detect 3 % vs 6 % (α 0.05, power 0.8) | **749** |
| … 2 % vs 7 % | 269 |
| … 3 % vs 4.5 % | 2,518 |
| Time from `first_seen` to `applied_at` | **0 days in all 14 dated applications** — the candidate already applies the day a posting is found; what is unknown is how old the posting was when found (no `posted_at`) |

**Prior-strength sensitivity — a flaw in CHG-9.** Two buckets sharing prior rate 3 %, fed the
real seniority data (0/7 vs 1/9):

| Prior strength `m` (pseudo-applications) | Resulting level ratio |
|---|---|
| **30 (CHG-9 as written)** | **0.50** — one single positive outcome halves the Senior factor |
| 100 | 0.76 |
| 300 | 0.91 |

At base rates of 2–7 %, `α = m·r` is below one pseudo-success when `m = 30`, so the posterior is
dominated by whichever bucket happened to receive the one positive. **CHG-9's "learned from your
outcomes" was, in practice, "decided by one outcome".**

**Verdict.** The three priors **cannot be verified** — not externally (no magnitude exists),
not internally (hundreds of applications per bucket would be needed). Therefore they must stop
being presented as statistics. Resolution proposed in plan **CHG-16** and asked as
`clarify.md` **Q19**:

1. Separate **discovery channel** (where the posting was found) from **apply destination**
   (where the application lands). Only the latter can plausibly affect replies.
2. Every opportunity constant carries an **evidence grade** — A own outcomes · B external
   dataset with published method (ordering only) · C external claim without method ·
   D assumption — shown in the UI with its source.
3. Magnitudes of grade B–D are **the candidate's own policy**, editable in settings and
   labelled "policy", never "statistic".
4. **Evidence gate:** own outcomes replace a policy value only once the compared buckets have
   ≥ 30 applications with a usable outcome and ≥ 3 positives between them; below that they are
   displayed with a credible interval and not applied. After the gate, prior strength
   `N0 = 100` pseudo-applications.
5. A **neutral mode** (`Q = 1`) stays one toggle away, and SC-13 keeps proving that `Q = 1`
   reproduces pure fit order.

## §17.3 — V-2: does Laravel's vector query use the index? (measured)

**How.** A throwaway script in the session scratchpad bootstrapped the application and, inside
**one transaction that was always rolled back**: created the `vector` extension, a temporary
table (20,000 random 3-d vectors, `user_id` 1–5, two `owner_type` values, nullable
`deleted_at`), an HNSW index `USING hnsw (embedding vector_cosine_ops) WITH (m = 16,
ef_construction = 64) WHERE deleted_at IS NULL`, ran `ANALYZE`, then printed the SQL generated
by Laravel's builder and `EXPLAIN` for four query shapes. Database: PostgreSQL 17.6 (Supabase).

**Generated SQL (Laravel 13.26.1):**

```sql
-- selectVectorDistance + orderByVectorDistance
select "id", ("embedding" <=> '[0.1,0.2,0.3]') as "distance" from "studio_probe"
 where "deleted_at" is null order by ("embedding" <=> '[0.1,0.2,0.3]') asc limit 3
-- whereVectorSimilarTo(..., minSimilarity: 0.4)
select "id" from "studio_probe" where "deleted_at" is null and "user_id" = 2
 and ("embedding" <=> '[0.1,0.2,0.3]') <= 0.6 order by ("embedding" <=> '[0.1,0.2,0.3]') asc limit 3
```

**Results:**

| Query shape | Plan | Rows for `LIMIT 3` |
|---|---|---|
| Unfiltered `orderByVectorDistance` | `Index Scan using studio_probe_hnsw` · `Order By: (embedding <=> …)` | 3 |
| Filtered `user_id` + `owner_type`, default settings | `Index Scan using studio_probe_hnsw` + `Filter` | **2 — silent under-return** |
| Same, after `SET LOCAL hnsw.iterative_scan = strict_order` + `ef_search = 100` | same plan | **3** |
| `whereVectorSimilarTo` (minSimilarity 0.4) with filter, iterative scan on | `Index Scan using studio_probe_hnsw` | 3 |

Before the probe the extension was **not enabled** (available 0.8.2); after rollback it is
**still not enabled** — nothing persisted.

**Conclusions**

1. **Verified:** the builder emits `<=>` (cosine distance), which is exactly what a
   `vector_cosine_ops` index serves; Postgres used the HNSW index in all four shapes.
2. **`minSimilarity` means `1 − cosine distance`** — 0.4 becomes `distance <= 0.6`.
3. **The filtered-HNSW trap is real on this instance, not theoretical:** the default filtered
   query returned 2 rows for `LIMIT 3` with no error. `SET LOCAL hnsw.iterative_scan =
   strict_order` fixed it. It is **mandatory** for every filtered similarity query in the
   module, and must run inside the same transaction as the query (`SET LOCAL`).
4. The partial index (`WHERE deleted_at IS NULL`) is matched by `whereNull('deleted_at')`,
   which `SoftDeletes` adds automatically — consistent with CHG-15.
5. Also proven: the connection's role **may `CREATE EXTENSION vector`** (plan RK-8's grant
   risk is cleared for `vector`; `pg_trgm` and `unaccent` were not probed — task T-002).
6. **Limits of this probe:** 3 dimensions and random data, not 1536-d real embeddings. The
   operator/index match is dimension-independent; recall under filters is data-dependent and
   is measured again by T-000 and T-052 on realistic shapes.

## §17.4 — Test-environment constraint discovered while planning V-2

`phpunit.xml` runs the suite on **SQLite in memory** (`DB_CONNECTION=sqlite`,
`DB_DATABASE=:memory:`). SQLite has no `vector` type, no `<=>`, no `pg_trgm`, no `unaccent`, and
no guaranteed `power()`/`EXTRACT(EPOCH …)`. Consequences for the plan (→ CHG-17):

- Every similarity query sits behind a port with an **in-memory cosine fake** for the default
  suite, plus a **pgsql-only Pest group** for the real adapter (T-000, T-052).
- Title matching for resolve-to-source is a **pure PHP trigram similarity**, not SQL `pg_trgm`.
- CHG-9's read-time SQL `ORDER BY … power(2, …)` is **not portable** → `apply_priority` is
  materialised and lazily refreshed with an injected clock; ordering is a plain column.
- The pgsql group needs a Postgres test database that is **not** the shared development
  database → `clarify.md` Q20.

## §17.5 — Portals or keywords? (measured)

**Evidence from the candidate's own history** (33 cached postings, by host): LinkedIn 10 ·
**JustJoin.it 6** · WeAreDevelopers 2 · Tecnoempleo 2 · RemoteRocketship 2 · ITJobs.pt 1 ·
Net-Empregos 1 · Remotive 1 · WeWorkRemotely 1 · NoDesk 1 · JobLeads 1 · Jobgether 1 ·
HotRemoteJobs 1 · MotorsportJobs 1 · MailerLite (direct) 1 · SecureIT (direct) 1.
**23 of 33 postings — including the only positive reply (JustJoin.it) and the only rejection
(WeAreDevelopers) — came from portal-scoped discovery, not from generic search.** Huntr's
ranking points the same way: niche and regional boards beat the giants.

**Answer: keywords alone are not enough.** Generic search skews toward LinkedIn and SEO-heavy
aggregators; regional portals carry inventory that never reaches them. The module needs a
**keywords × portals matrix** — but the *access mode* differs per portal, and it is not a
free choice.

**Access verified on 2026-09-18** (`curl` of `robots.txt` and of candidate endpoints; user
agent rules read for `*` and for named AI crawlers):

| Portal | `robots.txt` (for `*`) | Official machine access | Access mode |
|---|---|---|---|
| **Net-Empregos** (PT) | `Allow: /`; advertises `rss.asp`, `rss_pesquisas.asp` as sitemaps | **RSS** — `rss.asp` 200, 1.2 MB, ISO-8859-1 | `api_feed` — terms of reuse to read (V-5) |
| **ITJobs.pt** (PT) | allowed, `Crawl-delay: 1`; `GPTBot`, `CCBot` fully disallowed | **Public API** — `itjobs.pt/api` docs; `api.itjobs.pt/job/search.json` answers ("missing args") | `api_feed` with key (V-5) |
| **InfoJobs** (ES) | search/listing paths disallowed | **Official API** — `developer.infojobs.net`; `api.infojobs.net` → 401 "client credentials not valid" | `api_feed` after app registration (V-5) |
| **Tecnoempleo** (ES) | `/alertas-empleo-rss.php` **disallowed**; `ClaudeBot`, `Claude-Web`, `anthropic-ai`, `GPTBot` **fully disallowed** | RSS exists but its path is disallowed | **`link_only`** — discovered through search results, never fetched |
| **JustJoin.it** (PL/EU) | `/api/` disallowed; publishes `active-jobs.xml` sitemap | none official (internal API 404/503) | `sitemap` discovery **pending its terms** (V-5), else `search_scoped` |
| **RemoteRocketship** | `Disallow: /` (except API docs) | documents an API; is an aggregator of employer pages | **`resolve_only`** — never an apply destination |
| **Indeed** | job paths (`/emprego/`, `/job/`) disallowed | **no job-search API** (Publisher API deprecated; current APIs cover posting, Apply, sponsored jobs, Hiring Lab); Terms prohibit bots and scrapers without written permission — <https://www.indeed.com/legal> | **`link_only`** |
| **LinkedIn** | `/jobs-guest/`, `/jobs?runSearch*`, `/job-apply/` disallowed | none for retrieval | **`link_only`** (unchanged from §2/§15) |

**Disclosure:** during this check the Tecnoempleo RSS URL was fetched **once** (to confirm it
exists) *before* its `robots.txt` was read. It will not be fetched again, and the module
treats Tecnoempleo as `link_only`.

The access modes become a column on `studio_sources` (→ CHG-18), seeded per portal; the
seeder from `portal-tiers-cache.json` (67 distinct domains, tiers 1–4) assigns a mode to every
domain, defaulting to `link_only` for any domain whose terms have not been checked.

## §17.6 — Net result of the verification round

| Item | Status | Decision / next step |
|---|---|---|
| V-1 priors | **Not verifiable** (no magnitude exists; own data needs ~750 applications per bucket; CHG-9's `m = 30` lets one outcome halve a factor) | Evidence grades + policy values + evidence gate (CHG-16) — **user decision Q19** |
| V-2 vector index | **Verified by measurement** — `<=>`, HNSW used, `iterative_scan` mandatory and proven | Codified as **T-000, the first technical test**, pgsql group |
| V-3 external figures | **Source verified, demoted** — ordinal (grade B), not a rule; plan mapping error corrected | CHG-16 |
| V-4 test database | New blocker from §17.4 | **User decision Q20** |
| V-5 portal terms | Access modes measured; four portals' reuse terms still to read | Phase 0 task, no code |
| Portals vs keywords | **Both** — keywords × portals, per-portal access mode, measured yield | CHG-18 + T-031 / T-148 |

---

# §18 — LLM cost and resilience: prompt caching and failover (2026-09-18, night)

User request: *"recuerda implementar en el plan que laravel ai sdk implemente prompt caching y
failover si amerita para ahorrar tokens"*. The operative words are **"si amerita"** (where it
is worth it). This section establishes what exists, what the SDK supports, and what the
providers actually charge. Caching is not free, so it must be applied selectively and proven
by measurement.

## §18.1 — `laravel/ai` v0.11.0 (Context7 `/laravel/ai`, verified)

| Capability | Verified behaviour |
|---|---|
| Failover | `->prompt('…', provider: [Lab::OpenAI, Lab::Anthropic])`, or the `#[Provider]` attribute with an array of provider→model pairs |
| What triggers failover (0.11 UPGRADE notes) | `ProviderConnectionException`; HTTP **502, 504, 520, 522, 524** (set expanded in 0.11); **Anthropic usage-limit rejections** |
| Provider options | `HasProviderOptions::providerOptions(Lab\|string $provider)` → merged over the request body (e.g. OpenAI gateway `array_merge($body, $providerOptions)`) |
| Usage accounting | `Usage` exposes `input_tokens`, `output_tokens`, **`cache_read_input_tokens`**, **`cache_creation_input_tokens`**, `total_tokens` |
| Embedding cache | `config/ai.php` `caching.embeddings` (enabled here by `AI_EMBEDDINGS_CACHE`, default `true`) |

## §18.2 — What this repository already has (read from source, not assumed)

`src/Shared/Infrastructure/AI/` already implements the mechanism. It is used today by the
**CourseScripts** and **Campaigns** modules:

| Class | What it does |
|---|---|
| `PromptCache/CacheablePrompt` | Prompt split **most-stable-first**: agent instructions → `PromptLayer`s (long-lived before short-lived, enforced in the constructor) → per-call `tail`; plus a `cacheKey`. Layers must be rendered deterministically (no timestamps, ids or unordered JSON). |
| `PromptCache/UsesPromptCache` (trait) | Implements `providerOptions()`. **Anthropic:** replaces `system` with text blocks carrying `cache_control` breakpoints (instructions + long-lived layers get `ai.prompt_cache.long_ttl`, short-lived get the default), capped at **4 breakpoints**. **OpenAI:** sends `prompt_cache_key` (≤ 64 chars). **Others:** implicit prefix caching, nothing sent. |
| `PromptCache/PromptCachingAIClient` | Wraps `AIClientInterface` (circuit breaker + provider switch). For Anthropic, layers go in `system` and only the tail is the user message; for others, one message with a stable prefix. **Logs cache hits from response usage.** |
| `PromptCache/PromptCacheScope` | Carries the current `CacheablePrompt` to the agent during the call. |
| `ProviderFailover` | Ordered attempts: the preferred provider, then `ai.failover_order` (`AI_FAILOVER_ORDER`, default `openai,anthropic`), de-duplicated. The docblock notes that **evaluation judges stay outside failover** so they remain on a different provider from the writer. |
| `LaravelAIAdapter` | Circuit breaker per `ai:structured:{provider}`; logs only a bounded 500-char error prefix (LLM02). |

**Established consumer pattern** (`CourseScripts/.../LaravelAiScriptWriterAdapter`): `foreach
([$provider, ...$this->policy->fallbacksFor($provider)] as $attempt)` → `PromptCachingAIClient
::generateStructured(...)`. On failure it logs **the exception class only** ("provider errors can
echo the author's content") and moves to the next provider.

Config already present: `ai.prompt_cache.enabled`, `ai.prompt_cache.long_ttl` (default **`1h`**),
`ai.failover_order`, `default_for_embeddings = openai`.

## §18.3 — Provider economics (official docs + dated secondary sources)

| Fact | Source | Grade |
|---|---|---|
| Anthropic: **below the model's minimum cacheable length a prompt is silently not cached — no error**. Verify via usage: if both `cache_creation_input_tokens` and `cache_read_input_tokens` are 0, nothing was cached. | <https://docs.claude.com/en/docs/build-with-claude/prompt-caching> (official) | A-doc |
| OpenAI: automatic prefix caching; minimum reusable prefix **1,024 tokens** (GPT-5.6 and later); `prompt_cache_key` routes related requests to the same cache. | <https://developers.openai.com/api/docs/guides/prompt-caching/diagnostics> (official) | A-doc |
| Anthropic minimums are model-dependent (reported 1,024–4,096 tokens by tier); cache **writes cost 1.25× input (5-minute TTL) or ~2× (1-hour TTL)**; **reads ~0.1×**; a write is typically earned back after ~2 reuses. | <https://tech-insider.org/llm-prompt-caching-setup-2026>, <https://tokencalculator.ai/anthropic-prompt-caching-pricing> (secondary, checked 2026-08) | C — verify against the live pricing page in T-154 |
| Caching **loses money** when the prefix is too small, is not reused enough, or expires between requests (paying the write again). | same secondary sources | C |

## §18.4 — Consequences for this module

1. **Reuse the Shared mechanism; build nothing new.** The module's agents use
   `UsesPromptCache` + `CacheablePrompt` and follow the CourseScripts failover loop.
2. **"Si amerita" becomes a per-purpose rule.** Cache only where the stable prefix can exceed
   the provider minimum **and** is reused ≥ 2–3 times within the TTL. Per-document one-off calls
   (CV parse, a single audit) get **no** cache flag: with Anthropic they would pay the write
   premium for nothing.
3. **The global `long_ttl = 1h` doubles the write cost.** Batch purposes (requirement
   extraction across a run's postings) reuse the prefix within minutes, so the 5-minute tier
   (refreshed on each hit) is likely enough. Decide it **by measurement** (T-154), not by
   assumption. Any per-agent TTL override would be a small, backward-compatible change to
   `UsesPromptCache`, made only if the measurement justifies it.
4. **Silent non-caching means cache effectiveness must be measured.** Record
   `cache_read_input_tokens` / `cache_creation_input_tokens` per call and turn off a purpose's
   cache flag if it never produces reads.
5. **Failover applies to text generation, never to embeddings.** Vectors from different models
   live in different spaces (§3, plan §4.3), so an embedding outage must delay work (queue
   retry), not switch model.
6. **Failover must not change stored scores.** Requirement extraction feeds `H`, and a
   different provider may extract differently. Record `extracted_by` (provider, model, prompt
   version) on requirements. Rescoring reads stored rows (NFR-1), so failover affects only
   *new* extractions, and the breakdown shows which provider produced them.
7. **Fail over only on availability errors.** A structured-output validation failure retried on
   another provider doubles the spend without addressing the cause. The 0.11 failover set
   (connection errors, 5xx gateway codes, usage limits) is the right trigger list; plus 429.
8. **The largest token saving is not caching.** It is *not calling the model at all*:
   - one extraction per job, keyed on the posting-text hash (dedup CHG-2 + resolve-to-source
     CHG-11);
   - deterministic gates and `D_disc` before any LLM call;
   - the G4 snippet short-circuit;
   - trimming boilerplate (benefits, EEO, legal) from JD text before sending;
   - a cheap model tier for extraction and narrative.

---

# §19 — V-5: portal terms, read (2026-09-18, night)

**Rule applied while reading:** the same access rules as the module. A terms page was read
only where the site's `robots.txt` allows it. **Tecnoempleo** (`/politicas/`, `/politica.php`)
and **JustJoin.it** (`/terms-and-privacy-policies`) disallow crawlers on their terms pages, so
for those two only search-engine results were used. **RemoteRocketship** disallows everything
except `/api-docs`, so only the API docs were read.

## §19.1 — Findings per portal

| Portal | Read (how) | What the terms say | Access decision |
|---|---|---|---|
| **Net-Empregos** (PT) | `politica-privacidade.asp` — "Política de Privacidade & Termos de Utilização" (Firecrawl, basic proxy) | §8: the offers database may be accessed **"para fins de procura activa de emprego"** (active job search only). §9: **any commercial use is expressly prohibited**. No clause on automated access; `robots.txt` allows everything and advertises the RSS feeds, which are also supplied to aggregators (Trovit, Careerjet feeds). | **`api_feed` (RSS)** for the candidate's **personal, non-commercial** search. |
| **ITJobs.pt** (PT) | `itjobs.pt/api` (docs), `/api/docs`, `/termos_condicoes` | Public, mostly **read-only REST API**; **every call needs an API key**, a read-only key is issued by email; JSON or XML. Site terms: materials may be used **"unicamente para as suas necessidades pessoais"**, with **no commercial exploitation**. `robots.txt`: `Crawl-delay: 1`. | **`api_feed` with key** — personal, non-commercial. **User action:** request the key by email. |
| **InfoJobs** (ES) | `developer.infojobs.net`, quick-start, `/legal/legal/terms-of-use` (curl) | API needs an app registered with an InfoJobs account. **User data may not be stored**; request only the data the app needs; never share the secret key; do not try to get around limits. **"Uso comercial: si tu aplicación cae en una o más de estas categorías, tendrás que formar parte de nuestro programa de Partners y firmar un acuerdo"** — the categories include **"Aplicaciones que sean agregadores de ofertas"** and apps similar to the official InfoJobs apps. | **This module aggregates offers, so it falls in that category.** → **`link_only`** unless the candidate joins the InfoJobs Partners program and signs an agreement (**Q22**). The adapter (T-115) is **not built** by default. |
| **Tecnoempleo** (ES) | not fetched (terms paths disallowed) | Already established (§17.5): RSS path disallowed for all agents; Claude/Anthropic and GPT crawlers disallowed site-wide. | **`link_only`** — confirmed. |
| **JustJoin.it** (PL/EU) | search results only (terms path disallowed) | The terms page lists "Terms and conditions – users" (a separate document) — **content not readable by permitted means**. `/api/` is disallowed; only sitemaps are published. | **`link_only`** by default (FR-52: unchecked terms). The candidate can read the user terms manually; if they permit it, T-116 (sitemap discovery) can be enabled. |
| **RemoteRocketship** | `/api-docs` (allowed by `robots.txt`) | **Official Jobs API**: `POST /api/openclaw/jobs`, Bearer key, **requires an active paid subscription**, rate limits **500 requests or 3,000 jobs per UTC day**, up to 50 jobs per request; the docs invite calling it "from your own scripts or agents". Each job returns the **employer's own `url`**, `created_at`, `validUntilDate`, experience in months, required languages, tech stack, `isOnLinkedIn`, and supports **`hideGhostJobsFilter`**. Subscription price reported by a secondary review: $21/month · $42/quarter · $84/year (grade C). Its `/terms` page is disallowed to crawlers and was not read. | Default **`resolve_only`** (discovery → employer copy). Optional **`api_feed`** if the candidate subscribes (**Q21**). Because the API returns the employer URL, it would be a strong feed for resolve-to-source. |
| **Glassdoor** | not fetched | Same group as Indeed (Recruit Holdings); the Indeed terms apply to bots (§17.5). | **`link_only`** |
| **Employer career pages** | per site | Governed by each site's `robots.txt`. | **Fetch allowed only when `robots.txt` permits it**, through `FetchLadder` (direct HTTP → Firecrawl). Last resort in the priority order. |

## §19.2 — Incident: Firecrawl used a "stealth" proxy on its own

When `itjobs.pt/api` was first scraped with no explicit proxy, Firecrawl's automatic mode
answered with **`proxyUsed: "stealth"`**, a mode designed to get past bot protection. The
same URL family failed with `proxy: "basic"` ("the website is blocking automated access"),
while a plain `curl` with an honest user agent returned the page (200). The content read
through the stealth fetch (the public API landing page) was not relied on beyond what the
direct `curl` also confirmed.

**Consequence — a hard rule for the module:** every Firecrawl call made by the module sets
**`proxy: "basic"` explicitly**; `auto`, `stealth` and `enhanced` are never requested. A page
that fails with the basic proxy is treated as **not fetchable**, and the posting stays a
reference. That is the operational meaning of spec §8 ("never circumvent an anti-bot
measure"). → plan CHG-21, task T-159, SC-20.

## §19.3 — The priority order, confirmed by these findings

The user stated it explicitly: **official API/ATS/feed → permitted portal → web search →
permitted scraping as a last resort**. The V-5 results support it. The richest and cleanest
data (Greenhouse/Lever/Ashby, ITJobs.pt, Net-Empregos, optionally RemoteRocketship) comes
through official channels. The biggest portals (LinkedIn, Indeed, Glassdoor, InfoJobs without
agreement, Tecnoempleo, JustJoin.it) are **discovery signals only**. Web search is what turns a
signal into an employer's own copy. Scraping is the narrow tail: employer pages whose
`robots.txt` allows it, with a basic proxy only.

## §19.4 — Commercial-use constraint

Net-Empregos, ITJobs.pt and InfoJobs **all prohibit commercial use** of their content.
Net-Empregos and ITJobs.pt allow personal job search; InfoJobs requires a partner agreement for
aggregators whatever the purpose. **This module is the candidate's personal tool** (spec §8:
not multi-tenant, not hosted for others). If it ever became the white-label product discussed
in `GUIDE/JOBORA-CLAUDE/PIPELINE-A-B-COMPARATION.md`, access to these three sources would have
to be renegotiated or removed. → spec A-18, `studio_sources.usage_restriction`.

## §20 — T-002 extension probe, executed 2026-09-18 (Slice 1 build)

Rolled-back probe against the Supabase dev database (PostgreSQL 17.6), then the
real `2026_09_18_200001` migration:

- `pg_available_extensions`: **vector 0.8.2, pg_trgm 1.6, unaccent 1.1** — all
  available. `CREATE EXTENSION` grants work (verified in a rolled-back
  transaction first: `WOULD-INSTALL: pg_trgm / unaccent`, `PROBE-ROLLED-BACK-OK`).
- The migration is pgsql-guarded so the SQLite suite still migrates (CHG-17).
- Live verification 2026-09-18 (Context7 `/laravel/ai`, `/websites/laravel_13_x`):
  `Embeddings::for([...])->dimensions(1536)->cache()->generate()`,
  `HasStructuredOutput` agents, native `$table->vector()`,
  `whereVectorSimilarTo` / `orderByVectorDistance` / `selectVectorDistance`
  with `'embedding' => 'array'` cast. Tavily (Sept 2026): filtered HNSW needs
  `hnsw.iterative_scan = strict_order`, otherwise selective filters silently
  under-return — the Slice-2 vector store must `SET LOCAL` it per transaction.

## §21 — Field contract per confirmed source (T-006, 2026-09-19)

Which field means *first published* — Greenhouse `updated_at` does not (RK-14).

| Source | Title | Employer | Location | First-published field | Full-text field | Apply URL |
|---|---|---|---|---|---|---|
| Greenhouse | `title` | board slug | `location.name` | none (unknown) | `content` (`?content=true`) | `absolute_url` |
| Lever | `text` | board slug | `categories.location` | `createdAt` (ms epoch) | `additionalPlain` | `hostedUrl` |
| Ashby | `title` | board slug | `locationName` | none (unknown) | `descriptionPlain` | `jobUrl` |
| Arbeitnow | `title` | `company_name` | `location` | `created_at` | `description` | `url` |
| RSS/Atom | `title`/`title` | — | — | `pubDate`/`published` | `description`/`summary` | `link`/`id` |
| ITJobs.pt | `title` | `company` | `location` | `published_at` | `body` | `url` |
| Tavily | `title` | — (snippet only) | — | none (unknown) | snippet as `snippet_only` | `url` |

## §22 — SmartRecruiters + Teamtailor access (T-004/T-005, 2026-09-19)

- **SmartRecruiters: no free public board API.** The official API is
  partner-scoped; public inventory flows through aggregators — and Arbeitnow
  already lists SmartRecruiters among its ATS-direct sources. Per T-037 the
  source is registered (`access_mode = link_only`) with **no adapter**; its
  postings arrive via Arbeitnow. Revisit only with a partner agreement.
- **Teamtailor: official API needs a per-tenant X-Api-Key** (no aggregator
  path), but every customer's career site is statically rendered at
  `{slug}.teamtailor.com` with a discoverable sitemap. Implemented as
  `TeamtailorSitemapSource` (`requires_api_key = false, company_scoped =
  true`): sitemap walk for `/jobs/` URLs, full text later through the
  permitted ladder. Tier-1 employer-site discovery.
