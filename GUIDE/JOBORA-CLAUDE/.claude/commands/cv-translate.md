Translate a CV using the language rules from `.claude/rules/cv-job-studio.md`
and `.claude/skills/cv-job-studio/references/scoring-rubric.md` §6.

1. Figure out which CV to translate and which folder it lives in:
   - If I named a language or file below this line, use that.
   - Otherwise, use the most recent file in `fullstack cv/output/`
     (fallback: the base CV in `fullstack cv/`) unless I say to use
     `other cv/` instead.
2. Translate it into the language(s) I asked for — default to all three
   (`en`, `es`, `pt-PT`) if I didn't specify. European Portuguese only
   (Portugal vocabulary: telemóvel, equipa, utilizador — never Brazilian
   pt-BR).
3. Keep it fully truthful: translate meaning, don't add or drop content, keep
   the same ATS-safe single-column Markdown structure and XYZ bullets. Do not
   introduce AI-sounding filler phrases in the target language.
4. Save each translation as `output/CV_<lang>.md` next to the source file
   (e.g. `fullstack cv/output/CV_en.md`) — never overwrite the source CV or
   the ATS rewrite it came from.
5. Confirm to me which files you wrote.

Language(s) / source file, if specified, follow below this line:
