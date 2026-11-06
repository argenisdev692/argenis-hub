# Technical plan: CV ATS Job Studio

- **Feature slug:** `004-cv-ats-job-studio`
- **Module:** `src/Modules/CvJobStudio` → namespace `Modules\CvJobStudio\`
- **Table prefix:** `studio_` (mirrors `LeadScout`'s `scout_` convention)
- **Config:** `config/cv-job-studio.php`
- **Date:** 2026-09-18
- **Inputs:** `spec.md` (requirements), `clarify.md` (Q1–Q4 resolved), `research.md` (verified findings)
- **Revision 2 (2026-09-18 pm):** §12 (CHG-6 … CHG-15) — fullstack-only v1, native `vector`,
  amended rules-v2 fit maths, Opportunity layer, resolve-to-source, skill relations, audit
  extensions, UX pages. Where §12 conflicts with §1–§11, §12 wins.
- **Revision 3 (2026-09-18 evening):** §13 (CHG-16 … CHG-19) — evidence-graded opportunity
  policy (withdraws CHG-9's priors), SQLite-portable design, per-portal access modes, verified
  vector facts. Where §13 conflicts with §12, §13 wins. Q19 and Q20 block implementation.
- **Revision 4 (2026-09-18 night):** §14 (CHG-20) — prompt caching and failover per AI purpose
  through the existing Shared AI layer, "si amerita" decided per purpose and by measurement.
- **Revision 5 (2026-09-18 night):** §15 (CHG-21). Link-only sites are treated as discovery
  signals, resolved to the employer's own copy in the order official API/ATS/feed → permitted
  portal → web search → permitted scraping. Adds references + paste, the basic-proxy rule and
  the V-5 access matrix.
- **Architecture tier:** intermediate — `.claude/skills/ARCHITECTURE-PHP/SKILL.md`. Promotion is justified on 4 of the router's 5 triggers: multi-step run lifecycle with domain invariants (1), five third-party integrations (2), far more than 15 persisted fields across sub-entities (4), and exports + queue workers co-existing (5).

---

## 1. Technical summary

One engine, four stored profiles. A run is a queued five-stage pipeline —
**discover → gate → shortlist → extract → score** — whose every intermediate result is a
row, followed by a reporting pass. The CV is parsed once into addressable sections,
entries, bullets and skills; those rows are what tailoring re-orders, what position
weighting measures, and what provenance points back to.

The scoring mathematics is ported from the local pipeline unchanged in shape and constants
(`0.45·H + 0.25·S + 0.30·D`, caps 55/65/69, bands 85/75/70/60, `D_disc` shortlist), with
three substantive upgrades that together constitute **rules version 2**:

1. `S` becomes real cosine similarity over `pgvector`, replacing the declared proxy.
2. `H` gains a **context factor** — a skill evidenced inside an accomplishment bullet
   outranks one appearing only in a skills list.
3. `H` gains a **position factor** — evidence in the top third of the rendered CV counts
   fully; lower evidence is discounted.

Both new factors are multiplicative and bounded at 1.0, so they can only reduce a credit,
never inflate it. A CV that scored 100 on `H` under version 1 still scores 100 under
version 2. That monotonicity is deliberate: it makes the version-2 delta interpretable as
"what my layout is costing me", which is precisely the per-move point-lift figure
`research.md` §5 found competitors estimating and we can compute exactly.

## 2. Technology stack (verified with real-time research)

| Concern | Choice | Version | Verified by |
|---|---|---|---|
| Framework | Laravel | 13.26.1 | `php artisan --version` on this machine |
| Language | PHP | 8.5.9 | `php -v` |
| Database | PostgreSQL (Supabase, `eu-central-1`) | 17.6 | `php artisan db:show` |
| Vector storage | `pgvector` | **0.8.2 available**, to be enabled | `pg_available_extensions` query (`research.md` §3) |
| Fuzzy skill aliasing | `pg_trgm` | **1.6 available**, to be enabled | same query |
| ES/PT accent folding | `unaccent` | **1.1 available**, to be enabled | same query |
| Embeddings + LLM | `laravel/ai` | ^0.11.0 installed | `composer.json`; API from Context7 `/laravel/ai` |
| DTOs / response shapes | `spatie/laravel-data` | ^4.23 | `composer.json` |
| TS type generation | `spatie/laravel-typescript-transformer` | ^3.3 | `composer.json` |
| Word export | `phpoffice/phpword` | ^1.4 installed, **no consumer yet** | `composer.json` + repo-wide grep (`research.md` §4) |
| PDF export | `barryvdh/laravel-dompdf` | ^3.1 via `DomPdfExportAdapter` | existing adapter |
| CV text extraction | `smalot/pdfparser` | ^2.12 | `composer.json` |
| Search provider | Tavily via existing adapter | — | `Shared/Infrastructure/Research/TavilyResearchAdapter` |
| Page extraction | Firecrawl via existing adapter | — | `Shared/Infrastructure/Research/FirecrawlScrapeAdapter` |
| Authorization | `spatie/laravel-permission` | ^8.3 | `composer.json` |
| Audit trail | `spatie/laravel-activitylog` | ^5.1 | `composer.json` |
| API docs | `dedoc/scramble` | ^0.13.42 | `composer.json` |
| Tests | Pest | 5 | project rule |

**Nothing new is added to `composer.json`.** Three PostgreSQL extensions are enabled by
migration. This satisfies NFR-10 and is the concrete reason the FastAPI alternative was
rejected (`research.md` §3).

`[UNVERIFIED]` — the specific embedding model and its cosine calibration floor/ceiling
(§4.3). The model is configuration; the calibration constants require the held-out set and
are seeded with stated provisional defaults.

## 3. Architecture

### 3.1 Run pipeline

Five queued stages, one job per stage, each delegating to an Application handler — the
shape `LeadScout` already proves in production here (`research.md` §4):

```
StartRunHandler ─dispatch→ DiscoverPostingsJob
                              │  providers → candidates (+ provider, query, attribution)
                              ↓
                           GatePostingsJob
                              │  G1 remote scope · G2 stack lock · G3 listing quality
                              │  → studio_gate_results (pass AND fail, with reasons)
                              ↓
                           ShortlistPostingsJob
                              │  D_disc = 0.35P + 0.25K + 0.20R + 0.10F + 0.10N
                              │  → top 5–8 with D_disc ≥ 0.55, else best remaining
                              ↓
                           ExtractPostingsJob
                              │  FetchLadder: direct HTTP → Firecrawl → careers pivot
                              │  G4/G4b snippet short-circuit skips extraction entirely
                              ↓
                           ScorePostingJob  (one per shortlisted posting, parallel)
                              │  requirements → H · S · D → caps → band
                              ↓
                           BuildInsightReportJob  (always runs, even on zero matches)
```

Each stage is idempotent on `(run_id, posting_id)` and records its own counters on the run,
so a retried job cannot double-count or double-spend. `BudgetLedger::ensure()` is called
**before** each provider call, not after (FR-33, SC-8).

### 3.2 Ports and adapters

Domain declares ports; Infrastructure implements them. Swapping a provider touches no
domain logic (NFR-12).

| Port | Adapters | Reuse |
|---|---|---|
| `PostingSourcePort` | `TavilySearchSource`, `ArbeitnowSource`, `GreenhouseBoardSource`, `LeverBoardSource`, `AshbyBoardSource`, `AdzunaSource`, `RemoteOkSource`, `RemotiveSource`, `HimalayasSource`, `JobicySource`, `RssFeedSource` | Tavily + Arbeitnow + RSS patterns exist |
| `PostingTextFetcherPort` | `DirectHttpPostingFetcher`, `FirecrawlPostingFetcher` behind `PostingFetchLadder` | `FetchLadder` pattern + guards |
| `EmbeddingPort` | `LaravelAiEmbeddingAdapter` | `laravel/ai` |
| `RequirementExtractorPort` | `LaravelAiRequirementExtractor` (+ `ExtractRequirementsAgent`) | `AIClientInterface` seam |
| `CvJudgePort` | `LaravelAiCvJudge` (+ `JudgeCvAgent`) | same |
| `CvRewriterPort` | `LaravelAiCvRewriter` (+ `RewriteCvAgent`) | same |
| `CvStructureParserPort` | `LaravelAiCvStructureParser` | same |
| `CvSourcePort` | `EloquentCvSource` reading `Modules\Cvs` | `LeadScout` has the identical seam |
| `ProjectSourcePort` | `EloquentPortfolioProjectSource` reading `Modules\Portfolios` | Q4 resolution |
| `ExportPort` (Shared) | existing `DomPdfExportAdapter` + **new `PhpWordExportAdapter`** | one genuine gap |
| `SpendGuardPort` | `StudioBudgetLedger` | `BudgetLedger` pattern |

### 3.3 Where the mathematics lives

`Domain/Services/` — pure, no infrastructure, no Eloquent, fully unit-testable:

`GateEvaluator` · `RemoteScopeClassifier` · `SkillAliasResolver` · `RequirementWeigher` ·
`HeuristicSkillScorer` · `SemanticScorer` · `DeterministicScorer` · `CapLadder` ·
`MatchScoreCalculator` · `DiscoveryScorer` · `AtsStructureChecker` · `BandClassifier` ·
`RequirementFrequencyCounter` · `ReachCalculator` · `OutcomeCorrelator`

Every weight, cap, band and sub-score table is injected from `config/cv-job-studio.php`, never
literal in a class (SC-11). The LLM supplies extraction and narrative; it never returns a
number that reaches a score (NFR-2).

### 3.4 What is deliberately absent

No `Article14Notice`, no `PersonalDataScrubber`, no suppression list, no privacy-request
handler. Those exist in `LeadScout` because it processes people. This module stores employer
data only (NFR-8), which is the whole basis of the Q1 boundary and of `research.md` §2's
favourable GDPR reading. An auditor should treat their *presence* here as a smell.

## 4. Data model (physical schema)

PostgreSQL. Every table: `id` bigint identity PK, `uuid` **UUIDv7** unique, `user_id` FK →
`users` with the mandatory inverse `hasMany` on `App\Models\User`, `timestamps`, and
`softDeletes` on user-facing aggregates. Every list query uses explicit-column eager loads.

### 4.1 Enabling extensions

```sql
CREATE EXTENSION IF NOT EXISTS vector;    -- 0.8.2
CREATE EXTENSION IF NOT EXISTS pg_trgm;   -- 1.6
CREATE EXTENSION IF NOT EXISTS unaccent;  -- 1.1
```

One migration, guarded and reversible. Supabase permits these on the `public` schema.

### 4.2 Tables

**Profiles and vocabulary**

| Table | Key columns |
|---|---|
| `studio_profiles` | `name`, `slug`, `flow` (`fullstack\|trainer\|video_content\|other`), `is_active`, `base_city`, `base_country`, `accepted_remote_scopes` jsonb, `stack_must` jsonb, `stack_reject` jsonb, `never_seed` jsonb, `search_languages` jsonb, `geography_prefer` jsonb, `geography_deny` jsonb, `years_baseline`, `education_level`, `language_levels` jsonb, `protected_block` jsonb, `seniority_band` jsonb, `tone`, `rules_version` |
| `studio_vocabulary` | `profile_id`, `term`, `kind` (`strong\|emerging\|role\|stack_pair\|modality\|never_seed`), `language`, `weight`, `last_seen_at`, `refreshed_at` |
| `studio_sources` | `kind` (`search_api\|board_api\|ats_board\|rss`), `name`, `endpoint`, `auth_kind`, `tier` 1–4, `layer` (`core\|expansion`), `attribution_required`, `attribution_text`, `redistribution_allowed`, `publish_delay_hours`, `rate_limit_per_minute`, `status`, `health_checked_at`, `gate_tags` jsonb |
| `studio_source_companies` | `source_id`, `slug`, `company_name`, `last_seen_at` — the slug registry the ATS board endpoints need (`clarify.md` Q2) |

`studio_profiles.protected_block` and `language_levels` are what move the author's baseline
out of code (A-8). `unaccent` + `pg_trgm` indexes go on `studio_vocabulary.term`.

**Structured CV (the Q4 resolution)**

| Table | Key columns |
|---|---|
| `studio_cv_structures` | `cv_id` FK → `cvs`, `source_text_hash`, `parser_version`, `parsed_at`, `confirmed_at`, `profile_facts` jsonb (name, city, country, links, contacts) |
| `studio_cv_entries` | `structure_id`, `kind` (`experience\|project\|education\|certification\|achievement\|language`), `ordinal`, `organization`, `role_title`, `location`, `started_on`, `ended_on`, `is_current`, `is_protected` |
| `studio_cv_bullets` | `structure_id`, `entry_id`, `ordinal`, `text`, `has_metric`, `xyz_complete`, `char_count` |
| `studio_cv_skills` | `structure_id`, `canonical_name`, `raw_name`, `nature` (`hard\|soft\|tool\|language`), `evidence` (`list_only\|in_bullet\|both`), `evidence_bullet_id` nullable, `years`, `first_ordinal` |

`raw_text` on `cvs` stays the immutable source of truth; these rows are derived and
re-derivable. `studio_cv_skills.evidence` is what feeds the new context factor;
`studio_cv_bullets.ordinal` + `studio_cv_entries.ordinal` feed the position factor.

**Audit, versions, exports**

| Table | Key columns |
|---|---|
| `studio_cv_audits` | `cv_id`, `structure_id`, `profile_id`, `verdict` (`pass\|borderline\|reject_risk`), `verdict_reasons` jsonb, `structural_checks` jsonb (per-check pass/fail/detail), `ats_heuristic`, `target_job_title`, `strengths` jsonb, `improvements` jsonb, `keyword_gaps` jsonb, `xyz_gaps` jsonb, `rules_version` |
| `studio_metric_answers` | `audit_id`, `cv_id`, `question`, `answer`, `answered_at` |
| `studio_cv_versions` | `cv_id`, `structure_id`, `profile_id`, `posting_id` nullable, `purpose` (`ats_rewrite\|tailored`), `language` (`es\|en\|pt-PT`), `content` jsonb (rendered structure snapshot), `page_count_estimate`, `parent_version_id`, `bullet_provenance` jsonb, `rules_version` |
| `studio_exports` | `cv_version_id`, `format` (`docx\|pdf`), `language`, `disk`, `path`, `bytes`, `text_extraction_verified`, `extracted_char_count`, `structural_checks` jsonb |

`studio_exports.text_extraction_verified` is the persisted result of SC-5 — the PDF's own
text is re-extracted with `smalot/pdfparser` after generation and compared, so the user's
manual `pdftotext` check becomes an automated assertion.

**Runs, postings, scoring**

| Table | Key columns |
|---|---|
| `studio_runs` | `profile_id`, `cv_id`, `structure_id`, `status`, `started_at`, `finished_at`, `queries` jsonb, `candidates_count`, `gate_passed_count`, `extracted_count`, `scored_count`, `new_matches_count`, `excluded_count`, `spend_micros`, `refresh_flags` jsonb, `notes`, `rules_version` |
| `studio_postings` | `canonical_url`, `url_hash`, `source_id`, `first_run_id`, `profile_id`, `employer_name`, `title`, `location_text`, `remote_scope` (`remote_global\|remote_eu\|remote_pt_es\|remote_unclear\|hybrid_local`), `posted_at`, `salary_text`, `currency`, `attribution`, `status` (`new\|saved\|applied\|dismissed\|skipped`), `first_seen_at`, `last_seen_at`, `d_disc`, `d_disc_components` jsonb |
| `studio_posting_texts` | `posting_id`, `ladder_step` (`snippet\|direct_http\|firecrawl\|careers_pivot`), `completeness` (`full\|partial\|snippet_only`), `text`, `char_count`, `fetched_at` |
| `studio_requirements` | `posting_id`, `canonical_name`, `raw_text`, `tag` (`required\|preferred\|bonus`), `nature` (`hard\|soft`), `weight`, `normalized_weight` |
| `studio_gate_results` | `posting_id`, `run_id`, `gate_code` (`G1\|G2\|G3\|G4\|G4b`), `passed`, `reason_code`, `detail` |
| `studio_scores` | `posting_id`, `run_id`, `cv_id`, `structure_id`, `h`, `s`, `d`, `h_components` jsonb, `s_components` jsonb, `d_components` jsonb, `raw_score`, `total_score`, `band`, `caps_applied` jsonb, `cap_reason`, `embedding_model`, `embedding_dims`, `rules_version`, `computed_at` |
| `studio_skill_matches` | `score_id`, `requirement_id`, `credit_base`, `context_factor`, `position_factor`, `credit_final`, `evidence_bullet_id` nullable, `evidence_text` |
| `studio_applications` | `posting_id`, `cv_version_id`, `export_id`, `status`, `applied_at`, `outcome` (`unknown\|pending\|no_reply\|rejected\|screening\|interview\|offer\|withdrawn`), `outcome_at`, `outcome_note` |
| `studio_insight_reports` | `run_id`, `jds_analyzed`, `sample_note`, `requirements` jsonb, `reach_table` jsonb, `recommendations` jsonb, `outcome_correlation` jsonb, `rules_version` |

`studio_scores` stores `raw_score` (uncapped) beside `total_score` — the local pipeline
learned this the hard way, and it is what makes the reach table computable (FR-27, SC-10).
`h_components`/`s_components`/`d_components` make SC-2 (offline recompute) hold.

**Spend and vectors**

| Table | Key columns |
|---|---|
| `studio_budgets` | `period` (`YYYY-MM`), `category` (`search\|fetch\|llm\|embedding`), `limit_micros`, `spent_micros` — unique on `(user_id, period, category)`, mutated under `lockForUpdate` |
| `studio_provider_calls` | `run_id`, `category`, `provider`, `operation`, `cost_micros`, `succeeded`, `http_status`, `called_at` |
| `studio_embeddings` | `owner_type` (`cv_structure\|posting_text\|cv_bullet\|requirement`), `owner_id`, `model`, `dims`, `embedding halfvec(1536)`, `content_hash` — unique on `(owner_type, owner_id, model)` |

`studio_provider_calls` is the per-call audit the aggregate ledger cannot give; it is what
NFR-4's "reports actual spend after" reads.

### 4.3 Vector storage — the `pgvector` golden path

Per the four converged 2026 sources in `research.md` §3:

```sql
ALTER TABLE studio_embeddings ADD COLUMN embedding halfvec(1536) NOT NULL;

CREATE INDEX CONCURRENTLY studio_embeddings_hnsw
  ON studio_embeddings USING hnsw (embedding halfvec_cosine_ops)
  WITH (m = 16, ef_construction = 64)
  WHERE deleted_at IS NULL;
```

- **`halfvec`, not `vector`** — half the storage and index size, negligible recall loss.
- **HNSW, not IVFFlat** — better speed/recall, buildable on an empty table.
- **Query with `<=>`** — a `halfvec_cosine_ops` index requires it; another operator silently
  skips the index.
- **`SET hnsw.ef_search = 100`** per session; tune this before touching `m`.
- **`SET hnsw.iterative_scan = strict_order`** — mandatory here, not optional. Every query
  is filtered by `user_id` and `owner_type`, and unfiltered HNSW terminates early under a
  `WHERE` clause and silently returns too few rows. 0.8.2 supports it; the pre-0.8
  overshoot-CTE workaround loses results on selective filters.
- Model and dims are stored per row so a model change is a re-embed, not silent corruption.

Embedding is **write-once per content hash**, with `laravel/ai`'s `->cache()` on top — each
posting is embedded once, ever. This is the main embedding cost control.

## 5. API contracts

Web routes (Inertia + session) are primary; the API mirror is secondary and Sanctum-guarded.
Every route: `auth` + `permission:*`, `->whereUuid('uuid')`, and `/export` declared **before**
`/{uuid}`. Responses are Spatie `Data` objects — no `JsonResource`.

Mounted by `CvJobStudioServiceProvider::registerWebRoutes()` / `registerApiRoutes()`.

| Method | Route | Purpose | Requirements |
|---|---|---|---|
| GET | `/cv-studio` | Dashboard: profiles, last runs, spend, pending outcomes | US-6, US-8 |
| GET | `/cv-studio/profiles` | List profiles | US-7 |
| POST | `/cv-studio/profiles` | Create profile | FR-30 |
| PUT | `/cv-studio/profiles/{uuid}` | Update gates / baseline | FR-30, FR-31 |
| POST | `/cv-studio/cvs/{uuid}/structure` | Parse CV into structured rows | FR-4 |
| PUT | `/cv-studio/structures/{uuid}` | Confirm / correct parsed structure | FR-4 |
| POST | `/cv-studio/cvs/{uuid}/audit` | Run judge + ATS structural audit | FR-2 |
| POST | `/cv-studio/audits/{uuid}/answers` | Submit metric answers | FR-3 |
| POST | `/cv-studio/cvs/{uuid}/rewrite` | Produce ATS-safe version | FR-5, FR-6, FR-7, FR-8 |
| POST | `/cv-studio/runs` | Start a discovery run (queued) | FR-9…FR-14, FR-31, FR-33 |
| GET | `/cv-studio/runs/{uuid}` | Run status + per-stage progress | NFR-5 |
| GET | `/cv-studio/runs/{uuid}/report` | Insight report | FR-26, FR-27, FR-28 |
| GET | `/cv-studio/postings` | Paginated postings, server-side filter/sort | FR-21 |
| GET | `/cv-studio/postings/{uuid}` | Posting + requirements + score breakdown | FR-15, FR-20 |
| POST | `/cv-studio/postings/{uuid}/requirements/{ruuid}/dismiss` | Drop a mis-extracted requirement and rescore | `research.md` §5 |
| POST | `/cv-studio/postings/{uuid}/rescore` | Recompute from stored data, no provider call | FR-37, SC-2 |
| POST | `/cv-studio/postings/{uuid}/tailor` | Tailored CV version for this posting | FR-22, FR-24 |
| GET | `/cv-studio/versions/{uuid}/export` | Export `docx` \| `pdf` × `es` \| `en` \| `pt-PT` | FR-22, FR-23 |
| PUT | `/cv-studio/postings/{uuid}/status` | Set candidate-side status | FR-25 |
| PUT | `/cv-studio/applications/{uuid}/outcome` | Set employer-side outcome | FR-25, FR-29 |
| POST | `/cv-studio/postings/bulk-delete` | Soft-delete by UUID array | project rule |
| POST | `/cv-studio/postings/bulk-restore` | Soft-restore by UUID array | project rule |
| GET | `/cv-studio/budgets` | Spend vs limit per category | FR-33 |
| GET | `/cv-studio/sources` | Source catalogue + health | FR-32, FR-35 |

## 6. Proposed folder structure

```
src/Modules/CvJobStudio/
├── Providers/  CvJobStudioServiceProvider.php
├── Domain/
│   ├── Enums/        Flow · RemoteScope · GateCode · RequirementTag · RequirementNature
│   │                 · SkillNature · SkillEvidence · ScoreBand · CapReason · LadderStep
│   │                 · TextCompleteness · CvVersionPurpose · ExportFormat · CvLanguage
│   │                 · PostingStatus · ApplicationOutcome · SourceKind · SourceLayer
│   │                 · BudgetCategory · AuditVerdict · CvEntryKind
│   ├── ValueObjects/ CanonicalPostingUrl · SkillKey · ScoreBreakdown · DiscoveryScore
│   │                 · GateVerdict · WeightedRequirement · EmbeddingVector · AtsCheckResult
│   ├── Services/     (the 15 pure classes listed in §3.3)
│   ├── Ports/        PostingSourcePort · PostingTextFetcherPort · EmbeddingPort
│   │                 · RequirementExtractorPort · CvJudgePort · CvRewriterPort
│   │                 · CvStructureParserPort · CvSourcePort · ProjectSourcePort
│   │                 · SpendGuardPort · PostingRepositoryPort · ScoreRepositoryPort
│   └── Exceptions/   ProfileGateIncompleteException · BudgetExceededException
│                     · PostingNotFoundException · StructureNotConfirmedException
│                     · AtsStructureViolationException
├── Application/
│   ├── DTOs/         (Spatie Data — profile, run, posting, score, report, export, …)
│   ├── Commands/     ParseCvStructureHandler · ConfirmCvStructureHandler
│   │                 · AuditCvHandler · SubmitMetricAnswersHandler · RewriteCvHandler
│   │                 · StartRunHandler · DiscoverPostingsHandler · GatePostingsHandler
│   │                 · ShortlistPostingsHandler · ExtractPostingHandler
│   │                 · ScorePostingHandler · RescorePostingHandler
│   │                 · DismissRequirementHandler · TailorCvHandler · ExportCvVersionHandler
│   │                 · UpdatePostingStatusHandler · RecordOutcomeHandler
│   │                 · BuildInsightReportHandler · RefreshVocabularyHandler
│   │                 · UpsertProfileHandler · BulkDeletePostingsHandler
│   │                 · BulkRestorePostingsHandler
│   └── Queries/      ListPostingsHandler · GetPostingHandler · GetRunHandler
│                     · GetInsightReportHandler · GetBudgetStatusHandler · ListProfilesHandler
└── Infrastructure/
    ├── Http/
    │   ├── Controllers/  ProfileController · CvStructureController · CvAuditController
    │   │                 · CvVersionController · RunController · PostingController
    │   │                 · ApplicationController · InsightReportController
    │   │                 · BudgetController · SourceController
    │   │                 └── Api/  (only where the API genuinely diverges)
    │   ├── Requests/     (Store/Update/Export/BulkDelete/BulkRestore per aggregate)
    │   └── Export/       CvVersionDocxExport · CvVersionPdfExport · CvVersionExportTransformer
    │                     · PostingExcelExport · PostingExportTransformer
    ├── Sources/          TavilySearchSource · ArbeitnowSource · GreenhouseBoardSource
    │                     · LeverBoardSource · AshbyBoardSource · AdzunaSource
    │                     · RemoteOkSource · RemotiveSource · HimalayasSource
    │                     · JobicySource · RssFeedSource
    ├── Fetching/         PostingFetchLadder · DirectHttpPostingFetcher · FirecrawlPostingFetcher
    ├── Ai/               LaravelAiRequirementExtractor · LaravelAiCvJudge
    │                     · LaravelAiCvRewriter · LaravelAiCvStructureParser
    │                     · ExtractRequirementsAgent · JudgeCvAgent · RewriteCvAgent
    ├── Embeddings/       LaravelAiEmbeddingAdapter · PgVectorEmbeddingStore
    ├── Budgets/          StudioBudgetLedger
    ├── Cvs/              EloquentCvSource · EloquentPortfolioProjectSource
    ├── Queue/            DiscoverPostingsJob · GatePostingsJob · ShortlistPostingsJob
    │                     · ExtractPostingsJob · ScorePostingJob · BuildInsightReportJob
    ├── Console/Commands/ StudioRunCommand · StudioRescoreCommand · StudioRefreshVocabularyCommand
    │                     · StudioReembedCommand · StudioAgeOutcomesCommand
    ├── Persistence/
    │   ├── Eloquent/Models/  (one `Studio*EloquentModel` per table in §4.2)
    │   └── Repositories/     EloquentPostingRepository · EloquentScoreRepository
    └── Routes/           web.php · api.php
```

New Shared file — the one genuine infrastructure gap:
`src/Shared/Infrastructure/Export/PhpWordExportAdapter.php`, behind the existing
`Shared\Domain\Ports\ExportPort`.

## 7. Testing strategy

Pest 5. Run scoped: `php artisan test --compact --filter=CvJobStudio`. Never the full suite.

| Layer | Coverage |
|---|---|
| **Unit — `Domain/Services`** | The whole formula, with table-driven cases straight from `job-match-scoring.md`: soft-skill denominator cap, credit ladder 1.0/0.5/0.0, the new context and position factors, `D` renormalisation when a signal is unreadable, the cap ladder's lowest-cap-wins, the 69 evidence cap (including the worked 69.55 → 70 case that motivated it), band boundaries, `D_disc` including the 0.8825 worked example. No database. |
| **Unit — determinism** | Same inputs + rules version → identical total (NFR-1, SC-2). Recompute from stored `*_components` with all ports faked. |
| **Unit — VOs** | `CanonicalPostingUrl` normalisation: fragment, the seven tracking params, trailing slash. |
| **Feature — pipeline** | Each stage in isolation with faked ports; then full run end-to-end on fixtures. Idempotency: re-running a stage does not double-count or double-spend. |
| **Feature — gates** | One test per remote-scope label and per gate-fail reason; asserts gate-failed postings are counted but never scored (FR-12). |
| **Feature — budget** | A call that would breach the period limit is refused **before** the provider is invoked (SC-8), under concurrency. |
| **Feature — guards** | `OutboundUrlGuard` rejects a denied target; robots policy honoured; **no source or ladder step targets LinkedIn** (SC-9). An architecture test asserts no LinkedIn host appears in any source or fetcher. |
| **Feature — export** | Generated PDF's text re-extracted with `smalot/pdfparser` and compared to intent (SC-5). Generated DOCX and PDF pass every `AtsStructureChecker` check the module asserts (SC-6). Each of `es`/`en`/`pt-PT`; untranslatable terms preserved (FR-23). |
| **Feature — profiles** | A second profile added with no schema change; its run does not inherit the first's gates (SC-7); a profile missing a required gate input blocks with a stated reason (FR-31). |
| **Feature — reporting** | Report produced on a zero-match run; reach table prices a cap in postings unlocked (SC-10); outcome correlation suppressed below the minimum sample; aged silence counted as non-reply without overwriting the stored value (FR-29). |
| **Feature — authorization** | Every route rejects unauthenticated and cross-user access (NFR-7). |
| **Feature — embeddings** | Vector written once per content hash; filtered similarity query with `iterative_scan` returns the expected count (guards against the HNSW early-termination trap). |
| **Arch — `CvJobStudioArchTest`** | Domain imports no Eloquent/facade/HTTP; Application imports no Eloquent model; no `JsonResource` anywhere; every score-bearing number traces to config, not a literal. |
| **Calibration — held-out set** | The judged postings in `GUIDE/JOBORA-CLAUDE/*/job-search-cache.json` become fixtures for SC-3 and SC-4: band agreement on a majority, and the version-1 → version-2 delta inspected per posting. |

Factories and seeders for every model; a seeder creates the four profiles from the existing
flow constants.

## 8. Security and compliance

Satisfies `.claude/OWASP/SKILL.md` baseline; items specific to this module:

| Control | Implementation |
|---|---|
| Authorization | `auth` + `permission:*` on every route; `user_id` scoping on every query; Policy on posting and CV version ownership. Frontend gates on `permissions`, never `roles`. |
| Route binding | `->whereUuid('uuid')` everywhere; UUIDv7 via `HasUuids`. |
| SSRF | `OutboundUrlGuard` on every fetch; private/link-local ranges denied; redirects re-validated per hop. |
| Robots / rate limits | `RobotsTxtPolicy` honoured; per-source `rate_limit_per_minute` enforced; `CircuitBreaker` on each provider. |
| No authenticated third-party access | Architectural exclusion (FR-36) asserted by test (SC-9). No third-party credentials are stored beyond our own API keys. |
| Personal data | Employer data only (NFR-8). Named individuals not retained unless the posting itself is the public application channel. No recruiter contact extraction — that is `LeadScout`'s job and stays there. |
| Attribution | `attribution_required` / `attribution_text` / `publish_delay_hours` on `studio_sources`, honoured on display (Remotive and RemoteOK both require link-back; Remotive additionally forbids resubmission and delays 24h). |
| Prompt injection | Posting text is untrusted input. Requirement extraction runs in a constrained schema; extracted values are validated against enums and never executed or interpolated into a later prompt as instructions (LLM Top 10). |
| LLM output containment | The model never returns a score (NFR-2); numbers come from `Domain/Services` over stored rows. |
| Secrets | Provider keys via `config/services.php` + env; never logged. `studio_provider_calls` records cost and status, never payloads. |
| Audit trail | `LogsActivity` with explicit `logOnly([...])` + `logOnlyDirty()` + `dontLogEmptyChanges()` on profile, posting status and application outcome. Never `logAll()`; no PII logged. |
| File uploads | Inherited from `Modules\Cvs` (MIME + size + extension); generated exports served via signed URLs only. |
| Spend | `StudioBudgetLedger::ensure()` pre-flight on every provider call, `lockForUpdate` on the period row. |

## 9. Risks and open decisions

| # | Risk | Mitigation |
|---|---|---|
| RK-1 | **Cosine similarity is not a percentage.** Modern embedding models put unrelated technical text at ~0.5–0.7 cosine, so a naive `(x+1)/2` mapping compresses every posting into 0.8–0.9 and `S` stops discriminating — it would look fine and be useless. | `S` is calibrated, not mapped: `norm(x) = clamp((x − floor)/(ceil − floor), 0, 1)` with `floor`/`ceil` in config, provisionally 0.30/0.85, **`[UNVERIFIED]` pending the held-out set**. Keep the local pipeline's 40% title / 60% responsibility split inside `S`. SC-4 is the check that this actually discriminates. |
| RK-2 | Rules version 2 shifts stored scores, so old and new are not directly comparable. | `rules_version` stamped on every score, run and report (NFR-9). `StudioRescoreCommand` recomputes a stored corpus under a new version with no provider calls, so v1 vs v2 is measurable rather than assumed. |
| RK-3 | LLM requirement extraction is the largest error source — a hallucinated "required" skill inflates the denominator and depresses every score. | Extraction constrained to a schema; every requirement stored as an inspectable row with its `raw_text`; the user can dismiss one and rescore (`research.md` §5). Requirement-level precision measured against the held-out set. |
| RK-4 | Free job APIs are individually thin; coverage per run may disappoint versus the current Tavily-led approach. | Eleven sources behind one port, tiered core/expansion with rotation. Tavily remains the breadth source. Low-yield rescue retained (FR-32). |
| RK-5 | Source terms change, or a free tier closes. | Terms modelled as data on `studio_sources`, not code; `status` + `health_checked_at` disable a source without a deploy. |
| RK-6 | Structured CV parsing is imperfect on unusual layouts. | `raw_text` remains the source of truth; parsing is re-runnable; `confirmed_at` gates a structure from use until the candidate has approved it (`StructureNotConfirmedException`). |
| RK-7 | `phpoffice/phpword` has never run in this codebase — DOCX generation is unproven here. | Build `PhpWordExportAdapter` behind the existing `ExportPort` first (Phase A), with its structural assertions, before any tailoring work depends on it. |
| RK-8 | Enabling extensions on hosted Supabase could require elevated grants. | `CREATE EXTENSION IF NOT EXISTS`, guarded and reversible; verified available (not merely assumed) in `research.md` §3. Task T-002 is a standalone spike so a permissions problem surfaces on day one. |
| RK-9 | Embedding cost grows with corpus size. | Write-once per `content_hash` + `laravel/ai` `->cache()` + `halfvec`; embedding is its own budget category with its own limit. |
| RK-10 | Position weighting depends on where text lands on the *rendered* page, which the structure rows only approximate. | v1 approximates by ordinal (entry + bullet position) rather than by measured pixels, and the config exposes the thresholds. Stated as an approximation in the score breakdown rather than presented as exact. |
| OD-1 | Embedding model choice and its calibration constants. | `[UNVERIFIED]` — config-driven; decided during the Phase F calibration task against the held-out set. |
| OD-2 | Per-employer ATS detection (Workday vs iCIMS behave differently — `research.md` §1). | Deferred. Real, but needs employer→ATS resolution. Logged as a later rules version. |

## 10. Traceability

| Requirement | Plan section | Tables / classes |
|---|---|---|
| FR-1 | §4.2 | `cvs` (existing), `studio_cv_structures` |
| FR-2 | §3.3, §4.2 | `AtsStructureChecker`, `LaravelAiCvJudge`, `studio_cv_audits.structural_checks` |
| FR-3 | §4.2, §5 | `studio_metric_answers`, `SubmitMetricAnswersHandler` |
| FR-4 | §4.2 | `studio_cv_entries`, `studio_cv_bullets`, `studio_cv_skills`, `LaravelAiCvStructureParser` |
| FR-5 | §4.2 | `studio_cv_versions.parent_version_id` |
| FR-6 | §4.2, §7 | `AtsStructureChecker`, `studio_exports.text_extraction_verified` |
| FR-7 | §4.2 | `studio_profiles.protected_block`, `studio_cv_entries.is_protected` |
| FR-8 | §4.2 | `studio_cv_versions.bullet_provenance`, `RewriteCvAgent` schema |
| FR-9 | §3.2, §4.2 | `PostingSourcePort` + 11 adapters, `studio_postings.source_id` |
| FR-10 | §6, §7 | `CanonicalPostingUrl`, `studio_postings.url_hash` unique |
| FR-11 | §3.3, §4.2 | `RemoteScopeClassifier`, `studio_postings.remote_scope` |
| FR-12 | §3.1, §4.2 | `GateEvaluator`, `studio_gate_results` |
| FR-13 | §3.1, §4.2 | `DiscoveryScorer`, `studio_postings.d_disc` |
| FR-14 | §3.2, §4.2 | `PostingFetchLadder`, `studio_posting_texts.ladder_step` |
| FR-15 | §3.2, §4.2 | `LaravelAiRequirementExtractor`, `RequirementWeigher`, `studio_requirements` |
| FR-16 | §1, §3.3 | `HeuristicSkillScorer`, `studio_skill_matches` |
| FR-17 | §4.3 | `LaravelAiEmbeddingAdapter`, `SemanticScorer`, `studio_embeddings` |
| FR-18 | §3.3 | `DeterministicScorer` |
| FR-19 | §1, §3.3 | `MatchScoreCalculator`, `CapLadder` |
| FR-20 | §4.2 | `studio_scores.*_components`, `raw_score`, `rules_version` |
| FR-21 | §3.3, §5 | `BandClassifier`, `GET /cv-studio/postings` |
| FR-22 | §6, §8 | `CvVersionDocxExport`, `CvVersionPdfExport`, `PhpWordExportAdapter` |
| FR-23 | §7 | export language tests, untranslatable-term list in config |
| FR-24 | §4.2 | `studio_exports.cv_version_id`, `studio_cv_versions.posting_id` |
| FR-25 | §4.2 | `studio_applications.status` / `outcome` |
| FR-26 | §3.1, §4.2 | `RequirementFrequencyCounter`, `studio_insight_reports.requirements` |
| FR-27 | §4.2 | `ReachCalculator`, `studio_scores.raw_score` + `cap_reason` |
| FR-28 | §4.2, §7 | `OutcomeCorrelator`, `studio_insight_reports.outcome_correlation` |
| FR-29 | §7 | `StudioAgeOutcomesCommand` (analysis only, no write-back) |
| FR-30 | §4.2 | `studio_profiles` |
| FR-31 | §6, §7 | `ProfileGateIncompleteException` |
| FR-32 | §4.2 | `studio_vocabulary`, `studio_sources`, `RefreshVocabularyHandler` |
| FR-33 | §3.1, §4.2, §8 | `StudioBudgetLedger`, `studio_budgets`, `studio_provider_calls` |
| FR-34 | §8 | `RobotsTxtPolicy`, `OutboundUrlGuard` |
| FR-35 | §4.2, §8 | `studio_sources.attribution_*`, `studio_postings.attribution` |
| FR-36 | §3.4, §8, §7 | architectural exclusion + arch test (SC-9) |
| FR-37 | §5, §7 | `RescorePostingHandler`, `StudioRescoreCommand` |
| NFR-1, NFR-2 | §3.3, §7 | pure `Domain/Services`, determinism tests |
| NFR-3 | §5 | every score DTO carries a heuristic label |
| NFR-4 | §4.2 | `studio_runs.spend_micros`, `studio_provider_calls` |
| NFR-5 | §3.1, §5 | queued stages, `GET /cv-studio/runs/{uuid}` |
| NFR-6 | §3.2, §8 | `ProviderFailover`, `CircuitBreaker` |
| NFR-7 | §8 | `user_id` scoping + policies |
| NFR-8 | §3.4, §8 | no personal-data components, by design |
| NFR-9 | §4.2 | `rules_version` on scores, runs, reports |
| NFR-10 | §2 | zero new composer packages; three PG extensions |
| NFR-11 | §4.2 | `studio_cv_versions.content` snapshot |
| NFR-12 | §3.2 | ports + adapters |
| SC-1…SC-12 | §7 | see the testing table row by row |

---

## 11. Architectural changes from the source research — FLAGGED, NOT SILENT (2026-09-18)

`research.md` §7–§9 changed decisions that §1 and §4.2 above already committed to. Per the
user's explicit instruction ("do not modify the architecture silently"), each change is
listed here with its cost and its blast radius **before** any code is written. Sections 1–10
above are left as originally written so the delta stays visible; where a change supersedes
them, this section wins.

### CHG-1 — Discovery becomes two-tier; extraction becomes conditional (supersedes §3.1)

**Was:** one discovery stage over N equal providers, then a shortlist, then always an
extraction stage through the fetch ladder.

**Now:** structured sources are harvested first and, where they already return the full job
description, **the extraction stage is skipped entirely for those postings**. Tavily runs
second, scoped to gap-filling and to discovering employers outside the known feeds.

```
StartRunHandler
   |
   +-- 1. HarvestStructuredSourcesJob      (ATS APIs + feeds + boards, parallel per source)
   |        -> postings WITH full text where the source supplies it
   |
   +-- 2. CanonicalizeAndDedupeJob         (canonical URL + cross-source fingerprint)
   |
   +-- 3. DiscoverViaSearchJob             (Tavily; gap-fill + unknown employers only)
   |
   +-- 4. GatePostingsJob                  (G1 / G2 / G3)
   |
   +-- 5. ShortlistPostingsJob             (D_disc — only over postings still lacking text)
   |
   +-- 6. ExtractPostingsJob               (CONDITIONAL: skipped when text_source = source_api)
   |
   +-- 7. ScorePostingJob                  (per posting, parallel)
   |
   +-- 8. BuildInsightReportJob
```

**Why:** Greenhouse (`?content=true`) and Lever (`additionalPlain`) return authoritative JD
text. Fetching the same posting through a SERP and an HTML parser is both an extra provider
call and *lossier* data (`research.md` §7).

**Cost:** one extra job class, one extra pipeline stage, and `D_disc` now runs over a
**subset** rather than all gate-passed postings. `D_disc` is a budget-rationing device, and
a posting that already has its text costs nothing more to score — so rationing it would be
incoherent. This is a behavioural change to shortlisting, not just a reordering.

**Blast radius:** §3.1, §6 (`Infrastructure/Queue/`), and the pipeline feature tests.

### CHG-2 — Cross-source deduplication needs a fingerprint; canonical URL is insufficient (supersedes FR-10's implementation, not its intent)

**Was:** `studio_postings.url_hash` unique on the canonicalised URL (FR-10).

**Now:** canonical URL **plus** a content fingerprint. The same job legitimately appears as
four different URLs — the employer's Greenhouse board, Arbeitnow's mirror, WeWorkRemotely,
and a LinkedIn listing Tavily surfaced. URL-only dedup stores it four times, which would
inflate `N` in `D_disc`, quadruple the LLM extraction spend, and corrupt every count in the
insight report.

Proposed fingerprint: `sha256(normalized_employer | normalized_title | normalized_location |
posted_on_week)`, with `unaccent` + `pg_trgm` doing the normalisation so
`"Möller GmbH"` and `"Moller Gmbh"` collide as intended. A fingerprint collision creates
**one** posting with **many** source rows — and the cheapest/richest source wins as the text
provider.

**Cost:** one new table (`studio_posting_sources`, the many side), one new column, and the
`ListPostings` query must join to show "seen on 4 boards". Non-trivial, and wrong-by-default
if deferred.

**Note:** `LeadScout/Domain/ValueObjects/PostingFingerprint` already exists and solves the
same problem in the B2B module. Read it before writing a new one (task T-014).

**Blast radius:** §4.2, FR-10, `CanonicalPostingUrl` VO, insight-report counting.

### CHG-3 — Postings expire; liveness is a first-class state (new, from `research.md` §9)

**Was:** not modelled at all.

**Now:** the user reported that several cached postings had **already expired** before being
acted on. A dead posting wastes the whole pipeline — extraction, LLM extraction, embedding,
scoring — and pollutes the reach table with opportunities that were never actually
available.

Adds to `studio_postings`: `is_expired`, `expired_at`, `last_alive_check_at`,
`alive_check_method`. Structured sources give this nearly free — a posting that vanishes
from a Greenhouse board between two harvests is expired, with no extra request. That is
another advantage of CHG-1 that the search-first path cannot offer.

**Cost:** four columns, a re-check command, and a rule about whether an expired posting is
still scored (proposal: keep the score, exclude from the apply table, retain in market
insights — its requirements are still valid market signal).

**Blast radius:** §4.2, FR-21, the insight report, `StudioAgeOutcomesCommand`.

### CHG-4 — `studio_sources` gains resolution metadata (extends §4.2)

New columns, so the hierarchy is data rather than code (the user's requirement that the
architecture not be hard-coded around today's known sources):

| Column | Purpose |
|---|---|
| `resolution_priority` | Integer ordering the hierarchy. Editable without deploy. |
| `supplies_full_text` | Whether a harvest yields JD text — this is what makes extraction conditional (CHG-1). |
| `company_scoped` | True for ATS boards (one slug per call), false for feeds. |
| `ats_kind` | `greenhouse \| lever \| ashby \| smartrecruiters \| recruitee \| workable \| teamtailor \| null` |
| `endpoint_template` | e.g. `https://boards-api.greenhouse.io/v1/boards/{slug}/jobs?content=true` |
| `supports_since_filter` | Whether incremental harvest by date is possible. |
| `requires_api_key` | Separates Teamtailor-class sources from unauthenticated ones. |

Plus a new `studio_source_locales` table seeded from `portal-tiers-cache.json`'s
`multinationals` (125 site queries, per-brand country hosts).

**Blast radius:** §4.2 only. Cheap now, migration-with-backfill later.

### CHG-5 — Query strategy becomes a measured experiment, not a constant (new, from the user's point 4)

Adds `studio_query_templates` (dimensions: country, language, title variation, stack
synonym, seniority, modality, regional terminology) and `studio_query_experiments`
(query → candidates → gate-pass rate → unique-after-dedup → scored ≥70 → cost).

This exists so "does a curated global source list + country/language keywords beat generic
Tavily queries?" is **answered with numbers** rather than asserted. The metric is
deliberately *relevant-and-deduplicated* yield per euro, not raw result count.

**Blast radius:** §4.2, plus tasks T-025–T-031.

### Unchanged

- **§1's rules-version-2 formula** — the source research touched discovery, not scoring.
  The `H`/`S`/`D` blend, the context and position factors, and the cap ladder all stand.
  The one candidate for revision is `D_disc`'s freshness weight (0.10) in light of CHG-3,
  and that is queued as a **measured** change (T-064), not an assumed one.
- **§2's stack** — no new composer package. Every change above is tables and classes.
- **§8's compliance posture** — reinforced: CHG-1 moves the primary path onto
  employer-sanctioned endpoints. LinkedIn remains discovery-only and never enters the
  extraction ladder, now asserted by a dedicated test (T-045).

### Net effect on §4.2

| Change | Tables |
|---|---|
| New | `studio_posting_sources`, `studio_source_locales`, `studio_query_templates`, `studio_query_experiments` |
| Extended | `studio_sources` (+7 cols), `studio_postings` (+4 liveness, +1 fingerprint), `studio_source_companies` (+`ats_kind`, `discovered_via`, `verified_at`, `job_count_last_seen`), `studio_posting_texts` (`ladder_step` gains `source_api`) |
| Unchanged | all 16 others |

Approximately 24 tables rather than 20. **This is the expensive decision on the table, and
it is the one to review before coding starts.**

---

## 12. Revision 2 — round-2 changes (2026-09-18 pm) — FLAGGED, NOT SILENT

Inputs: `spec.md` revision 2 (US-9, US-10, FR-38…FR-50, NFR-13…NFR-15, SC-13…SC-17),
`clarify.md` Q11–Q18 (all resolved), `research.md` §10–§16. Where a change below supersedes
§1–§11, **this section wins**. Skill files read for this revision: `BACKEND-PHP` §4–§4.2,
`ARCHITECTURE-PHP` lean-mode + identifiers, `ARCHITECTURE-VUE` tree, and the `LeadScout`
model convention (`ScoutAiSettingEloquentModel`).

### CHG-6 — v1 is fullstack-only (Q11) — supersedes the "four profiles" parts of §1, §7

- Only the **fullstack** profile is seeded and tested. `studio_profiles.flow` keeps its enum
  values so a later posting-based profile (gap, other) is data, not schema.
- Blend weights, caps, bands and D sub-score tables move from global config to
  **`studio_profiles.rules` jsonb**, with `config/cv-job-studio.php` holding the fullstack
  defaults the seeder writes. Reason: gap uses `0.25/0.25/0.50` and video `0.30/0.25/0.45`;
  a global weight set would force a code change for the next profile.
- **Removed from v1:** trainer and video-content flows (company discovery — later spec),
  their gates (G5 pay floor, G6 pay model, anti-marketing, anti-fraud), and the multi-profile
  seeder. §7's "seeder creates the four profiles" becomes "seeder creates the fullstack profile".
- **Blast radius:** §4.2 `studio_profiles` (+`rules` jsonb), §7, tasks for other profiles.

### CHG-7 — Laravel 13 native `vector` (Q14) — supersedes §4.3

```php
// migration — extensions
Schema::ensureVectorExtensionExists();
DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');

// migration — studio_embeddings
Schema::create('studio_embeddings', function (Blueprint $table): void {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('owner_type', 32);            // cv_bullet | cv_title | posting_title | posting_responsibility | requirement
    $table->unsignedBigInteger('owner_id');
    $table->string('model', 64);
    $table->unsignedSmallInteger('dims');
    $table->char('content_hash', 64);
    $table->vector('embedding', dimensions: 1536);
    $table->timestamps();
    $table->softDeletes();
    $table->unique(['owner_type', 'owner_id', 'model']);
    $table->index(['user_id', 'owner_type']);
});
DB::statement('CREATE INDEX studio_embeddings_hnsw ON studio_embeddings
    USING hnsw (embedding vector_cosine_ops) WITH (m = 16, ef_construction = 64)
    WHERE deleted_at IS NULL');
```

Query side (Eloquent cast `'embedding' => 'array'`):

```php
DB::transaction(function () use ($userId, $vector) {
    DB::statement('SET LOCAL hnsw.iterative_scan = strict_order');   // filtered HNSW trap, §4.3
    DB::statement('SET LOCAL hnsw.ef_search = 100');
    return StudioEmbeddingEloquentModel::query()
        ->select(['id', 'owner_id'])
        ->selectVectorDistance('embedding', $vector, as: 'distance')
        ->where('user_id', $userId)->where('owner_type', 'cv_bullet')
        ->orderByVectorDistance('embedding', $vector)
        ->limit(3)->get();
});
```

- `CREATE INDEX` without `CONCURRENTLY` — the table is empty at migration time and Laravel
  wraps Postgres migrations in a transaction.
- **`[UNVERIFIED]` until spike T-R2-01:** that `orderByVectorDistance` emits the cosine
  operator `<=>` matching `vector_cosine_ops` (an operator mismatch silently skips the
  index). Verify with `EXPLAIN` on a seeded table before any scorer depends on it.
- `halfvec` is dropped (`research.md` §14.1 — storage saving immaterial at this scale).

### CHG-8 — Fit formula, rules version 2 amended (U-1, U-2, U-3) — extends §1, §3.3

The shape, weights, caps and bands are unchanged. The complete v2 fit mathematics:

**Requirements.** Each extracted requirement `r` has tag weight
`w_r ∈ {required 1.0, preferred 0.6, bonus 0.25}` and nature hard | soft.
`W_hard = Σ w_r (hard)`, `W_soft = min(Σ w_r (soft), (0.15/0.85)·W_hard)`; soft weights are
scaled by `W_soft / W_soft_raw` when capped.

**H — heuristic skill match (U-1 + context + position):**

```
c_r   = 1.0  if canonical(r) = canonical(skill) or a CONFIRMED alias relation links them
        0.5  if a CONFIRMED family relation links them            ← U-1: table, not LLM
        0.0  otherwise
κ_r   = 1.0 if the CV evidences the skill inside a bullet, κ_list (cfg 0.85) if list-only
π_r   = 1.0 if its first evidence sits in the top third of rendered order, π_low (cfg 0.90) otherwise
H     = 100 · Σ_r w_r·c_r·κ_r·π_r / (W_hard + W_soft)            (H = 50 + flag if denominator = 0)
```

With `κ = π = 1` this reduces exactly to the local pipeline's H — so v1 → v2 deltas read as
"what layout and evidence placement cost", and the change is monotone (never inflates).

**S — semantic (U-2, requirement-level MaxSim):**

```
norm(x)  = clamp((x − f) / (c − f), 0, 1)        f, c per embedding model (cfg; provisional 0.30 / 0.85)
S_title  = norm( max_t cos(e(jd_title), e(t)) ),  t ∈ {target_job_title, last two role titles}
S_resp   = Σ_j ω_j · norm( max_b cos(e(resp_j), e(b)) ) / Σ_j ω_j
           j = JD responsibilities (ω = 1 must-do, 0.6 nice-to-have), b = CV bullets
S        = 100 · (0.4·S_title + 0.6·S_resp)     (S = 100·S_title + flag if no responsibilities)
```

The 40/60 title/responsibility split is the local pipeline's, now computed instead of
judged. The per-`j` best bullet id, raw cosine and normalised value go to
`studio_scores.s_components`, so SC-2's offline recompute holds with no provider call.

**U-3 — evidence retrieval (the "RAG over the main CV").** The same MaxSim pass returns the
top-3 bullets per requirement and responsibility. They feed (a) the ✓ evidence quote in the
match report, (b) `RewriteCvAgent` / tailoring context, (c) the partial-credit *proposal*
flow (an embedding hit on an unrelated skill creates a **pending** relation, never credit).

**D, caps, bands:** unchanged from §1 / `job-match-scoring.md` (D renormalised over readable
signals 40/30/15/15; caps 55 / 65 / 69, lowest wins; round once; bands 85/75/70/60).

**Purity:** `HeuristicSkillScorer`, `SemanticScorer` and `MatchScoreCalculator` stay pure.
Cosines are computed in infrastructure (pgvector) and passed in as a `SimilarityMatrix`
value object; relations arrive as a `SkillRelationSet` value object.

### CHG-9 — Opportunity layer (Q12, Q13) — new

**Mathematics:**

```
apply_priority = round( fit · Q ),   Q = q_channel · q_fresh · q_ghost · q_level

q_channel  = clamp( p̂(channel) / p̂(direct), floor_ch, 1 )                        floor_ch = 0.15
q_level    = clamp( p̂(level_step) / p̂(level_0), floor_lv, 1 )                    floor_lv = 0.20
p̂(b)       = (α_b + k_b) / (α_b + β_b + n_b),   α_b = m·r_b,  β_b = m·(1 − r_b),  m = 30
             k_b = positive outcomes (screening | interview | offer), n_b = applications with a
             usable outcome (aged silence counts as no_reply for this analysis only — FR-29)
q_fresh    = max( floor_fr, 2^(−age_days / h) ),  h = 5 d, floor_fr = 0.25;  unknown age → 0.60
q_ghost    = 1 − min( 0.60, Σ_s w_s·[s] )
```

| Prior `r_b` (channel) | Value | Source |
|---|---|---|
| `employer_site`, `employer_ats` | 0.0687 | `research.md` §12.1 [VENDOR] |
| `search_engine_jobs` | 0.0660 | same |
| `startup_board` | 0.0375 | same |
| `job_board` | 0.0292 | same (Indeed) |
| `social_listing` (LinkedIn) | 0.0195 | same |
| `aggregator_repost` | 0.0200 | **[UNVERIFIED]** — no dataset; set at LinkedIn level |
| `staffing_agency` | 0.0250 | **[UNVERIFIED]** |

| Level step | Prior ratio vs level 0 | Note |
|---|---|---|
| 0 (title within the profile band) | 1.0 | reference |
| +1 (e.g. "Senior" on a mid band, no years floor) | 0.6 | Q13 — **[UNVERIFIED]**, learned |
| +2 (lead / staff / principal / head / architect) | 0.3 | **[UNVERIFIED]**, learned |

| Ghost signal `s` | `w_s` |
|---|---|
| no publication date | 0.15 |
| age > 30 days | 0.20 |
| same fingerprint re-listed ≥ 3 times spanning ≥ 30 days | 0.25 |
| evergreen wording ("talent pool", "general application", "always hiring", "banco de talento", "candidatura espontânea") | 0.25 |
| aggregator-only — resolution (CHG-11) found no employer copy | 0.10 |

All constants live in `studio_profiles.rules` (CHG-6); every `[UNVERIFIED]` value is shown as
such in the UI next to its sample size (NFR-14).

**Why multiply.** Each factor is a relative likelihood of being read; multiplying assumes they
are independent (a naive-Bayes approximation). That is stated in the breakdown as an
approximation (risk RK-11). **SC-13** is the guard that `Q = 1` reproduces pure fit order.

**Where it is stored and computed:**

- `q_channel`, `q_ghost`, `q_level` change only on events (new sighting, resolution, outcome),
  so they are stored: `studio_scores.opportunity_static` (their product) +
  `studio_scores.o_components` jsonb (per factor: value, reason code, bucket, `n`, prior `m`).
- `q_fresh` changes with the clock, so it is **computed at read time** in SQL, which keeps
  server-side sort + pagination correct without a nightly job (Q5: no scheduler in v1):

  ```sql
  ORDER BY round(s.total_score * s.opportunity_static *
                 CASE WHEN p.posted_at IS NULL THEN :unknown
                      ELSE GREATEST(:floor, power(2, -(EXTRACT(EPOCH FROM now() - p.posted_at) / 86400.0) / :h))
                 END) DESC
  ```

- **Entry into the apply list is still `total_score ≥ 70`** (FR-47); priority only orders it.
- `RecordOutcomeHandler` → `RecomputeChannelBaselinesHandler` (synchronous, a few rows) →
  refreshes `opportunity_static` for that user's open scored postings. **Never** touches
  `total_score`, `raw_score` or any component (NFR-13).

**New table** `studio_channel_baselines`: `user_id`, `bucket_kind` (`channel|level`),
`bucket`, `prior_rate`, `prior_strength`, `positives`, `trials`, `posterior`,
`recomputed_at` — unique `(user_id, bucket_kind, bucket)`. Seeded from the tables above.

**Domain services (pure):** `OpportunityCalculator`, `BetaBinomialEstimator`,
`GhostSignalDetector`, `TitleLevelClassifier`, `FreshnessDecay` (the PHP twin of the SQL
expression, used by tests to prove both agree).

### CHG-10 — Posting provenance: `posted_at`, sightings, channel, ATS kind, liveness

Extends CHG-2 / CHG-3.

| Table | Change |
|---|---|
| `studio_postings` | + `posted_at` (nullable) · `posted_at_source` (`source_field\|json_ld\|page_text\|unknown`) · `apply_channel` (enum, CHG-9 buckets) · `ats_kind` (nullable enum) · `preferred_source_id` (FK → `studio_posting_sources`) |
| `studio_posting_sightings` **(new)** | `posting_id`, `posting_source_id`, `run_id`, `seen_at` — one row per observation; `Prunable` after 180 days; feeds the re-listing ghost signal and expiry (FR-41, FR-42) |
| `studio_sources` | + `fetch_policy` (`fetch\|link_only`) — `link_only` hosts are never fetched by any stage (FR-50) |

- **`posted_at` capture is part of every source adapter's contract** (`PostingSourcePort`
  returns it or an explicit unknown). Greenhouse `updated_at` is *not* a publication date —
  adapters map only fields that mean "first published", else `unknown`. Risk RK-14.
- **`AtsDetector`** (pure) maps apply-URL host/path patterns to `ats_kind`; the pattern list
  lives in `config/cv-job-studio.php` (`research.md` §16.4). Unknown → `null`.
- **Expiry** (CHG-3) now also derives from sightings: absent from its source for N
  consecutive harvests (cfg, default 2) → `is_expired`.

### CHG-11 — Resolve to source (US-10, Q15) — new pipeline stage

```
StartRunHandler
 1 HarvestStructuredSourcesJob
 2 CanonicalizeAndDedupeJob        + sightings, posted_at, apply_channel, ats_kind
 3 DiscoverViaSearchJob            Tavily — gap-fill + unknown employers
 4 ResolveToSourceJob              ← NEW
 5 GatePostingsJob
 6 ShortlistPostingsJob            D_disc over postings still lacking text
 7 ExtractPostingsJob              conditional; never for fetch_policy = link_only
 8 ScorePostingJob                 fit (CHG-8) + opportunity static factors (CHG-9)
 9 BuildInsightReportJob
```

**Algorithm** (`ResolvePostingSourceHandler`), for postings whose `apply_channel` is
`social_listing | aggregator_repost | staffing_agency | job_board` and whose employer is known:

1. `SlugCandidateGenerator` (pure): lowercase, `unaccent`, strip legal suffixes (GmbH, Ltd,
   S.L., Lda, Inc, UAB, SA…), then `{joined, hyphenated}` variants + registry hits from
   `studio_source_companies`.
2. Probe ATS board endpoints in `studio_sources.resolution_priority` order (Greenhouse →
   Lever → Ashby → Recruitee → Workable → SmartRecruiters). Every probe passes
   `OutboundUrlGuard` + `SpendGuardPort` (category `fetch`). Negative results are cached on
   `studio_source_companies` (`status = not_found`, `verified_at`) and not re-probed for
   30 days.
3. On a board hit, match by title trigram similarity
   `similarity(unaccent(lower(board.title)), unaccent(lower(:title))) ≥ 0.55` (cfg) **and**
   a compatible remote scope. Ambiguity (two matches within 0.05) → no link, note it.
4. On a match: add a `studio_posting_sources` row (`channel = employer_ats`), set
   `preferred_source_id`, store the API text as `studio_posting_texts` with
   `ladder_step = source_api` and a `resolution_note`, and re-extract requirements. The
   posting's `apply_channel` becomes `employer_ats` (its `q_channel` rises accordingly).
5. Per-run cap on probes (cfg, default 20).

The candidate can **unlink** a wrong match from the posting page (risk RK-13).
`NeverFetchHostPolicy` (config list: `linkedin.com` and subdomains, plus any host whose
`studio_sources.fetch_policy = link_only`) is asserted in every fetcher and probe; SC-16 is
its test.

### CHG-12 — Skill relations (U-1, Q16) — new table

`studio_skill_relations`: `user_id`, `from_skill`, `to_skill` (canonical names), `kind`
(`alias|family`), `origin` (`seed|esco|model_proposal|user`), `status`
(`confirmed|pending|rejected`), `esco_uri` (nullable), `evidence_note`, `confirmed_at` —
unique `(user_id, from_skill, to_skill, kind)`; `pg_trgm` + `unaccent` index on both names.

- **Seeded** (confirmed) from the JOBORA alias table (`job-match-scoring.md`: JavaScript ⇄
  JS/ES6/ECMAScript, TypeScript ⇄ TS, Vue.js ⇄ Vue/VueJS/Vue 3, Node.js ⇄ Node/NodeJS,
  PostgreSQL ⇄ Postgres, REST APIs ⇄ REST/RESTful/API REST; family: CI/CD ⇄ GitHub Actions).
- **Optional ESCO import:** `studio:import-esco-skills {csv}` reads the ESCO CSV download
  (ICT/digital subset, ES/EN/PT labels) into `origin = esco`, `status = pending`. **ESCO
  version to pin at import time — `[UNVERIFIED]` which release is current.**
- Only `confirmed` rows reach `SkillRelationSet`. A "relations inbox" in the UI confirms or
  rejects pending ones (CHG-14).

### CHG-13 — Audit extensions (FR-38, FR-39) — extends `AtsStructureChecker`, `studio_cv_audits`

| Check | Kind | Rule |
|---|---|---|
| Tailored headline title | deterministic | trigram similarity of CV headline to `target_job_title` ≥ cfg |
| Date format / age-bias dates | deterministic | `MM/YYYY` consistency; flags graduation years > 15 years back and experience listed > 15 years back |
| Employment gaps | deterministic | gap between consecutive entries > 6 months (cfg), reported with a suggested explanation line, never auto-filled |
| Career progression | deterministic | role-title level trend over `studio_cv_entries.ordinal` |
| Skill evidenced in an accomplishment | deterministic | `studio_cv_skills.evidence` ≠ `list_only` |
| Credibility / vague claims | LLM narrative | listed, never scored |

`studio_cv_audits` + `parse_recovery_ratio` (decimal 0–1: token recall of the re-extracted
export text vs the intended text) + `content_issues` jsonb. The two are displayed apart,
never merged (FR-39).

### CHG-14 — UX / UI (NFR-15; `research.md` §11)

Pages under `resources/js/pages/cv-studio/`, module code under
`resources/js/modules/cv-studio/` (the repo's existing kebab-case pattern, e.g.
`lead-scout`). Volt primitives only; tokens only; mobile-first; WCAG AA.

| Page | What it shows | Pattern borrowed |
|---|---|---|
| `Dashboard` | KPI tiles: apply-ready count · applications this month · reply rate **with n and CI** · spend vs budget; "Next best applications" = top 5 by `apply_priority` | — |
| `Postings/Index` | Server-side lazy `DataTable` (desktop) / card list (mobile): fit badge + band tag, opportunity chips (channel · age · ghost · level), `apply_priority`, filters (band, scope, channel, expired, status); bulk delete/restore | AIApply job card |
| `Postings/Show` | **Match report**: fit + priority header, **"Apply direct"** when resolved; requirement checklist ✓ / ~ / ✗ grouped required → preferred → bonus with the evidence bullet quoted; dismiss requirement → rescore; H/S/D bars with the formula in a tooltip; opportunity factors with reason and n; caps + uncapped score ("what it would take"); point-lift suggestions; Tailor CTA | Jobscan match report |
| `Cvs/Audit` | Two-tier report: measured parse recovery + categorised issue checklist; metric-question form | Enhancv checker |
| `Cvs/Structure` | Confirm / correct the parsed structure before first use | — |
| `Versions/Diff` | Tailored vs base, per-bullet diff with provenance; accept/reject per edit; export menu `docx \| pdf` × `es \| en \| pt-PT` | Jobscan sign-off |
| `Applications/Board` | Kanban by status/outcome, aged-silence badge (21 d), link to the exact version sent | Enhancv / Jobscan tracker |
| `Runs/Show` | Stage progress (polling), funnel counts per stage, spend | — |
| `Insights/Show` | Requirement frequency `n/N`, reach table, outcome correlation (suppressed below n = 8), channel baselines with CI | — |
| `Settings/Profile` | Gates, baseline, protected block, rules, **relations inbox** | — |

Module components: `FitScoreBadge`, `OpportunityChips`, `RequirementChecklist`,
`ScoreBreakdownBars`, `RunStageProgress`, `ApplicationBoardColumn`, `RelationsInbox`.
Server state via Pinia Colada (`staleTime` + `gcTime` on every query); run progress via
Inertia v3 polling; heavy report sections via deferred props with skeletons. Every score
label says "heuristic" (NFR-3).

### CHG-15 — Eloquent conventions confirmed for this module (`research.md` §14.5)

Follow `LeadScout` exactly: `#[Table('studio_…')]` + `#[Fillable([...])]` attributes,
`protected $hidden = ['id']`, bigint `id` internal + `uuid` (UUIDv7) public, `final` models
under `Infrastructure/Persistence/Eloquent/Models/`, `casts()` method, enums cast. Per
`BACKEND-PHP` §4 Database Rules: `timestamps` + **`softDeletes` on every table** (supersedes
§4's "user-facing aggregates only"); `Prunable` for `studio_posting_sightings` and
`studio_provider_calls`. `LogsActivity` (v5 namespaces) with explicit `logOnly` on profile,
posting status, application outcome and skill-relation confirmation. `user_id` FKs get the
inverse `hasMany` on `App\Models\User`.

### Stack delta

| Concern | Change |
|---|---|
| Vector storage | Laravel 13.26.1 native `vector` + `whereVectorSimilarTo` / `orderByVectorDistance` — replaces raw `halfvec` (C-5) |
| Structured LLM output | `laravel/ai` v0.11.0 `HasStructuredOutput` — requirement extraction, CV parsing |
| Reranking | `laravel/ai` Reranking available (Cohere default in `config/ai.php`) — **not used in v1** (Q17) |
| Crawl4AI / FastAPI | **Rejected** again (`research.md` §15.4) |
| New composer packages | **None** |

### Risks added

| # | Risk | Mitigation |
|---|---|---|
| RK-11 | Multiplying opportunity factors assumes independence (channel and age correlate on reposts). | Stated as an approximation in the UI; each factor shown separately; SC-13 guards fit order. |
| RK-12 | Channel priors come from a vendor dataset (US-heavy, all industries). | Priors only, `m = 30` pseudo-count, learned per user; `[VENDOR]` label and n shown. |
| RK-13 | Resolve-to-source links the wrong posting. | Title similarity ≥ 0.55 + scope match + ambiguity refusal; one-click unlink; resolution note stored. |
| RK-14 | Boards expose "updated" or "refreshed" dates that look like publication dates. | `posted_at_source` recorded; adapters map only true first-published fields; else `unknown` (penalised, flagged). |
| RK-15 | ESCO release drift. | Import is optional, versioned (`esco_uri`), and only ever creates `pending` relations. |

### Net effect on the schema

| Change | Tables |
|---|---|
| New | `studio_posting_sightings`, `studio_skill_relations`, `studio_channel_baselines` |
| Extended | `studio_profiles` (+`rules`), `studio_postings` (+5), `studio_sources` (+`fetch_policy`), `studio_scores` (+`opportunity_static`, `o_components`), `studio_cv_audits` (+`parse_recovery_ratio`, `content_issues`), `studio_embeddings` (`vector` instead of `halfvec`) |
| Every table | `softDeletes` (CHG-15) |

≈ 27 tables. Opportunity factors live as jsonb on `studio_scores`, like `h_components`, rather
than as a table — the same recompute-offline property with one table fewer.

### Traceability — round-2 requirements

| Requirement | Section | Tables / classes |
|---|---|---|
| FR-38 | CHG-13 | `AtsStructureChecker`, `studio_cv_audits.content_issues` |
| FR-39 | CHG-13 | `studio_cv_audits.parse_recovery_ratio`, `studio_exports.text_extraction_verified` |
| FR-40 | CHG-10 | `studio_postings.posted_at`, `posted_at_source`, `PostingSourcePort` contract |
| FR-41 | CHG-10 | `studio_posting_sightings` |
| FR-42 | CHG-3, CHG-10 | `studio_postings.is_expired`, sightings-derived expiry |
| FR-43 | CHG-10 | `AtsDetector`, `studio_postings.ats_kind` |
| FR-44 | CHG-8, CHG-12 | `studio_skill_relations`, `SkillRelationSet`, `HeuristicSkillScorer` |
| FR-45 | CHG-7, CHG-8 | `studio_embeddings`, `SimilarityMatrix`, `SemanticScorer`, `studio_scores.s_components` |
| FR-46 | CHG-9 | `OpportunityCalculator`, `GhostSignalDetector`, `TitleLevelClassifier`, `studio_scores.o_components` |
| FR-47 | CHG-9 | `ListPostingsHandler` SQL ordering, `FreshnessDecay` |
| FR-48 | CHG-9 | `BetaBinomialEstimator`, `studio_channel_baselines`, `RecomputeChannelBaselinesHandler` |
| FR-49 | CHG-11 | `ResolvePostingSourceHandler`, `ResolveToSourceJob`, `studio_posting_sources`, `preferred_source_id` |
| FR-50 | CHG-10, CHG-11 | `studio_sources.fetch_policy`, `NeverFetchHostPolicy` |
| NFR-13 | CHG-9 | fit components never written by outcome handlers; SC-13 test |
| NFR-14 | CHG-9, CHG-14 | `o_components.n` / `prior_strength`, `OpportunityChips` |
| NFR-15 | CHG-14 | page set, mobile card list, WCAG AA |
| SC-13 … SC-17 | CHG-9 … CHG-11 | see `tasks.md` round-2 phase |

### Unchanged

The §2 stack (bar the vector column type), the §3 ports-and-adapters shape, the §8
compliance posture (reinforced by `fetch_policy` and resolve-to-source), and the round-1
source hierarchy (CHG-1).

---

## 13. Revision 3 — verification round (2026-09-18 evening) — FLAGGED, NOT SILENT

Inputs: `research.md` §17, spec revision 3 (FR-51, FR-52, FR-36 reworded, US-9 and SC-14
amended), `clarify.md` round 3 (Q19, Q20 open). **Where this section conflicts with §12, this
section wins.** Nothing here is implemented; Q19 and Q20 block Phase 7.

### CHG-16 — Evidence-graded opportunity policy — supersedes CHG-9's priors, tables and `m = 30`

**Withdrawn from CHG-9:** the channel prior table (7 rates), the level ratios 0.6 / 0.3, the
ghost weights as "values", and the Beta-Binomial with `m = 30`. Reasons: `research.md` §17.1
(external figures are an ordering, and CHG-9 mapped `employer_ats` to the career-page rate)
and §17.2 (`m = 30` lets one outcome halve a factor; no magnitude is externally verifiable).

**Discovery channel ≠ apply destination.** Two fields on `studio_postings`:

| Field | Values | Meaning |
|---|---|---|
| `discovery_channel` | `ats_api \| feed \| board_api \| search_result \| sitemap \| aggregator \| social_listing` | how the module *found* it — used for yield measurement only |
| `apply_destination` | `at_source` (employer career page or employer ATS form) · `job_board` (board hosts the application) · `intermediary` (aggregator, staffing agency, social listing, unresolved) | where the application *lands* — the only input to `q_channel` |

`ApplyDestinationClassifier` (pure) derives `apply_destination` from the preferred source's
apply URL plus `ats_kind`; resolve-to-source (CHG-11) upgrades `intermediary → at_source`.

**Policy object.** `studio_profiles.rules.opportunity`:

```json
{
  "mode": "policy",                      // "policy" | "neutral"  (neutral ⇒ Q = 1)
  "channel":   { "at_source": 1.0, "job_board": 0.85, "intermediary": 0.70,
                 "grade": "B", "source": "research.md §17.1 (ordering only)" },
  "freshness": { "half_life_days": 7, "floor": 0.5, "unknown_age": 0.7,
                 "grade": "C", "source": "research.md §12.2 (no method published)" },
  "level":     { "plus_one": 0.85, "plus_two": 0.60,
                 "grade": "C", "source": "research.md §17.2 (direction only)" },
  "ghost":     { "max_penalty": 0.30,
                 "weights": { "no_date": 0.10, "age_over_30d": 0.10, "relisted": 0.15,
                              "evergreen_wording": 0.15, "intermediary_only": 0.05 },
                 "grade": "D", "source": "assumption" },
  "evidence_gate": { "min_applications": 30, "min_positives": 3, "prior_strength": 100 }
}
```

The values above are **option (b) of Q19 — confirmed by the user on 2026-09-18** and seeded by
T-018. Options (a) and (c) remain reachable by editing this JSON (no code change). `OpportunityPolicy` (value object) validates it: every factor
in `[0.1, 1]`, every entry carries `grade` and `source`.

**Evidence gate — `BetaBinomialEstimator`.** For each compared pair (e.g. `job_board` vs
`at_source`; level `+1` vs `0`): while `n_a + n_b < 30` **or** `k_a + k_b < 3`, the factor is the
policy value and the UI shows own rates with a 90 % credible interval, flagged "not applied".
Past the gate, `p̂(b) = (N0·r + k_b) / (N0 + n_b)` with `N0 = 100` and `r` = the candidate's pooled
rate, and the factor becomes `clamp(p̂(b) / p̂(ref), 0.1, 1)`, graded **A**. With today's data
(16 applications, 1 positive) **no pair passes the gate** — SC-14 asserts exactly that.

**`o_components`** per factor: `value`, `grade`, `source`, `applied_from` (`policy|own_data`),
`n`, `k`, `reason_code`. The UI renders grade and source next to every chip (NFR-14).

### CHG-17 — SQLite-portable design — supersedes CHG-9's read-time SQL ordering

`phpunit.xml` runs on SQLite in memory (`research.md` §17.4).

| Concern | Design |
|---|---|
| Similarity queries | `SimilaritySearchPort` (Domain). `PgVectorSimilaritySearch` (Infrastructure) wraps each query in a transaction with `SET LOCAL hnsw.iterative_scan = strict_order` and `SET LOCAL hnsw.ef_search = 100`, `whereNull('deleted_at')`, `orderByVectorDistance`. `InMemorySimilaritySearch` (test double, exact cosine in PHP) for the default suite. |
| Title matching (resolve-to-source) | `TrigramSimilarity` — pure PHP, pg_trgm-compatible (lowercase, `unaccent`-equivalent transliteration, padded trigrams, Jaccard). No SQL dependency. |
| Apply ordering | `studio_scores.apply_priority` + `priority_computed_at` **materialised**. `RefreshApplyPriorityHandler` recomputes a user's open apply-list rows when `priority_computed_at` is older than 6 h (config), using an injected `Clock`; list queries `ORDER BY apply_priority DESC` — portable. `FreshnessDecay` is the single implementation. |
| Extension-dependent migrations | `ensureVectorExtensionExists()`, `pg_trgm`, `unaccent`, HNSW index and the `vector` column are guarded by `DB::getDriverName() === 'pgsql'`; SQLite gets a `json`/`text` stand-in column so the schema migrates in tests. |
| PostgreSQL tests | Pest group **`pgsql`**, skipped unless the `pgsql_testing` connection is configured and reachable. Where it points is **Q20**; never the shared development database. |

### CHG-18 — Per-portal access modes — replaces CHG-10's boolean `fetch_policy`

`studio_sources.access_mode` enum, seeded from `research.md` §17.5 and applied to all 67
domains of `portal-tiers-cache.json`:

| `access_mode` | Behaviour | Seeded for |
|---|---|---|
| `api_feed` | Structured harvest through an official API / RSS; fetch allowed per terms | Greenhouse, Lever, Ashby, Recruitee, Workable, Arbeitnow, WWR, RemoteOK, Remotive, Himalayas, Jobicy, Adzuna, **Net-Empregos (RSS)**, **ITJobs.pt (API key)**, **InfoJobs (registered app)** — the last three conditional on V-5 |
| `sitemap` | Discovery from a published sitemap; page fetch only if terms allow | **JustJoin.it** — conditional on V-5, else `search_scoped` |
| `search_scoped` | Search API with `include_domains=[portal]` × keyword templates; URL + snippet only; page fetch only if `robots.txt` allows it for our user agent | boards with no feed whose terms are checked and permit it |
| `link_only` | Discovered via search results; **never fetched** by any stage | **LinkedIn, Indeed, Tecnoempleo**, Glassdoor, and **every domain whose terms are unchecked** (default) |
| `resolve_only` | Discovery only; always passed to resolve-to-source; never an apply destination | **RemoteRocketship, JobLeads, Jobgether, HotRemoteJobs**, other aggregators |

`NeverFetchHostPolicy` reads `access_mode ∈ {link_only, resolve_only}` and is asserted in every
fetcher, probe and ladder step (SC-16). `RobotsTxtPolicy` still gates every allowed fetch.

**Keywords × portals.** `SeedStrategyBuilder` (T-029) crosses keyword templates with portals by
access mode: `api_feed` sources are harvested whole and filtered locally (keywords become
filters, not queries); `search_scoped` / `link_only` portals get `include_domains` searches;
`sitemap` sources are walked. `studio_query_experiments` (CHG-5) records yield per
**(portal × template × locale)** cell — the measurement that answers "portals or keywords" per
portal rather than in general.

New adapters: `NetEmpregosRssSource` (ISO-8859-1 decoding), `ItJobsApiSource`,
`InfoJobsApiSource`, `JustJoinSitemapSource` — each gated on V-5.

### CHG-19 — Vector facts now verified (`research.md` §17.3) — refines CHG-7

- **Verified:** `orderByVectorDistance`, `selectVectorDistance` and `whereVectorSimilarTo` emit
  `<=>`; a `vector_cosine_ops` HNSW index is used; `minSimilarity = 1 − distance`.
- **Verified:** filtered queries under-return without iterative scan (2 rows for `LIMIT 3`).
  `SET LOCAL hnsw.iterative_scan = strict_order` inside the query's transaction is mandatory
  (CHG-17 adapter).
- **Verified:** the connection role can create the `vector` extension. `pg_trgm` and `unaccent`
  remain to probe (T-002).
- CHG-7's `[UNVERIFIED]` marker on the operator is **removed**. **T-000**, the pgsql-group test
  that re-proves all of the above, is the first test written when implementation starts.

### Net effect of revision 3

| Change | Detail |
|---|---|
| Columns | `studio_postings`: + `discovery_channel`, + `apply_destination` (replaces `apply_channel`). `studio_sources`: `access_mode` replaces `fetch_policy`. `studio_scores`: + `apply_priority`, + `priority_computed_at`. |
| Tables | none added; `studio_channel_baselines` gains `grade`, `applied_from`, `gate_passed_at`. |
| Classes | + `ApplyDestinationClassifier`, `OpportunityPolicy`, `SimilaritySearchPort`, `PgVectorSimilaritySearch`, `InMemorySimilaritySearch`, `TrigramSimilarity`, `RefreshApplyPriorityHandler`, four portal adapters. |
| Risks | RK-11/RK-12 downgraded: no external magnitude is applied as fact. New **RK-16** — policy values are subjective; mitigated by grades, the neutral toggle and SC-13. New **RK-17** — pgsql-group tests silently skipped if the test DB is absent; mitigated by T-000 failing loudly in CI when `pgsql_testing` is configured but unreachable. |

### Amendment to §3.4 ("What is deliberately absent") — from `analyze.md` GAP-A3

§3.4 stays true for the **B2B machinery** — no Article 14 notice, no suppression list, no
privacy-request handler: this module never processes people as its subject. But posting text
and search snippets can *incidentally* contain a recruiter's name, e-mail or phone, and spec
US-8 / NFR-8 require that such data is not retained unless it is the posting's application
channel. So a **minimal** minimiser is present (task T-149): contact e-mails/phones kept only
when they are the application channel, detectable named-contact lines removed. An auditor
should read "PersonalDataScrubber-*pattern* present, B2B privacy machinery absent" as correct.

### Routes added by revisions 2–3 (extends §5) — from `analyze.md` GAP-A4

| Method | Route | Purpose | Requirements |
|---|---|---|---|
| POST | `/cv-studio/postings/{uuid}/resolution/unlink` | Undo a wrong resolve-to-source link | FR-49 · RK-13 |
| GET | `/cv-studio/relations` | Pending / confirmed skill relations inbox | FR-44 |
| PUT | `/cv-studio/relations/{uuid}` | Confirm or reject a relation | FR-44 |
| PUT | `/cv-studio/profiles/{uuid}/opportunity-policy` | Edit policy values (validated by `OpportunityPolicy`), incl. `mode: neutral` | FR-51 · US-9 |
| GET | `/cv-studio/insights/own-rates` | Own reply rates per bucket with intervals and gate status | FR-48 · NFR-14 |

Same rules as §5: `auth` + `permission:*`, `->whereUuid('uuid')`, Spatie `Data` responses.

### Traceability — revision 3

| Requirement | Section | Classes / columns |
|---|---|---|
| FR-51 | CHG-16 | `OpportunityPolicy`, `studio_profiles.rules.opportunity`, `BetaBinomialEstimator`, `o_components.grade/applied_from` |
| FR-52 | CHG-18 | `studio_sources.access_mode`, `NeverFetchHostPolicy`, source seeder |
| FR-36 (reworded) | CHG-18 | provider-issued API keys in `config/services.php`; no user-session credentials anywhere |
| SC-14 (amended) | CHG-16 | evidence-gate test over the 16 historical applications |

---

## 14. Revision 4 — LLM cost and resilience (2026-09-18 night) — FLAGGED, NOT SILENT

Input: the user's instruction to use `laravel/ai` prompt caching and failover "si amerita" to
save tokens; `research.md` §18; spec NFR-16, NFR-17, SC-18, SC-19. **Reuses the Shared AI layer
as-is. No new Shared class is required.**

### CHG-20 — Prompt caching + failover per AI purpose

**Mechanism (existing, reused):** agents implement `HasProviderOptions` through the Shared
`UsesPromptCache` trait. Handlers build a `CacheablePrompt` (instructions → long-lived layers →
short-lived layers → tail) and call `PromptCachingAIClient::generateStructured()` once per
failover attempt, following the CourseScripts loop:

```php
foreach ([$purpose->provider, ...$purpose->fallbacks] as $attempt) {
    $this->spendGuard->ensure(BudgetCategory::Llm, $this->estimate($purpose, $prompt, $attempt));
    try {
        return $this->client->generateStructured($purpose->agent, $prompt, $attempt,
            $purpose->modelFor($attempt), $purpose->timeout, step: $purpose->value);
    } catch (Throwable $e) {
        if (! $this->failoverPolicy->isRetryable($e)) { throw $e; }   // availability errors only
        $this->logger->warning('cv_job_studio.ai_failover', ['purpose' => $purpose->value,
            'provider' => $attempt, 'exception' => $e::class]);          // class only — LLM02
    }
}
throw AllProvidersFailedException::for($purpose);
```

The explicit loop is used rather than `laravel/ai`'s native `provider: [...]` array because the
cache options are provider-specific and must be recomputed per attempt, which
`PromptCacheScope` does. This keeps the module consistent with CourseScripts and Campaigns.

**`FailoverPolicy::isRetryable()`** (pure): `ProviderConnectionException`, HTTP 429, 502, 504,
520, 522, 524, provider usage-limit rejections (the 0.11 set, `research.md` §18.1). **Not**
retryable: invalid structured output, validation errors, 4xx client errors. Failing over on
those would double the spend without fixing the cause.

**Per-purpose decision table** ("si amerita"), in `config/cv-job-studio.php` under `ai.purposes`:

| Purpose (`AiPurpose` enum) | Agent | Calls | Stable layers (most stable first) | Tail | Prompt cache | Failover | Model tier |
|---|---|---|---|---|---|---|---|
| `requirement_extraction` | `ExtractRequirementsAgent` | one per **new** posting text (dozens per run) | extraction rubric + JSON schema notes + canonical skill vocabulary (long-lived) | trimmed JD text | **Yes** — highest volume, identical prefix | **Yes** | economy |
| `match_narrative` | `NarrateMatchAgent` | one per scored posting ≥ 60 | CV snapshot of the confirmed structure (long-lived) | score breakdown + ✓/✗ requirements | **Yes** — CV snapshot reused across a run | **Yes**; if all fail, the report renders without narrative (numbers never depend on it) | economy |
| `tailor` | `TailorCvAgent` | one per tailored posting | CV snapshot + protected block + metric answers (long-lived) | posting requirements + evidence bullets (U-3) | **Yes when ≥ 2 tailorings fall within the TTL** — flag on, measured | **Yes** | standard |
| `translate` | `TranslateCvAgent` | three per version (es / en / pt-PT) | untranslatable-terms list + CV version content (long-lived) | target language | **Yes** — one version, three languages | **Yes** | economy |
| `cv_rewrite` | `RewriteCvAgent` | rare (per rewrite) | protected block + metric answers | CV structure | **No** — one-off; an Anthropic write premium would not be recouped | **Yes** | standard |
| `cv_judge` | `JudgeCvAgent` | rare (per audit) | rubric | CV | **No** — one-off | **Yes** | standard |
| `cv_structure_parse` | `ParseCvStructureAgent` | once per CV import | — | raw CV text | **No** — single call | **Yes** | standard |
| embeddings | `EmbeddingPort` | per content hash | n/a | n/a | n/a — `caching.embeddings` + write-once hash (T-053) | **No cross-model failover** — queue retry with backoff; never write a vector from another model | fixed per corpus |

Model names are **configuration**, not code. Defaults use the repo's current providers
(`default = openai`, `failover_order = openai,anthropic`); the exact model per tier is chosen
and priced in T-154.

**Layer rendering rules** (prerequisites for any cache hit, `CacheablePrompt` docblock):
- `CvSnapshotLayer` renders the confirmed structure by ordinal, with sorted keys, no
  timestamps, ids or UUIDs, and a stable `cacheKey` of the form
  `cvjs:{purpose}:{structure_content_hash}`.
- `ExtractionRubricLayer` includes the skill vocabulary sorted alphabetically, versioned by
  `prompt_version`.
- Test: the same input renders **byte-identical** layers twice.

**Ordering for reuse across purposes.** Instructions differ per agent, so prefixes are never
shared *across* purposes. Reuse happens *within* a purpose across calls, which is exactly where
the volume is (extraction and narrative over a run's postings; translation over three
languages).

**TTL.** The global `ai.prompt_cache.long_ttl` is `1h`, which costs about 2× on writes. For
extraction and narrative batches, whose calls fall within minutes, the 5-minute tier may be
cheaper. **T-154 measures both. Only if 1 h loses** does a backward-compatible per-agent TTL
hook get added to `UsesPromptCache` (a Shared change, flagged at that point, not now).

**Parallel fan-out.** `ScorePostingJob` runs in parallel. If measurement shows several
concurrent cache *writes* for the same prefix, the run warms the cache with one serial call per
purpose before fanning out. This is a measured decision, not a default.

**Token savings that are not caching** (listed first because they save the most):

1. **Never re-extract:** `studio_requirements` gains `source_text_hash`. Extraction runs only
   when no requirements exist for that hash (dedup + resolve-to-source mean one extraction per
   job).
2. The LLM runs only **after** deterministic gates, `D_disc` and the G4 snippet
   short-circuit.
3. **Input trimming** (`JdTextTrimmer`, pure): drop benefits, EEO and legal boilerplate by
   heading lexicon (ES/EN/PT), collapse whitespace, cap at `max_input_chars` per purpose.
4. **Output caps:** `max_output_tokens` per purpose; compact JSON schemas.
5. **Model tiering** per purpose (table above).

**Provenance and determinism.** `studio_requirements` gains `extracted_by_provider`,
`extracted_by_model` and `prompt_version`. Rescoring reads stored rows (NFR-1). A failover only
affects *new* extractions and is visible in the breakdown (NFR-17). Re-extracting a posting is
an explicit user action.

**Accounting.** `studio_provider_calls` gains `purpose`, `attempt_no`, `model`,
`input_tokens`, `output_tokens`, `cache_read_input_tokens` and `cache_creation_input_tokens`,
filled from `laravel/ai` `Usage`. Cost is computed from a **pricing table in config** per
provider/model (input, cached input, cache write, output), filled from the providers' live
pricing pages in T-154 and never guessed. The insights page shows per purpose: calls,
cache-hit ratio, tokens served from cache, and actual spend (SC-18).

**Privacy note (LLM02).** CV content travels in cached prefixes. Provider-side caches are
short-lived (minutes to hours), but OpenAI response storage is controlled by
`ai.providers.openai.store` (`OPENAI_STORE`). **T-154 verifies** whether the SDK lets this
module send `store: false` per call through `providerOptions()`; if it does, the module's
agents send it.

### Traceability — revision 4

| Requirement | Section | Classes / columns |
|---|---|---|
| NFR-16 | CHG-20 | `UsesPromptCache`, `CacheablePrompt`, `CvSnapshotLayer`, `ExtractionRubricLayer`, `JdTextTrimmer`, `studio_requirements.source_text_hash`, `studio_provider_calls.cache_*` |
| NFR-17 | CHG-20 | `FailoverPolicy`, per-purpose fallbacks, `studio_requirements.extracted_by_*`, embedding no-switch rule |
| SC-18 | CHG-20 | cache-effectiveness measurement (T-154), insights cost panel |
| SC-19 | CHG-20 | failover tests (T-152) |

### Risks added

| # | Risk | Mitigation |
|---|---|---|
| RK-18 | Caching enabled where the prefix is below the provider minimum, which silently gives no saving (or pays write premiums for nothing on Anthropic). | Per-purpose flag and measurement (T-154), with the flag turned off where there are no reads (SC-18). |
| RK-19 | A fallback provider extracts requirements differently, so a posting's score depends on which provider was up. | `extracted_by_*` provenance; rescoring never re-extracts; the breakdown shows the provider; an explicit re-extract action. |
| RK-20 | Failover on a non-availability error doubles spend. | `FailoverPolicy` retries on availability errors only, with a test. |

---

## 15. Revision 5 — sources as signals, the priority order, and V-5 (2026-09-18 night)

Inputs: `research.md` §19 (V-5), the user's confirmation of the model ("tratarlos como
índice/señal, no como fuente que tu sistema debe rastrear"), spec US-10 (extended), FR-53,
FR-54, A-18, SC-20, and `clarify.md` Q21–Q23. **Extends CHG-11 and CHG-18; where they
conflict, this section wins.**

### CHG-21 — Discover everywhere, extract only through permitted channels

**Final source matrix** (the user's table, with the V-5 evidence and the resulting mode):

| Source | Discovered? | Opens / extracts? | What the module does | `access_mode` | Evidence |
|---|---|---|---|---|---|
| LinkedIn | ✅ via web search | ❌ never | keeps URL/title/snippet → looks for the official copy | `link_only` | §2, §15.2, §17.5 |
| Indeed | ✅ via web search | ❌ never | same → employer's own site | `link_only` | §17.5 (terms ban bots) |
| Glassdoor | ✅ via web search | ❌ never | same | `link_only` | §19.1 |
| Tecnoempleo | ✅ via web search | ❌ never (RSS path disallowed; AI crawlers disallowed) | same | `link_only` | §17.5, §19.1 |
| InfoJobs | ✅ via web search | ❌ without a partner agreement | same | `link_only` (Q22) | §19.1 |
| JustJoin.it | ✅ via web search | ❌ until its user terms are confirmed | same | `link_only` (default) | §19.1 |
| RemoteRocketship | ✅ via web search (or its API) | ✅ only through its paid API | uses the job's employer `url` → official copy | `resolve_only`, or `api_feed` (Q21) | §19.1 |
| JobLeads, Jobgether, other aggregators | ✅ via web search | ❌ | → official copy | `resolve_only` | §12, §17.5 |
| **Net-Empregos** | ✅ | ✅ RSS | official feed, personal use | `api_feed` | §19.1 |
| **ITJobs.pt** | ✅ | ✅ API (key) | official API, personal use | `api_feed` | §19.1 |
| **Greenhouse / Lever / Ashby / Recruitee / Workable** | ✅ | ✅ | employer's ATS API | `api_feed` | §7 |
| Remote feeds (Arbeitnow, WWR, RemoteOK, Remotive, Himalayas, Jobicy), Adzuna | ✅ | ✅ | official APIs / RSS with attribution | `api_feed` | §2, §7 |
| **Employer's own careers page** | ✅ | ✅ **when `robots.txt` allows** | `FetchLadder`: direct HTTP → Firecrawl (**basic proxy only**) | fetch per robots | §19.2 |

**Priority order**, stored as `studio_sources.resolution_tier` and enforced by `SourceResolver`
and `ResolvePostingSourceHandler` (the user's explicit order):

```
tier 1  official API / ATS / feed        Greenhouse, Lever, Ashby, Recruitee, Workable, Arbeitnow,
                                          remote feeds, Adzuna, Net-Empregos RSS, ITJobs.pt API,
                                          RemoteRocketship API (if Q21)
tier 2  permitted portal                  portals whose terms allow sitemap / search-scoped access
                                          (none confirmed today; JustJoin.it if its terms allow)
tier 3  web search                        Tavily direct API — discovery + finding the employer's copy
tier 4  permitted scraping (last resort)  employer careers pages, robots.txt-allowed,
                                          FetchLadder → Firecrawl with proxy "basic"
never   link_only / resolve_only hosts    LinkedIn, Indeed, Glassdoor, Tecnoempleo, InfoJobs,
                                          JustJoin.it, aggregators — never opened
```

**Resolution flow for a signal** (the user's example, now the algorithm of
`ResolvePostingSourceHandler`; replaces CHG-11 steps 1–4):

```
Tavily result: "Senior Laravel Developer — Empresa X"   (linkedin.com/jobs/view/…)
  │
  ├─ AccessModePolicy: linkedin.com is link_only ─────────► NEVER fetched
  │
  ├─ store reference: canonical URL · title · snippet (T-149 strips any personal data)
  │
  ├─ tier 1: SlugCandidateGenerator("Empresa X") → probe Greenhouse / Lever / Ashby /
  │          Recruitee / Workable boards (negative cache 30 d) → TrigramSimilarity(title) ≥ 0.55
  │          + compatible scope → FOUND? ─────────────────► official text via API ✅
  │
  ├─ tier 3: Tavily search  "Empresa X" careers "Senior Laravel Developer"
  │          exclude_domains = every link_only / resolve_only host
  │          → candidate URLs → AtsDetector:
  │               known ATS (incl. Teamtailor/SmartRecruiters when resolved by T-004/T-005)
  │                                              → back to tier 1 with the discovered slug
  │               employer's own domain          → tier 4
  │
  ├─ tier 4: RobotsTxtPolicy allows? → FetchLadder (direct HTTP → Firecrawl, proxy "basic")
  │          → title/scope match → FOUND? ──────────────────► official text ✅
  │          basic proxy refused → not fetchable (never retried in stealth)
  │
  └─ nothing found → posting.status = reference · text_completeness = snippet_only
                     → "Open manually" list (not scored) · candidate may paste the JD (FR-53)
```

Each step records `studio_posting_texts.ladder_step` (`source_api | web_search |
direct_http | firecrawl | candidate_pasted`), and the chain of attempts is kept in
`resolution_note`, so the breakdown shows **where the text came from** (FR-54).

**References and pasted text (FR-53).** `studio_postings.status` gains `reference` (unresolved
signal, never scored). The "Open manually" list shows link, title, snippet, source and date.
**Paste JD** stores the text with `ladder_step = candidate_pasted` and then runs the normal
pipeline: gates → requirements → fit → opportunity. The paste goes through the same T-149
minimiser and the same prompt-injection containment as any posting text (untrusted input).

**Firecrawl proxy rule (SC-20).** `FirecrawlPostingFetcher` always sends `proxy: "basic"`. A
config guard rejects any other value at boot. A refusal in basic mode means "not fetchable" and
is never retried with `auto`, `stealth` or `enhanced`. The same rule applies to any Firecrawl
use inside the module (`research.md` §19.2).

**Usage restriction (A-18).** `studio_sources.usage_restriction` (`none |
personal_non_commercial | partner_agreement_required`), seeded: Net-Empregos and ITJobs.pt →
`personal_non_commercial`; InfoJobs → `partner_agreement_required`. The sources page shows it.
An arch/config test asserts that a source marked `partner_agreement_required` has no enabled
adapter.

### Schema delta of revision 5

| Table | Change |
|---|---|
| `studio_sources` | + `resolution_tier` (1–4), + `usage_restriction` |
| `studio_postings` | `status` + `reference` |
| `studio_posting_texts` | `ladder_step` + `web_search`, `candidate_pasted` |

### Tasks affected

T-113 (Net-Empregos) and T-114 (ITJobs.pt) unblocked. T-115 (InfoJobs) and T-116
(JustJoin.it) **not built** by default. New: T-157 (RemoteRocketship API, conditional on Q21),
T-158 (references + paste), T-159 (basic-proxy rule), T-160 (`resolution_tier` +
`usage_restriction` + seeding). T-118 extended with tier-3 web search and tier-4 fetch.

### Traceability — revision 5

| Requirement | Section | Classes / columns |
|---|---|---|
| FR-53 | CHG-21 | `studio_postings.status = reference`, `ladder_step = candidate_pasted`, `PasteJobTextHandler` |
| FR-54 | CHG-21 | `resolution_tier`, `SourceResolver`, `ResolvePostingSourceHandler`, `resolution_note` |
| SC-20 | CHG-21 | `FirecrawlPostingFetcher` basic-proxy guard + test |
| A-18 | CHG-21 | `studio_sources.usage_restriction` |
