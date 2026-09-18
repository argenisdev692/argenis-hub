---
name: post-studio
description: >-
  Local blog post pipeline (category pick -> Tavily trends -> 10 ideas ->
  user selects one -> sequential-thinking EEAT draft with human/SEO/ROI scores
  -> ChatGPT-ready cover prompt). Mirrors POST-MODULE Laravel AI assist and
  prompt-social-media-post-generator-v2. Use when generating blog topics or
  drafts for JOBORA categories AI, Software, Marketing Online, Social Network.
  Invoked by /post-ideas, /post-draft, /post-cover.
disable-model-invocation: true
---

# Post Studio (local)

Cursor-native workflow aligned with `POST-MODULE/Post` and
`POST-MODULE/prompt-social-media-post-generator-v2.md`. Follow hard rules in
`.claude/rules/post-studio.md`.

## Workflow

```
Progress:
- [ ] 0. Select blog category (mandatory — blocks all research)
- [ ] 1. Load/update POST-MODULE/output/post-generation-cache.json
- [ ] 2. Sequential thinking — category + audience + EEAT credibility
- [ ] 3. Tavily research (category niche) -> 10 ideas with scores
- [ ] 4. User selects exactly one idea (by number or title)
- [ ] 5. Sequential thinking — hook, sources, structure, SEO meta pack
- [ ] 6. Draft loop (max 5): Tavily -> write -> score vs rubric + SEO gate
- [ ] 7. Save draft under POST-MODULE/output/ (meta_title/description/keywords required)
- [ ] 8. Emit ChatGPT cover prompt (theme + style)
- [ ] 9. Offer /post-cover only, translation, or paste into CMS
```

### 0. Select blog category (before anything else)

**Do not run Tavily, sequential thinking for ideation, or generation until
this step is complete.**

1. Read `.claude/skills/post-studio/references/blog-categories.md`.
2. Present the four categories as a numbered list (name + description).
3. Use **AskQuestion** with exactly these options (user may pick only one):
   - AI — Artificial Intelligence trends, tools and insights
   - Software — Software development, engineering and best practices
   - Marketing Online — Digital marketing strategies, SEO and content marketing
   - Social Network — Social media platforms, networking and community building
4. If the user already named a category in the command or chat, confirm it
   matches one of the four; if ambiguous, ask again — **never default** to AI
   or Software.
5. Write `selected_category` to `post-generation-cache.json` per
   `references/cache-schema.md`.

Optional: ask for a **topic steer** (max one sentence) scoped inside the
category; store as `optional_topic_steer`.

### 1. Session cache

Read or create `POST-MODULE/output/post-generation-cache.json`. All later steps
must include `selected_category.blog_category_name` in filenames and run logs.

### 2. Sequential thinking (pre-research)

One `sequentialthinking` pass: who reads this category, what first-hand angles
are believable, ROI/SEO intent for the category, risks of generic AI slop.

### 3. Tavily — 10 ideas

Follow `references/tavily-queries.md` step 1. Niche =
`blog_category_description` (+ optional steer).

Produce a Markdown table of **exactly 10** ideas:

| # | Title | Hook | Virality | ROI | EEAT | Key trend | Why it works |

Sort by `(estimated_roi + estimated_virality) / 2` descending as a hint; user
still picks manually.

### 4. User selection

Stop and wait for **one** idea (# or title). Update cache with
`selected_idea_index` and `selected_idea` fields.

### 5. Sequential thinking (pre-draft)

Plan H1/H2, hook type, 2 Tavily source targets, CTA aligned with category,
**and** SEO pack: `primary_keyword` (long-tail), 5–8 `meta_keywords`,
draft `meta_title` (50–60) and `meta_description` (150–160) per
`references/seo-meta.md` — optimize for SERP CTR / ROI, not vanity head terms.

### 6. Draft quality loop

Follow `references/scoring-rubric.md`, `references/seo-meta.md`, and Tavily
iteration table in `references/tavily-queries.md`. Target 800–1500 words,
blog Markdown with H1/H2, excerpt, and the full SEO meta pack.

Self-score all five metrics honestly. If any fail — including the SEO meta
gate — iterate (max 5) with specific weakness feedback.

### 7. Save draft

**Block save** until the SEO meta gate in `references/seo-meta.md` passes
(`meta_title`, `meta_description`, long-tail `meta_keywords`).

`POST-MODULE/output/{category-slug}_{date}_{slug-title}.md` with YAML frontmatter:

```yaml
---
blog_category_name: AI
blog_category_description: "..."
title: "..."
primary_keyword: "..."
meta_title: "..."          # 50–60 chars, primary long-tail + intent
meta_description: "..."    # 150–160 chars, benefit + soft CTA
meta_keywords:             # 5–8 long-tails (≥3 words each)
  - "..."
  - "..."
lsi_keywords:
  - "..."
scores:
  human_writing_index: 0
  eeat_score: 0
  virality_score: 0
  roi_score: 0
  seo_score: 0
all_scores_pass: false
quality_warning: false
iterations_required: 1
---
```

After save, print the SEO block (char counts for title/description + primary
+ long-tails) as specified in `references/seo-meta.md`.

### 8. ChatGPT cover prompt

Write `POST-MODULE/output/{same-base}_cover-prompt.txt` using theme (title,
visual metaphor, **primary_keyword**) + fixed brand style (see rule file). User
pastes into ChatGPT / DALL·E — no requirement to call image APIs from Cursor.

**Always emit two copy-paste prompts in the same file:**

1. **PROMPT A — no text:** visual composition only; no readable headline on the
   image (abstract/unreadable document glyphs OK).
2. **PROMPT B — with text:** same scene + **one** short editorial headline
   (derive from draft theme/primary_keyword; modern premium type, ≤10–15% of
   frame). All other on-image text stays abstract/unreadable.

Do not replace A when adding B — keep both labeled sections under the shared
THEME / STYLE block.

### 9. Next actions

Offer: refine draft, `/post-cover` from saved file, or map category to CMS UUID
when publishing.

## References

- Categories: `references/blog-categories.md`
- Tavily: `references/tavily-queries.md`
- Scores: `references/scoring-rubric.md`
- SEO meta: `references/seo-meta.md`
- Cache: `references/cache-schema.md`
- Product spec: `POST-MODULE/prompt-social-media-post-generator-v2.md`
