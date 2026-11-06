# Specification: CV ATS Job Studio

- **Feature slug:** `004-cv-ats-job-studio`
- **Status:** **Revision 3 (2026-09-18 evening).** Round-1 (Q1–Q10) and round-2 (Q11–Q18)
  decisions stand. The verification round (`research.md` §17) demoted the external reply-rate
  figures to evidence for an *ordering* only, added FR-51/FR-52 and reworded FR-36. Q19
  (policy defaults → option b) and Q20 (tests on local PostgreSQL 17 + pgvector) were resolved
  the same evening. No `[NEEDS CLARIFICATION]` remains.
- **Author:** Jose Gonzalez
- **Created:** 2026-09-18 · **Revised:** 2026-09-18 (round 2)
- **Supersedes:** the Cursor-local pipeline in `GUIDE/JOBORA-CLAUDE/` (skill `cv-job-studio`, commands `/cv-job-search`, `/cv-job-search-other`, `/cv-judge`, `/cv-translate`)

---

## 1. Summary

A module that turns one stored CV into applications: it audits the CV the way a
recruiter and an ATS would, rewrites it into an ATS-safe form, discovers remote
job postings worldwide, scores each posting against the CV with an explainable
numeric formula, and exports a per-offer tailored CV in Spanish, English and
Portuguese.

The module productises a pipeline the author already runs by hand in Cursor and
reports as "85% working". The value it adds over that pipeline is not new
capability — it is **persistence, repeatability and measurement**: every run,
score, cap and application outcome becomes a row instead of a JSON file, so the
system can answer "what is this gap costing me in interviews" rather than only
"which jobs match today".

Revision 2 adds the second question the local pipeline never asked: **of the
postings that fit, which ones will anyone actually read?** Fit (does my CV match
this posting) and opportunity (is this posting real, fresh, and reachable through
a channel that gets replies) are scored separately and never mixed. The first
release delivers the **fullstack flow end to end** (`clarify.md` Q11); the
engine stays profile-driven so later posting-based flows need no schema change.

## 2. Motivation / Business context

The local pipeline has five limits the author hit in practice:

1. **The semantic component is a stated approximation.** `S` is a "semantic
   proxy" because the Cursor runtime cannot compute embeddings. Real ATS
   platforms moved to embedding-based matching in 2025–2026 (see `research.md`
   §1), so the proxy systematically mis-scores postings that describe the same
   work in different words — exactly the case the score exists to catch.
2. **No memory across runs.** 13 applications were recorded with zero outcomes
   until an `outcome` field was retro-fitted. Requirement-frequency analysis and
   the reach table only become trustworthy with many runs persisted.
3. **Every run is manual and serial.** Discovery, extraction and scoring are
   I/O-bound over Tavily and Firecrawl but run one at a time in a chat turn.
4. **Four near-identical flows are four copies.** `fullstack cv/`,
   `formador cv/`, `video content cv/` and `other cv/` duplicate the same gates
   and formula with different constants, kept in sync by hand.

5. **Fit alone cannot say where to apply first.** Of 16 applications, the only
   screening came from the *lowest* applied score (70); scores 90, 89, 87, 85, 84
   and 84 were all silent; 11 of the 16 went through a LinkedIn listing or an
   aggregator repost, none of which replied. With one positive outcome none of this
   is statistically significant (`research.md` §17.2) — but the pipeline treats
   every posting ≥70 as equally worth an application and never recorded when
   postings were published, so whether channel or age matter for *this* candidate
   cannot even be measured today. External data suggests they do, as an ordering
   (`research.md` §17.1).

Limit 4 drives the central design decision of this spec: **the four flows are
one engine plus four stored configurations**, not four features. The local
pipeline already proves this — "other mode" runs the identical mathematics over
a user-supplied `gate_config`.

## 3. Actors

| Actor | Description |
|---|---|
| **Candidate (owner)** | The authenticated user. Owns CVs, search profiles, runs and applications. Single-tenant in practice, but every row is scoped to `user_id`. |
| **Search profile** | A named, stored configuration (gates, stack lock, geography, languages, seniority band) that makes one flow out of the shared engine. |
| **Discovery provider** | External read-only source of candidate postings (search API, job-board API, ATS board endpoint, RSS feed). |
| **Extraction provider** | External read-only source of full posting text for shortlisted candidates. |
| **AI provider** | LLM used for requirement extraction, judge narrative and bullet rewriting; and an embedding model used for the semantic component. |
| **Reviewer (the user, again)** | Approves every outbound artifact. The module never applies to a job by itself. |

## 4. User stories

### US-1: Audit my CV like a recruiter and an ATS (Priority: High)

As the candidate, I want a blunt verdict on my CV so I know whether it survives
the first screen.

**Acceptance criteria**

- **Given** a stored CV with extracted text, **when** I request an audit,
  **then** I receive a 10-second-scan verdict of `pass`, `borderline` or
  `reject_risk`, each with concrete stated reasons.
- **Given** an audit, **then** it lists strengths, improvements, keyword gaps and
  bullets that lack a measurable result, plus at most 6 questions whose answers
  would let a rewrite add real metrics.
- **Given** an audit, **then** it reports a structural ATS-readability result per
  check (columns, tables, images, headers/footers, fonts, section headings, date
  format, page count, selectable text) and never reports a single opaque number
  without the per-check detail.
- **Given** the audit produced metric questions, **when** I answer them, **then**
  the answers are stored against the CV and available to a later rewrite.
- **Given** any audit output, **then** every number is labelled a heuristic and
  never presented as a vendor ATS score.
- **Given** an audit, **then** it also reports, each with a stated reason: whether
  the headline title is tailored to the target role, date formats that invite age
  bias, unexplained employment gaps, visible career progression, and whether each
  listed skill is evidenced in an accomplishment.
- **Given** an audit, **then** its readability result has two separate parts — the
  share of the document's content that was recovered when its own text was
  re-extracted, and the list of content issues — never merged into one number.

### US-2: Produce an ATS-safe CV (Priority: High)

As the candidate, I want a rewritten CV that parses cleanly in the major ATS
platforms.

**Acceptance criteria**

- **Given** a CV and an audit, **when** I request a rewrite, **then** the result
  is single-column, table-free, image-free, uses standard section headings, a
  standard font at 9–11pt, and `MM/YYYY` dates wherever the source had a month.
- **Given** a rewrite, **then** the output is at most 2 pages for a profile with
  4 or more years of experience, and the engine reports what it cut to fit.
- **Given** a rewrite, **then** a protected block of facts (education,
  city/country, personal site, contact channels, and any experience the profile
  marks as protected) is carried through unchanged.
- **Given** a rewrite, **then** no skill absent from the source CV is introduced,
  and the rewrite records which source bullet each output bullet derives from.
- **Given** a generated PDF, **then** its text is extractable and the extracted
  text matches the intended content — verified automatically, not assumed.
- **Given** a rewrite, **then** the source CV is never overwritten; the result is
  a new version.

### US-3: Discover remote postings worldwide (Priority: High)

As the candidate, I want postings I can actually take from where I live.

**Acceptance criteria**

- **Given** an active search profile, **when** a discovery run starts, **then**
  candidates are collected from the profile's enabled providers and each is
  stored with its originating provider and query.
- **Given** a candidate posting, **then** its URL is canonicalised before use as
  an identity key, and a posting already seen is not re-processed or re-listed as
  new.
- **Given** a candidate posting, **then** it is classified into exactly one
  remote-scope label and rejected when the scope is one the profile cannot
  accept.
- **Given** a candidate posting, **then** it is rejected when it fails the
  profile's stack lock or is an aggregator/search-results page rather than a
  single posting.
- **Given** gate-passed candidates, **then** a shortlist score ranks them and
  only the top ranked ones are sent for full-text extraction, so provider spend
  is bounded.
- **Given** a run that yields too few new matches, **then** one widening retry is
  attempted before the run reports empty.
- **Given** a discovery run, **then** it never requires authenticating to, or
  logging into, a third-party site.
- **Given** a candidate posting, **then** its publication date is stored when any
  source exposes one, and its absence is stored as absence — never guessed.
- **Given** a posting seen before, **then** each later sighting is recorded, so a
  posting that keeps being re-listed over weeks is detectable.
- **Given** a posting whose source stops listing it, **then** it is marked expired
  with the date and the method that established it, and it leaves the apply list
  while its requirements stay in market statistics.
- **Given** a posting's apply link, **then** the hiring system behind it is
  identified when the link reveals it, and recorded as unknown otherwise.

### US-4: Score each posting against my CV, explainably (Priority: High)

As the candidate, I want one number per posting plus the arithmetic behind it.

**Acceptance criteria**

- **Given** a gate-passed posting with extracted text, **then** scoring produces
  a hard-skill component, a semantic component and a deterministic component,
  each 0–100, and a blended total.
- **Given** a score, **then** the stored breakdown is sufficient to recompute the
  total without calling any external provider again.
- **Given** a posting whose requirements could not be read, **then** the total is
  capped so that location alone can never push an unreadable posting into the
  recommended band.
- **Given** a posting that requires a credential, an experience floor or a
  language level the CV does not meet, **then** the total is capped, the reason
  is stored, and the uncapped value is stored alongside it.
- **Given** a scored posting, **then** matched and missing required skills are
  listed individually, and the language model supplies only the narrative — never
  the number.
- **Given** the same CV, posting and rules version, **then** re-scoring yields the
  identical total.
- **Given** a rules change, **then** previously stored scores keep the rules
  version they were computed under.
- **Given** a requirement the CV does not name exactly, **then** partial credit is
  granted only through a stored relation between the two skills that the candidate
  has confirmed once; a model may propose such a relation, but a proposal alone
  never grants credit.
- **Given** a posting's responsibilities, **then** the semantic component is built
  from how closely each responsibility is matched by the CV's best-matching
  accomplishment, and the breakdown names that accomplishment per responsibility.

### US-9: Tell me which fitting postings are worth my application first (Priority: High)

As the candidate, among postings that fit, I want to apply first where a human is
most likely to read my application.

**Acceptance criteria**

- **Given** a scored posting, **then** it carries a separate opportunity factor
  built from its apply channel, its age, its ghost-posting risk and how its title's
  seniority compares with my band — each factor stored with its value and reason.
- **Given** the apply list, **then** it is ordered by fit multiplied by the
  opportunity factor, and both numbers are shown side by side; the fit threshold
  still decides which postings enter the list at all.
- **Given** a posting of unknown age, **then** the unknown is penalised by a stated
  amount and flagged, not treated as fresh.
- **Given** an opportunity factor whose size is not measured on my own outcomes,
  **then** it is stored and shown as *my policy* — editable, with its evidence
  grade and source — never as a statistic.
- **Given** recorded outcomes, **then** they replace a policy value only once the
  compared groups hold a minimum sample (at least 30 applications with a usable
  outcome and at least 3 positive outcomes between them); below that, my own rates
  are displayed with their uncertainty and not applied. The fit score is never
  changed by any outcome.
- **Given** any opportunity factor, **then** it is labelled an estimate and shows
  the sample size behind it.
- **Given** the apply list, **then** I can switch the opportunity layer to neutral
  and see the pure fit order.

### US-10: Let me apply at the source (Priority: High)

As the candidate, when a posting reaches me through a listing site, I want the
employer's own copy of it.

**Acceptance criteria**

- **Given** a posting reached through a listing site or an aggregator, **when** the
  employer publishes the same role on a public hiring endpoint, **then** the two are
  linked as one posting and the employer's copy becomes the preferred apply link.
- **Given** a linked employer copy, **then** its full text replaces the listing's
  text as the scoring input, and the change is recorded.
- **Given** a posting found on a site whose terms forbid automated access, **then**
  it is stored as a link for me to open myself and its page is never fetched by the
  module.
- **Given** no employer copy is found, **then** the posting stays listed with its
  original channel and that channel's opportunity factor.
- **Given** a posting discovered on a site the module may not open (LinkedIn,
  Indeed, Glassdoor, Tecnoempleo, …), **then** the module searches for the same role
  through permitted channels in this order — official API / ATS / feed → permitted
  portal → web search for the employer's own posting → a permitted fetch of the
  employer's page as a last resort — and never opens the original site.
- **Given** that search finds nothing, **then** the discovery is kept as a
  **reference** (link, title, snippet) in a list for me to open myself; it is not
  treated as a full posting and is not scored as one.
- **Given** a reference I opened myself, **when** I paste the posting text I read,
  **then** that text is scored like any other posting and marked as supplied by me.

### US-5: Tailor and export per offer in ES / EN / PT (Priority: High)

As the candidate, I want a CV aimed at one specific posting, in the posting's
language.

**Acceptance criteria**

- **Given** a scored posting, **when** I request a tailored CV, **then** the
  skills and summary are re-ordered and re-worded toward that posting's
  vocabulary without introducing absent skills.
- **Given** a tailored CV, **when** I export it, **then** I can obtain both a
  word-processor document and a PDF, and both satisfy the US-2 structural rules.
- **Given** an export, **then** I can choose Spanish, English or European
  Portuguese, and the language is applied to section headings and body alike.
- **Given** an export in a language, **then** technical terms that should stay in
  English are not translated.
- **Given** a tailored export, **then** it is linked to the posting it was
  produced for, so I can later see what I actually sent.

### US-6: Track applications and learn what blocks me (Priority: Medium)

As the candidate, I want to know which recurring requirement is costing me
replies.

**Acceptance criteria**

- **Given** a scored posting, **then** I can set what I did with it, and
  separately what the employer did back.
- **Given** applications with recorded results, **then** a report counts how
  often each requirement appeared across every posting read in a run — including
  capped and below-threshold ones — and marks which ones the CV covers.
- **Given** a requirement, **then** the report states the count and the total,
  and expresses a percentage only once the sample is large enough to support one.
- **Given** capped postings, **then** a reach table prices each cap in postings it
  would unlock, computed from the stored uncapped values.
- **Given** enough applications with usable results, **then** the report compares
  requirement frequency between answered and silent applications, and labels the
  comparison directional rather than causal.
- **Given** an application with no recorded result after a defined ageing period,
  **then** analysis may treat the silence as a non-reply, but the stored value is
  never overwritten with a guess.
- **Given** a run with zero matches, **then** the report is still produced.
- **Given** any report, **then** it never proposes adding to the CV a skill the
  candidate does not have.

### US-7: Run the four flows off one engine (Priority: Low — deferred beyond v1)

**Scoped by `clarify.md` Q11:** v1 seeds and tests the fullstack profile only.
What v1 *does* guarantee from this story is the first two criteria — gates,
weights and baselines are profile data, and a new posting-based profile needs no
code or table. The trainer and video-content flows discover *companies*, not
postings, with different blends and extra gates (pay floor, pay model,
anti-fraud); they are a separate later spec.

As the candidate, I want my other three CV flows to reuse the same mathematics.

**Acceptance criteria**

- **Given** the module, **then** gate inputs, stack locks, geography, accepted
  remote scopes, languages and seniority band are stored per profile, not
  hard-coded.
- **Given** a new profile, **then** creating it requires no code change and no new
  table.
- **Given** a profile, **then** a run may not silently inherit another profile's
  gates; unset required gate inputs block the run with a stated reason.
- **Given** two profiles, **then** their postings, scores and runs are separated
  and never mixed in one report.

### US-8: Keep provider spend and legal exposure bounded (Priority: High)

As the candidate, I want the module to be cheap to run and defensible.

**Acceptance criteria**

- **Given** any provider call, **then** its cost is recorded against a period
  budget per category, and a call that would exceed the budget is refused before
  it is made.
- **Given** a fetch of a page, **then** the site's robots policy is honoured and
  the target URL is checked against an outbound guard before the request.
- **Given** a source whose terms forbid redistribution or require attribution,
  **then** the stored record carries its provider and required attribution.
- **Given** any stored posting, **then** named individuals are not retained unless
  the posting itself is the public application channel.
- **Given** a provider failure, **then** the run degrades to the next provider or
  reports partial results, and never fails silently.

## 5. Functional requirements

| ID | Requirement | Stories |
|---|---|---|
| FR-1 | Store many CVs per candidate, one marked primary per profile, with extracted text retained separately from the source file. | US-1, US-2 |
| FR-2 | Produce a structural ATS-readability result per check, plus a recruiter-scan verdict with reasons. | US-1 |
| FR-3 | Store metric answers supplied by the candidate and expose them to rewriting. | US-1, US-2 |
| FR-4 | Represent a CV as structured, addressable sections and entries — not one opaque text blob — so tailoring can re-order and re-weight parts of it. | US-2, US-5 |
| FR-5 | Version every generated CV; never mutate a source. | US-2, US-5 |
| FR-6 | Enforce the ATS structural ruleset at generation time and verify the produced PDF's text is extractable. | US-2, US-5 |
| FR-7 | Carry a per-profile protected block of facts through every rewrite unchanged. | US-2 |
| FR-8 | Refuse to emit a skill or claim absent from the source CV, and record each output bullet's source. | US-2, US-5, US-6 |
| FR-9 | Collect candidate postings from multiple pluggable providers, recording provider and query per candidate. | US-3 |
| FR-10 | Canonicalise every posting URL and use it as the deduplication identity. | US-3 |
| FR-11 | Classify each posting into exactly one remote-scope label. | US-3, US-4 |
| FR-12 | Apply the profile's hard gates before scoring; a gate-failed posting is counted and reasoned but not scored. | US-3, US-7 |
| FR-13 | Rank gate-passed candidates by a shortlist score and extract full text only for the top ranked. | US-3, US-8 |
| FR-14 | Escalate extraction through a provider ladder, recording which step produced the text and its confidence. | US-3, US-4 |
| FR-15 | Extract each posting's requirements and tag each as required, preferred or bonus; weights are fixed by the engine, not chosen by the model. | US-4 |
| FR-16 | Compute a hard-skill component from weighted requirement coverage, with soft requirements capped as a share of the denominator. | US-4 |
| FR-17 | Compute a semantic component from vector similarity between CV and posting, and record the model and dimensions used. | US-4 |
| FR-18 | Compute a deterministic component from experience, location clarity, education and language, renormalising over the signals actually readable. | US-4 |
| FR-19 | Blend the three components by fixed weights, apply the lowest applicable cap, and round once. | US-4 |
| FR-20 | Store every score with its full breakdown, caps, uncapped value and rules version, sufficient to recompute offline. | US-4, US-6 |
| FR-21 | Classify each total into an action band and surface only the recommended bands, retaining the rest. | US-4, US-6 |
| FR-22 | Produce a tailored CV variant per posting and export it as a word-processor document and a PDF, in ES, EN or PT. | US-5 |
| FR-23 | Preserve terms that must remain untranslated across language variants. | US-5 |
| FR-24 | Link each export to the posting and CV version it came from. | US-5, US-6 |
| FR-25 | Record candidate-side status and employer-side result separately per posting. | US-6 |
| FR-26 | Produce a per-run requirement-frequency report over every posting read, including capped and below-threshold ones, excluding stack-fails. | US-6 |
| FR-27 | Produce a reach table pricing each cap in postings unlocked, from stored uncapped values. | US-6 |
| FR-28 | Produce a directional comparison of requirement frequency between answered and silent applications, gated on a minimum sample. | US-6 |
| FR-29 | Treat an aged application with no recorded result as a non-reply for analysis only, never overwriting the stored value. | US-6 |
| FR-30 | Store all gate inputs, locks, geography, languages and seniority band per profile. | US-7 |
| FR-31 | Block a run whose profile is missing a required gate input, stating which. | US-7 |
| FR-32 | Keep a refreshable catalogue of discovery sources and search vocabulary, with a defined refresh trigger rather than a refresh on every run. | US-3, US-8 |
| FR-33 | Record the cost of every provider call against a period budget per category and refuse a call that would exceed it. | US-8 |
| FR-34 | Honour robots policy and check outbound URLs before fetching. | US-8 |
| FR-35 | Retain provider attribution on any record whose source requires it. | US-8 |
| FR-36 | Never authenticate as a user to a third-party site, and never store a user's credentials for one. Keys a provider issues for its own official API (search, job-board and ATS APIs) are permitted and held as configuration. | US-8 |
| FR-37 | Re-run scoring on demand for stored postings without re-fetching, for rules changes and A/B comparison. | US-4, US-6 |
| FR-38 | Extend the audit with: tailored headline title, age-bias date formats, employment gaps, career progression, and skill-evidenced-in-accomplishment — each with a reason. | US-1 |
| FR-39 | Report readability as two separate results: measured share of content recovered on re-extraction, and the list of content issues. | US-1, US-2 |
| FR-40 | Store each posting's publication date when exposed, and record its absence explicitly. | US-3, US-9 |
| FR-41 | Record every sighting of a posting per source, so re-listing frequency and duration are computable. | US-3, US-9 |
| FR-42 | Mark a posting expired, with date and method, when its source stops listing it; exclude it from the apply list, keep it in market statistics. | US-3, US-6 |
| FR-43 | Identify the hiring system behind a posting's apply link when the link reveals it. | US-3, US-10 |
| FR-44 | Grant partial skill credit only through a stored, candidate-confirmed relation between skills; model-proposed relations stay pending until confirmed. | US-4 |
| FR-45 | Compute the semantic component per posting responsibility against the CV's best-matching accomplishment, and store which accomplishment matched. | US-4, US-5 |
| FR-46 | Compute an opportunity factor per scored posting from apply channel, age, ghost-posting risk and title seniority vs the profile band, storing each factor's value, reason and sample size. | US-9 |
| FR-47 | Order the apply list by fit × opportunity factor; entry into the list is still decided by the fit threshold alone. | US-9 |
| FR-48 | Update opportunity factors from the candidate's own outcomes blended with published baselines, weighted so few outcomes move them little; never modify a fit score from an outcome. | US-9, US-6 |
| FR-49 | Link a listing-site posting to the employer's own public copy of the same role when found, prefer it as apply link and scoring text, and record the substitution. | US-10 |
| FR-50 | Never fetch the page of a site whose terms forbid automated access; store such postings as links only. | US-10, US-8 |
| FR-51 | Store every opportunity constant not measured on the candidate's own outcomes as an editable policy value with evidence grade and source; let outcome data replace it only past a minimum sample; offer a neutral mode. | US-9 |
| FR-52 | Record for every discovery source the access its terms and robots policy permit (official feed/API, search-result discovery, sitemap, link only, resolve only); a source whose terms are unchecked defaults to link only. | US-3, US-8, US-10 |
| FR-53 | Keep unresolved discoveries from link-only / resolve-only sites as references (link, title, snippet) for the candidate to open, unscored; let the candidate paste a posting's text, score it like any other, and mark it as candidate-supplied. | US-10 |
| FR-54 | Resolve a discovery to its employer's own copy through permitted channels in a fixed priority order — official API/ATS/feed, permitted portal, web search, permitted page fetch — and record which step produced the text. | US-3, US-10 |

## 6. Non-functional requirements

| ID | Requirement |
|---|---|
| NFR-1 | Scoring is deterministic: same CV, posting and rules version produce the same total, bit for bit. |
| NFR-2 | The language model never determines a numeric score; it extracts and explains only. Every number traces to stored inputs and a stated formula. |
| NFR-3 | Every surfaced number is labelled a heuristic; no output claims equivalence to any named vendor's score. |
| NFR-4 | A discovery run is bounded in provider calls and cost before it starts, and reports actual spend after. |
| NFR-5 | Discovery and extraction run as background work; the interface stays responsive and shows per-stage progress. |
| NFR-6 | An external provider outage degrades the run to partial results with a stated cause; it never corrupts stored state. |
| NFR-7 | All rows are scoped to the owning candidate and unreachable by any other user. |
| NFR-8 | No personal data of third parties is retained beyond what a public posting exposes as its application channel. |
| NFR-9 | The rules version is stamped on every score, run and report, so history remains interpretable after a formula change. |
| NFR-10 | The module runs on the project's existing stack and adds no new runtime service. |
| NFR-11 | Generated documents are reproducible from stored data alone. |
| NFR-12 | Provider adapters sit behind ports; swapping or adding a provider touches no domain logic. |
| NFR-13 | Fit and opportunity are separate quantities: fit is computed from CV and posting alone; opportunity never feeds back into fit; outcomes adjust opportunity only. |
| NFR-14 | Every opportunity factor is shown with the sample size behind it and labelled an estimate. |
| NFR-15 | The interface is mobile-first and meets WCAG AA; a posting's fit, opportunity, matched and missing requirements are readable in one screen without opening a detail view. |
| NFR-16 | Model calls never repeat work: the same posting text is never extracted twice. Instructions and candidate material that repeat across calls are reused at the provider's reduced price **where that reuse is measured to pay for itself**. Tokens served from reuse are reported per purpose. |
| NFR-17 | A text-generation provider outage moves the call to the next configured provider; a result produced by a fallback provider records which provider and model produced it. Similarity vectors are never mixed across models — an embedding outage delays work instead of switching model. |

## 7. Data entities (conceptual, not a physical schema)

| Entity | Meaning |
|---|---|
| **CV** | A source document belonging to the candidate, with its extracted text. |
| **CV structure** | The addressable form of a CV: profile facts, experience entries, skill entries, project entries, education, languages. |
| **CV version** | A generated derivative of a CV — a rewrite or a tailored variant — with its language, its purpose and its provenance. |
| **CV audit** | One recruiter-scan and ATS-readability assessment of a CV, with per-check results and metric questions. |
| **Metric answer** | A candidate-supplied fact answering an audit question, reusable by later rewrites. |
| **Search profile** | A named flow: gates, stack lock, geography, accepted remote scopes, languages, seniority band, protected block. |
| **Discovery source** | A provider the engine may query, with its kind, terms constraints and health. |
| **Search vocabulary** | The refreshable set of query terms and fragments used to build seeds and to score shortlist keyword hits. |
| **Run** | One execution of the pipeline for one profile, with its queries, counts, spend, notes and rules version. |
| **Posting** | A discovered job, canonically identified, with its remote scope, location, employer, dates and provider attribution. |
| **Posting text** | Extracted full text of a posting, with which ladder step produced it and how complete it is. |
| **Requirement** | One extracted demand of a posting, tagged required / preferred / bonus, hard or soft. |
| **Gate result** | Why a posting passed or failed a specific gate. |
| **Score** | One evaluation of a posting against a CV: components, weights, caps, uncapped value, band, rules version. |
| **Skill match** | Per-requirement evidence: matched, partially matched or absent, and the CV text that justifies it. |
| **Application** | What the candidate did with a posting, what the employer did back, when, and which export was sent. |
| **Insight report** | A run's requirement-frequency table, reach table, recommendations and optional outcome comparison. |
| **Budget** | Spend and limit per period and provider category. |
| **Embedding** | A stored vector for a CV or posting text, with its model and dimensions. |
| **Skill relation** | A stored link between two skills (alias, same family), its origin (seed, taxonomy, model proposal) and whether the candidate confirmed it. |
| **Posting sighting** | One observation of a posting on one source at one time — the basis of re-listing and expiry detection. |
| **Apply channel** | How a posting is reached: employer's own site or hiring endpoint, job board, aggregator repost, staffing agency, social network listing. |
| **Opportunity factor** | Per scored posting: channel, freshness, ghost-risk and seniority factors with values, reasons and sample sizes, and their product. |
| **Channel baseline** | The published and learned reply rate per apply channel or seniority step, with prior strength and observed counts. |

## 8. Out of scope

- Automatically submitting applications, or any action inside a third party's
  authenticated session. The module produces artifacts; the candidate applies.
  Round 2 confirms this on evidence, not only principle: volume auto-apply is the
  opposite of what raises reply rates (`research.md` §11, §12).
- Any access to a site that requires logging in, or that circumvents an access
  control or anti-bot measure — including stealth or "enhanced" proxy modes of an
  extraction provider (`research.md` §19.2).
- Commercial or white-label use of content from sources whose terms restrict it to
  personal job search (A-18).
- Retaining recruiter or employee personal data for outreach. B2B agency outreach
  is a different, already-built pipeline (`src/Modules/LeadScout`) and stays
  separate — see `clarify.md` Q1.
- Claiming to replicate a named vendor's score, or to predict a specific ATS's
  ranking.
- Predicting hiring outcomes causally from the stored data.
- Multi-tenant or team features, and hosted use by anyone but the owner.
- Replacing the existing CV upload module; this module builds on it.
- Interview preparation, salary negotiation and recruiter messaging beyond a
  per-posting application draft.

## 9. Assumptions and open decisions

Answers arrive in `clarify.md`; high-impact ones block Phase 4.

| # | Item | Status |
|---|---|---|
| A-1 | This module is distinct from the B2B outreach pipeline and shares infrastructure but not domain. | **RESOLVED (Q1)** — separate `src/Modules/CvJobStudio`; shared infrastructure, no inherited GDPR machinery. |
| A-2 | The four flows are one engine plus four stored profiles. | **Assumption** — the local pipeline's "other mode" already works this way. |
| A-3 | The semantic component becomes real vector similarity rather than remaining a proxy. | **CONFIRMED** — `research.md` §3, verified against this database and `laravel/ai`. |
| A-4 | Postings are reached through search APIs, official job-board APIs, ATS board endpoints and feeds — never by crawling a site whose terms forbid it. | **RESOLVED (Q2)** — accepted; LinkedIn and Indeed are linked, never fetched. |
| A-5 | The blend weights and cap values carry over from the local pipeline unchanged, so historical scores stay comparable. | **RESOLVED (Q3)** — carried over unchanged; position weighting and keyword-in-context added as **rules version 2**. |
| A-6 | A CV must become structured data for per-offer tailoring to be more than string substitution. | **RESOLVED (Q4)** — structured in v1; `raw_text` stays the immutable source. |
| A-7 | Export covers both a word-processor document and a PDF, in three languages. | **Assumption** — both capabilities are installed; the Word adapter is greenfield. |
| A-8 | The experience floor, language baseline and location baseline are profile data, not engine constants. | **RESOLVED (Q7, default)** — profile data, seeded with the author's current values. |
| A-9 | Runs are candidate-triggered; scheduled autonomous runs are a later addition. | **RESOLVED (Q5, default)** — refresh triggers modelled as data so a scheduler needs no redesign. |
| A-10 | Provider spend is bounded by the same budget mechanism the B2B pipeline already uses. | **Assumption** — `BudgetLedger` exists and the pattern is reusable. |
| A-11 | Retrieval over the CV corpus is a scoring and tailoring input, not a chat feature. | **RESOLVED (Q6, default)** — vectors serve scoring and rewriting; no chat surface in v1. |
| A-12 | v1 delivers the fullstack flow end to end; the other flows remain possible but are not delivered. | **RESOLVED (Q11)** — fullstack only; video/trainer become a later spec. |
| A-13 | An opportunity layer, separate from fit, orders the apply list. | **RESOLVED (Q12)** — yes, in v1. |
| A-14 | "Senior" titles with no stated years floor are handled by the opportunity layer (seniority factor), not by a new fit cap. | **RESOLVED (Q13)** — opportunity factor; fit unchanged; Senior seeding continues. |
| A-15 | LinkedIn and aggregator postings are resolved to the employer's own copy when one exists. | **Resolved by default (Q15)** — consistent with Q2: LinkedIn still never fetched. |
| A-16 | Published reply-rate baselines by channel come from vendor datasets and are priors, not facts. | **Superseded (`research.md` §17.1–§17.2)** — they are observational and self-selected; they justify an *ordering* only. Magnitudes become the candidate's policy (FR-51). **Default values RESOLVED (Q19): option (b), mild directional.** |
| A-17 | Tests that need PostgreSQL run against a dedicated test database, never the shared development one. | **RESOLVED (Q20)** — local PostgreSQL 17 + pgvector, connection `pgsql_testing`. |
| A-18 | The module is the candidate's personal, non-commercial job-search tool. Net-Empregos and ITJobs.pt permit personal job search only; InfoJobs requires a partner agreement for any offer aggregator. Any commercial or white-label use requires re-assessing those sources. | **Constraint** — `research.md` §19.4. |

## 10. Success criteria (measurable)

| ID | Criterion |
|---|---|
| SC-1 | A run over a profile completes end to end and persists: run record, postings, gate results, scores with breakdowns, and an insight report — including when zero postings reach the recommended band. |
| SC-2 | Every score recomputes to the identical total from stored data alone, with no provider call. |
| SC-3 | For a held-out set of postings the candidate has already judged by hand, the engine's band agrees with the candidate's judgement on a majority, and every disagreement is explainable from the stored breakdown. |
| SC-4 | Replacing the proxy semantic component with vector similarity changes at least one posting's band on the held-out set, and the direction of change is defensible on inspection. |
| SC-5 | A generated PDF's extracted text matches the intended content, automatically verified in a test. |
| SC-6 | A generated document passes every ATS structural check the module itself asserts. |
| SC-7 | A second profile is added with no schema change and no domain-logic change, and its run does not inherit the first profile's gates. |
| SC-8 | A run's recorded provider spend is non-zero, attributed per category, and a run that would breach the period budget is refused before any spend. |
| SC-9 | No discovery or extraction path requires authentication to a third-party site; a test asserts the outbound guard rejects a denied target. |
| SC-10 | The reach table prices at least one cap in postings unlocked, derived from stored uncapped values. |
| SC-11 | The full ruleset — weights, caps, bands, sub-score tables — is readable as configuration, and changing a weight changes scores without a code edit. |
| SC-12 | Every requirement in §5 maps to at least one passing automated test. |
| SC-13 | Recomputing the apply list with every opportunity factor set to 1 reproduces pure fit order exactly — proving opportunity never alters fit. |
| SC-14 | Loading the 16 held-out applications leaves every opportunity factor at its policy value (no group reaches the minimum sample), shows each group's own rate with its uncertainty, and changes no fit score. |
| SC-15 | For a fixture posting reached via a listing site whose employer publishes it on a public hiring endpoint, the module links both, prefers the employer copy, and records the substitution. |
| SC-16 | A test asserts that no fetch is ever issued to a host whose terms forbid automated access, including when it is the only copy of a posting. |
| SC-17 | Every posting captured after release has either a publication date or an explicit "unknown" — none is silently blank. |
| SC-18 | On a real run, every purpose marked for reuse shows reused input tokens on the calls after the first; a purpose that never does is switched off, and the finding is recorded. |
| SC-19 | With the primary text provider failing at connection level, the call completes on the fallback provider, the result records that provider and model, and no stored fit score changes; with the embedding provider failing, no vector from another model is written. |
| SC-20 | Every page fetch through the extraction provider requests its basic mode only; a test asserts that no anti-bot-bypass mode is ever requested, and that a page refused in basic mode leaves the posting as a reference. |
