Use the `social-media-studio` skill (read
`.claude/skills/social-media-studio/SKILL.md` and its `references/` files).

Steps:
0. **Niche + language first:** show niche presets and use AskQuestion so I pick
   exactly one niche (or Custom) and one language (`es` | `en` | `pt-PT`). Do
   not run Tavily until I have chosen. Save to
   `SOCIAL-MEDIA/output/social-generation-cache.json`.
1. Sequential-thinking pass for this niche (audience, funnel mix TOFU/MOFU/BOFU,
   credible EEAT angles, language constraints).
2. Tavily research for viral / high-ROI / trend angles in that niche.
3. Return exactly 10 viral topic ideas in a table with hook, funnel_stage,
   format, virality, ROI, key trend, and why it works — sorted by combined
   potential as a hint only. Mix funnel stages; ≥3 ideas with virality ≥ 80.
4. **Stop and wait** for me to pick one idea by number or title; update the
   cache with my selection and funnel_stage.

Do not generate the full multi-platform package in this command — only steps
0–4.

Extra instructions (optional topic steer, business goal, brand voice,
provider preference), if any, follow below this line:
