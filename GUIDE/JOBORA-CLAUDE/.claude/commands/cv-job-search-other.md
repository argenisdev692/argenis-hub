Use the `cv-job-studio` skill (read `.claude/skills/cv-job-studio/SKILL.md`
and its `references/` files) in **other** (non-career) mode. Do NOT use
GitHub and do NOT default to Laravel/PHP/Inertia/Vue — this CV is unrelated
to my fullstack career track.

This mode has NO fixed defaults for geography, work modality, or output
language — unlike `/cv-job-search`. Everything comes from what I type below
this line each time I run it (target role, industry, city/country, remote or
hybrid, tone, and output language). If I don't specify one of these, ask me
before continuing instead of assuming a default.

**Scoring = same GUIDE pipeline as career:** gates →
`0.45·H + 0.25·S + 0.30·D` → table only if ≥ 70
(`references/job-match-scoring.md`). Only the **gate inputs** change.

Steps:
1. Go to the `other cv/` folder. If there's more than one CV there, ask me
   which one to use; if there's none, ask me to add one and stop.
2. If I haven't given you a targeting prompt below this line (target role,
   industry, city/country, remote vs hybrid, tone, search language), ask me
   for it before continuing — don't guess the niche, the location, or the
   modality.
3. Compile an explicit `gate_config` from my prompt (`role_keywords`,
   `stack_must`, `stack_reject`, `modality_allowed`, `remote_residency`,
   `geography`, `search_languages`). Show it to me once and wait for OK /
   corrections. **Never** inject career Lisbon-hybrid or PHP/Laravel locks
   unless I asked for them.
4. Do a sequential-thinking recruiter judge pass on this CV for that target:
   a blunt 10-second-scan verdict (what stands out, what's forgettable, would
   it survive the cut), plus XYZ-bullet audit and keyword gaps vs the
   targeting prompt. Ask any metric questions you need before rewriting.
5. Quickly ground the rewrite in current best practices with a Tavily search
   for ATS-friendly, high-response-rate resume guidance for 2026.
6. Produce an ATS-optimized rewrite (single-column Markdown, XYZ bullets, no
   AI-sounding filler) and save it under `other cv/output/` without touching
   the original file. Give me the heuristic ATS score and residual gaps.
7. Search Tavily (`time_range: week`) with seeds from `gate_config` in the
   language(s) I specified. Gate-filter with **my** `gate_config` (G1
   modality/residency, G2 stack_must). Deep-extract top gate-passed JDs
   per the cascade in `search-playbook.md` (Firecrawl → Tavily advanced
   extract → raw content → company careers pivot).
8. Skip URLs already in `other cv/job-search-cache.json`. Score with
   `job-match-scoring.md`. Show only ≥70 in:
   Title | Company | Link | ROI | H | S | D | Modality | Why/gaps | Posted.
9. Update `other cv/job-search-cache.json` with breakdown, modality, location,
   excluded_count, and this run's summary (note which `gate_config` was used).
10. Ask me if I want a translation (en / es / pt-PT), a cover draft, or to
    mark a match's status.

My targeting prompt / extra instructions, if any, follow below this line:
