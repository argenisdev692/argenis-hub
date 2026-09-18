Use the `social-media-studio` skill (read
`.claude/skills/social-media-studio/SKILL.md` and its `references/` files).

Steps:
0. If `SOCIAL-MEDIA/output/social-generation-cache.json` has no
   `selected_niche` or `language`, run **step 0** (AskQuestion — niche +
   language) before anything else.
1. If no idea is selected yet, run `/social-ideas` flow through idea selection
   first and stop until I pick one.
2. Sequential-thinking: SAAEEF outline, CTA by funnel_stage, two source types
   to fetch, CapCut beats + duration, image route A/B/C.
3. Tavily + draft quality loop (max 5 iterations) per scoring rubric —
   LinkedIn, Twitter/X, Instagram (+ CapCut), Facebook, TikTok (+ CapCut),
   honest scores.
4. Save Markdown package under `SOCIAL-MEDIA/output/` with YAML frontmatter
   including niche, language, funnel_stage, and scores.
5. Write the ChatGPT cover prompts file (`*_cover-prompts.md`) with theme +
   style (routes A/B/C).

Extra context (idea override, angle, audience, brand voice, language), if any,
follows below this line:
