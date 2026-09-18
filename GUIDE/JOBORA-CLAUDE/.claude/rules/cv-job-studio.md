---
description: Hard constraints for the local CV ATS + job-search workflow (fullstack cv/, other cv/, cv-job-studio skill/commands). Apply whenever reading, rewriting, scoring, translating a CV, or searching/deduplicating job links in this repo.
globs: fullstack cv/**,other cv/**,formador cv/**,video content cv/**,gap cv/**,.claude/skills/cv-job-studio/**,.claude/skills/cv-formador-fundae/**,.claude/skills/cv-video-content-es/**,.claude/skills/cv-gap-remote-es/**,.claude/commands/cv-*.md
alwaysApply: false
---

# CV ATS + Job Search — non-negotiable rules

This mirrors the truthfulness/scoring rules already implemented in
`AiResumeStudio` (`CvJudgeAgent`, `AtsRewriteAgent`, `JobMatchScorerAgent`,
`CanonicalUrlNormalizer`). Keep the local workflow consistent with the shipped
product logic.

## Imagina Formación — corrección de hecho (2026-08-25)

Las aulas de IA para **Imagina Formación fueron por CONTRATO**: impartición
de aulas y **grabación de píldoras de vídeo**. **No le entregaron
certificaciones.**

- Es **experiencia laboral**, no una credencial. Va en Experiencia, nunca en
  Certificaciones.
- **Nunca** escribir "Certified AI Trainer", "AI Trainer certificado",
  "Formador de IA Certificado" ni equivalente en ningún idioma.
- Redacción correcta: *"Formador de IA por contrato — Imagina Formación:
  aulas de Claude AI y Microsoft 365 Copilot, grabación de píldoras de
  vídeo"*.
- **Corregido el 2026-08-25** en los cinco CVs (base + `ATS_*` + `CV_en` +
  `CV_es` + `CV_pt-PT`): la sección `CERTIFICATIONS` pasó a
  `COURSES DELIVERED (UNDER CONTRACT)` / `FORMACIÓN IMPARTIDA (POR
  CONTRATO)` / `FORMAÇÃO MINISTRADA (POR CONTRATO)`. Ninguna reescritura
  futura debe reintroducir la palabra "certificado" para Imagina.
- **El contenido de vídeo tampoco fue certificado.** Las píldoras de vídeo
  y el contenido e-learning fueron también **por contrato**. No escribir
  "contenido e-learning certificado" ni atribuirle una norma de aprobación.
  En particular, **OWASP no aplica a contenido de vídeo** — OWASP es
  seguridad de aplicaciones web y solo debe aparecer ligado a Vidula y
  AquaShield. Si alguna vez hubo una norma real para el material formativo,
  sería del tipo SCORM / requisitos de la plataforma, y hay que
  confirmarlo con el usuario antes de escribirlo.

Presentar un contrato de impartición como credencial obtenida es
exactamente lo que prohíbe la regla de veracidad de abajo.

## Truthfulness (never violate)
- Use ONLY facts present in the source CV, GitHub evidence the user selected,
  the user's own answers, or the job posting text. NEVER invent employers,
  titles, dates, degrees, certifications, tools, clients, or metrics.
- If a metric is missing, ask a short targeted question instead of guessing.
  Prefer honest ranges ("~30%", "10–15 users") over fake precision.
- Flag unresolved gaps in feedback instead of fabricating content.

## Folders and files
- `fullstack cv/` = Career mode (niche `fullstack`): base CV + GitHub-enriched
  evidence, defaults to Laravel/PHP/Inertia/Vue remote search.
- `other cv/` = Other Niche mode: any non-career CV + a free-form targeting
  prompt (role/industry/location/tone). Never use GitHub or Laravel defaults
  here.
- `formador cv/` = Formador FUNDAE mode (`/cv-job-search-formador`,
  `.claude/skills/cv-formador-fundae/`): researches Spanish FUNDAE-bonified
  training companies/academies (not job-board vacancies) and produces a
  niche CV + one application message per company, always in Spanish. Source
  CV stays `fullstack cv/Argenis_Gonzalez_CV_2026.md` — this mode has no CV
  file of its own, only `output/` + `companies-cache.json`.
- `video content cv/` = Course Creator / píldoras mode
  (`/cv-job-search-video-content`, `.claude/skills/cv-video-content-es/`):
  researches Spanish-language e-learning / edtech / L&D that need people to
  grabar cursos, escribir cursos, crear laboratorios, crear ejercicios, or
  revisar contenido. Output always Spanish. Same source CV; own
  `companies-cache.json` — never mix with `formador cv/` cache.
  **LatAm removed from the pipeline 2026-08-26** (user decision, same day the
  widening was tried and measured). Scope is **Spain + EU + `contractor-global`**.
  Discovery runs on **direct company channels, not job portals** — see the
  video-mode gate block below.
- `gap cv/` = Gap mode (`/cv-job-search-gap`,
  `.claude/skills/cv-gap-remote-es/`): remote **tech-adjacent but
  non-programming** work as bridge income while the fullstack search runs —
  virtual assistant, customer/chat/email support, data entry, AI data
  trainer / AI rater, content moderation, transcription, remote admin.
  Output always Spanish. Source CV stays
  `fullstack cv/Argenis_Gonzalez_CV_2026.md`; the niche CV goes to
  `gap cv/output/`. Two caches: `job-search-cache.json` (dated postings) +
  `platforms-cache.json` (evergreen registration platforms).
- Never overwrite the base CV file. All rewrites/translations/reports are new
  files under that folder's `output/` subfolder.
- Each mode folder owns its own cache — never mix caches between
  `fullstack cv/`, `other cv/`, `formador cv/`, `video content cv/`, and
  `gap cv/`.

## GitHub enrichment (Career mode only)
- Never assume repos. After listing repositories via MCP GitHub, wait for the
  user's explicit selection before reading any README/file contents.
  If the user skips GitHub or it fails, continue with the CV alone.

## Deduplication cache
- Canonicalize URLs the same way as `CanonicalUrlNormalizer`: strip the
  fragment, strip tracking params (`utm_*`, `ref`, `fbclid`, `gclid`), strip
  trailing slash. Before listing a job in the results table, check it against
  `job-search-cache.json`'s `seen_urls`; skip links already present unless the
  user explicitly asks to re-show/refresh them.
- Always update `job-search-cache.json` after a run (new URLs + `runs` entry).

## Scoring honesty
- `ats_score` and `match_score` (ROI) are **heuristic 0–100 product scores**,
  not a universal vendor ATS / Jobscan score — always label them as heuristic.
- Job match math is defined in
  `.claude/skills/cv-job-studio/references/job-match-scoring.md`:
  **gates first**, then
  `match_score = round(0.45·H + 0.25·S + 0.30·D)`.
  The LLM explains gaps; it must **not** invent the percentage from vibes.
- Checklist: `.claude/skills/cv-job-studio/references/scoring-rubric.md`.
  Don't re-derive a different scale mid-run.

## Job filter gates — non-negotiable

Shared GUIDE pipeline for **both** modes: gates →
`match_score = round(0.45·H + 0.25·S + 0.30·D)` → table only if ≥ 70.
LLM explains gaps; does **not** invent the %. Details:
`.claude/skills/cv-job-studio/references/job-match-scoring.md`.

### Career mode (`fullstack cv/` / `/cv-job-search`)
- **Remote residency (not just “remote” / “remoto”):**
  - PASS: `remote-global`, `remote-eu`, `remote-pt-es`; bare
    `remote-unclear` only if no country lock (Firecrawl; D penalty).
  - FAIL: `remote-country-locked` outside PT/ES/EU; hybrid/onsite outside
    **Lisbon/Lisboa**.
  - Candidate in Covilhã.
- **Search languages:** EN + ES seeds each run.
- **Stack lock:** PHP/Laravel/Livewire/Inertia (or Vue + PHP/Laravel).
  Never seed from CV “Learning: NestJS/Angular”.
- **Seniority focus (mid-level first, 2026-08-24):** Core seeds lead with
  **mid-level + stack-pair** wording in `fullstack cv/keyword-cache.json`
  (`keywords.*.role`, `stack_pairs`, `fragment_policy`). **Expansion**
  rotation carries both edges: junior / entry / associate (thin — remote
  junior openings are scarce) and **senior** (`keywords.*.senior`, added
  2026-08-24; the CV scored 78 on a Senior Full-Stack Engineer posting).
  Lead / principal / staff / architect stay unseeded.
  **Seeding order is a budget decision — it must never leak into the
  score.** A junior JD found organically is still scored honestly by the G4
  years table and the D years sub-score. A weekly keyword refresh may
  **add** to `refresh_policy.never_drop` fields, never remove from them.
- **Seniority band:** target is **entry level → mid, ~0–5 years**. The G4
  years table and the D years sub-score must stay in sync; a floor above 6
  years caps at 55, and a range is read by its **floor** (“3–7 years” asks
  for 3). Over-qualification is never a penalty.
- **Front-end is not a gate:** the lock is PHP/Laravel. Laravel + **React**
  passes and scores as a real match (the CV has React 19 + TypeScript);
  “Vue preferred” / “React or Vue” is a `preferred` weight (0.6), not a
  wall. Only a posting with no PHP/Laravel anywhere fails G2.
- **Language floor (G4b):** English demands are a cap, not a D nuance —
  EN C1+/native or a third local language required → cap 55; EN B2
  **required** → cap 65 (lands in the skipped band). CV baseline: ES native,
  PT resident level, **EN B1**. **Read the floor, not the ceiling:** a range
  that accepts B1 (“B1–C2”, “B1 or above”, “intermediate to advanced”) is
  **not** capped. Vague wording with no CEFR letter (“good English”,
  “English is a plus”) is B2-*preferred*, not required — no cap.
- **Reach analysis:** every capped entry stores `cap_reason` + `raw_score`
  (uncapped) so the market-insights report can price each gap in jobs
  unlocked. Never present an unlocked score as a current match.
- **`outcome` (applied entries):** `status` = what the user did, `outcome` =
  what the employer did back. Values in `cache-schema.md`. **Never guess an
  outcome** — if the user hasn't said, it stays `unknown`. Entries aged past
  **21 days** count as `no_reply` **for analysis only**; the stored value is
  never overwritten. The outcome-correlation section needs **≥8 usable
  outcomes** and is always reported as *directional*, never causal — and it
  never revises a `match_score` (scoring is CV-vs-JD; outcomes are market
  feedback).

### Gap mode (`gap cv/` / `/cv-job-search-gap`)

Deliberate deviations from the shared pipeline — approved 2026-08-25, do not
"correct" them back:

- **Formula:** `match_score = round(0.25·H + 0.25·S + 0.50·D)`. In support /
  data-entry work hard skills barely discriminate; residency, language, pay
  and required experience decide. At H=45% the table would come out empty
  for roles the candidate clearly qualifies for.
- **G4 anti-fraud gate runs FIRST**, on title/snippet, before any extract
  spend. Full signal list in
  `.claude/skills/cv-gap-remote-es/references/gap-scoring.md`. When in
  doubt, exclude: a false negative costs one vacancy, a false positive can
  cost money, ID documents, or a bank account used as a mule. **Never**
  surface a G4-excluded posting "just in case".
- **G2b contract-model gate (hard FAIL):** only permanent remote employment
  by contract, or freelance **with** a contract. Micro-task queues, paid
  surveys, pay-per-click, and contract-less gig work are **out of scope**
  even when the job title is on the priority list — the delivery model is
  rejected, not the trade. An in-house AI rater on a contract: PASS. The
  same task as a platform task queue: FAIL. This removed the former
  "registration platforms" block (Outlier, Appen, Clickworker, Toloka,
  Remotasks) — **do not reintroduce it**.
- **Hard filters:** 100% remote (no hybrid, not even Lisbon) · hires in PT
  or any EU country · Spanish primary (basic English OK) · no experience
  required / junior · **€1,000–3,000/month** equivalent · contract or
  freelance-with-contract. Confirmed pay **above €3,000** for an entry-level
  role is a fraud-scrutiny trigger, not an automatic reject.
- **Two output blocks:** A (confirmed pay in band) · B (pay not published —
  never estimate a figure).
- **Freshness 24–72h**, not the career 7 days.
- **Sort by salary desc, then no-experience-first** — the user's explicit
  request, deliberately not the career ROI sort. `match_score` stays visible
  as a column.
- **Salary honesty:** an hourly rate converted at ×160 h is a *full-time
  equivalence*, never promised income. Always record the original rate and
  the assumed FX rate. A per-task rate is **G2b FAIL**, not block B. Track
  `hours_guarantee` to separate fixed-hours contracts from variable-hours
  ones.
- **Always report `excluded_breakdown.scam_gate` and `contract_model`**, even
  when both are 0. How much fraud and how many task queues got filtered is
  part of the result in this niche, not an internal pipeline detail.
- G4b language caps apply unchanged. In customer support the language *is*
  the job, so an English-native role is not an opportunity with a gap.
- **Freshness is 7 days, not 72h** (user decision, 2026-08-25). Raised after a
  Majorel posting dated 2023 surfaced as the run's best candidate. **Every
  listed entry must carry a visible `posted` date** — no date, no listing.
- **Cache links must be triaged for the user, not just deduped.** The cache
  carries a top-level `apply_links` block with three named lists —
  `1_aplicar_ya` (all gates passed, apply as-is) · `2_verificar_antes` (real
  fit, but one concrete thing to confirm first — always state *what* to ask) ·
  `3_no_aplicar` (dismissed, with the reason). Every `seen_urls` entry also
  carries `safe_to_apply: "yes" | "verify_first" | "no"`. The user reads
  `apply_links` only; `seen_urls` stays the technical dedupe record.

### Market report as a file (gap mode — mandatory when there are observations)

- Whenever a gap run produces **any** market observation, write the report to
  **`gap cv/output/Informe_Mercado_Gap_<YYYY-MM-DD>.md`** — not only to the
  chat. The user reads it later, calmly; a report that lives in terminal
  scrollback is a report that gets lost.
- **This applies even when the results table is empty.** An empty table with a
  structural explanation is the most valuable report this mode produces.
- Still persist `runs[].market_insights` to the cache as well. The file is for
  the human; the cache field is for the next run's `persistent` marking.
- The file follows `.claude/skills/cv-job-studio/references/market-insights.md`
  (counts over JDs actually read, `n/N`, percentages only when `N ≥ 5`, ≥1
  evidence URL per row, `✔`/`~`/`✘` for "do you have it", recommendations split
  into wording vs real skill gap, never recommend adding an absent skill).
- Gap-specific sections the file must carry: **language level demanded** ·
  **support tooling actually required** · **shifts and time zones** ·
  **contract type** · **share of discovery that turned out to be fraud** ·
  **share that turned out to be sales behind a support job title**.
- Report over **every run in the session**, not just the last one, when several
  rounds ran (e.g. a Spanish round plus a Portuguese round) — and say which
  round each number came from.

### Video content mode (`video content cv/` / `/cv-job-search-video-content`)

Deliberate deviations — approved 2026-08-26, do not "correct" them back.
Full spec: `.claude/skills/cv-video-content-es/references/video-scoring.md`.

- **Formula:** `match_score = round(0.30·H + 0.25·S + 0.45·D)`, with
  `D = 0.35·residencia + 0.25·pago + 0.20·modelo_pago + 0.20·idioma`.
  Sits between career (stack decides) and gap (logistics decide): the authoring
  tools here are real but learnable, so H is 0.30 — not 0.45, not 0.25.
- **Two orthogonal axes, never collapsed into one.** `content_language` (what
  language the course is delivered in — must be Spanish) is **not**
  `hiring_residency` (where the person must live). The v1 pipeline had only the
  first, which is why **ADR Formación** was cached as viable and then answered
  that remote was **Spain-residents only**. Candidate delivers in Spanish and
  resides in **Covilhã, Portugal**.
- **G1 residency (hard FAIL):** the word "remoto" decides nothing — the
  **contract vehicle** does. `nómina`/payroll/W2/EOR needs a legal entity where
  he lives → wall. `mercantil`/freelance/factura/contractor → he invoices from
  Portugal → fine. FAIL: `remote-es-locked`, `remote-latam-locked`,
  `remote-country-locked`. PASS: `contractor-global`, `remote-eu`, `remote-pt`,
  `remote-pt-es`.
  `residency-unclear` **PASSES** with the residencia sub-score capped at 60 and
  the entry routed to `2_verificar_antes` — it must **never** become a final-score
  cap: that was tested and emptied the table (5 of 6 real companies at once).
- **LatAm is OUT of the pipeline (user decision, 2026-08-26).** Do not seed
  México, Centroamérica, LatAm sur, or LatAm-facing intermediaries. Do not
  reintroduce them in a keyword refresh. Scope: **Spain + EU +
  `contractor-global`**. A LatAm-residency posting found incidentally is
  `remote-latam-locked` → G1 FAIL, as before.
  The widening was tried and measured the same day, over 27 JDs read: **22 (81%)
  carried a residency wall, 0 offered a vehicle that works from Portugal**, and
  the only two published rates were **4,12 and 5,29 EUR/h** against a 15 EUR/h
  floor. It was closed on evidence, not on preference.
- **Discovery is direct company channels, not job portals (2026-08-26).** Portals
  are structurally biased toward hiring-by-country, and their LatAm inventory is
  priced on another cost base. Lead with round 5 of the playbook
  (`site:<dominio>` + "colabora" / "trabaja con nosotros" / "sé formador") and
  with company career pages. The three live channels already in cache
  (OpenWebinars, Nanfor, Imagina) all came from that route, never from a portal.
- **The mirror finding — watch for it (2026-08-26).** In Europe the contract
  vehicle is right and the **content language flips to English**: Lemon Learning
  offers exactly `remote-eu` + Independent Contractor and demands *native
  English* (G3 FAIL + cap 55); Codeway produces Spanish content but hires on
  Spanish payroll with full professional English. The target is the narrow
  intersection — **Spanish content + mercantil/contractor + invoiceable from
  Portugal** — which in practice means Spanish edtech collaborator channels.
- **G2 anti-marketing (hard FAIL):** "creador de contenido" is a homonym and in
  LatAm defaults to influencer/social marketing. Negative list — UGC, redes
  sociales, TikTok, Reels, community manager, influencer, social media, brand
  content, marketing de contenidos — unless an explicit training signal
  (curso/formación/capacitación/alumnos/LMS) is also present. It is a **gate,
  not a sub-score**, for the same reason G4b exists: a real hard filter cannot
  live as 25% of a weighted mean.
- **G5 pay floor (hard FAIL):** effective rate below `pay_floor_eur_hour` =
  **15 EUR/h** (confirmed by the user 2026-08-26) fails outright. Calibrated to
  **Portugal** cost of living, never to the market of origin — LatAm rate cards
  are a discount built for someone else's cost base and he is on the wrong side
  of that arbitrage. A low sub-score is not enough: a 6 USD/h listing scored 71
  and reached the table before this gate existed.
- **G6 pay model:** `fee_fijo`/`mixto` → block A/B. `royalty_puro` → **cap 69**
  (cached, never in the table, **never** given a projected € figure).
  `impago` ("visibilidad", "portfolio") → hard FAIL. Unlike gap's G2b, royalties
  are capped rather than rejected — a royalty on top of a fixed fee is normal in
  this industry.
- **Evidence cap 65** (stricter than career's 69): with D at 0.45 an evergreen
  "colabora con nosotros" page with clean residency and no readable skill list
  manufactures a 70 on location alone.
- **Currency + finished minute:** convert to EUR declaring the rate and its date.
  A per-finished-minute rate becomes EUR/h only via `R` (hours of work per
  finished minute), which is **not derivable from the CV**. Confirmed by the
  user 2026-08-26: `R = 4–6 h`, **a band, not a point**. Compute both ends and
  present the band. Derived G5 thresholds: **< 60 EUR/min FAIL** ·
  **60–89 verify** (band straddles the floor — never resolve it by picking the
  convenient end) · **≥ 90 clean pass**.
- **Sort by `ROI_app = match_score / (1 + C)`**, where `C` = hours to apply,
  built only from observable requirements (demo video minutes, sample module,
  authoring test). This niche charges hours per application (Revolutia 4–6 min
  video, Be-skiller 2–3 min, OpenWebinars audiovisual test). It is a
  prioritization heuristic, **not** a probability of being hired — no response-rate
  term may be invented.
- **Always report `excluded_breakdown`** (`residencia_locked`, `rol_marketing`,
  `idioma_contenido`, `frescura`, `suelo_tarifa`, `impago`), even at zero.
- **Market report to file** — `video content cv/output/Informe_Mercado_Video_<date>.md`,
  mandatory whenever there is any observation, empty table included.
- **Recalibrating any weight requires re-running the verification battery** in
  `video-scoring.md` § "Verificación numérica" before adopting it.

### Other mode (`other cv/` / `/cv-job-search-other`)
- **No career defaults.** Compile a `gate_config` from the targeting prompt
  (role, `stack_must`, modality_allowed, remote_residency, geography,
  search_languages). Ask if missing — never assume Lisbon or Laravel.
- G1/G2 = that `gate_config`. G3–G4 = same listing quality + dealbreaker cap.
- Show `gate_config` once before searching so the user can correct it.
- Search language(s) = prompt only (not forced EN+ES).

### Cache (both modes)
- Scored matches ≥70: `score_breakdown` `{H,S,D}`, `modality`, `location`,
  `status: new`.
- Scores 60–69: same breakdown, `status: skipped` (dedupe; no table).
- Gate failures / G4 snippet skips / score&lt;60 → `runs.notes` +
  `excluded_count` (optional thin `skipped` for G4 URL dedupe).

## Market insights (career runs — mandatory)

- Every `/cv-job-search` run ends with the recurring-requirement report from
  `.claude/skills/cv-job-studio/references/market-insights.md` — even when
  the results table is empty.
- Count over **every JD whose text was read this run**: table matches,
  60–69 `skipped`, and **G4/G4b-capped** ones. Never count G2 stack-fails.
- Numbers are counts of JDs read (`n/N`), traceable to cached URLs.
  Percentages only when `N ≥ 5`. Never import "the market wants X" from a
  blog post, from training data, or from a Tavily article — only from JDs
  read this run plus the stored `runs[].market_insights` history.
- Recommendations split **wording** (evidence already in the CV, said
  differently) vs **real skill gaps** (study time, honest effort estimate).
  **Never** recommend writing an absent skill into the CV — that is the same
  fabrication ban as the rewrite. `~` rows may be reworded; `✘` rows may
  only be learned.

## Recruiter "10-second scan" judge
- Always evaluate the CV the way a Fortune-500 recruiter skims it in the first
  10 seconds: what stands out, what's forgettable, would it survive the cut or
  get silently rejected. Be blunt and specific, not encouraging by default.

## Always-preserve block (career CV)
- Never remove or shrink these from `fullstack cv/` rewrites, no matter how
  "ATS-clean" an edit would look: Education, city/country (Covilhã, Portugal —
  no full street address), personal website (argenis.dev), WhatsApp number,
  email, LinkedIn (linkedin.com/in/argenisdev692), GitHub, and the social row
  (Instagram, TikTok, Facebook). These are deliberate personal-branding
  choices (tied to the Imagina Formación / AI Trainer side of the profile),
  not an oversight — keep them even if a generic ATS guide would suggest
  trimming social links.
- Always keep the Imagina Formación / Procademy training roles (Claude AI,
  Microsoft 365 Copilot, GitHub Copilot trainer) in Experience — do not cut
  them for being "non-fullstack."
- Put the "fix" effort of each rewrite into the Projects section instead:
  tighten wording, apply XYZ bullets, and make sure every project links to
  its live demo/repo when one already exists in the source CV. Every project
  entry must stay genuinely fullstack-software related — don't add unrelated
  projects.
- If the judge pass thinks the social row hurts the 10-second recruiter scan,
  say so explicitly in feedback — but still keep it in the rewritten CV
  unless the user says otherwise.

## ATS Markdown format
- Single-column Markdown only. No tables/icons/multi-column layout for the CV
  itself (tables ARE fine for the job-matches results view).
- Standard headings; XYZ-style bullets ("Achieved X, measured by Y, by doing
  Z") when the evidence supports it.
- Avoid AI-sounding filler ("leveraged", "passionate about", "proven track
  record", "seamless", "cutting-edge", "synergy", em-dash chains, emoji). Vary
  bullet openings; sound like a human wrote and edited it.

## Language
- Supported output languages: `en`, `es`, `pt-PT` (European Portuguese —
  Covilhã/Portugal vocabulary, never Brazilian pt-BR).
- Translating never replaces the source CV; save as
  `output/CV_<lang>.md`.

## No auto-apply / no auto-send
- This workflow only produces drafts, tables, and files for human review.
  Never claim a message was sent or an application was submitted.

## Use available tools deliberately
- Use `sequentialthinking` before the judge pass, after raw job discovery
  (gate exclusions), and before final H/S/D ranking — not a single-shot
  guess.
- Career: before job discovery, read
  `fullstack cv/portal-tiers-cache.json` and
  `fullstack cv/keyword-cache.json`. Check today in `Europe/Lisbon`.
  **Skip** portal/keyword Tavily on weekdays when both are populated.
  **Refresh both** on Sunday (stale `last_weekly_refresh`), low yield
  (`new_matches < 2` or gate-passed `< 3`), cache miss, or user
  “refresh portals/keywords”. Merge new boards into the open catalog (no
  hard top-15 exclude); discover multinational country locales; merge
  strong/emerging keywords. Specs:
  `.claude/skills/cv-job-studio/references/portal-tiers.md` and
  `keywords.md`. Prefer geo locales **PT/ES/UK/US**; **omit Asia** locales
  and Asia-locked JDs even if the brand has Asia sites.
- Use Tavily for **job** discovery (`time_range: week` for "last 7 days"/
  real-time). Career seeds: portal-tiers Core/Expansion + keyword-cache
  fragments + LinkedIn Job `site:` (EN+ES). Order: **G1–G3 gates** →
  **G4 snippet check** (skip extract if dealbreaker clear) → **D_disc**
  (P/K/R/F/N scales in `portal-tiers.md`) → deep extract. US/UK/CA portal
  hits still need G1 (global/EU/PT-ES/hybrid-Lisbon); country-locked remote
  FAIL.
- For **gate-passed** top candidates (after D_disc), run the deep extract
  cascade in
  `.claude/skills/cv-job-studio/references/search-playbook.md` (Firecrawl
  scrape → Tavily `extract_depth: advanced` → raw-content boost → pivot to
  company/ATS careers). `site:linkedin.com/jobs` is discovery; a blocked
  scrape is not a stop — continue the cascade.
- Cache `match_score` 60–69 as `status: skipped` so they are not
  re-extracted next run; table only ≥70.
