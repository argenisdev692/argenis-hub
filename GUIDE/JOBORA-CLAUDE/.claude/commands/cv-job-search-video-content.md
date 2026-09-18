Use the `cv-video-content-es` skill (read
`.claude/skills/cv-video-content-es/SKILL.md` and its `references/` files —
especially `references/video-scoring.md` for gates, formula and caps).
Do NOT use GitHub. Output language: **español only**.

This command researches companies/edtech/L&D/studios that need **Course
Creators** and related roles producing **Spanish-language** training content —
not FUNDAE classroom trainers (that is `/cv-job-search-formador`).

**Scope (widened 2026-08-26):** España · México · Centroamérica · LatAm sur ·
global/USD clients. The widening is of the **content market**, never of the
**country of residence** — I deliver in Spanish and I live in **Covilhã,
Portugal**.

Search and target companies that need people to:

* grabar cursos
* escribir cursos
* crear laboratorios
* crear ejercicios
* revisar contenido

Seed with the niche keywords **and the regional vocabulary** from the playbook
(MX says *capacitación* / *cápsulas*, not *formación* / *píldoras*): Course
Creator, píldoras formativas, cápsulas de video, videotutoriales, diseño
instruccional, guion instruccional, autor de cursos, capacitación en línea,
microlearning, instructional designer Spanish.

## Non-negotiable gates — run BEFORE scoring

1. **G2 anti-marketing (cheapest reject, do it first).** "Creador de contenido"
   is a homonym; in LatAm it defaults to influencer/social marketing. Reject
   UGC · redes sociales · TikTok · Reels · community manager · influencer ·
   social media · brand content · marketing de contenidos — unless an explicit
   training signal (curso/formación/capacitación/alumnos/LMS) is also present.
2. **G1 residency — the gate whose absence cost me ADR Formación.** The word
   "remoto" decides nothing; the **contract vehicle** does. nómina/payroll/W2/EOR
   → needs a legal entity where I live → FAIL. mercantil/freelance/factura/
   contractor → I invoice from Portugal → PASS. Hard FAIL on
   `remote-es-locked`, `remote-latam-locked`, `remote-country-locked`
   (incl. "anywhere in the U.S.", "from anywhere in Latin America").
   `residency-unclear` PASSES with residencia sub-score ≤60 → `2_verificar_antes`.
   **Never** turn it into a final-score cap — that empties the table.
   Most "LatAm pays USD" agencies require LatAm residency; chase the client who
   pays international rate as `contractor-global`, not the agency reselling
   residency.
3. **G3 content language** = español (or pt-PT). This is the *deliverable's*
   language, a different axis from where I must live.
4. **G4 freshness by entry type:** `vacante` → ≤7 days, no visible date =
   exclude. `canal_abierto` (evergreen "colabora con nosotros") → freshness
   does not apply; verify the channel is still live and record `channel_checked`.
5. **G5 pay floor = 15 EUR/h effective (confirmed).** Below it → FAIL, however
   good the fit. Calibrated to Portugal's cost of living, never to the market of
   origin. Per-finished-minute rates: use `R = 4–6 h` (confirmed, a **band**) →
   **< 60 EUR/min FAIL · 60–89 verify · ≥ 90 clean pass**. Never resolve the
   middle band by picking the convenient end.
6. **G6 pay model:** `fee_fijo`/`mixto` → blocks A/B. `royalty_puro` → cap 69,
   cached, never in the table, **never** given a projected € figure. `impago`
   ("visibilidad", "portfolio") → hard FAIL.

## Scoring

`match_score = round(min(0.30·H + 0.25·S + 0.45·D, caps))`, with
`D = 0.35·residencia + 0.25·pago + 0.20·modelo_pago + 0.20·idioma`
(renormalize over readable components; never score an unknown as 0).
Caps: language C1+ → 55 · language B2 required → 65 · **evidence cap 65** ·
royalty puro → 69. Table only ≥70; 60–69 cached as `skipped`.
Label every number **heuristic**. Do not invent the percentage.

## Steps

1. Base CV: `fullstack cv/Argenis_Gonzalez_CV_2026.md` (or the file I name
   below) — never edit it. Read `video content cv/companies-cache.json` (v2;
   create per `references/companies-cache-schema.md` if missing).
   `pay_floor_eur_hour` (15) and `R` (4–6 h) are already confirmed in `params` —
   do not ask again. Do resolve today's **FX rate** and declare it with its date
   on every conversion.
2. Compile the target list by geographic tier (playbook): companies I name below
   + seeds + Tavily expansion. **ADR Formación is `3_no_aplicar` — never
   re-propose it.** Show me the list if it grows past ~6 companies with a real
   channel before spending Firecrawl on all of them.
3. Discovery with Tavily by rounds (ES · MX/CA · LatAm sur · global-USD), using
   the regional vocabulary and the **negative keyword list**. Triage on the
   snippet in gate order above — log `extract: skipped_g1_snippet` when a
   residency lock is already visible and skip the extract spend.
4. Deep extract cascade on survivors (Firecrawl → Tavily `extract_depth:
   advanced` → raw-content boost → pivot to company/ATS page). Do not scrape
   LinkedIn profiles; `site:linkedin.com/jobs` is discovery — a blocked scrape
   is not a stop. Extract `hiring_residency`, `contract_vehicle`, `pay_model`,
   rate + currency + unit, and demo-video requirements (they feed `C`).
5. `sequentialthinking` before the final H/S/D ranking — not a one-shot guess.
6. Sequential-thinking judge pass on the base CV against "Course Creator /
   Creador de píldoras y videotutoriales e-learning (español)" — 10s scan,
   strengths, keyword_gaps, xyz_gaps. Never invent video clients, view counts,
   or courses not in the CV. **Imagina was a delivery/recording contract, never
   a certification** — never write "certificado"; OWASP does not apply to video.
7. Quick Tavily grounding on ATS practices for e-learning / instructional
   content CVs (format only).
8. Rewrite the CV for this niche (lead with píldoras / videotutoriales / Course
   Creator; Imagina metrics + 1080p/FFmpeg pipeline; condense dev as lab/
   exercise credibility) →
   `video content cv/output/CV_Video_Pildoras_ATS_<YYYY-MM-DD>.md`.
9. Results in **three blocks, sorted by `ROI_app = match_score / (1 + C)`**
   (`C` = hours to apply, from observable requirements only — demo video
   minutes, sample module, authoring test; it is a prioritization heuristic,
   **not** a probability of being hired):
   **A** tarifa confirmada · **B** tarifa no publicada (never estimate) ·
   **C** royalty puro (informational, no € figure).
   Columns: Empresa | Puesto | Tarifa (orig. → EUR/h ef.) | Modelo | Residencia |
   Idioma | Publicado | Link | Match | C (h) | ROI.
10. One `video content cv/output/<empresa-slug>/mensaje-solicitud.md` per
    company (150–220 words). If they require a demo video, leave an explicit
    checklist. Continuity tone for companies I already work with (Imagina).
    **Where `residency_verified` is false, the message must ask:** *"¿Aceptáis a
    un colaborador que factura desde Portugal, o el remoto es solo para
    residentes en España?"* Truthfulness note at the end — drafts only, never
    claim sent.
11. `/cv-judge`-style audit on the CV from step 8 →
    `video content cv/output/ats-informe.md`. Target band **75–85**, never
    force >90.
12. Update `video content cv/companies-cache.json` (v2 schema, with `apply_links`
    triaged into `1_aplicar_ya` / `2_verificar_antes` / `3_no_aplicar` — each
    "verify" entry must say **what** to ask) + refresh
    `video content cv/output/resumen.md`. **Always report `excluded_breakdown`**
    (residencia_locked, rol_marketing, idioma_contenido, frescura, suelo_tarifa,
    impago), even at zero.
13. **Market report to file** —
    `video content cv/output/Informe_Mercado_Video_<YYYY-MM-DD>.md`, mandatory
    whenever there is any observation, **including an empty table** (that is when
    it is worth most). Follow `market-insights.md`: count over JDs actually read,
    `n/N`, percentages only when N≥5, ≥1 evidence URL per row, never recommend
    putting a skill I don't have on the CV.
14. Ask if I want a per-company CV variant, more keywords, or to mark `status`
    (`researched` → `applied`/`dismissed`) after I send them myself.

Target companies / extra instructions, if any, follow below this line:
