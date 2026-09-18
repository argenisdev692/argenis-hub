# Social generation session cache

File: `SOCIAL-MEDIA/output/social-generation-cache.json`

Create the file and `SOCIAL-MEDIA/output/` if missing. One active session per
run; update after each major step.

```json
{
  "selected_niche": {
    "niche_name": "Laravel & PHP backends",
    "research_niche": "Laravel PHP backend development engineering"
  },
  "language": "es",
  "business_goal": "awareness",
  "brand_voice": "conversational",
  "optional_topic_steer": null,
  "author_overrides": null,
  "provider": "gemini",
  "ideas_run": {
    "at": "ISO-8601",
    "tavily_queries": [],
    "selected_idea_index": null,
    "selected_idea": null,
    "funnel_stage": null
  },
  "draft_run": {
    "at": null,
    "iterations": 0,
    "all_scores_pass": false,
    "scores": {},
    "output_file": null
  },
  "cover_prompt_file": null,
  "runs": []
}
```

After each command completes, append a short entry to `runs`:

```json
{
  "command": "social-ideas",
  "at": "ISO-8601",
  "niche": "Laravel & PHP backends",
  "language": "es",
  "summary": "10 ideas generated; user selected idea #3 (mofu)"
}
```
