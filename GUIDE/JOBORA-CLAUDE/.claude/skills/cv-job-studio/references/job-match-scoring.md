# Job match scoring (Jobscan-inspired hybrid)

Heuristic product score for CV ↔ job compatibility in **cv-job-studio**.
Grounded in `GUIDE-ATS-ANALISIS/` (Claude/ChatGPT/Gemini/Grok) and Jobscan
public methodology as of mid-2026 (exact hard-skill presence, required vs
preferred, format/title/education signals; target band ~75–85%).

**Label every number as heuristic** — not a vendor ATS / Jobscan Match Rate.

## Architecture (three layers)

```
JD + CV text
    → extract structured fields (skills, years, title, modality, location)
    → GATES (binary / hard filters)     ← reject before scoring
    → H Heuristic + S Semantic proxy + D Deterministic
    → match_score = 0.45·H + 0.25·S + 0.30·D   (0–100)
    → LLM explains gaps / ROI only — never invents the %
```

| Layer | Role | Weight in blend |
|---|---|---|
| **Heuristic (H)** | Exact / alias hard-skill presence vs JD (Jobscan core) | **45%** |
| **Semantic proxy (S)** | Title + responsibility synonym fit (no real embeddings in Cursor) | **25%** |
| **Deterministic (D)** | Years, seniority, geography/modality soft fit, education, language | **30%** |

Aligned with GUIDE consensus:

- Claude: gates × (H + S + D); technical roles → heuristic heaviest.
- Gemini: ~0.45 keyword + 0.35 semantic + 0.20 structural.
- ChatGPT: deterministic engine owns most of the %; LLM explains.
- Grok: hybrid keywords + semantic + LLM judge.

In Cursor we **cannot** run real cosine embeddings; call `S` a
`semantic_proxy` in the breakdown. When the Laravel product later adds
embeddings, replace the proxy without changing weights.

## Step 0 — Pre-filter gates (must all pass)

Run **before** computing H/S/D. Failed gate → **do not** put the job in the
results table and **do not** cache it as a scored match (mention in
`runs.notes` as excluded).

### G1 — Work modality + remote residency (career default)

Candidate base: **Covilhã, Portugal**. “Remote” alone is not enough — classify
the **residency scope** of the posting.

#### Remote scope taxonomy

| Label | JD signals (examples) | Gate |
|---|---|---|
| `remote-global` | “remote worldwide”, “work from anywhere”, “global remote”, “hire anywhere”, “remoto global”, “trabajo desde cualquier lugar”, “sin restricción de ubicación” | **PASS** |
| `remote-eu` | “remote Europe”, “EMEA”, “EU/EEA”, “remoto Europa”, “remoto UE”, “hires in the EU” | **PASS** |
| `remote-pt-es` | Remote limited to **Portugal** and/or **Spain** (“remoto España”, “remoto Portugal”, Iberia) | **PASS** |
| `remote-country-locked` | “remote US only”, “must be based in the UK/US/Canada”, “remoto solo USA”, “debe residir en [país ≠ PT/ES]” | **FAIL** |
| `remote-asia-locked` / Asia-omit | Asia / APAC-only remote, “must be in India/Singapore/Japan…”, Asia agency hiring only APAC, timezone lock IST/JST/SGT with no EU/worldwide hire | **FAIL** — do **not** seed Asia LinkedIn/Indeed locales (`jp`, `cn`, `in`, `sg`, …). See `portal-tiers.md` `geography_policy`. |
| `hybrid-lisbon` | Hybrid / híbrido + Lisbon/Lisboa, Portugal | **PASS** |
| `hybrid-other` | Hybrid/híbrido any other city/country | **FAIL** |
| `onsite` | Office-only / presencial outside Lisbon | **FAIL** |
| `remote-unclear` | Only “Remote” / “Remoto” with **no** country lock and **no** global/EU cue | **PASS** with the **location sub-score set to 50** in § D (not 100) — worth −15 D ≈ **−4.5 final points**. Prefer the deep extract cascade to reclassify first. If any cascade step finds a country lock → **FAIL** |

**Rule of thumb:** accept remote that you can do from Covilhã (global / EU /
PT / ES). Reject “remote inside another country’s payroll/timezone/legal
fence”. **Omit Asia** for seeds and for Asia-locked JDs even if the brand
(LinkedIn, Indeed, etc.) has Asia sites — user geography focus is
**PT, ES, UK, US** (+ EU remote), not Asia agencies/employers.

Cache `modality` as one of:
`remote-global` | `remote-eu` | `remote-pt-es` | `remote-unclear` | `hybrid-lisbon`

### G2 — Career stack lock (fullstack mode only)

JD must include **at least one** of:

`PHP` · `Laravel` · `Livewire` · `Inertia` · `Inertia.js`

**OR** (`Vue` / `Vue.js`) **together with** PHP/Laravel context in the same
posting.

**Reject** postings whose primary stack is NestJS, Angular, .NET-only,
Java-only, Python-only, etc. with no PHP/Laravel signal.

**Never** use the CV “Learning: NestJS / Angular …” line to build search
seeds or to force Nest/Angular jobs into career mode.

#### The front-end variant is not a gate

**PHP/Laravel is the lock. The front-end framework is not.** Once Laravel or
PHP is present, the JD passes G2 whatever it pairs with:

| JD front-end | G2 | Effect on the score |
|---|---|---|
| Laravel + **Vue** / Inertia / Livewire | PASS | Best case — the CV's primary evidence |
| Laravel + **React** | PASS | CV has React 19 + TypeScript (AquaShield). Counts as a real match in **H**, not a partial |
| Laravel + “**Vue preferred**” / “React or Vue” / “Vue nice to have” | PASS | It is a `preferred` skill (weight 0.6), not `required`. Do not treat a preference as a wall |
| Laravel + Blade / no front-end named | PASS | Score on the backend evidence; note it in Why/gaps |
| Laravel + Angular/Svelte only, **required** | PASS G2 | Front-end weight is simply unmatched in H; the Laravel side still scores |

The only front-end that fails is one with **no PHP/Laravel anywhere** —
that is a different market, not a stack variant.

### G3 — Listing quality

- Single job URL (not a search-results / category aggregator page).
- Prefer postings ≤ ~7 days when a date is visible; older → exclude unless
  user asked to widen.

### G4 — Dealbreakers inside the JD (cap, not always hard-fail)

If the JD **requires** a credential / years floor the CV clearly lacks
(e.g. “7+ years Laravel required” vs CV ~4):

**Years floor table** (CV baseline: **4+ years** PHP/Laravel — the target
band is entry level → mid, roughly **0–5 years**):

| JD requires | Gate | D years sub-score |
|---|---|---|
| No years line / “entry level” / “junior” / 0–2 yrs | No cap | 100 |
| 1–3 yrs · 2–4 yrs · “mid-level” · 3–5 yrs | No cap | 100 |
| 5 yrs | No cap | 100 |
| 6 yrs | No cap — mention the gap in Why/gaps | 70 |
| **7+ yrs required** (or “senior” with an explicit 7+ floor) | **Cap 55** | 40 |
| “Senior” with no years number | No cap — score it normally | 75 |

A years *range* is read by its **floor**: “3–7 years” asks for 3, so no cap.
Only a floor above 6 caps.

- Still may PASS G1–G3 if stack/modality/listing OK.
- Cap final `match_score` at **55** and say so in reasoning.
- **Skip deep extract** when the dealbreaker is already clear from the
  discovery title/snippet (years floor, mandatory cert the CV lacks). Log
  `extract: skipped_g4_snippet` in `runs.notes`, count toward
  `excluded_count`, and do **not** burn Firecrawl/Tavily budget — a capped
  55 never reaches the ≥70 table.
- If the gap is only suspected, still deep-extract once; apply the cap after
  H/S/D if confirmed.

### G4b — Language floor (same cap mechanics)

Language is only 15% of D (≈4.5% of the final score), which is far too small
for what is in practice a hard filter. Treat an explicit language floor the
CV cannot meet as a **G4 dealbreaker**, not as a D nuance.

Candidate baseline: **Spanish native · Portuguese resident level ·
English B1**.

**Read the floor, not the ceiling.** A range or an "or higher" phrasing is
judged by its **lowest accepted level**. `English B1–C2`, `B1 or above`,
`intermediate to advanced English`, `B1+` → the floor is B1, the CV meets
it, **no cap**. Only a floor **above** B1 caps.

| JD requirement (explicit CEFR) | Floor | Action |
|---|---|---|
| Not stated · ES/PT only · **B1** · **A2/B1** · **B1–B2** · **B1–C2** · “B1 or higher” | ≤ B1 | **No cap**; language sub-score **100** |
| **B2 preferred / valued / nice to have** (not required) | B1 | **No cap**; sub-score **70**; note it in Why/gaps |
| **B2 required / must have / mandatory** · **B2–C1** | B2 | Cap **65** → lands in the 60–69 `skipped` band. Cached, counted in market insights, **not** in the table |
| **C1 / C2 / native / near-native required** · **C1–C2** | C1+ | Cap **55**. Skip deep extract when the snippet already says it (`extract: skipped_g4_snippet`) |
| A **third local language required** (Polish, German, Dutch, French, Italian…) | — | Cap **55** — same as a missing mandatory credential |

**No CEFR letter in the JD** (the common case). Judge by what the wording
actually demands — do not auto-cap on a stray adjective:

| Wording | Treat as |
|---|---|
| “English is a plus”, “good English”, “English for documentation”, “async written English” | B2 preferred → **no cap**, sub-score 70 |
| “professional working proficiency”, “strong communication in English”, “English-speaking team” | B2 preferred → **no cap**, sub-score 70 |
| “**excellent** written **and** verbal English **required**”, “daily client-facing calls in English”, “you will present to stakeholders in English” | B2 required → cap **65** |
| “**native** English”, “near-native”, “flawless English”, “English copywriting / content ownership” | C1+ → cap **55** |

Log every language cap in `runs.notes` (e.g. `lang_cap: EN C1 required`) and
feed it to the market-insights pass (`market-insights.md`) — a recurring
English floor is exactly the signal the user wants surfaced.

**Net effect for this CV (EN B1):** C1-required postings never reach the
results table. B2-required ones do not either — they are cached as
`skipped` and counted in the report, so you can see how much of the market
one CEFR level is costing you without them cluttering the apply list. A
posting that accepts B1 anywhere in its range is a normal match.

## Step 1 — Extract & classify JD requirements

From cascade JD text (Firecrawl / Tavily advanced extract / company careers; preferred) or discovery snippet:

1. List hard skills / tools / frameworks.
2. Tag each: `required` | `preferred` | `bonus`
   (LLM may label; then apply fixed weights — LLM does not pick the %).
3. Weights: **required = 1.0**, **preferred = 0.6**, **bonus = 0.25**.
4. Soft skills are secondary. Cap soft weight so soft is **≤15% of the
   final H denominator** (Jobscan: hard skills dominate ATS filters):

```
W_hard = Σ weights of hard JD skills
W_soft_raw = Σ weights of soft JD skills
W_soft_cap = (0.15 / 0.85) × W_hard    # ≡ soft/(hard+soft) ≤ 0.15
W_soft = min(W_soft_raw, W_soft_cap)
```

If `W_hard = 0` and only soft skills exist, set `W_soft = W_soft_raw` and
flag “JD hard skills unclear” (H will be soft-only).

If `W_soft_raw > W_soft`, scale every soft skill’s weight by
`W_soft / W_soft_raw` (hard weights unchanged). Then:

```
H = 100 × (Σ matched weights) / (W_hard + W_soft)
```

Example: `W_hard=10`, `W_soft_raw=10` → `W_soft_cap≈1.765` → soft share
of denominator ≈15%, not 50%.

### Alias normalization (examples)

| Canonical | Aliases |
|---|---|
| JavaScript | JS, ES6, ECMAScript |
| TypeScript | TS |
| Vue.js | Vue, VueJS, Vue 3 |
| Node.js | Node, NodeJS |
| PostgreSQL | Postgres |
| CI/CD | continuous integration, GitHub Actions (partial) |
| REST APIs | REST, RESTful, API REST |

Presence **beats** frequency (Jobscan 2026): already applied in H — do not
inflate by repeating keywords.

### Match credit per skill (no other values allowed)

A JD skill contributes `credit × weight` to the numerator of H:

| Situation | `credit` |
|---|---|
| Exact term or a listed alias present in the CV | **1.0** |
| Same-family / adjacent evidence, no exact term (alias table rows marked *(partial)*; e.g. JD “CI/CD” vs CV “GitHub Actions”, JD “AWS” vs CV “Cloudflare Workers + VPS + Docker”) | **0.5** |
| Absent | **0.0** |

Only rows explicitly marked *(partial)* or a defensible same-family case get
0.5 — and the reasoning must name the CV evidence. Never invent a partial
match for a tool the CV does not mention at all (Kubernetes, RabbitMQ,
Kafka, Terraform → 0.0 today).

## Step 2 — Component scores (each 0–100)

### H — Heuristic skill match

After soft-skill renormalization (Step 1):

```
H = 100 × (Σ weights of matched JD skills) / (W_hard + W_soft)
```

If denominator is 0, set H = 50 and flag “JD skills unclear”.
Presence **beats** frequency: count each skill once when present in the CV.

### S — Semantic proxy

Score 0–100 from:

- Job title vs CV target title / recent titles (≈40% of S).
- Responsibility overlap: JD must-do bullets vs CV experience bullets using
  synonym judgment grounded in text (≈60% of S).

Do **not** award high S solely because both texts say “software” / “agile”.

### D — Deterministic fit

Four signals, each scored **0–100**, then blended by share:

```
D = Σ(share_i × sub_i) / Σ(share_i actually scored)
```

If a signal cannot be read from the JD at all, **drop it and renormalize**
over the remaining shares (that is what “adjust lightly” used to mean — it is
now a formula, not a judgment call). Never silently score an unknown as 0.

| Signal | Share |
|---|---|
| Years / seniority band | 40% |
| Location / remote clarity | 30% |
| Education | 15% |
| Language | 15% |

Sub-score tables (candidate: **4+ yrs PHP/Laravel**, BSc Computer Science,
ES native / PT resident / **EN B1**, based in Covilhã):

**Years / seniority (40%)** — over-qualification is *not* a penalty:

Mirrors the **G4 years floor table** — keep the two in sync. The target band
is **entry level → mid, roughly 0–5 years**, which is where this CV sits at
4+ years:

| JD asks | sub |
|---|---|
| Junior / entry level / associate / graduate / 0–2 yrs | **100** (drop to 85 only if the JD explicitly caps experience, e.g. “máximo 2 años”, internship-only, or the salary band is stated and clearly below market) |
| Mid-level / 1–3 / 2–4 / 3–5 yrs | **100** |
| 5 yrs | **100** |
| 6 yrs | **70** |
| 7+ yrs required | **40** — and apply the G4 cap 55 |
| “Senior” with no years number | **75** — no cap |
| Not stated | **75** |

**Location / remote clarity (30%)**

| Modality | sub |
|---|---|
| `remote-global` / `remote-eu` / `remote-pt-es` / `hybrid-lisbon` | **100** |
| `remote-unclear` still unresolved after the full extract cascade | **50** |

(Anything worse is already a G1 FAIL and never reaches D.)

**Education (15%)**

| JD | sub |
|---|---|
| Requires a CS/engineering degree — CV has BSc Computer Science | **100** |
| Degree not mentioned | **90** |
| Requires a specific degree/certification the CV lacks | **40** — and check the G4 cap |

**Language (15%)** — see **G4b**; the cap does the real filtering, this
sub-score only shades the ranking:

| JD | sub |
|---|---|
| Not stated · ES/PT required · EN ≤ B1 | **100** |
| EN B2 preferred / “nice to have” | **70** |
| EN B2 required | **50** (+ G4b cap 65) |
| EN C1+/native required | **20** (+ G4b cap 55) |
| Third local language required | **0** (+ G4b cap 55) |

Worked example — junior Laravel JD, remote-EU, no degree line, EN B2
preferred: `D = (0.40·100 + 0.30·100 + 0.15·90 + 0.15·70) / 1.0 = 94`.

## Step 3 — Final match_score (ROI)

```
raw          = 0.45·H + 0.25·S + 0.30·D
match_score  = round( min(raw, cap_1, cap_2, …) )
```

Round **once**, at the end. Caps are applied to the raw value, and the
**lowest applicable cap wins**:

| Cap | Value | Trigger |
|---|---|---|
| G4 dealbreaker | **55** | Years floor / mandatory credential the CV lacks |
| G4b language | **55** / **65** | EN C1+ or third local language / EN B2 required |
| **Evidence cap** | **69** | JD text is `extract: snippet_only` **and** H fell back to the “JD skills unclear” default (H = 50) |

The evidence cap closes a real hole. Take a `remote-global` posting with no
degree or language line and a plausible title: `D = 0.40·100 + 0.30·100 +
0.15·90 + 0.15·100 = 98.5`. Pair it with the unclear-skills fallback
`H = 50` and a generous `S = 70`:
`0.45·50 + 0.25·70 + 0.30·98.5 = 69.55` → rounds to **70**. A posting whose
requirements nobody could read lands in the table on the strength of its
location alone. Capping at 69 sends it to
the `skipped` band instead, where it is cached and can be re-extracted on
request. If the cascade produced a real skill list, this cap does not apply
no matter how thin the page was.

### Bands (Jobscan-aligned apply guidance)

| Score | Label | Table? |
|---|---|---|
| 85–100 | Strong — high ROI apply | Yes |
| 75–84 | Good — worth applying (Jobscan “good” band) | Yes |
| 70–74 | Borderline — show with gaps | Yes |
| 60–69 | Weak — exclude from table; note in `runs.notes` | No |
| 0–59 | Reject / poor fit | No |

Target for a tailored apply: **~75–85**. Chasing >90 often means stuffing.

## Mandatory output per scored job

Always show:

1. `match_score` (heuristic ROI)
2. Breakdown: `H`, `S` (`semantic_proxy`), `D`
3. `modality` + `location`
4. Matched hard skills (✔) and missing **required** hard skills (✘)
5. 2–4 sentence reasoning: strengths first, then gaps

## What the LLM must / must not do

| Must | Must not |
|---|---|
| Extract & classify requirements | Invent the percentage from vibes |
| Apply gates + formula above | Score Nest/Angular-only jobs in career mode |
| Explain gaps like a Jobscan report | Claim this equals Jobscan or Workday |
| Prefer cascade JD text (Firecrawl / Tavily extract / careers pivot) for top candidates | Fabricate skills on the CV to raise H |

## Career vs Other mode

### Career (`fullstack cv/`)

- **G1** = fixed remote residency taxonomy (global / EU / PT-ES / Lisbon
  hybrid) — Covilhã base.
- **G2** = PHP/Laravel/Livewire/Inertia stack lock.
- **G3–G4** = listing quality + dealbreaker cap.
- Seeds: EN + ES Laravel/PHP/Vue.

### Other (`other cv/` — `/cv-job-search-other`)

Same **GUIDE math** (gates → H/S/D → ≥70). Different **gate inputs**:

1. **Before searching**, compile the user's targeting prompt into an explicit
   `gate_config` (ask if any field is missing — never inherit career
   Lisbon/Laravel defaults):

```yaml
gate_config:
  role_keywords: []       # must-have title/role terms from prompt
  stack_must: []          # hard skills/tools required (G2 for this run)
  stack_reject: []        # optional hard rejects (e.g. "no PHP")
  modality_allowed: []    # e.g. [remote-global, remote-eu, hybrid:<city>]
  remote_residency: []    # countries/regions OK for remote (from prompt)
  geography: ""           # city/country focus for search seeds
  search_languages: []    # e.g. [es], [en], [es, en] — from prompt
  tone: ""
```

2. **G1 (other):** PASS only if JD modality ∈ `modality_allowed` **and**
   remote residency matches `remote_residency` (same taxonomy labels as
   career when useful: `remote-global`, `remote-eu`, `remote-country-locked`,
   etc.). Example: user says “remoto solo México” → PASS Mexico remote;
   FAIL remote-US-only / hybrid Madrid unless they allowed it.
3. **G2 (other):** JD must hit `stack_must` (same spirit as career stack
   lock — prompt defines the stack). Apply `stack_reject` if set.
4. **G3–G4:** same as career (listing quality, dealbreaker cap).
5. **Do not** apply career Lisbon-only hybrid or PHP/Laravel lock unless the
   targeting prompt asks for them.
6. Seeds: derive from `gate_config` in `search_languages` (not forced EN+ES
   unless the user asked for Spanish/English).

Show `gate_config` to the user once before Tavily so they can correct it.
