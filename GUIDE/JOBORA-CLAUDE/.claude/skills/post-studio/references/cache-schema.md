# Post generation session cache

File: `POST-MODULE/output/post-generation-cache.json`

Create the file and `POST-MODULE/output/` if missing. One active session per
run; update after each major step.

```json
{
  "selected_category": {
    "blog_category_name": "AI",
    "blog_category_description": "Artificial Intelligence trends, tools and insights"
  },
  "optional_topic_steer": null,
  "provider": "gemini",
  "ideas_run": {
    "at": "ISO-8601",
    "tavily_queries": [],
    "selected_idea_index": null,
    "selected_idea": null
  },
  "draft_run": {
    "at": null,
    "iterations": 0,
    "all_scores_pass": false,
    "scores": {},
    "primary_keyword": null,
    "meta_title": null,
    "meta_description": null,
    "meta_keywords": [],
    "seo_meta_gate_pass": false,
    "output_file": null
  },
  "cover_prompt_file": null,
  "runs": []
}
```

After each command completes, append a short entry to `runs`:

```json
{
  "command": "post-ideas",
  "at": "ISO-8601",
  "category": "AI",
  "summary": "10 ideas generated; user selected idea #3"
}
```
