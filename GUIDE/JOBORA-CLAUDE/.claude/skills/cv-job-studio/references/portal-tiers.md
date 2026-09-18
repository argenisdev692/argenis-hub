# Portal tiers cache — open catalog + weekly locale discovery

Career discovery uses an **open portal catalog** by region (Europe, USA, UK,
Canada, Iberia, Lisbon-hybrid) — **not a hard top-15 cutoff**. A strong JD on
a long-tail board must still be eligible if it passes gates.

Data: `fullstack cv/portal-tiers-cache.json`  
Paired keywords: `fullstack cv/keyword-cache.json` + `references/keywords.md`

## Core idea (why not “top 15 only”)

| Layer | Role |
|---|---|
| **Core** (`layer: "core"`) | Always queried (~8–12). High-signal boards for Covilhã career. |
| **Expansion catalog** | **Unlimited** list in JSON. Rotated via buckets so long-tail sites get turns. |
| **Organic / unknown domain** | If Tavily job search returns a URL **not** in the catalog and it passes G1–G3, **keep and score it**. Optionally add the domain to Expansion on Sunday refresh. |
| **Soft ranks** | `rank` / `tier` only affect **P** in `D_disc` and rotation priority — never a hard exclude. |

Do **not** drop a gate-passed match because “it wasn’t in the top 15.”

Budget control = Core + rotating Expansion queries + `D_disc` shortlist —
**not** deleting portals from the cache.

## When to research portals (check calendar day)

Use local date in **`Europe/Lisbon`**. Before Tavily portal/locale research:

1. Read `fullstack cv/portal-tiers-cache.json` (and keyword-cache).
2. **SKIP research** (reuse catalog) when all of:
   - file exists and parses as JSON
   - `populated === true`
   - `skip_research_when_populated !== false`
   - each of `europe`, `usa`, `uk`, `canada` has `portals.length >= 10`
     (`lisbon_hybrid` ≥8)
   - **and** it is **not** Sunday-with-stale-week refresh
   - **and** user did not force refresh
   - **and** (for mid-week) not in a low-yield refresh pass
3. **RUN research** when any of:
   - file missing / invalid / empty regions / `populated === false`
   - `force_refresh: true`
   - user says **refresh portals** / **refresh tiers** / **actualizar portales**
   - **Sunday weekly:** today is Sunday **and**
     `last_weekly_refresh` is missing or older than this week’s Sunday
   - **Low yield:** `new_matches < 2` or gate-passed `< 3` — refresh once,
     then one rediscovery pass

Weekday job discovery still runs; **catalog / locale research** is first-time,
Sunday, low-yield, or forced.

| Note in run | Meaning |
|---|---|
| `portal_tiers: cache_hit` | Reused JSON |
| `portal_tiers: sunday_refresh` | Weekly catalog + locale update |
| `portal_tiers: low_yield_refresh` | Mid-week yield rescue |
| `portal_tiers: refreshed` | Forced / first populate |

Bump `researched_at`, `updated_at`, and on Sunday/weekly also
`last_weekly_refresh`. Set `populated: true`.

## First-time / Sunday / refresh research procedure

### A) Region catalog (open list — no 15-cap)

Run Tavily (`search_depth: advanced`, `time_range: year`), e.g.:

1. `best remote IT developer job boards Europe 2026`
2. `best remote tech developer job boards United States 2026`
3. `best remote developer job boards UK 2026`
4. `best remote developer job boards Canada 2026`
5. `remote developer job boards Spain Portugal Tecnoempleo Landing.Jobs`
6. Optional: `Lisbon hybrid tech jobs portals Portugal ITJobs`

Also: `best Spanish Portuguese remote IT job boards 2026`

**Merge (never truncate to 15):**

- Add new domains with real `site_query`, default `tier: 3`, `layer: "expansion"`
  (use `tier: 4` if clearly freelance/gig).
- Promote to tier 1 only for multi-country **employment** megasites
  (LinkedIn/Indeed/InfoJobs/Glassdoor/Monster-class). Tier 2 for strong
  specialized remote/tech boards.
- Soft-reorder `rank` for rotation priority; keep old portals unless clearly dead.
- Do not wipe Core (LinkedIn, WWR, Remote OK, Remotive, Landing.Jobs, ITJobs.pt,
  RemoteRocketship, Indeed PT/ES) without evidence they died.
- Cap is soft for **research attention** only (skim ~20–30 new candidates/region);
  the JSON catalog may grow beyond that.

Then run keyword Tavily per `keywords.md`.

### B) Multinational locale discovery (mandatory on Sunday refresh + on new portal)

Known brands often have **more country sites** than the cache lists. For each
entry under `multinationals` (LinkedIn, Indeed, InfoJobs, Glassdoor, Monster,
Randstad, …) **and for every newly discovered portal**:

1. Tavily: does this brand have other country websites?
   - `LinkedIn jobs country sites list es.linkedin.com pt.linkedin.com uk.linkedin.com`
   - `Indeed country domains list pt.indeed.com es.indeed.com uk.indeed.com`
   - `InfoJobs country sites infojobs.net infojobs.it`
   - `Randstad / Glassdoor / Monster country websites list`
   - Generic: `"<brand>" job site country domains OR locales list`
2. Extract country codes + hostnames.
3. **Filter with `geography_policy`** before writing anything:
   - **Prefer:** `pt`, `es`, `uk`, `us`, `global`
   - **Allow Expansion:** prefer + EU helpers (`de`, `fr`, `nl`, `ie`, `it`,
     `eu`, `ca` as global-hire only)
   - **OMIT — never query / never activate:** **Asia** and other deny codes
     (`jp`, `cn`, `in`, `sg`, `kr`, `hk`, `tw`, …). Hosts like
     `jp.linkedin.com`, `linkedin.cn`, `indeed.co.in`, `sg.indeed.com` must
     **not** enter active `locales[]`.
   - Record skipped hosts in `omitted_locales[]` with `reason: "geo_deny"`.
4. Merge **only allowed** locales into `multinationals.<brand>.locales[]`.
5. Career **Core prefer:** LinkedIn/Indeed/InfoJobs → **PT + ES + UK + US +
   global** (US/UK still need G1 worldwide/hire-abroad). Asia never seeded.

### C) New portal → strength score → tier (mandatory)

When a **new** job board domain appears:

1. Run multinational check (B) — keep only geography-allowed locales.
2. Compute `S_portal` (0–1) as a **weighted sum** (not a product):

```
S_portal = 0.30·IT + 0.25·Vol + 0.20·Remote + 0.15·Geo + 0.10·Perm
```

Each factor is scored **0–1** by the agent from evidence (site focus, volume,
remote/EU friendliness, geography fit, permanence). Then:

| Factor | Weight | 1.0 means | 0 means |
|---|---|---|---|
| **IT** | 0.30 | Clear IT/dev board | General/non-tech only |
| **Vol** | 0.25 | High volume / strong reputation | Tiny / unknown |
| **Remote** | 0.20 | Remote/EU-friendly hiring | Onsite-local only |
| **Geo** | 0.15 | Fits prefer/allow locales | Asia-only / deny → **0** (do not add) |
| **Perm** | 0.10 | Employment postings | Gig/freelance marketplace |

3. Assign tier:
   - Mega multi-country + `S_portal ≥ 0.75` → **1**
   - Specialized remote/tech + `S_portal ≥ 0.55` → **2**
   - Niche/agency or `S_portal` 0.35–0.54 → **3**
   - Freelance/gig → **4**
   - Asia-only / deny-region → **do not add** (omitted)
4. Write into the matching region bucket; bump `updated_at`.

**Never** add an Asia locale because LinkedIn/Indeed exists there.

### Geography policy (career — hard)

Also in `portal-tiers-cache.json` → `geography_policy`.

| | |
|---|---|
| **Prefer (Core)** | `pt`, `es`, `uk`, `us`, `global` |
| **Allow (Expansion)** | Prefer + EU helpers; `ca` only as global-hire discovery |
| **Deny (omit)** | **Asia**, Middle East–only, Africa–only locales/boards |
| **JD pipeline** | Asia-resident / APAC-only / Asia agencies → **G1 FAIL** |

Assign portal fields:

| Field | Meaning |
|---|---|
| `tier` | 1 = mega employment · 2 = strong specialized · 3 = long-tail · 4 = freelance |
| `layer` | `core` = every run · `expansion` = rotation only |
| `gate_tags` | Which residency/modality this portal may feed |
| `locales_ref` / `prefer_locales` | Multinational `site_query`s (**geo-filtered**) |

### Tier scale (source of truth)

| Tier | Meaning | Examples | Typical `layer` |
|---|---|---|---|
| **1** | Best **employment** megasites — high volume IT/jobs, often **multi-country** | LinkedIn, Indeed, InfoJobs (+ all known locales), Glassdoor, Monster | Prefer Core / always-on locales |
| **2** | Strong **specialized** remote or tech boards (not mega generalists) | We Work Remotely, Remote OK, Remotive, Landing.Jobs, ITJobs.pt, Just Join, Dice, Arc, Otta, Tecnoempleo, Honeypot | Core or Expansion |
| **3** | Long-tail / niche / agency / aggregator | Adzuna, Jobsite, Randstad, Net-Empregos, Get on Board, Remoteo, Jobspresso | Expansion |
| **4** | **Freelance / gig** marketplaces | Upwork, Freelancer.com, Freelancer.es, Workana | Expansion only; low `D_disc` P; G3 often FAIL |

New unknown domains default to **tier 3** (not 4) unless clearly a freelance marketplace → **tier 4**.
Promote to tier 1 only for true multi-country employment brands.

### D_disc — discovery shortlist score (source of truth)

Mandatory **after G1–G3 pass**, **before** deep extract. Budget control only —
never a hard exclude of a gate-passed job from eventual scoring if the
shortlist is empty (then take best remaining).

```
D_disc = 0.35·P + 0.25·K + 0.20·R + 0.10·F + 0.10·N
```

All components are **0–1**. Keep top ~5–8 with `D_disc ≥ 0.55`, or the best
remaining if fewer clear the bar. Tier-4 freelance rarely clears unless
K/R/F are excellent.

| Component | Weight | Scale (pick one bucket) |
|---|---|---|
| **P** — portal tier | 0.35 | tier1→**1.0** · tier2→**0.75** · tier3→**0.5** · tier4→**0.25** · unknown domain→**0.5** · clearly gig unknown→**0.25** |
| **K** — keyword-cache hit | 0.25 | title/snippet hits ≥2 `strong`/`role`/`stack_pairs` fragments→**1.0** · exactly 1 strong/role/stack_pair **or** ≥1 `emerging`→**0.6** · only weak/generic stack word (e.g. lone “PHP”)→**0.3** · no stack/keyword overlap→**0.0**. No seniority bonus: a mid-level title like “Mid-level Full Stack Developer Laravel” already scores 1.0 through `role` + `strong` hits, so an extra multiplier would only double-count. A seniority word alone (“Junior Developer”, no stack) is worth **0** and fails G2 anyway. |
| **R** — residency hint (snippet) | 0.20 | clear `remote-global` / `remote-eu` / `remote-pt-es` / `hybrid-lisbon`→**1.0** · bare “Remote”/`Remoto` (`remote-unclear`)→**0.5** · ambiguous geography but not locked→**0.3** · (country-locked / hybrid-other already **G1 FAIL** — do not score) |
| **F** — freshness | 0.10 | posted ≤3 days or “today/new”→**1.0** · ≤7 days / `time_range: week` hit with no older date→**0.7** · 8–14 days visible→**0.3** · >14 days visible→**0.0** (G3 usually excludes) · date unknown→**0.5** |
| **N** — novelty vs cache | 0.10 | URL not in `seen_urls`→**1.0** · in cache as `dismissed`/`applied`→**0.0** (still skip table) · in cache as `skipped` (60–69)→**0.2** · in cache as `new`/`saved` still open→**0.1** (dedupe; don't re-extract unless user asks) |

Worked example: tier-2 board (P=0.75), 2 keyword hits (K=1.0), remote-eu
snippet (R=1.0), posted this week unknown exact day (F=0.7), new URL (N=1.0)
→ `D_disc = 0.35·0.75 + 0.25·1 + 0.20·1 + 0.10·0.7 + 0.10·1 = 0.8825` → deep extract.

### Gate tags (must align with job-match-scoring G1)

| Tag | Use |
|---|---|
| `eu-first` | Prefer for Europe-resident remote |
| `remote-eu` | EU/EMEA remote OK |
| `remote-pt-es` | PT/ES remote OK |
| `remote-global` | Worldwide / anywhere OK |
| `hybrid-lisbon` | Hybrid Lisbon/Lisboa only |
| `global-only` | US/UK/CA boards — only keep worldwide/hire-abroad JDs; country-locked FAIL |

## Multinational locales (how to query)

Same brand ≠ one `site:` query. Use `multinationals` in the JSON.

| Brand | Career prefer (Core) | Rest |
|---|---|---|
| LinkedIn | **PT + ES + UK + US + global** | EU helpers on Expansion; **never Asia** |
| Indeed | **pt + es + uk + us** | ca/EU helpers on Expansion; **never Asia** |
| InfoJobs | **es** (+ it if EU allow) | other allow-list hosts only; **never Asia** |
| Randstad | es + pt | uk/us on Expansion; **never Asia** |
| Glassdoor | es + pt + uk + us | .ca on Expansion; **never Asia** |

When a portal has `locales_ref` + `prefer_locales`, build seeds from those
locale `site_query` values. Still apply G1 on every JD.

Iberia extras: region `iberia_es_pt`. Gig boards (Upwork/Freelancer/Workana)
are **tier 4** — contractor noise; G3 may FAIL many; low Expansion priority.

## Discovery budget (each career job run)

1. **Core (~8–10 queries):** `layer: "core"` + LinkedIn/Indeed/InfoJobs
   prefer locales **PT+ES+UK+US+global** + `keyword-cache` fragments
   (tier-1 megas first; never Asia hosts).
2. **Expansion (~6–8 queries):** next `rotation_buckets[rotation_index]`
   — prefer tier 2, then 3; include tier 4 freelance **at most 1** query
   unless the user asks for freelance. Increment index after run.
3. **Organic hits:** unknown domains from any query are first-class candidates
   if gates pass (`P` in D_disc = 0.5 if unknown; 0.25 if clearly gig).
4. Stack lock PHP/Laravel/Vue/Inertia + EN/ES. Never Nest/Angular seeds.
5. Gates: Covilhã residency + stack + listing quality (G1–G3).
6. **D_disc** (mandatory before deep extract) — full P/K/R/F/N scales in
   the **D_disc** section above. Deep-extract top ~5–8 with
   `D_disc ≥ 0.55`, or best remaining. Skip deep extract when G4
   dealbreaker is already clear from the snippet (see
   `job-match-scoring.md`).

## Schema sketch

Required keys: `version`, `populated`, `skip_research_when_populated`,
`researched_at`, `last_weekly_refresh`, `regions`, `multinationals`,
`discovery`, `refresh_policy`.

## Other mode

Other niche does **not** use this career portal cache by default. Create
`other cv/portal-tiers-cache.json` from approved `gate_config` if needed.
