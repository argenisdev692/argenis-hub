# SEO meta — mandatory on every draft

Every saved post under `POST-MODULE/output/` **must** include these three
fields in YAML frontmatter. Missing any → do **not** mark ready; regenerate
meta before finishing `/post-draft`.

## Required fields

| Field | Length | Purpose |
|-------|--------|---------|
| `meta_title` | **50–60** characters | SERP title — primary long-tail + intent + soft brand/CTA signal |
| `meta_description` | **150–160** characters | SERP snippet — benefit, proof cue, clear next step (CTR → ROI) |
| `meta_keywords` | **5–8** long-tail phrases | Comma-separated or YAML list — 3–6+ words each, intent-rich |

Also keep:

- `title` — H1 (can be longer / more editorial than `meta_title`)
- `primary_keyword` — one main long-tail the post ranks for
- `lsi_keywords` — 3–5 related terms woven into body (not a dump in meta)

## `meta_title` (SEO + ROI / CTR)

Rules:

1. Put the **primary long-tail** near the start (first ~40 chars when possible).
2. Match search **intent** (how-to, vs, year, for [audience], without X).
3. Prefer specificity over brand-first: benefit or outcome beats “Company | Topic”.
4. Avoid clickbait that the body cannot deliver (hurts trust + ROI).
5. No keyword stuffing; no ALL CAPS; one optional `|` or `—` separator max.
6. Count characters honestly; trim to **≤60**, aim **≥50**.

Patterns that usually score well:

- `{Primary long-tail}: {outcome or year}`
- `{How to …} ({audience or constraint})`
- `{Topic} vs {alternative}: {decision cue}`

Bad: `AI Trends 2026 | Blog` (too short, no intent, weak CTR).  
Good: `Enterprise GenAI ROI Checklist for 2026 Engineering Leads` (~58).

## `meta_description` (CTR → ROI)

Rules:

1. Open with the **problem or promise** tied to the primary long-tail.
2. Add one concrete cue (number, timeframe, or audience) when sourced.
3. End with an implicit or soft CTA (learn, compare, avoid mistake, get checklist).
4. **150–160** characters; never truncated mid-word in the draft.
5. Do not repeat `meta_title` verbatim; expand the value prop.

Bad: `This article discusses AI trends and tools.`  
Good: `See which GenAI bets pay off in 2026—and which burn budget. A practical ROI checklist for eng leads who already tried pilots.`

## `meta_keywords` (long-tails only)

Rules:

1. **5–8** phrases; each is a **long-tail** (typically **3–6+ words**).
2. First keyword = `primary_keyword` (exact or near-exact).
3. Mix intents: informational (`how to…`), commercial (`best … for…`),
   comparison (`… vs …`), temporal (`… 2026`).
4. Stay inside the selected **blog category** niche.
5. Ground candidates in Tavily query language when possible — do not invent
   fake “volume” numbers.
6. **Forbidden**: single-word generics (`AI`, `SEO`, `marketing`, `software`)
   as standalone keyword entries; short head terms only allowed inside a
   longer phrase.

Format in frontmatter (prefer YAML list):

```yaml
primary_keyword: "enterprise generative AI ROI checklist 2026"
meta_keywords:
  - "enterprise generative AI ROI checklist 2026"
  - "how to measure GenAI pilot ROI for engineering teams"
  - "generative AI tools that reduce software delivery cost"
  - "GenAI vs traditional automation ROI for SaaS"
  - "AI adoption mistakes that waste engineering budget"
meta_title: "Enterprise GenAI ROI Checklist for 2026 Engineering Leads"
meta_description: "See which GenAI bets pay off in 2026—and which burn budget. A practical ROI checklist for eng leads who already tried pilots."
```

Comma-separated string is acceptable only if each phrase is clearly long-tail:

```yaml
meta_keywords: "enterprise generative AI ROI checklist 2026, how to measure GenAI pilot ROI for engineering teams, ..."
```

## Gate before save

Before writing the `.md` file, verify:

- [ ] `meta_title` length 50–60
- [ ] `meta_description` length 150–160
- [ ] `meta_keywords` has 5–8 long-tails (≥3 words each)
- [ ] `primary_keyword` appears in `meta_title` (or obvious close variant)
- [ ] `primary_keyword` appears in first ~100 words of body
- [ ] At least 2 H2s relate to LSI / long-tail angles

If any check fails → fix meta (or one draft iteration) before claiming
`all_scores_pass` / ready. SEO score cannot pass (≥70) if this gate fails.

## Chat display

After save, show a short SEO block to the user:

```
Meta title (N chars): ...
Meta description (N chars): ...
Primary: ...
Long-tails: ...
```
