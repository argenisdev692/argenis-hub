---
description: Hard constraints for the local Social Media Studio workflow (SOCIAL-MEDIA, social-media-studio skill, /social-* commands). Apply when generating viral topics, multi-platform drafts, CapCut packages, or ChatGPT cover prompts.
globs: SOCIAL-MEDIA/**,.claude/skills/social-media-studio/**,.claude/commands/social-*.md
alwaysApply: false
---

# Social Media Studio — non-negotiable rules

Mirrors `ContentQualityEvaluator`, `GenerateSocialMediaContentAgent`,
`SuggestSocialMediaTopicsAgent`, and
`SOCIAL-MEDIA/prompt-social-media-generator-v2.md` (+ Argenis TOFU/MOFU/BOFU
prompt).

## Step 0 — niche + language gate

- **Never** run Tavily, draft generation, or scoring until the user has
  selected a **niche** (preset or custom) and an **output language**
  (`es` | `en` | `pt-PT`) from
  `.claude/skills/social-media-studio/references/niches.md`.
- Use **AskQuestion** (or an explicit user reply) — do not infer niche from
  vague prompts like "write something viral".
- Persist the choice in `SOCIAL-MEDIA/output/social-generation-cache.json`.

## Truthfulness

- Trends, stats, and release claims must come from Tavily/Firecrawl sources or
  be clearly framed as opinion/experience. Do not invent studies, versions,
  or URLs.
- Expertise signals must be plausible for the author's real background
  (`references/author-context.md`); use anonymized scenarios and hedging when
  not verified.

## Quality thresholds

- `human_writing_index` ≥ 75 is mandatory for "ready to publish".
- `virality_score`, `engagement_score`, `roi_score`, `trend_alignment` ≥ 70 each.
- Max 5 draft iterations; then best attempt + `quality_warning: true`.

## Funnel (TOFU / MOFU / BOFU)

- Every idea must carry one funnel stage. One post = one stage.
- CTA must match stage (TOFU: save/follow · MOFU: discuss/resource · BOFU: book/hire).
- Never mix a TOFU hook with a BOFU hard-sell.

## Human voice

- Enforce the banned AI phrase list from `scoring-rubric.md` (ES + EN).
- Varied rhythm; specific dates/numbers when sourced; SAAEEF structure for
  LinkedIn body.

## Platforms + CapCut

- Deliver LinkedIn, Twitter/X, Instagram, Facebook, TikTok adaptations.
- Instagram + TikTok require CapCut `video_package` (UGC-native, 15–30s,
  stage-aware duration). Never invent a specific trending track name.

## Cover / image prompts (ChatGPT / external)

- Always output **copy-paste** image prompts with **theme** (headline, keyword,
  visual metaphor) and **style** (JOBORA / Argenis dark editorial: deep navy
  `#0B1120`, electric purple `#6366F1`, routes A/B/C from the product prompt).
- Image prompts in **English**. Do not require Gemini Imagen in Cursor; image
  generation is user-driven unless they ask otherwise.

## MCP usage

- Tavily for discovery and per-iteration research (`references/tavily-queries.md`).
- `sequentialthinking` before ideation research and before the draft loop (and
  between failed score passes).
- Optional Firecrawl for 2–3 URLs max when a dated release/source is needed.

## Files

- Drafts and prompts live under `SOCIAL-MEDIA/output/` only — do not overwrite
  seeded CMS data or production DB from Cursor.
