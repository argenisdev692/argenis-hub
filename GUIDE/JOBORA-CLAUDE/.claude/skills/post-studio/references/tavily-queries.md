# Tavily playbook — Post Studio

Use `tavily_search` (`plugin-tavily-tavily` or `user-tavily`). One concern per
query, &lt;400 chars. Category description is the **niche** unless the user
adds an optional topic steer.

## Step 1 — Ideas (after category selected)

Base queries (replace `{niche}` with `blog_category_description` or a short
label like "AI" when clearer):

1. `{niche} trends 2026 high search intent`
2. `{niche} viral content ideas ROI`
3. `{niche} audience pain points SEO`
4. `{niche} EEAT thought leadership topics`

Category-specific seeds (run 1–2 in addition to base):

| Category | Extra queries |
|----------|----------------|
| AI | `enterprise generative AI ROI 2026`, `AI tools developers adoption statistics` |
| Software | `software engineering best practices 2026`, `Laravel PHP remote team productivity` |
| Marketing Online | `content marketing SEO trends 2026`, `B2B digital marketing conversion benchmarks` |
| Social Network | `social media algorithm updates 2026`, `community building B2B LinkedIn engagement` |

## Step 2 — Draft loop (per iteration 1–5)

Use the **selected idea** title + `key_trend` + category niche.

| Iteration | Extra queries (add to base title/trend queries) |
|-----------|---------------------------------------------------|
| 1 | `{title} {niche} 2026`, `{key_trend} statistics recent data` |
| 2 | `{niche} case study results ROI`, `{title} viral examples` |
| 3 | `{niche} expert opinion thought leadership`, `{key_trend} industry report 2026` |
| 4 | `{title} authoritative sources citations`, `{title} SEO keywords search volume`, `{niche} long-tail keywords {title}` |
| 5 | `{niche} top performing posts engagement`, `{title} conversion benchmarks` |

## Sequential thinking checkpoints

Call `sequentialthinking` (`user-sequential-thinking`) at least:

1. **After category pick, before Tavily:** audience, credible EEAT angles for
   this category, what not to fake.
2. **After idea list, before draft:** why this idea wins on ROI+SEO; hook plan;
   2 source types to find.
3. **After each failed score pass:** which metric failed and concrete rewrite
   actions (not "write better").

## Firecrawl (optional)

For EEAT, scrape at most 2–3 top Tavily URLs for quotable facts — never invent
stats not in source text.
