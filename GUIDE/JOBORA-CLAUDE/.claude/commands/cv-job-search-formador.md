Use the `cv-formador-fundae` skill (read `.claude/skills/cv-formador-fundae/SKILL.md`
and its `references/` files). Do NOT use GitHub. Do NOT search job boards for
vacancies — this command researches **empresas/academias** de formación
bonificada FUNDAE (España) that recruit freelance trainers, then produces a
tailored CV + an application message per company.

Output language: **español** (this niche always targets Spain).

Steps:
1. Base CV: `fullstack cv/Argenis_Gonzalez_CV_2026.md` (or the file I name
   below) — never edit it. Read `formador cv/companies-cache.json` (create it
   per `references/companies-cache-schema.md` if missing).
2. Compile the target company list: start from the companies I name below
   this line (if any), plus the skill's default seeds (Imagina Formación,
   Formadores IT, OpenWebinars, KeepCoding, Deusto Formación Empresas, Femxa,
   Mainfor, Grupo Hedima, IMF Smart Education, Euroinnova Empresas). **Do
   not limit yourself to the seeds** — use Tavily to find more Spanish tech
   training companies that recruit freelance formadores/instructores for
   FUNDAE-bonified courses. Show me the final list if it grows past ~6
   companies before spending Firecrawl budget on all of them.
3. Research each company with Tavily + Firecrawl per
   `references/companies-playbook.md` (deep extract cascade: Firecrawl →
   Tavily advanced extract → raw content → alternate public channel): find
   their real "sé formador / colabora / trabaja con nosotros / bolsa de
   empleo" page and extract tech focus, modality, language, requirements,
   and the real application channel (form URL / email). If a host is found
   by search but scrape fails, continue the cascade — do not stop at the
   snippet.
4. Sequential-thinking judge pass on the base CV against the target
   "Formador de Inteligencia Artificial / Formador Tecnológico freelance
   (España, FUNDAE)" — 10-second recruiter scan, strengths, keyword_gaps,
   xyz_gaps. Never invent a personal FUNDAE accreditation — only the
   organizing entity (the company) is accredited, the freelance trainer
   provides the teaching.
5. Quick Tavily grounding search on 2026 ATS best practices for
   training/e-learning CVs (format sanity-check only, never a source of
   facts about the candidate).
6. Rewrite the CV for this niche (lead with the AI Trainer / formador
   experience and real metrics already in the base CV; keep the dev
   experience condensed as technical credibility) and save as
   `formador cv/output/CV_Formador_IA_ATS_<YYYY-MM-DD>.md` — never overwrite
   the source CV.
7. Write one `formador cv/output/<empresa-slug>/mensaje-solicitud.md` per
   researched company (150–220 words, human tone, no AI-sounding filler,
   using the real channel/vocabulary found on their site; continuity tone
   instead of cold-application tone for companies I already collaborate
   with). Always end each draft with a truthfulness note — these are drafts
   for my review, never claim one was sent.
8. Run a `/cv-judge`-style audit (scoring-rubric §1–3) on the CV from step 6:
   10-second verdict, strengths/improvements/keyword_gaps/xyz_gaps, heuristic
   `ats_score`. **Target band 75–85 — never force >90 with keyword
   stuffing**, even if an older note of mine says otherwise; flag the
   correction if relevant. Save as `formador cv/output/ats-informe.md`.
9. Update `formador cv/companies-cache.json` (add/update each researched
   company + a `runs` entry) and write/refresh
   `formador cv/output/resumen.md` with the comparison table (company, real
   channel, what they look for, message file).
10. Ask me if I want: a per-company CV variant (only if a company's profile
    differs a lot from the others), a translation, or to mark a company's
    `status` (`researched` → `applied`/`dismissed`) once I confirm I sent it
    myself.

Target companies / extra instructions, if any, follow below this line:
