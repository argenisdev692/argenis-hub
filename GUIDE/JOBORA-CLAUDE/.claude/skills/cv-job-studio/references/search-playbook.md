# Search playbook — Tavily + Firecrawl + GitHub MCP

## GitHub enrichment (Career mode only)

1. `get_me` (GitHub MCP) to confirm the authenticated account, or use the
   username from the CV (`github.com/argenisdev692`) if `get_me` isn't the
   right account.
2. `search_repositories` with `query: "user:<username>"`, `sort: "updated"`,
   `perPage: 20` to list repositories.
3. Show the list (name, description, stars, language) to the user and
   **wait for their explicit selection** — never auto-pick "all" or "top N".
4. For each selected repo, `get_file_contents` with `owner`, `repo`,
   `path: "README.md"` (fallback `path: "/"` to see what's there if missing).
   Optionally also fetch `composer.json` / `package.json` to confirm the real
   stack.
5. Summarize per repo: purpose, stack, one measurable-if-honest highlight.
   This becomes GITHUB evidence for the judge/rewrite steps — never invent
   metrics the README doesn't support.

## Job discovery (Tavily-first, both modes)

Use the `tavily_search` MCP tool (server `plugin-tavily-tavily` or
`user-tavily`). Keep queries under ~400 chars, one concern per query, and run
several query variants rather than one giant query. For "last 7 days /
real-time" set `time_range: "week"`.

### Step 0 — Portal tiers + keywords (career, before job discovery)

Read `fullstack cv/portal-tiers-cache.json` and
`fullstack cv/keyword-cache.json`. Follow `references/portal-tiers.md` +
`references/keywords.md`.

**Calendar check (Europe/Lisbon):** get today’s weekday. Sunday + stale
`last_weekly_refresh` → weekly refresh of **both** caches.

1. **SKIP** portal/keyword Tavily research when both caches are populated
   and healthy, it is **not** a due Sunday weekly refresh, yield rescue is
   not active, and the user did not force refresh. Note
   `portal_tiers: cache_hit` + `keywords: cache_hit`.
2. **RUN** portal + keyword research together when:
   - either cache missing/empty/`populated: false`
   - `force_refresh: true` or user says refresh portals/keywords
   - **Sunday weekly** (stale `last_weekly_refresh`)
   - **Low yield** after discovery (see below) — once per run
   Then **merge** new portals (multinational check → `geography_policy`
   filter omit Asia → `S_portal` → tier 1–4), merge strong/emerging
   keywords, set both `populated: true`, bump `researched_at` + Sunday
   `last_weekly_refresh`.
3. Weekday daily job search still runs; portal/keyword inventory research
   does **not** (except Sunday / low-yield / force).
4. Build this run’s site seeds:
   - **Core (~8–10):** `layer: "core"` + LinkedIn/Indeed/InfoJobs locales
     **PT+ES+UK+US+global** + `keyword-cache` `query_fragments`.
   - **Expansion (~6–8):** `rotation_buckets[rotation_index]` portals
     (tier 2–3 first; ≤1 tier-4 freelance unless user asks) + optional ≤2
     `emerging` fragments; then increment `rotation_index` and save.
5. US/UK/CA boards OK for discovery; each JD must still pass G1.
   **Never** seed Asia locales. Asia-locked JDs → FAIL.

**Low-yield rescue:** after Core+Expansion + gates, if
`new_matches < 2` or gate-passed `< 3`, refresh keywords (+ portals if
ranks look stale) once, rebuild ~4–6 Expansion queries, rediscover once.
Note `keywords: low_yield_refresh` / `portal_tiers: low_yield_refresh`.

Other mode: do not use career portal/keyword caches by default.

### Career mode geographic + modality rules

**Search scope (remote):** Prefer **PT, ES, UK, US** (+ EU remote from
Covilhã). May include US/UK/Canada **employers** if fully remote and hires
abroad. **Omit Asia** locales and Asia-locked roles even if LinkedIn/Indeed
have Asia sites.

**Work modality + residency (hard filter — apply before scoring):**

Classify each posting’s remote scope (see `job-match-scoring.md` G1):

- **PASS:** `remote-global` (anywhere/worldwide), `remote-eu` (Europe/EMEA/EU),
  `remote-pt-es` (Portugal and/or Spain), `hybrid-lisbon`, or
  `remote-unclear` (bare “Remote”, no country lock — cascade extract to confirm).
- **FAIL:** `remote-country-locked` (e.g. remote US-only, UK-only, “must live
  in Canada”), hybrid/onsite outside Lisbon, onsite-only.
- Candidate base: Covilhã, Portugal — country-locked remote outside PT/ES/EU
  wastes apply time even if the title says “Remote”.

Do **not** treat “USA UK” query hits as automatically listable — they must
still pass residency + stack gates in `job-match-scoring.md`.

### Career mode query seeds

Adapt to `target_job_title` + **career-relevant** keyword gaps only
(PHP/Laravel/Vue/Inertia). Never seed from NestJS/Angular “Learning” lines.

Prefer generating Core + Expansion seeds from
`fullstack cv/portal-tiers-cache.json` (`layer` + rotation). Fallback if the
cache is unusable — run **both EN and ES** each run:

**Seniority order (career, since 2026-08-24):** lead with **mid-level** and
`stack_pairs` wording from `keyword-cache.json` (`keywords.*.role`,
`stack_pairs`). Both languages, every run. Junior/entry wording moved to
**Expansion** (remote junior openings are scarce — see `keywords.md`
§ Seniority routing). Senior/lead wording is not seeded; organic senior hits
still score normally.

**English — mid-level Core (run these first)**

M1. `"mid-level" OR "mid level" full stack developer Laravel Vue remote`
M2. `"PHP developer" Vue OR "Laravel Vue developer" remote Europe`
M3. `"Laravel Inertia developer" OR "Laravel TypeScript Vue" OR "Laravel React TypeScript" remote`
M4. `"full stack" Laravel Vue OR Inertia remote Europe`
M5. `PHP Laravel backend developer remote Spain OR Portugal OR EU`

**Spanish — mid-level Core (español correcto)**

M6. `"desarrollador full stack Laravel Vue" remoto OR teletrabajo`
M7. `"desarrollador PHP Vue" OR "desarrollador Vue Laravel" remoto España OR Portugal`
M8. `"desarrollador web Laravel" OR "desarrollador full stack PHP" teletrabajo`
M9. `programador Laravel Vue remoto España OR Portugal`

**Both seniority edges — Expansion only (rotation, not every run)**

E1. `"junior" OR "entry level" OR "associate" Laravel OR "PHP developer" remote`
E2. `"desarrollador PHP Laravel junior" OR "programador Laravel junior" remoto`
E3. `"senior" (Laravel OR "PHP developer" OR "full stack PHP") remote Europe OR EMEA`
E4. `"desarrollador senior" Laravel OR PHP remoto España OR Portugal`

Keep E1–E2 rather than deleting them: postings labelled "Junior" that
actually ask for 3–4 years do appear, and they cost one query.

E3–E4 (added 2026-08-24) exist because the candidate's history includes a
**78** on a "Senior Full-Stack Engineer" posting. Seeding senior is cheap
and safe: any JD with an explicit **7+ year floor is capped at 55 by G4**
before it can reach the table, and the G4 snippet check skips its deep
extract, so the cost is one query — not table noise or Firecrawl budget.
“Senior” with no years number scores normally (D years = 75, no cap).

Lead / principal / staff / architect / engineering manager stay **unseeded**
— those floors are consistently out of range.

**English (generic fallback)**

1. `remote Laravel developer PHP Vue Inertia Spain Portugal`
2. `remote full stack Laravel PHP Vue.js job Europe`
3. `Laravel Livewire developer remote Europe hiring`
4. `remote PHP Laravel backend developer Portugal Spain`
5. `hybrid Laravel developer Lisbon Lisboa Portugal` (Lisbon hybrid only)
6. Optional catch-all: `remote Laravel Vue developer job` — then **strict**
   post-filter for remote residency + PHP/Laravel stack

**Spanish (español correcto — ES, no mezclar pt-BR)**

7. `desarrollador Laravel remoto PHP Vue España Portugal`
8. `oferta empleo full stack Laravel PHP remoto Europa`
9. `desarrollador backend PHP Laravel remoto España`
10. `programador Laravel Vue Inertia remoto España Portugal`
11. `empleo híbrido Laravel Lisboa Portugal` (solo híbrido Lisboa)
12. Optional: `vacante Laravel PHP remoto` — same strict post-filter

**Portal `site:` seeds from cache (preferred over hand-listing)**

Append Laravel/PHP/Inertia/Vue (or ES equivalents) to each portal’s
`site_query`. Core examples when cache is present:
`site:weworkremotely.com`, `site:remoteok.com`, `site:landing.jobs`,
`site:remotive.com`, `site:itjobs.pt`, `site:remoterocketship.com`,
`site:pt.indeed.com` / `site:es.indeed.com`. Expansion comes from the
active rotation bucket (JustJoin, Dice, Otta, Job Bank, etc.).

**LinkedIn Jobs (dedicado — Tavily no “incluye LinkedIn” solo por ser global)**

Tavily indexa la web abierta. Un job de LinkedIn **puede** aparecer en seeds
genéricas si está indexado, pero **no es fiable**. Para cobertura real de
LinkedIn, cada corrida debe incluir al menos 2–4 queries con
`site:linkedin.com/jobs` (EN + ES). Luego aplicar los mismos gates
(modalidad / residencia / stack).

`site:` = **consulta al índice** (URL + título + snippet), no extracción
fiable de la página. LinkedIn y otros ATS con login/anti-bot suelen ser
**consultables pero no scrapables** con Firecrawl — usar la cascada de
extracción abajo, no insistir en scrape directo de perfiles.

13. `site:linkedin.com/jobs remote Laravel PHP developer Spain OR Portugal OR Europe`
14. `site:linkedin.com/jobs "PHP developer" OR "Laravel developer" OR "backend developer PHP" remote`
15. `site:linkedin.com/jobs desarrollador Laravel OR "desarrollador PHP" OR "full stack PHP" remoto España OR Portugal`
16. `site:linkedin.com/jobs "software engineer" PHP OR Laravel remote (Spain OR Portugal OR Europe OR EMEA)`
17. `site:linkedin.com/jobs "mid-level" OR "full stack developer" Laravel Vue remote Europe OR EMEA`
18. `site:linkedin.com/jobs "desarrollador full stack Laravel Vue" OR "desarrollador PHP Vue" remoto España OR Portugal`

Seeds 17–18 are part of the **Core** LinkedIn round (mid-level focus).
The junior variant (`"junior" OR "entry level" OR "associate" Laravel
remote`) and the senior one (`"senior" Laravel OR "PHP developer" remote
Europe`) belong to **Expansion** rotation only.

US/UK/CA **portal** expansion is OK via portal-tiers rotation; still apply
strict G1 — country-locked remote FAIL. Prefer Europe/worldwide wording on
generic seeds.

Run seeds with `country` boost (`Portugal`, `Spain`) when useful. For ES
queries, prefer `country: "Spain"` / `"Portugal"` boosts.

Other Niche mode: derive query seeds, geography, work modality, and output
language entirely from the user's **approved `gate_config`** (see
`job-match-scoring.md`). No career Lisbon/Laravel/EN+ES defaults. Ask for
whatever is missing before searching; show `gate_config` once for OK.

Recommended params for **job** discovery: `search_depth: "advanced"`,
`max_results: 10`, `time_range: "week"`, `include_raw_content: false`
(keep it light — deep content via the extraction cascade on selected URLs).
Portal-list research (cache miss only): `time_range: "year"`,
`search_depth: "advanced"`.

For each result: normalize the URL (see `cache-schema.md`), skip if already
in `seen_urls`, otherwise keep title/company/URL/snippet as a **raw
candidate**.

## Filter → score pipeline (mandatory)

For every raw candidate, in order:

1. **Gates** (`job-match-scoring.md` G1–G3): modality, career stack lock,
   listing quality. Fail → exclude; log in `runs.notes`.
2. **G4 snippet check:** if a hard years/credential dealbreaker is already
   clear from title/snippet → skip deep extract, note
   `extract: skipped_g4_snippet`, count in `excluded_count`. Do not spend
   cascade budget (capped score 55 never reaches the ≥70 table).
3. **Discovery score** (career — mandatory before deep extract):
   `D_disc = 0.35·P + 0.25·K + 0.20·R + 0.10·F + 0.10·N`
   with full 0–1 scales in `portal-tiers.md` § D_disc (P portal, K keyword
   hit, R residency hint, F freshness, N novelty). Keep top ~5–8 with
   `D_disc ≥ 0.55` (or best remaining).
4. **Deep extract cascade** for those shortlisted candidates — see below.
5. **Score** H / S / D → `match_score` per `job-match-scoring.md`
   (soft-skill 15% renormalize; apply G4 cap if confirmed only after extract).
6. **Table** only if `match_score >= 70`. Cache 60–69 as `status: skipped`
   (see `cache-schema.md`) so they are not re-extracted next run.
7. **Keep the requirement list of every JD you read** — table, skipped, and
   G4/G4b-capped alike — for the market-insights pass
   (`market-insights.md`). Do not discard a capped JD's requirements just
   because it never reached the table; those are the highest-signal ones.

## Deep extract cascade (Tavily + Firecrawl)

Only for **new** gate-passed candidates that cleared the G4 snippet check
and the `D_disc` shortlist (don't scrape every historical match — mirrors
FR-8b). Goal: obtain enough JD text to score modality, residency, and
stack — not to force-open every blocked URL.

**Applies to every discovery host**, not only LinkedIn: InfoJobs, Indeed,
Glassdoor, Wellfound, board aggregators, cookie walls, login walls, empty
JS shells, soft 403s — same cascade whenever search finds a posting but
step 1 returns unusable content.

Stop at the first step that yields usable JD content (≥ ~400 chars of
role/requirements text, or clear modality + stack signals). Record which
step succeeded in `runs.notes` (e.g. `extract: tavily_advanced`).

### Step 1 — Firecrawl scrape (preferred when the host allows it)

`firecrawl_scrape` (`user-firecrawl`): `formats: ["markdown"]`,
`onlyMainContent: true`. Optional `proxy: "auto"` if the first attempt
returns empty / challenge / login wall.

**Skip step 1** for `linkedin.com/in/*` profiles (almost always blocked).
For `linkedin.com/jobs/*` and other repeatedly blocked hosts: try **once**;
if blocked/empty/challenge, go to step 2 — do not retry the same URL.

Greenhouse, Lever, Ashby, Teamtailor, Workable, and company `/careers`
pages usually succeed here — use them as the primary JD source when the
apply URL already left the aggregator.

### Step 2 — Tavily extract (found by search, blocked by scrape)

`tavily_extract` (`user-tavily` or `plugin-tavily-tavily`):
- `urls`: [candidate job URL]
- `extract_depth: "advanced"` (LinkedIn, protected boards, heavy JS/tables)
- `format: "markdown"`
- Optional `query`: short role keywords to rerank chunks

Use returned content as the JOB DESCRIPTION for scoring.

### Step 3 — Tavily raw content boost

If extract fails or is too thin: one `tavily_search` with the **exact job
URL** or `"<exact title>" "<company>"` plus
`include_raw_content: true`, `search_depth: "advanced"`, `max_results: 3`.
Prefer the result whose URL matches the candidate.

### Step 4 — Pivot to a scrapable careers / ATS page

When the original host (LinkedIn, InfoJobs, etc.) still has no usable JD:

1. From snippet/title, take **company name** + role keywords.
2. `tavily_search`: `"<Company>" (careers OR jobs OR "join us" OR empleo) Laravel OR PHP` (adapt stack). Optionally
   `exclude_domains` with the blocked host(s) so results leave the wall.
3. Or `firecrawl_extract` on the original job URL with
   `allowExternalLinks: true`, `enableWebSearch: true`, and a prompt asking
   for the public apply/careers URL + remote scope + must-have stack.
4. `firecrawl_scrape` the company/ATS URL found in (2) or (3).

Never invent a careers URL. If no scrapable mirror exists, score from the
best available snippet/raw content, mark `extract: snippet_only`, and apply
a D penalty when modality/residency stays `remote-unclear`.

### What counts as success vs give-up

| Outcome | Action |
|---|---|
| Full JD (any cascade step) | Score normally; prefer this text over the discovery snippet |
| Partial JD (modality + stack clear) | Score; note gaps in Why/gaps |
| Snippet only, gates still PASS | Score with D penalty if remote scope unclear; list only if ≥ 70 |
| Blocked + no company mirror + gates ambiguous | Exclude; log reason |

Do **not** burn Firecrawl budget retrying the same blocked URL. Cascades
2–4 exist so aggregators stay discovery channels even when their pages
cannot be scraped.

## Grounding the ATS rewrite in 2026 best practices

Before rewriting, run one `tavily_search` (or `tavily_research` for a deeper
pass) query such as `ATS friendly resume best practices 2026 high response
rate recruiter Jobscan match rate` with `search_depth: "advanced"`,
`time_range: "month"`. Use findings only to sanity-check formatting/keyword
guidance — never as a source of facts about the candidate.

## Sequential thinking checkpoints

Call `sequentialthinking` (server `user-sequential-thinking`) at least
**three** times per full run:

1. Before finalizing the judge audit — strengths/gaps/XYZ; keep keyword_gaps
   inside the career stack.
2. After raw Tavily results — which URLs fail modality/stack gates and why.
3. Before the final ranked table — verify each `match_score` from H/S/D and
   that ranking order matches the formula (not vibes).
4. Before the market-insights report — go through the JDs read this run
   (including capped/skipped ones), tally the requirements, and check each
   count against the actual JD text before writing a percentage. This is the
   step where invented "the market wants X" claims get caught.
