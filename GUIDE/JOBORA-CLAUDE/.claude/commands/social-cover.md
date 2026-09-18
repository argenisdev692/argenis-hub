Use the `social-media-studio` skill — cover / image prompts only.

Steps:
0. If niche or language is missing from cache, ask me to pick niche + language
   (AskQuestion) before building cover prompts.
1. Read the latest draft in `SOCIAL-MEDIA/output/*.md` (or the file path I give
   below) and its frontmatter (title, funnel_stage, scores).
2. Emit copy-paste English prompts for ChatGPT image generation: theme
   (headline, primary keyword, visual metaphor) + style (navy `#0B1120`,
   purple `#6366F1`, dark editorial, route A/B/C, constraints from
   `social-media-studio.mdc` and `references/platforms.md`).
3. Include cover + per-platform aspects when useful (16:9, 1:1/4:5, 9:16).
4. Save as `SOCIAL-MEDIA/output/{matching-base}_cover-prompts.md` and show it
   in chat.

Draft file path (optional — leave empty to use latest):

Extra instructions follow below this line:
