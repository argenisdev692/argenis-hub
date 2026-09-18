# Tavily playbook — Social Media Studio

Use `tavily_search` (`plugin-tavily-tavily` or `user-tavily`). One concern per
query, &lt;400 chars. Niche =
`selected_niche.research_niche` (+ optional topic steer).

## Step 1 — Ideas (after niche + language selected)

Base queries:

1. `{niche} trends 2026 viral social media`
2. `{niche} viral content ideas LinkedIn TikTok`
3. `{niche} audience pain points engagement`
4. `{niche} short form video hooks Reels TikTok ROI`

Niche-specific seeds (run 1–2 in addition to base):

| Niche preset | Extra queries |
|--------------|---------------|
| Laravel & PHP backends | `Laravel release news 2026`, `PHP Laravel developer LinkedIn viral posts` |
| NestJS / Node backends | `NestJS Node.js trends 2026`, `backend API performance viral threads` |
| Angular frontend | `Angular release features 2026`, `frontend TypeScript developer content viral` |
| AI integration | `AI developer tools ROI 2026`, `RAG agents Laravel AI adoption statistics` |
| Custom | derive 2 queries from the user niche string + `viral` / `statistics 2026` |

## Step 2 — Draft loop (per iteration 1–5)

Use selected idea `title` + `key_trend` + niche.

| Iteration | Extra queries |
|-----------|---------------|
| 1 | `{title} {niche} 2026`, `{key_trend} statistics recent data`, `{niche} short form video viral hooks 2026` |
| 2 | `{niche} viral examples social media`, `{title} engagement benchmarks`, `{niche} short form video Reels TikTok ROI 2026` |
| 3 | `{niche} expert opinion thought leadership`, `{key_trend} industry report 2026` |
| 4 | `{niche} authority sources citations`, `{title} best practices`, `{niche} CapCut trending sounds` |
| 5 | `{niche} top performing posts engagement`, `{title} conversion benchmarks CTA` |

## Sequential thinking checkpoints

Call `sequentialthinking` (`user-sequential-thinking`) at least:

1. **After niche+language pick, before Tavily:** audience, funnel mix for the 10
   ideas, what not to fake, language constraints.
2. **After idea pick, before draft:** why this idea wins on virality+ROI; SAAEEF
   plan; CTA by funnel; CapCut duration; 2 source types to find.
3. **After each failed score pass:** which metric failed and concrete rewrite
   actions (not "write better").

## Firecrawl (optional)

Scrape at most 2–3 top Tavily URLs for dated releases / quotable facts — never
invent version numbers or stats not in source text.
