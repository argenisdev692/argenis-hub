# Keyword cache — weekly Tavily refresh

Career job discovery appends **strong keywords** from
`fullstack cv/keyword-cache.json` onto portal `site_query` seeds.
Never mix with other modes. Stack lock stays PHP/Laravel/Vue/Inertia —
never seed NestJS/Angular from “Learning” lines.

Human-readable companion: this file. Machine source of truth: the JSON.

## When to research keywords (Tavily)

Check **local date** in `Europe/Lisbon` (Covilhã).

| Trigger | Action |
|---|---|
| Cache missing / `populated !== true` / empty `keywords` | Full keyword research + write cache |
| **Sunday** and `last_weekly_refresh` is not this calendar week’s Sunday | Weekly refresh (with portal tiers) |
| **Low yield** after a job discovery pass: `new_matches < 2` **or** gate-passed candidates `< 3` | Mid-week keyword (and optionally portal) refresh, then one expanded rediscovery pass |
| User says **refresh keywords** / **actualizar keywords** (or portals) | Force refresh |
| Any other weekday with cache populated and yield OK | **SKIP** — reuse cache |

Do **not** re-query keyword Tavily on every daily `/cv-job-search`.

When skipping: `keywords: cache_hit (researched_at=…)`.
When refreshing: `keywords: refreshed`, bump `researched_at`,
`last_weekly_refresh` (Sunday ISO date `YYYY-MM-DD`), `updated_at`.

## Weekly / Sunday procedure (with portal tiers)

Run on Sunday (or low-yield / force) **before** building job seeds:

1. Portal tiers research per `portal-tiers.md` (open catalog merge +
   multinational locale discovery — no top-15 truncate).
2. Keyword Tavily (`search_depth: advanced`, `time_range: year`), e.g.:
   - `Laravel PHP Vue Inertia remote job titles keywords 2026 Europe`
   - `desarrollador Laravel PHP teletrabajo keywords ofertas España Portugal 2026`
   - `most common skills Laravel full stack job descriptions 2026`
3. Merge into `keywords.en|es.strong` / `role` / `seniority` /
   `stack_pairs` / `modality` and `query_fragments`. Keep stack lock; drop
   Nest/Angular/Django noise.
   **Never remove** anything listed in `refresh_policy.never_drop`
   (`user_pinned.titles`, `seniority_focus`, `stack_lock`, `never_seed`) —
   a refresh only ever **adds** to those. The user pinned the junior tier on
   purpose; a Tavily pass that "found better titles" does not override it.
4. Put genuinely new high-signal terms in `emerging[]` (max ~10). Promote
   to `strong` only if they appear in ≥2 solid sources and fit the stack.
5. If portal research finds **new top domains**, re-rank
   `portal-tiers-cache.json` (update `rank`, `tier`, `layer`) and note
   domains added/removed in the run notes.
6. Set both caches `populated: true`.

## Using keywords each job run

1. Read `keyword-cache.json`.
2. **Core seeds (EN + ES): use `fragment_policy.core_*`** — the **mid-level**
   and `stack_pairs` fragments (currently 1–5 in each language). These are
   the primary target and must appear in every run.
3. **Expansion seeds:** the junior / entry / associate fragments (EN 6–7,
   ES 6) + ≤2 `emerging`, on rotation only. Junior wording never displaces
   a mid-level Core fragment.
4. Prefer `strong` + `role` + `seniority` + `stack_pairs` terms in
   title/snippet matching for **K** in the discovery score
   (`portal-tiers.md` § D_disc: ≥2 hits→1.0, 1 hit→0.6, weak→0.3,
   none→0.0). A `seniority` hit (e.g. "Junior", "Associate", "Entry Level")
   counts as a full keyword hit only when it sits next to a stack term —
   "Junior Developer" with no PHP/Laravel/Vue is **not** a K hit (and fails
   G2 anyway).
5. `seniority_focus.deprioritize_seeds` (senior / lead / principal / staff /
   architect) are **not** seeded in Core. They are **not** gate-excluded: a
   senior JD found organically still goes through gates and H/S/D like any
   other posting.

## Seniority routing (user decision, 2026-08-24)

**Mid-level first.** The junior-first tier pinned earlier the same day was
demoted to Expansion for two reasons worth remembering, because they will
come up again on the next refresh:

1. **Remote junior openings are scarce.** Remote-first companies open mid+
   roles — onboarding and mentoring a junior at a distance is expensive.
   A junior-led Core round spends seed budget for near-zero yield.
2. **The cache disagreed with the junior thesis.** The candidate's own
   history scores 78–90 on mid/senior titles (Reputation Arm 90, Nuweb 85,
   Pixelmatters 78). No junior posting ever produced a comparable match.

What this changes and what it does not:

- **Discovery:** `keywords.*.role` (mid) + `stack_pairs` drive Core.
  **Expansion rotation carries both edges:** `keywords.*.seniority`
  (junior/entry/associate — kept thin, catches postings mislabelled
  "Junior" that really want 3–4 years) and `keywords.*.senior`
  (added 2026-08-24 — the candidate scored **78** on a "Senior Full-Stack
  Engineer" posting, so the senior edge is real, not aspirational).
- **Why seeding senior is cheap:** a JD with an explicit **7+ year floor is
  capped at 55 by G4** before it can reach the ≥70 table, and the G4
  snippet check skips its deep extract. The cost is one query per rotation
  — not table noise, not Firecrawl budget. Those capped entries then feed
  the reach table as `years_cap`, which is how you find out what the "7+
  years" wall is actually costing.
- **Still unseeded:** lead / principal / staff / architect / engineering
  manager. Those floors are consistently out of range.
- **Scoring is untouched.** A junior JD found organically still scores
  honestly: `job-match-scoring.md` § G4 years table + § D years sub-score
  give full credit, because over-qualification is not a mismatch. Seeding
  order is a budget decision; it must never leak into the score.
- `user_pinned.titles` stays verbatim — pinning protects the *list*,
  `seniority_focus` + `fragment_policy` decide the *routing*.

## Low-yield loop

After Core+Expansion discovery + gates:

- If yield is low → trigger keyword (+ portal if ranks look stale) refresh
  once, rebuild ~4–6 Expansion queries with new strong/emerging terms,
  re-run discovery **once** (no infinite loop).
- Record `keywords: low_yield_refresh` in `job-search-cache.json` run notes.

## Schema

See `fullstack cv/keyword-cache.json`. Required: `version`, `populated`,
`seniority_focus`, `user_pinned`, `fragment_policy`,
`keywords`, `query_fragments`, `stack_lock`, `never_seed`,
`last_weekly_refresh`, `refresh_policy`.
