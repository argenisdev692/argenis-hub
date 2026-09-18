Use the `cv-job-studio` skill (read `.claude/skills/cv-job-studio/SKILL.md`
and its `references/` files) in **career** mode.

Steps:
1. Go to the `fullstack cv/` folder and read the base CV Markdown there.
2. Run GitHub MCP enrichment: list my repositories, show them to me, and wait
   for me to tell you which ones to use. Read the README (and
   composer.json/package.json) of only the repos I select.
3. Do a sequential-thinking recruiter judge pass: audit the CV like a
   Fortune-500 recruiter doing a 10-second scan — tell me plainly what stands
   out, what's forgettable, and whether this CV would get rejected in the
   first 10 seconds or survive the cut. Include the XYZ-bullet audit and
   keyword gaps (career stack only: PHP/Laravel/Vue/Inertia — do not pivot
   gaps toward NestJS/Angular). Ask me any metric questions you need before
   rewriting.
4. Quickly ground the rewrite in current best practices with a Tavily search
   for ATS-friendly / Jobscan-style high-response resume guidance for 2026.
5. Produce an ATS-optimized rewrite of my CV (single-column Markdown, XYZ
   bullets, no AI-sounding filler) and save it under `fullstack cv/output/`
   without touching the original file. Give me the heuristic ATS score and
   what's still weak.
6. **Portal tiers + keywords first:** read
   `fullstack cv/portal-tiers-cache.json` and
   `fullstack cv/keyword-cache.json`. Check today (`Europe/Lisbon`).
   - Weekday + both populated → **skip** portal/keyword research; reuse.
   - **Sunday** (stale `last_weekly_refresh`), low yield, cache miss, or
     I say **refresh portals/keywords** → Tavily: merge new boards into the
     open catalog (no top-15 cutoff), discover multinational country locales,
     update strong/emerging keywords, then continue.
   Then search Tavily for remote Laravel / PHP / Inertia / Vue openings
   (last 7 days) using Core + rotating Expansion + keyword fragments, in
   **English and Spanish** (español ES correcto). Europe first
   (Spain/Portugal). **Core seeds lead with mid-level + stack-pair wording**
   (`keyword-cache.json` → `keywords.*.role`, `stack_pairs`,
   `fragment_policy`); **Expansion** rotation carries both edges — junior /
   entry / associate (thin) and **senior**. Lead/principal/staff/architect
   stay unseeded. Nothing here is a gate. **Also run dedicated LinkedIn Job seeds** with
   `site:linkedin.com/jobs` (EN + ES) per `search-playbook.md`. Accept only
   remote from Covilhã: **global/anywhere**, **Europe/EU/EMEA**, or
   **PT/ES**. Reject country-locked remote (US-only, UK-only). Bare
   “Remote”/“Remoto” with no lock → provisional OK after deep extract.
   **Hybrid only Lisbon/Lisboa.** Stack lock: PHP/Laravel family.
   US/UK/CA boards may appear in discovery but each JD must still pass
   those residency gates. **Gates (G1–G3) first**, then **G4 snippet
   check** (skip deep extract if years/credential dealbreaker is already
   clear), then shortlist with **D_disc** (P/K/R/F/N scales in
   `portal-tiers.md`) before deep extract.
7. Gate-filter results, then run the **deep extract cascade** from
   `search-playbook.md` on the top gate-passed JDs (Firecrawl scrape →
   Tavily `extract_depth: advanced` → raw-content boost → pivot to company
   careers). LinkedIn is discovery via `site:`; do not stop at a blocked
   scrape. Then score with `job-match-scoring.md`:
   `match_score = round(0.45·H + 0.25·S + 0.30·D)` (soft ≤15% renormalize),
   then apply the lowest applicable cap: G4 dealbreaker 55, **G4b language**
   (EN C1+/third language 55, EN B2 required 65), evidence cap 69 when the
   JD was snippet-only *and* its skills were unreadable.
   Skip URLs already in `fullstack cv/job-search-cache.json`. Show only
   scores ≥ 70 in one Markdown table sorted by ROI:
   Title | Company | Link | ROI | H | S | D | Modality | Why/gaps | Posted.
   Cache 60–69 as `status: skipped` (no table). If yield is low
   (`new_matches < 2` or gate-passed `< 3`), refresh keywords (± portals)
   once and rediscover Expansion once.
8. Update `fullstack cv/job-search-cache.json` with breakdown, modality,
   location, `excluded_count`, skipped 60–69 entries, and this run's
   summary. Also bump `portal-tiers-cache.json` `rotation_index` (do not
   clear portals). On Sunday/low-yield refreshes, persist both portal +
   keyword caches.
9. **Market insights (always, even with zero matches).** Per
   `.claude/skills/cv-job-studio/references/market-insights.md`, count the
   recurring requirements across **every JD you actually read this run** —
   table matches, 60–69 skipped, **and the G4/G4b-capped ones** (never
   G2 stack-fails). Give me a table `Requirement | Seen in n/N | % | In your
   CV? (✔/~/✘) | Verdict`, covering at least: English level demanded (B1 /
   B2 / C1 / native, required vs preferred), infra (Docker, Kubernetes,
   AWS/GCP, Terraform, CI/CD), architecture (microservices, message queues /
   RabbitMQ / Kafka, DDD, Redis), PHP/Laravel version floors, frontend
   (Vue 3, React, TypeScript, Inertia), testing (Pest/PHPUnit, TDD), and
   process (years floor, contract type, timezone overlap). Percentages only
   when `N ≥ 5`; otherwise say `sample: anecdotal`. Include the **reach
   table**: for each blocker (English B2 required, English C1+, 7+ years,
   evidence cap), how many gate-passed JDs it blocked this run, their
   **uncapped** heuristic scores, and how many would have entered the ≥70
   table without it — so I can see which single gap widens the funnel most. Mark `persistent`
   anything also seen in ≥2 of the last 3 runs. Then give me at most 5
   ranked recommendations split into **wording** (evidence already in my CV
   said differently) and **real skill gaps** (with honest effort estimates).
   Never suggest putting a skill I don't have into the CV. Save it as
   `runs[].market_insights` in the cache.
10. Ask me if I want a translation of the rewritten CV (en / es / pt-PT), a
   cover draft for one of the matches, to mark a match's status, or to
   record the **`outcome`** of applications still sitting at `unknown`
   (`no_reply` / `rejected` / `screening` / `interview` / `offer`). Never
   guess an outcome for me.

Extra instructions from me (role focus, location, extra prompt, etc.), if
any, follow below this line — apply them on top of the defaults above:
