# Scoring rubric (heuristic, not a vendor ATS score)

Condensed from `AiResumeStudio` agents + `GUIDE-ATS-ANALISIS/` + Jobscan-style
match logic (mid-2026). Keep numbers/labels consistent.

**Job match math lives in `job-match-scoring.md`** — this file is the checklist
the agent must follow each run.

## 1. Recruiter "10-second scan" judge

Evaluate like a Fortune-500 recruiter skimming hundreds of resumes:

- What stands out immediately in the first 10 seconds?
- What is forgettable / generic / would blend into "average candidate"?
- Would this survive the first cut, or get silently rejected?
- Verdict: `PASS` (clear value fast) / `BORDERLINE` (needs edits) / `REJECT
  RISK` (would likely be skipped) — always with concrete reasons, not vibes.

## 2. CV judge / audit (before rewrite)

Produce, without rewriting the CV yet:

- `target_job_title` — best inferred title from CV/briefs/JD.
- `strengths` / `improvements` (paraphrased, not a full dump).
- `keyword_gaps` — vs target **career** role / job description / GitHub
  evidence. In career mode, gaps must stay inside PHP/Laravel/Vue/Inertia/
  related stack — **never** invent NestJS/Angular “must learn” gaps that
  redirect the job search.
- `xyz_gaps` — bullets that lack a measurable **Y** in "Achieved X, measured
  by Y, by doing Z".
- `metric_questions` — 0–6 short questions to unlock honest metrics. Never
  invent numbers yourself; if the CV is already well quantified, return none.

## 3. ATS rewrite score (`ats_score`, 0–100)

Heuristic product score for THIS rewrite vs the target brief/JD (format +
clarity + honest keyword alignment). **Separate from** `match_score`.

- Reward: clarity, natural keyword alignment (no stuffing), real quantified
  impact, scannability, single-column ATS-safe formatting.
- Penalize: vagueness, fluff, keyword stuffing, fabricated-sounding claims,
  inconsistent formatting.
- Always pair the score with residual `strengths` / `improvements` /
  `keyword_gaps` / `weak_lines` (what still needs work after this pass).
- Jobscan guidance: aim content quality that supports ~75–85% JD alignment
  when tailored — do not push for fake 95%+ stuffing.

## 4. Job match score / ROI (`match_score`, 0–100)

**Follow `references/job-match-scoring.md` exactly.**

Pipeline:

1. **Gates** (modality, career stack lock, listing quality) — fail → exclude.
   G4 snippet dealbreaker → skip deep extract (never reaches table).
2. **D_disc** shortlist (career) with P/K/R/F/N scales — then deep extract.
3. Compute **H** (45%, soft ≤15% renormalize), **S semantic_proxy** (25%),
   **D** (30%).
4. `match_score = round(min(0.45·H + 0.25·S + 0.30·D, caps))` — round once,
   at the end; the **lowest** applicable cap wins:
   G4 dealbreaker **55** · G4b language (EN C1+/third language **55**,
   EN B2 required **65**) · evidence cap **69** (snippet-only JD whose
   skills were unreadable, i.e. the `H = 50` fallback).
5. Show only scores **≥ 70** in the results table; cache **60–69** as
   `status: skipped` (no table unless user re-shows).
6. **Market insights** — after the table, count recurring requirements
   across every JD read (including capped/skipped) per
   `references/market-insights.md`, and persist `runs[].market_insights`.

| Range | Meaning | Table |
|---|---|---|
| 85–100 | Strong match on must-haves, seniority, domain | Yes |
| 75–84 | Good Jobscan-like band; apply | Yes |
| 70–74 | Borderline; 1–2 real gaps | Yes |
| 0–69 | Weak / fail gates — do not list as a match | No |

Every listed job must include breakdown `H | S | D`, modality, location,
✔ matched / ✘ missing **required** hard skills, and 2–4 sentences of
reasoning (strengths first, then gaps).

Ground every claim in the CV + job text only. Prefer exact skill/tool
matches (with alias table) over vague synonym guesses for **H**.

## 5. Banned AI-sounding phrases (EN/ES/PT)

Never use in rewrites, cover drafts, or digests: "leveraged", "spearheaded"
spam, "passionate about" / "apasionado por" / "apaixonado por", "results-driven
professional" / "profesional orientado a resultados", "in today's fast-paced…"
/ "en el mundo actual", "proven track record" / "trayectoria demostrada",
"seamless", "cutting-edge", "synergy", "robust solution", "harness",
"utilize" (prefer "use"), "I am writing to express" / "Me dirijo a ustedes
para" / "Venho por este meio", "thrilled to apply", emoji, filler transitions,
em-dash chains, perfectly parallel buzzword lists.

Prefer concrete verbs: built, shipped, fixed, reduced, grew, owned, designed,
migrated, maintained, documented. Vary bullet length/openings.

## 6. Language

Output languages: `en`, `es`, `pt-PT` (European Portuguese — Portugal
vocabulary: telemóvel, equipa, utilizador — never Brazilian pt-BR).

## 7. Market insights checklist (career runs)

Full spec: `references/market-insights.md`. Per run, verify:

- [ ] `N` = JDs whose text you actually read (not URLs discovered).
- [ ] Capped (G4/G4b) and 60–69 skipped JDs **are** in the count;
      G2 stack-fails are **not**.
- [ ] Every requirement row has ≥1 evidence URL from this run's cache.
- [ ] `%` shown only when `N ≥ 5`; otherwise `sample: anecdotal (N=…)`.
- [ ] `In your CV?` marked `✔` / `~` (adjacent evidence) / `✘` (absent).
- [ ] `persistent` marked when present in ≥2 of the last 3 runs.
- [ ] ≤5 recommendations, split wording vs real skill gap.
- [ ] **No recommendation adds an absent skill to the CV.** `~` rows may be
      reworded; `✘` rows may only be learned.
- [ ] `runs[].market_insights` written to the cache.
