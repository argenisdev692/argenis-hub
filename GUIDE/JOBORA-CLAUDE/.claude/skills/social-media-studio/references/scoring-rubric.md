# Social draft quality rubric

Mirrors `ContentQualityEvaluator` and
`SOCIAL-MEDIA/prompt-social-media-generator-v2.md`.

## Thresholds (all must pass for "ready")

| Key | Min | Critical |
|-----|-----|----------|
| human_writing_index | 75 | Yes — blocks "ready" |
| virality_score | 70 | — |
| engagement_score | 70 | — |
| roi_score | 70 | — |
| trend_alignment | 70 | — |

Max quality-loop iterations: **5**. If still failing, ship best attempt with
`quality_warning: true` and list failing scores.

## Human writing (gatekeeper)

Ban list (any language): "In conclusion", "It's important to note", "In today's
fast-paced world", "As we can see", "Needless to say", "In summary", "At the
end of the day", "Moving forward", "Leverage synergies", "Paradigm shift",
"game-changer", "delve", "unlock", "comprehensive", "in today's digital
landscape".

ES ban: "en el mundo actual", "en la era digital", "no es un secreto que",
"desbloquea/potencia/revoluciona tu…", "sumérgete", "el futuro es ahora",
"cambio de paradigma", "solución integral", "lleva tu X al siguiente nivel",
chained exclamation marks, "Hola a todos".

Require: varied rhythm; one concrete mistake/anecdote with numbers/dates when
sourced; natural hedging; no score inflation.

## Virality

Hook in first 1–2 lines: shocking stat, provocative question, contrarian take,
or specific failure number. Standalone before "see more". One emotional trigger.
Reference a trend/data point from research (last ~90 days).

## Engagement

Stage-matched CTA + explicit interaction prompt. Every platform variation
delivers standalone value (no teaser-only).

## ROI

Author positioned as go-to for this problem. Path to go deeper sized to funnel
stage. Prefer short-form packaging on TikTok + Instagram Reels.

## Trend alignment

At least one current trend/format per relevant platform (threads, carousels,
Reels, CapCut sound **type**). Use supplied research — never claim a trend
without it.

## Funnel / EEAT notes

- Document `funnel_stage` and justify CTA.
- Include EEAT bullets even though they are not threshold keys: experience,
  expertise, ≥1 real source URL when citing facts, one limitation/caveat.

## Label scores in output

Always show heuristic 0–100 scores with threshold and PASS/FAIL per metric.
