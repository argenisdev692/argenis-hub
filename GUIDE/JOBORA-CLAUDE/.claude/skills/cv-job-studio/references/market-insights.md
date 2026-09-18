# Market insights — recurring-requirement report

Runs **after** the results table and the ATS rewrite, **before** the
"next actions" prompt. Answers one question: *across everything I actually
read this run, what do employers keep asking for that this CV does not have?*

Typical outputs: "6 of 11 JDs asked for English B2+", "Kubernetes showed up
4 times", "microservices wording in 5 of 11", "RabbitMQ/queues in 3".

## Rule 0 — count, don't guess

This is a **counting** report, not an opinion piece.

- Every number is `n out of N JDs read this run`. N is the number of
  postings for which real text was obtained (cascade success **or** a
  substantive snippet), **not** the number of URLs discovered.
- Every recurring requirement must be traceable to at least one cached job
  URL. If you cannot point at the JD, it does not go in the table.
- **Never** import "the market wants X" from a blog post, from training
  data, or from a Tavily article. Only JDs read in this run (plus the
  cross-run history already stored in the cache) count.
- If `N < 5`, print counts **without percentages** and label the section
  `sample: anecdotal (N=<n>)`. Four JDs are not a trend.

## Input set — the whole funnel, not just the winners

Aggregate over **every JD whose text you read this run**, including the ones
that never reached the table:

| Source | Include? | Why |
|---|---|---|
| Scored ≥70 (table) | Yes | The roles being applied to |
| Scored 60–69 (`skipped`) | Yes | Near misses — usually where the real gap shows |
| G4 / G4b capped (years, credential, language) | Yes | **The most informative bucket** — these are the blockers |
| G1 residency FAIL | Requirements only | Skill demand is still valid signal; ignore their location data |
| G2 stack FAIL (Nest/Angular/Java-only) | No | Different market, would poison the counts |
| G3 aggregator / no text | No | Nothing was read |

Excluding the capped and skipped JDs is the classic mistake: it makes the
report say "you match everything", because the postings that rejected the
candidate were filtered out before counting.

## Buckets to count

Count a requirement once per JD (presence, not frequency — same rule as H).

1. **Language** — English level (B1/B2/C1/native/"fluent"), Spanish,
   Portuguese, and any third local language. Record the exact wording and
   whether it was *required* or *preferred*; that distinction is the whole
   difference between a G4b cap and a footnote.
2. **Infra / DevOps** — Docker, Kubernetes, AWS/GCP/Azure, Terraform,
   CI/CD, Linux, observability.
3. **Architecture / scale** — microservices, event-driven, message queues
   (RabbitMQ, Kafka, SQS, Laravel Horizon), DDD, hexagonal, caching, Redis.
4. **Backend / PHP ecosystem** — PHP version floors (8.1/8.2/8.3+), Laravel
   version floors, Livewire, Symfony, API design, REST vs GraphQL.
5. **Frontend** — Vue 3 vs React, TypeScript, Inertia, Nuxt/Next, Tailwind.
6. **Data** — MySQL/PostgreSQL, query optimisation, Elasticsearch, Redis.
7. **Testing / quality** — PHPUnit/Pest, TDD, coverage floors, code review.
8. **Process / seniority** — years floors, agile/Scrum, on-call, mentoring,
   contract type (freelance/B2B vs employee), timezone overlap demands.

## Output format

```markdown
### Market insights — <N> JDs read on <YYYY-MM-DD>

**Recurring requirements**

| Requirement | Seen in | % | In your CV? | Verdict |
|---|---|---|---|---|
| English B2+ (required) | 6/11 | 55% | ✘ (B1) | Blocking |
| Kubernetes | 4/11 | 36% | ✘ | Recurring gap |
| Microservices wording | 5/11 | 45% | ~ (hexagonal/DDD in Vidula) | Reframe |
| RabbitMQ / message queues | 3/11 | 27% | ~ (Horizon + Redis queues) | Reframe |
| Docker | 8/11 | 73% | ✔ | Covered |
```

`In your CV?` — `✔` present · `~` adjacent evidence exists · `✘` absent.
The `~` rows are the valuable ones: real experience described in the wrong
vocabulary.

**Verdict thresholds** (only when `N ≥ 5`):

| Frequency | Verdict | Meaning |
|---|---|---|
| ≥50% | **Blocking** | Fix this before the next batch of applications |
| 25–49% | **Recurring gap** | Worth real effort in the next month |
| <25% | **Noise** | Mention only if targeting that specific employer |

## Recommendations — max 5, ranked, honest

Split them, because the two kinds cost wildly different amounts:

**A. Free today — wording, not skills.** Only where the CV *already* holds
the evidence and the JD uses a different word for it. Example: the CV says
"Horizon + Redis queues"; JDs say "message queues / async workers" — same
work, so the wording can change. This is a rewrite instruction for the next
ATS pass.

**B. Real gaps — study/build time.** Skills genuinely absent (Kubernetes,
RabbitMQ, AWS, an English exam). Give an honest estimate of effort and say
what it unlocks: *"Kubernetes appeared in 4/11 JDs, all of them ≥75 fits
otherwise"*.

### Truthfulness (non-negotiable — same rule as the rewrite)

**Never** recommend adding a skill to the CV that the candidate does not
have. A recurring-requirement report is a *learning* list and a *vocabulary*
list — never a licence to write Kubernetes into the skills section because
7 postings wanted it. Rows marked `~` may be **reworded**, never invented;
rows marked `✘` may only be **learned**. If in doubt, ask instead of
rewriting.

Also state the honest counter-argument when it applies: an "English B2
required" wall is not solved by a CV edit, and a candidate at B1 applying to
C1 postings is spending applications, not building a pipeline.

## Reach analysis — what a cap is actually costing

The point of counting the capped postings is not trivia: it is to price each
gap in **jobs unlocked**. Every cap (G4 years, G4b language, evidence cap)
is applied *after* `raw = 0.45·H + 0.25·S + 0.30·D`, so the uncapped value
is already computed — no estimation needed.

For each blocked JD, record `raw` alongside the cap reason, then aggregate:

```markdown
**Reach — what unlocks if you close a gap**

| Blocker | JDs blocked | Uncapped scores | Would enter the table (≥70) |
|---|---|---|---|
| English B2 required | 3 | 84, 79, 71 | 3 of 3 |
| English C1+ required | 2 | 88, 62 | 1 of 2 |
| 7+ years required | 2 | 76, 68 | 1 of 2 |
| Skills gap only (scored 60–69) | 4 | 68, 66, 64, 61 | 0 — needs skills, not a cap |
```

Read it as: *"one CEFR level, B1 → B2, would have put 3 more postings on
this week's apply list; going to C1 adds 1 more."* That is a concrete
return on a language exam, not a vague "improve your English".

Rules:

- Only count JDs that **passed G1–G3** — a capped posting that was also
  remote-US-only unlocks nothing.
- Report the uncapped `raw` rounded, and label the column
  **uncapped heuristic** so it is never confused with `match_score`.
- Never present an unlocked score as a current match. It is conditional.
- Sum the same table across the last 3 runs when the history exists —
  "English B2 has blocked 11 otherwise-qualifying postings this month" is a
  far stronger argument than one week's three.

This section is the answer to "amplitud de experiencia / ampliar match":
it shows which single change widens the funnel most, ranked by jobs gained
rather than by how hard the skill sounds.

## Outcome correlation — what is costing you replies

The reach table prices gaps in *jobs unlocked*. This one prices them in
*replies received*, using the `outcome` field on applied entries
(`cache-schema.md`).

Split the applied history in two groups:

- **Responded** — `screening` | `interview` | `offer`
- **Silent/negative** — `rejected` | `no_reply`, plus entries aged past the
  21-day rule (counted as `no_reply`, never rewritten in the cache)

Then compare requirement frequency between the groups:

```markdown
**Outcome correlation** — 13 applications, 11 with a usable result

| Requirement | In responded (n=3) | In silent (n=8) | Direction |
|---|---|---|---|
| English B2+ required | 0 of 3 | 6 of 8 | Against you |
| 7+ years asked | 0 of 3 | 4 of 8 | Against you |
| Laravel + Vue named | 3 of 3 | 3 of 8 | For you |
```

### Honesty rules (this section is the easiest place to lie with numbers)

- **Minimum sample: 8 applications with a usable outcome.** Below that,
  print the raw counts and stop — no direction column, no conclusions.
  Three applications cannot separate a language wall from bad luck.
- Say **directional**, never **causal**. A rejection has one visible
  requirement list and a dozen invisible reasons — internal candidates,
  budget freezes, someone who simply applied first.
- `unknown` entries older than 21 days count as `no_reply` **for analysis
  only**. State the aged count explicitly, e.g. `8 of 13 aged into no_reply`
  — the reader deserves to know how much of the conclusion rests on silence
  rather than on a real answer.
- `outcome_note` beats inference every time. One recorded
  `"rejected: needed C1"` outweighs ten correlations.
- Never let this section revise a `match_score`. Scoring is CV-vs-JD;
  outcomes are market feedback. Keep them apart.

## Cross-run trend

Read the last 3 `runs[].market_insights` entries in
`job-search-cache.json`. If a requirement appears in ≥2 of the last 3 runs,
mark it `persistent` in the table — a one-run spike is often just one
recruiter's template, while three runs is a market.

Report the trend in one line, e.g.
`English B2+ required: 5/9 → 6/11 → 6/10 (persistent)`.

## Persist

Append to this run's `runs[]` entry (see `cache-schema.md`):

```json
"market_insights": {
  "jds_analyzed": 11,
  "sample_note": "8 full JD text, 3 substantive snippet",
  "requirements": [
    {"name": "English B2+", "bucket": "language", "seen": 6, "of": 11,
     "required": 5, "preferred": 1, "in_cv": false, "verdict": "blocking",
     "evidence_urls": ["https://…"]}
  ],
  "reach": [
    {"blocker": "lang_cap_b2", "jds_blocked": 3, "uncapped": [84, 79, 71],
     "would_enter_table": 3}
  ],
  "recommendations": [
    {"type": "wording", "text": "…"},
    {"type": "skill_gap", "text": "…", "effort": "…"}
  ]
}
```

`blocker` values: `lang_cap_b2` · `lang_cap_c1` · `lang_cap_other_language` ·
`years_cap` · `credential_cap` · `evidence_cap` · `skills_only` (scored
60–69 with no cap — the gap is real, not administrative).

`evidence_urls` keeps the report auditable: every claim can be re-opened.
Keep 1–3 URLs per requirement — enough to verify, not a dump.
