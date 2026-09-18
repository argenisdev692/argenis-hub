# Cache schemas

## Portal tiers cache (career)

- Path: `fullstack cv/portal-tiers-cache.json`
- Spec: `references/portal-tiers.md`
- Purpose: **open** IT portal catalog per region (Europe, USA, UK, Canada,
  Iberia, Lisbon hybrid) + multinational locales. Soft ranks only — **no
  hard top-15 exclude**. Organic unknown domains that pass gates still score.
- **Skip research** on weekdays when `populated: true`.
- **Refresh** on Sunday (if `last_weekly_refresh` stale), low yield,
  cache miss, or user force — with keyword-cache; include **locale discovery**
  for LinkedIn/Indeed/Randstad/Glassdoor (merge new country hosts).
- Job URL dedupe stays in `job-search-cache.json`; portal inventory is
  separate.
- After each career job-search run: increment `discovery.rotation_index`
  (mod bucket count) and bump `updated_at`. Do not wipe `regions`.

## Keyword cache (career)

- Path: `fullstack cv/keyword-cache.json`
- Spec: `references/keywords.md`
- Purpose: strong EN/ES stack + role + modality terms and
  `query_fragments` for seeds; `emerging` from weekly Tavily.
- Same refresh cadence as portal tiers (Sunday / low-yield / miss / force).
- `stack_lock` must stay PHP/Laravel/Vue/Inertia; `never_seed` blocks
  Nest/Angular pivots.
- Feeds **K** in discovery score `D_disc` (scales in `portal-tiers.md`).

## job-search-cache.json schema

One cache file per mode folder (`fullstack cv/job-search-cache.json` and
`other cv/job-search-cache.json`). Never mix the two.

```json
{
  "version": 2,
  "mode": "fullstack",
  "updated_at": "2026-08-01T09:00:00Z",
  "seen_urls": {
    "https://example.com/jobs/senior-laravel-developer": {
      "title": "Senior Laravel Developer",
      "company": "Example Co",
      "source": "tavily",
      "match_score": 82,
      "score_breakdown": {
        "H": 88,
        "S": 78,
        "D": 76,
        "semantic_note": "proxy"
      },
      "modality": "remote-eu",
      "location": "Europe (remote)",
      "status": "new",
      "first_seen": "2026-08-01",
      "last_seen": "2026-08-01"
    }
  },
  "runs": [
    {
      "date": "2026-08-01",
      "queries": ["remote Laravel PHP Vue Inertia Spain Portugal"],
      "new_matches": 5,
      "excluded_count": 12,
      "ats_score": 78,
      "target_job_title": "Senior Full Stack Developer (Laravel/Vue)",
      "notes": "Excluded 3 hybrid-USA, 4 NestJS/Angular-only, 5 score<70.",
      "market_insights": {
        "jds_analyzed": 11,
        "sample_note": "8 full JD text, 3 substantive snippet",
        "requirements": [
          {
            "name": "English B2+",
            "bucket": "language",
            "seen": 6,
            "of": 11,
            "required": 5,
            "preferred": 1,
            "in_cv": false,
            "verdict": "blocking",
            "evidence_urls": ["https://example.com/jobs/x"]
          }
        ],
        "recommendations": [
          { "type": "wording", "text": "…" },
          { "type": "skill_gap", "text": "…", "effort": "…" }
        ]
      }
    }
  ]
}
```

## Fields

| Field | Required | Notes |
|---|---|---|
| `match_score` | yes (scored matches) | Heuristic ROI 0–100 from `job-match-scoring.md` |
| `score_breakdown` | yes for new scored entries | `H`, `S`, `D` (0–100 each) |
| `modality` | yes | `remote-global` \| `remote-eu` \| `remote-pt-es` \| `remote-unclear` \| `hybrid-lisbon` |
| `location` | yes | Free text from JD (city/country/remote) |
| `status` | yes | see below |

Do **not** cache gate-failed jobs as scored matches. Summarize them in
`runs.notes` / `excluded_count` instead.

**Capped entries:** when a G4 / G4b / evidence cap was applied, also store
`"cap_reason"` (`lang_cap_b2` | `lang_cap_c1` | `lang_cap_other_language` |
`years_cap` | `credential_cap` | `evidence_cap`) and `"raw_score"` — the
**uncapped** `0.45·H + 0.25·S + 0.30·D`, rounded. Both feed the reach
analysis in `market-insights.md`, which prices each gap in jobs unlocked.
Without `raw_score` the next run cannot tell a posting that scored 84 and
was capped by a language line from one that genuinely scored 55.

**Below-table scores (60–69):** cache with `status: "skipped"`, full
`score_breakdown` / `modality` / `location`, so the next run skips deep
extract (`N` in `D_disc` = 0.2). Do **not** show them in the results table
unless the user asks to re-show. G4 snippet skips are notes-only (no
`seen_urls` entry required; optional thin entry with `status: "skipped"`
and `match_score: 55` if the URL should stay suppressed).

`version` bumped to **2** when breakdown/modality fields are present.
Older v1 entries without breakdown remain valid for dedup only.

## Canonical URL key (must match `CanonicalUrlNormalizer.php`)

Before using a URL as a `seen_urls` key:

1. Strip the fragment (`#...`).
2. Strip tracking query params: `utm_source`, `utm_medium`, `utm_campaign`,
   `utm_term`, `utm_content`, `ref`, `fbclid`, `gclid`.
3. Strip a trailing `/`.

## `outcome` values (applied entries only — added 2026-08-24)

`status` says what **you** did with a posting. `outcome` says what **they**
did back. Set it only when `status: "applied"`.

`unknown` | `pending` | `no_reply` | `rejected` | `screening` | `interview` |
`offer` | `withdrawn`

- Default on a fresh apply: `pending`. `unknown` = backfilled history where
  the real result was never recorded.
- Companion fields: `outcome_at` (ISO date, `null` while pending) and
  optional `outcome_note` (one line — e.g. `"rejected: needed C1"`, which is
  gold for the correlation pass).
- **Aging rule:** an entry still `unknown`/`pending` more than **21 days**
  after `applied_at` is **counted as `no_reply`** by the market-insights
  correlation — but the stored value is **never** overwritten. Silence is a
  reasonable analytical default; recording a rejection nobody sent is
  fabrication. Only the user marks a real outcome.
- Never guess an outcome from context. If the user has not said, it stays
  `unknown`.

## `status` values

`new` | `saved` | `applied` | `dismissed` | `skipped`

- `skipped` = scored but below table threshold (60–69), or optional G4-capped
  URL kept for dedupe. Suppressed from the results table; low `N` in
  `D_disc` so they are not re-extracted unless the user asks.
- Dismissed/applied URLs stay in the cache and stay suppressed from future
  result tables (mirrors FR-5/US-9 in `003-cv-ats-job-studio/spec.md`) unless
  the user explicitly asks to refresh/re-show them.

## Update procedure

1. Read the file (create it with empty `seen_urls`/`runs` if missing).
2. For each new job found this run, normalize its URL and skip it if already
   a key in `seen_urls` with `status` != nothing-to-do (still show it in a
   separate "already seen, still open" note only if the user asks).
3. Add genuinely new **gate-passed** entries:
   - `match_score >= 70` → `status: "new"` (table + breakdown)
   - `60–69` → `status: "skipped"` (breakdown, no table)
   - G4-capped (≤55) from snippet → notes/`excluded_count` only, or thin
     `skipped` entry if dedupe is useful
4. Append one object to `runs` (include `excluded_count` + notes), bump
   `updated_at`.
5. Add `market_insights` to that same `runs` entry — see
   `references/market-insights.md`. Required on every **career** run,
   including runs with zero table matches. `verdict` ∈
   `blocking` | `recurring` | `noise` | `covered`; `in_cv` ∈
   `true` | `false` | `"adjacent"` (the `~` rows). `evidence_urls` keeps
   1–3 JD links per requirement so any claim can be re-audited later.
   The cross-run trend line reads the last 3 entries' `market_insights`.
6. Write the file back — keep it valid JSON (no comments).
