Use the `post-studio` skill (read `.claude/skills/post-studio/SKILL.md` and
its `references/` files).

Steps:
0. **Category first:** show the four blog categories and use AskQuestion so I
   pick exactly one (AI, Software, Marketing Online, Social Network). Do not
   run Tavily until I have chosen. Save to
   `POST-MODULE/output/post-generation-cache.json`.
1. Sequential-thinking pass for this category (audience, EEAT credibility, SEO
   intent).
2. Tavily research for high-ROI / SEO / trend angles in that category niche.
3. Return exactly 10 topic ideas in a table with virality, ROI, EEAT, hook,
   and key trend — sorted by combined potential as a hint only.
4. **Stop and wait** for me to pick one idea by number or title; update the
   cache with my selection.

Do not generate the full blog draft in this command — only steps 0–4.

Extra instructions (optional topic steer, provider preference, language), if
any, follow below this line:
