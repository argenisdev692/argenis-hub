# Post draft quality rubric

Mirrors `PostContentQualityEvaluator` and
`POST-MODULE/prompt-social-media-post-generator-v2.md`.

## Thresholds (all must pass for "ready")

| Key | Min | Critical |
|-----|-----|----------|
| human_writing_index | 75 | Yes — blocks "ready" |
| eeat_score | 70 | — |
| virality_score | 70 | — |
| roi_score | 70 | — |
| seo_score | 70 | — |

Max quality-loop iterations: **5**. If still failing, ship best attempt with
`quality_warning: true` and list failing scores.

## Human writing (gatekeeper)

- Ban list: "In conclusion", "It's important to note", "In today's fast-paced
  world", "As we can see", "Needless to say", "In summary", "At the end of the
  day", "Moving forward", "Leverage synergies", "Paradigm shift".
- Mix sentence and paragraph lengths; one concrete anecdote or mistake with
  numbers/dates; natural hedging where honest.
- Do not inflate self-reported scores.

## EEAT

- Experience: one scenario with timeline + lesson.
- Expertise: domain terms + explain why.
- Authoritativeness: ≥2 real sources from Tavily (URL + date if known).
- Trustworthiness: one limitation or caveat.

## SEO (blog) + meta pack

Full rules: `seo-meta.md`. Summary for scoring:

- `primary_keyword` (long-tail) in H1/`meta_title`, first ~100 words, ≥2 H2s.
- 3–5 LSI terms in body; snippet-friendly opening.
- **Required meta pack** (blocks SEO pass if missing/out of range):
  - `meta_title` 50–60 chars (long-tail + intent, CTR/ROI).
  - `meta_description` 150–160 chars (benefit + soft CTA).
  - `meta_keywords` 5–8 long-tails (≥3 words); no lone head terms.
- Cap `seo_score` at **≤65** (FAIL) if any meta field is missing, too short/
  long, or keywords are only 1–2 word generics — even if body SEO looks fine.
- ROI score should reflect CTA + SERP click potential: weak meta description /
  vague meta title → lower `roi_score` as well.

## Label scores in output

Always show heuristic 0–100 scores with threshold and PASS/FAIL per metric.
Always print char counts for `meta_title` and `meta_description` next to PASS/FAIL.
