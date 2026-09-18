Use the `post-studio` skill (read `.claude/skills/post-studio/SKILL.md` and
its `references/` files — especially `seo-meta.md` and `scoring-rubric.md`).

Steps:
0. If `POST-MODULE/output/post-generation-cache.json` has no
   `selected_category`, run **step 0** (AskQuestion — pick one of the four
   categories) before anything else.
1. If no idea is selected yet, run `/post-ideas` flow through idea selection
   first and stop until I pick one.
2. Sequential-thinking: hook, H2 outline, two source types to fetch, CTA,
   plus SEO pack — primary long-tail, 5–8 meta_keywords, draft meta_title
   (50–60) and meta_description (150–160) optimized for SERP CTR / ROI.
3. Tavily + draft quality loop (max 5 iterations) per scoring rubric — blog
   post 800–1500 words, honest scores. Enforce the SEO meta gate in
   `seo-meta.md` (missing/weak meta = seo_score cannot pass).
4. Save Markdown under `POST-MODULE/output/` with YAML frontmatter including
   `blog_category_name`, `primary_keyword`, `meta_title`, `meta_description`,
   `meta_keywords` (long-tails), `lsi_keywords`, and scores. Show char counts
   for title/description in chat.
5. Write the ChatGPT cover prompt file (`*_cover-prompt.txt`) with theme +
   style (include primary_keyword) and **two** labeled prompts: A (no text)
   and B (with one short editorial headline).

Extra context (idea override, angle, brand voice, language), if any, follows
below this line:
