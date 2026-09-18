---
description: Hard constraints for the local Post Studio workflow (POST-MODULE, post-studio skill, /post-* commands). Apply when generating blog ideas, drafts, or cover prompts in this repo.
globs: POST-MODULE/**,.claude/skills/post-studio/**,.claude/commands/post-*.md
alwaysApply: false
---

# Post Studio — non-negotiable rules

Mirrors `PostContentQualityEvaluator`, `GeneratePostContentAgent`, and
`POST-MODULE/prompt-social-media-post-generator-v2.md`.

## Step 0 — category gate

- **Never** run Tavily, draft generation, or scoring until the user has
  selected one of the four canonical categories in
  `.claude/skills/post-studio/references/blog-categories.md`.
- Use **AskQuestion** (or an explicit user reply) — do not infer category from
  vague prompts like "write about tech".
- Persist the choice in `POST-MODULE/output/post-generation-cache.json`.

## Truthfulness

- Statistics and citations must come from Tavily/Firecrawl sources or be clearly
  framed as opinion/experience. Do not invent studies, percentages, or URLs.
- EEAT experience signals must be plausible for the author's real background;
  use anonymized scenarios and hedging when not verified.

## Quality thresholds

- `human_writing_index` ≥ 75 is mandatory for "ready to publish".
- `eeat_score`, `virality_score`, `roi_score`, `seo_score` ≥ 70 each.
- Max 5 draft iterations; then best attempt + `quality_warning: true`.

## SEO meta (every draft — mandatory)

Follow `.claude/skills/post-studio/references/seo-meta.md`. Never save a
"ready" draft without all three:

- `meta_title` — **50–60** chars; primary **long-tail** near the start;
  intent + CTR/ROI oriented (not brand-only fluff).
- `meta_description` — **150–160** chars; benefit + proof cue + soft CTA.
- `meta_keywords` — **5–8** long-tail phrases (≥3 words each); first =
  `primary_keyword`. No single-word head terms as standalone keywords.

If the SEO meta gate fails, `seo_score` cannot pass and the draft is not ready.
Also set `primary_keyword` + `lsi_keywords` in frontmatter.

## Human voice

- Enforce the banned AI phrase list from `scoring-rubric.md`.
- Varied structure; specific dates/numbers when sourced.

## Cover prompt (ChatGPT / external)

- Always output a **copy-paste** cover prompt file with **theme** (headline,
  keyword, visual metaphor from the draft) and **style** (JOBORA dark editorial:
  deep navy `#0a0a0f`, accents indigo `#6366f1` / lilac `#a855f7`, minimalist,
  16:9, no watermark).
- **Two prompts required in every `*_cover-prompt.txt`:**
  - **PROMPT A — no text:** composition only; no readable headline.
  - **PROMPT B — with text:** same scene + one short editorial headline
    (≤10–15% of frame); other document text abstract/unreadable.
- Keep A intact when writing B; label both clearly for copy-paste.
- Do not require Gemini Imagen in Cursor; image generation is user-driven
  unless they ask otherwise.

## MCP usage

- Tavily for discovery and per-iteration research (`references/tavily-queries.md`).
- `sequentialthinking` before ideation research and before the draft loop (and
  between failed score passes).
- Optional Firecrawl for 2–3 URLs max when EEAT needs verbatim sources.

## Files

- Drafts and prompts live under `POST-MODULE/output/` only — do not overwrite
  seeded CMS data or production DB from Cursor.
