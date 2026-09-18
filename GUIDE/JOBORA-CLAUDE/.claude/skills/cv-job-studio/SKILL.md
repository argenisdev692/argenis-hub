---
name: cv-job-studio
description: >-
  Local mirror of the AiResumeStudio pipeline (judge audit -> ATS rewrite ->
  job search -> match scoring -> translation) run entirely inside Cursor with
  MCP GitHub, Tavily, Firecrawl, and sequential-thinking. Use when the user
  asks to fix/optimize/audit their CV for ATS, search remote Laravel/PHP/
  Inertia/Vue jobs matching their CV, score job/CV compatibility (ROI), act as
  a recruiter judging the first-10-seconds impression, or translate the CV to
  en/es/pt-PT. Invoked by the /cv-job-search, /cv-job-search-other,
  /cv-judge, and /cv-translate commands.
disable-model-invocation: true
---

# CV Job Studio (local)

Local, Cursor-native version of the Laravel module `AiResumeStudio`
(`003-cv-ats-job-studio`). Always follow the hard rules in
`.claude/rules/cv-job-studio.md` — this file is the step-by-step workflow.

Two modes:
- **career** — folder `fullstack cv/`, uses GitHub MCP enrichment, defaults to
  Laravel/PHP/Inertia/Vue remote search.
- **other** — folder `other cv/`, no GitHub, driven by a user-supplied
  targeting prompt (role/industry/location/tone).

## Workflow

```
Progress:
- [ ] 1. Resolve CV + cache for the mode
- [ ] 2. Career: GitHub enrichment (user-selected repos) | Other: targeting prompt
- [ ] 3. Sequential-thinking judge pass (10s scan + XYZ + keyword gaps)
- [ ] 4. Ground in 2026 ATS best practices (Tavily)
- [ ] 5. ATS rewrite -> output/ATS_<name>_<date>.md
- [ ] 6. Portal tiers + keyword-cache: Sunday/low-yield/miss → refresh; else skip
- [ ] 7. Job search (Core + Expansion) → gates → G4 snippet check → D_disc → deep extract
- [ ] 8. Score H/S/D, dedupe, table (≥70); cache 60–69 as skipped; low-yield rescue if needed
- [ ] 9. Update job-search-cache + bump portal rotation / weekly stamps
- [ ] 10. Market insights: recurring requirements across every JD read + recommendations
- [ ] 11. Offer next actions (translate, cover draft, digest, mark status)
```

### 1. Resolve CV + cache

- List markdown files in the mode folder (`fullstack cv/` or `other cv/`).
  If more than one, ask which is the base CV. If none, ask the user to add
  one and stop.
- Read the CV file and the folder's `job-search-cache.json` (create an empty
  one per `references/cache-schema.md` if missing).

### 2. Enrichment

**Career:** follow `references/search-playbook.md` "GitHub enrichment" —
list repos via MCP GitHub, **wait for the user's explicit selection**, read
README (+ composer.json/package.json) for selected repos only. If the user
skips this or GitHub fails, continue CV-only.

**Other:** ask for a targeting prompt if not already provided in the command
arguments: target role, industry, location/remote scope, tone, search
language(s). Block and ask again if it's empty — don't guess a niche.
Then compile and show a `gate_config` per
`references/job-match-scoring.md` “Career vs Other mode” — wait for user OK
before Tavily. Never inherit career Lisbon/Laravel gates.

### 3. Judge pass (recruiter 10-second scan + XYZ audit)

Use `sequentialthinking` to reason through the CV before writing anything
(see `references/search-playbook.md` checkpoint 1). Then produce, following
`references/scoring-rubric.md` §1–2:

- 10-second scan verdict (PASS / BORDERLINE / REJECT RISK) with concrete
  reasons — be a blunt Fortune-500 recruiter, not a cheerleader.
- `target_job_title`, `strengths`, `improvements`, `keyword_gaps`,
  `xyz_gaps`, and up to 6 `metric_questions`.
- If there are `metric_questions`, ask the user now and wait for answers
  before rewriting (skip only if the user says to proceed without them).

### 4. Ground in current best practices

Run one Tavily search/research call per `references/search-playbook.md`
"Grounding the ATS rewrite" — use it to sanity-check formatting/keyword
guidance, never as a source of facts about the candidate.

### 5. ATS rewrite

Rewrite the CV per `references/scoring-rubric.md` §3 and the ATS Markdown /
banned-phrases rules in the project rule. For the career CV, always keep the
"Always-preserve block" from the project rule intact (Education, city/
country, argenis.dev, WhatsApp, LinkedIn, GitHub, social row, Imagina
Formación training roles) and concentrate rewrite effort on Projects (XYZ
bullets + verified demo/repo links, all genuinely fullstack-related). Save as
`<mode folder>/output/ATS_<cv-name>_<YYYY-MM-DD>.md` (never overwrite the
source CV). Report `ats_score` (labeled heuristic) + residual feedback.

### 6. Portal tiers + keywords (career — before job discovery)

Read `fullstack cv/portal-tiers-cache.json`,
`fullstack cv/keyword-cache.json`, plus `references/portal-tiers.md` and
`references/keywords.md`.

Check **today** in `Europe/Lisbon`:

- **Skip** weekday portal/keyword Tavily when both caches are populated and
  healthy. Note `portal_tiers: cache_hit` + `keywords: cache_hit`.
- **Refresh both** when: cache miss/empty; user says refresh portals /
  keywords; **Sunday** and `last_weekly_refresh` is stale for this week;
  or later **low yield** after discovery.
- On refresh: **merge** new boards into the open catalog (no top-15 truncate);
  run multinational **locale discovery** (new country hosts for LinkedIn/
  Indeed/Randstad/Glassdoor); merge strong + emerging keywords; set
  `populated: true` and bump `last_weekly_refresh` on Sunday runs.
- Build Core + rotating Expansion seeds from portals + `query_fragments`.
  Prefer locales **PT, ES, UK, US, global**. On new boards: discover country
  sites → filter `geography_policy` (omit Asia) → `S_portal` → tier 1–4.
  Never query Asia LinkedIn/Indeed locales.

Other mode: skip unless other-mode caches exist for the approved
`gate_config`.

### 7. Job search + gates + discovery score

Follow `references/search-playbook.md`. Career seeds: portal Core/Expansion
+ keyword-cache fragments + `target_job_title` + career-stack gaps only
(PHP / Laravel / Vue / Inertia — never NestJS/Angular). Other: targeting
prompt. Use `time_range: "week"`.

**Seniority:** Core seeds lead with **mid-level** + `stack_pairs` wording
(`keyword-cache.json` → `keywords.*.role`, `stack_pairs`,
`fragment_policy`). **Expansion rotation carries both edges** — junior /
entry / associate (thin) and **senior** (`keywords.*.senior`). Lead /
principal / staff / architect stay unseeded. Nothing here is a gate:
seeding order is a budget decision and never changes how a JD scores. A 7+
year floor is capped at 55 by G4 regardless of how the posting was found.

After Tavily: **gates** G1–G3 (`job-match-scoring.md`) → **G4 snippet
check** (skip extract if dealbreaker clear) → **D_disc** shortlist
(P/K/R/F/N in `portal-tiers.md`) → deep extract cascade on top ~5–8.
Career: Covilhã/Lisbon + PHP/Laravel + EN/ES + LinkedIn seeds. US/UK/CA
portal hits still need G1. Other: approved `gate_config` only.

If after gates `new_matches < 2` or gate-passed `< 3`, run the **low-yield
rescue** once (keyword ± portal refresh + ~4–6 Expansion queries).

### 8. Score + present results

Use `sequentialthinking` then score each deep-extracted candidate with
`references/job-match-scoring.md` + rubric §4:

`match_score = round(0.45·H + 0.25·S + 0.30·D)` (heuristic ROI; soft ≤15%
renormalize).

Drop URLs already in `seen_urls` (canonicalize first). **Only list
`match_score >= 70`.** Cache 60–69 as `status: skipped`. Present:

| Title | Company | Link | ROI | H | S | D | Modality | Why / gaps | Posted |
|---|---|---|---|---|---|---|---|---|---|

Sort by ROI descending. Mention prior `new`/`saved` cache hits separately if
useful — don't re-list as new.

### 9. Update caches

Write gate-passed scored entries (`score_breakdown`, `modality`, `location`)
— including `status: skipped` for 60–69 — + a `runs` record
(`excluded_count`, notes, `portal_tiers` / `keywords`:
`cache_hit|sunday_refresh|low_yield_refresh|refreshed`) per
`references/cache-schema.md`. Always update even if 0 new matches.
On a normal career job run, bump `portal-tiers-cache.json`
`discovery.rotation_index` and `updated_at`. Do **not** clear portals or
keywords on a weekday hit.

### 10. Market insights (mandatory — every career run)

After the table and the cache write, produce the recurring-requirement
report per `references/market-insights.md`:

- Count requirements across **every JD read this run** — table matches,
  60–69 `skipped`, **and the G4/G4b capped ones** (the capped bucket is
  where the real blockers live). Never count G2 stack-fails.
- Report `n/N` (+ % only when `N ≥ 5`), what the CV covers (`✔ / ~ / ✘`),
  and mark `persistent` anything also present in ≥2 of the last 3 runs.
- Close with ≤5 ranked recommendations split into **wording** (evidence
  already in the CV, said differently) and **real skill gaps** (study time).
  **Never** propose writing an absent skill into the CV.
- Add the **reach table** (what each cap costs in jobs unlocked, from
  `cap_reason` + `raw_score`) and, once **≥8 applications have a usable
  outcome**, the **outcome correlation** (requirement frequency in
  responded vs silent applications — directional, never causal).
- Persist as `runs[].market_insights` (`cache-schema.md`).

Run this even when the table is empty — zero matches is itself the strongest
market signal, and the report explains why.

### 11. Offer next actions

Ask if the user wants any of:
- **Translate** the latest ATS CV to `en` / `es` / `pt-PT` — save each as
  `output/CV_<lang>.md`, never overwrite. Full language rules in the project
  rule and `references/scoring-rubric.md` §6.
- A per-job cover/application draft (150–220 words, human voice, no AI
  filler) for a selected match.
- A short digest summarizing this run's new matches.
- Mark a match's `status` in the cache (`saved`/`applied`/`dismissed`).
- Record an **`outcome`** on an applied entry (`no_reply` / `rejected` /
  `screening` / `interview` / `offer`) + optional `outcome_note`. Ask
  whenever applied entries are sitting at `unknown` — this is the input the
  outcome-correlation section runs on. Never guess one.

## Additional resources

- [references/job-match-scoring.md](references/job-match-scoring.md) —
  Jobscan-inspired gates + H/S/D formula (source of truth for ROI).
- [references/scoring-rubric.md](references/scoring-rubric.md) — judge/ATS/
  match checklist, banned phrases, language rules.
- [references/cache-schema.md](references/cache-schema.md) — job + portal
  tiers cache schemas and update procedures.
- [references/portal-tiers.md](references/portal-tiers.md) — open portal
  catalog, `S_portal` sum, **D_disc P/K/R/F/N scales**, Sunday / low-yield.
- [references/keywords.md](references/keywords.md) — keyword-cache weekly
  Tavily refresh + fragments for seeds / D_disc.
- [references/search-playbook.md](references/search-playbook.md) — exact
  GitHub/Tavily/Firecrawl/sequential-thinking tool usage.
- [references/market-insights.md](references/market-insights.md) — recurring
  requirement counting + recommendations after each run.
