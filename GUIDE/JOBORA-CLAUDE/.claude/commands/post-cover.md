Use the `post-studio` skill — cover prompt only.

Steps:
0. If category is missing from cache, ask me to pick one of the four categories
   (AskQuestion) before building the cover prompt.
1. Read the latest draft in `POST-MODULE/output/*.md` (or the file path I give
   below) and its frontmatter scores/title.
2. Emit **two** copy-paste prompts for ChatGPT image generation (same theme +
   style): **PROMPT A — no text** and **PROMPT B — with text** (one short
   editorial headline, ≤10–15% of frame). Shared theme/style block; JOBORA
   palette, dark editorial, 16:9, constraints from `post-studio.mdc`.
3. Save as `POST-MODULE/output/{matching-base}_cover-prompt.txt` and show it
   in chat.

Draft file path (optional — leave empty to use latest):

Extra instructions follow below this line:
