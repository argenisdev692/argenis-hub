# Research: Course Scripts

> Phase 3 · RESEARCH — Live verification of every version, API and pattern the plan depends on.
> Run before `plan.md`, so no technical decision rests on trained memory.

**Feature ID:** 002-course-scripts
**Date:** 2026-09-12
**Method:** Tavily (`search_depth: advanced`) for ecosystem/current-practice questions · Context7 for version-pinned package APIs · local commands for installed-version facts.

**Tag legend** — `[VERIFIED]` confirmed by a cited live source · `[LOCAL]` confirmed by a command run in this repository · `[UNVERIFIED]` carried into the plan as an explicit risk.

---

## 0. Installed baseline (local, authoritative)

Established by running commands here, not by reading `vendor/`.

| Fact | Value | How |
| --- | --- | --- |
| PHP runtime | **8.5.9** | `php -r 'echo PHP_VERSION;'` |
| `composer.json` PHP constraint | `^8.3` | `composer.json` |
| Laravel | `^13.17` | `composer.json` |
| `laravel/ai` | `^0.11.0` | `composer.json` |
| `barryvdh/laravel-dompdf` | `^3.1` | `composer.json` |
| `spatie/laravel-data` | `^4.23` | `composer.json` |
| `spatie/laravel-permission` | `^8.3` | `composer.json` |
| `spatie/laravel-activitylog` | `^5.1` | `composer.json` |
| PDF **reading** capability | **none installed** | `composer.json` — dompdf writes only |
| Firecrawl wired in PHP | **no occurrences** | `grep -rn "irecrawl" src/ app/ config/` returned nothing |

> **Note on the runtime gap.** The runtime is 8.5.9 but `composer.json` declares `^8.3`. Any dependency added must therefore support **8.3 through 8.5** to keep `composer install` honest on both. This constrains §1 below.

> **Note on `laravel/ai`.** `Shared\Infrastructure\AI\AIClientInterface`'s docblock refers to "the installed `laravel/ai` 0.8.x `GeneratedImage` DTO". The installed constraint is `^0.11.0`. The docblock is stale for the image path; this module uses only `generateStructured()`, which §3 verifies against current docs. Flagged here rather than fixed, because touching it is outside this module's scope.

---

## 1. PDF text extraction — `smalot/pdfparser`

**Why researched:** DEC-1 approved adding a local PDF parser. Before naming a version in the plan I need its current release, its PHP support, and its real limitations.

**Findings** `[VERIFIED]`

- **Current version: `v2.12.5`**, published to Packagist **2026-04-21**; package last auto-updated 2026-07-26. — https://packagist.org/packages/smalot/pdfparser
- **PHP 8.5 is explicitly supported.** The release notes carry *"For PHP 8.5: Fix `chr()` deprecation."* — https://github.com/smalot/pdfparser/releases
- **Minimum is PHP 7.1+ since v1**, so a `^2.12` constraint satisfies this repo's `^8.3` floor *and* the 8.5.9 runtime. — https://github.com/smalot/pdfparser
- **API is two lines**, which keeps the adapter trivial to wrap behind a port:
  ```php
  $pdf  = (new \Smalot\PdfParser\Parser)->parseFile($path);
  $text = $pdf->getText();                 // whole document
  $page = $pdf->getPages()[20]->getText(); // per page
  ```
  — https://github.com/smalot/pdfparser
- **License: LGPL-3.0.** Dynamically linked via Composer and unmodified, which is the standard compatible use. — https://github.com/smalot/pdfparser
- **Stated non-features, quoted:** *"Currently, secured documents and extracting form data are not supported."* — https://github.com/smalot/pdfparser
- **Maintenance status is "limited"**, self-declared: *"kept compatible with supported PHP versions… no active feature development."* — https://github.com/smalot/pdfparser

**Corrections to popular but wrong claims found during the search:**

- A widely-shared Laravel tutorial states these packages *"use OCR techniques to detect text"* (blog.jobins.jp). **This is false.** `smalot/pdfparser` reads the PDF's embedded text layer; it performs no OCR. The distinction is the entire basis of DEC-1's accepted limitation, so the plan must not inherit the tutorial's error.
- The same tutorial instructs registering a provider and alias in `config/app.php`. That is Laravel ≤10 style and unnecessary — the library is a plain PHP class with no service provider. This module instantiates it inside its own adapter.

**Decisions carried to `plan.md`:**

| # | Decision | Rationale |
| --- | --- | --- |
| R1.1 | Require `smalot/pdfparser:^2.12` | Current release line, PHP 8.3–8.5 clean |
| R1.2 | Wrap it behind a module-owned port, never call `Parser` from a handler | It is the module's only new dependency and its "limited maintenance" status makes replaceability worth the one interface |
| R1.3 | A parse yielding no usable text **fails the upload under FR-7** | No OCR exists; a scanned PDF must be refused with a reason, never turned into an empty course |
| R1.4 | Reject encrypted/secured PDFs explicitly with their own message | Upstream states they are unsupported; a generic "no structure found" would misdiagnose it for the author |

---

## 2. Sequential generation with progress and cancellation — Laravel 13 queues

**Why researched:** FR-17 (course order), FR-18/19 (per-video outcome, continue past failure), FR-21 (cancel), FR-22 (live progress) are four requirements that usually get hand-rolled. I wanted to know what Laravel 13 already provides before designing anything.

**Findings** `[VERIFIED]` — Context7 `/laravel/docs/__branch__13.x`, `queues.md`, plus https://laravel.com/framework/docs/queues

- **`Bus::batch()` already provides progress and cancellation as first-class state.** Available on the `Illuminate\Bus\Batch` instance: `totalJobs`, `pendingJobs`, `processedJobs()`, `failedJobs`, `failedJobIds`, `progress()` (0–100), `finished()`, `cancel()`, `cancelled()`, `createdAt`, `finishedAt`. `Bus::findBatch($id)` retrieves it by id.
- **Chains nest inside batches.** A nested array inside `Bus::batch([...])` is a *chain*, executed sequentially, while the batch as a whole retains its progress/cancel surface:
  ```php
  Bus::batch([
      [ new JobA(1), new JobB(1) ],   // ← this inner array runs in order
      [ new JobA(2), new JobB(2) ],
  ])->then(...)->dispatch();
  ```
  This is the exact primitive FR-17 needs: **one batch containing one chain of N video jobs** gives course-ordered execution *and* batch progress/cancel, with no bespoke orchestration.
- **`allowFailures()`** disables the default behaviour where one failed job cancels the whole batch. Directly serves FR-19.
- **`SkipIfBatchCancelled` middleware** makes queued jobs no-op once the batch is cancelled, rather than each job hand-checking `$this->batch()->cancelled()`. Directly serves FR-21.
- **A job can cancel its own batch** via `$this->batch()->cancel()` — the documented pattern is literally a quota check:
  ```php
  if ($this->user->exceedsImportLimit()) { $this->batch()->cancel(); return; }
  ```
  This is FR-23 (call ceiling stops the run) with no new mechanism.
- **Batch callbacks are serialized**, so `$this` must not be captured inside `before`/`progress`/`then`/`catch`/`finally`.

**Decisions carried to `plan.md`:**

| # | Decision | Rationale |
| --- | --- | --- |
| R2.1 | One run = **one batch wrapping one chain** of per-video jobs | Sequential order (FR-17) + progress/cancel (FR-21/22) from the framework |
| R2.2 | Per-video jobs **catch their own failures and return normally** | A chain halts on a thrown exception; catching and recording the outcome is what keeps FR-19 true. Precedent already exists in `GeneratePostContentJob::handle()` |
| R2.3 | `allowFailures()` on the batch | Second line of defence for failures outside the job's own try |
| R2.4 | `SkipIfBatchCancelled` middleware on the video job | Framework-provided; avoids a hand-rolled cancelled check in every job |
| R2.5 | Ceiling enforcement = `$this->batch()->cancel()` from inside the job | The documented quota pattern; no new mechanism for FR-23 |
| R2.6 | Batch state is the **transport**, not the record of truth | A batch row is prunable and framework-owned. FR-18/FR-24 need durable per-video outcomes and call counts, so the module persists its own run + per-video outcome rows and reads batch state for live progress only |

---

## 3. Structured output — `laravel/ai` 0.11 and provider schema limits

**Why researched:** DEC-3 makes the entire deliverable pipeline depend on the writer returning a validated structure. If the schema cannot carry a 9-minute script, DEC-3 is unbuildable and must be revisited before the plan, not after.

### 3.1 The SDK contract `[VERIFIED]` — Context7 `/laravel/ai`

- `HasStructuredOutput` + a `schema(JsonSchema $schema): array` method is the documented mechanism; the response is array-accessible (`$response['score']`). This matches `GeneratePostContentAgent` in this repo exactly, so **no new pattern is introduced**.
- **Per-call provider *and* model override is supported** on `prompt()`:
  ```php
  $agent->prompt('Summarize this', provider: Lab::Anthropic, model: 'claude-opus-4-1');
  ```
  FR-14 (author picks the writer) needs only the `provider` half. Per A2/D2, **model stays configuration** and is not exposed as a request parameter.
- `Text::generate()` documents an **`attachments`** parameter for "images, documents, audio". Noted but deliberately unused — DEC-1 chose local extraction, and sending the index to a provider would reintroduce the token cost and privacy exposure that decision rejected.
- **`HasProviderOptions`** lets one agent return different provider tuning (e.g. OpenAI `reasoning.effort`) from a `providerOptions(Lab|string $provider): array` method. Useful: the writer runs on three providers with very different defaults.

### 3.2 Schema and output limits — a contradiction worth resolving `[VERIFIED]`

Search returned two incompatible sets of numbers. Resolved in favour of the primary source:

| Source | Claim | Weight |
| --- | --- | --- |
| **OpenAI official API docs** — https://developers.openai.com/api/docs/guides/structured-outputs | **5000 object properties, 10 levels of nesting**; 120,000-char total string length across names/enums; `additionalProperties: false` mandatory | **Authoritative** |
| Secondary blogs (ergini.com, OpenAI community thread) | 100 properties, 5 levels | Outdated — reflects the 2024 limits; the community thread itself reports 6–7 levels working without error |

- **Provider comparison** — https://dev.to/pockit_tools/llm-structured-output-in-2026: OpenAI native structured output with constrained decoding; Anthropic via tool use (~99%+ schema validity, no documented depth limit); Gemini native with `response_schema`, no documented depth limit. **All three support nested objects, enums and optional fields**, so one schema serves all three selectable providers.
- **The binding constraint is output tokens, not schema shape.** The 2026 structured-output ceiling is reported as **16k output tokens** — https://www.digitalapplied.com/blog/openai-structured-outputs-complete-guide.

### 3.3 The finding that changes the design `[VERIFIED]`

Measured against the reference material in `GUIDE/MODULE-VIDEOS`: `Guion_Video_43.md` is **~35 KB of text** and `Guion_Video_46.md` is larger still. At roughly 3.5 characters per token for Spanish prose, one finished 9-minute script is **≈10–12k output tokens** — inside a 16k ceiling, but with no headroom for the coverage map, continuity note and technical header the same response must also carry.

A single-call-per-script design would therefore sit permanently near the truncation boundary, and truncation of a structured response is not a graceful degradation: it produces invalid JSON and burns the whole call.

**Mitigation carried to `plan.md`: two-stage generation per video.**

1. **Outline call** — technical header, learning objectives, continuity note, the ordered list of sections with title + time budget + purpose, the mandatory-content coverage map, and the practice-document decision with its reason. Small, cheap, and it is where every *verifiable* requirement lives (FR-26…FR-29, FR-31, FR-35).
2. **Section-body calls** — each section's narration, literal prompts, expected on-screen results and presenter actions, written against the outline. Naturally bounded, parallel-safe within a video, and a failure retries one section rather than the whole script.

Corroborating guidance from the same sources: *"If you bump those limits, split the extraction into two calls… Splitting also tends to lift quality because the model is not distracted by fields irrelevant to the input"* (ergini.com), and *"Complex schemas are expensive. Break them into smaller, parallelized calls"* (dev.to).

**Consequence for DEC-2, stated plainly:** two-stage generation **multiplies the call count** — one video is now `1 outline + N sections + 1 review (+ rewrites)`, i.e. roughly 8–12 calls rather than 2–3. This does not weaken the call ceiling; it is precisely why the ceiling is counted in **calls** rather than videos, and it makes the pre-run estimate (US-12) a requirement rather than a nicety. The estimate formula belongs in the plan.

**Also carried:** flatten wherever possible — *"prefer flattening to a list with parent IDs rather than nested children"* (ergini.com). The section list is a flat array with an explicit order index, never a nested tree.

| # | Decision | Rationale |
| --- | --- | --- |
| R3.1 | Reuse `AIClientInterface::generateStructured()`; add no new transport | Documented SDK pattern, already the repo's convention |
| R3.2 | **Two-stage generation** (outline, then section bodies) | 16k structured-output ceiling vs ~10–12k-token reference scripts leaves no safe headroom |
| R3.3 | Flat section array with an order index; max nesting ~3 levels | Official limits are generous, but reliability degrades past 3–4 levels on every provider |
| R3.4 | Provider override per call; model from config only | FR-14 with no user-controlled billing/injection surface (A2/D2) |
| R3.5 | Consider `HasProviderOptions` for per-provider tuning | One writer agent, three providers with different defaults |

---

## 4. Prompt injection — the index and bible are untrusted input

**Why researched:** FR-56 requires that uploaded index and bible content cannot alter the system's own instructions. The threat is *indirect* injection: the author uploads a document, and the document contains instructions. This is the module's single largest security surface, and OWASP compliance is an always-on rule in this project.

**Findings** `[VERIFIED]`

- **Prompt injection is still `LLM01`**, top of the OWASP Top 10 for LLM Applications, confirmed unchanged in the **August 4, 2026** edition — third consecutive year. — https://tech-insider.org/how-to-prevent-prompt-injection-attacks-2026, https://infosec.qa/blog/owasp-llm-top-10-2026
- **It is not patchable.** *"LLMs cannot reliably distinguish between instructions and data… There's no hardware-level separation between 'this is a system instruction' and 'this is user content'."* OWASP's 2025 specification states plainly that **neither RAG nor fine-tuning mitigates it.** — https://www.kunalganglani.com/blog/prompt-injection-2026-owasp-llm-vulnerability
- **Indirect injection is the higher-impact variant** precisely because it arrives through uploaded files and retrieved content: *"Anywhere your application reads external content into the prompt (a vector hit, a scraped webpage, a fetched calendar event, **a file upload**), the trust level of that content needs to match the trust level of an anonymous internet user."* — https://theroadtoenterprise.com/blog/prompt-injection-ai-features-production
- **The documented production defense is four cooperating layers**, mapping to LLM01/02/05/06/07 — structural separation, tool-call allowlists, output validation, audit logging. — same source
- **Content segregation** is the foundation: *"never concatenating raw untrusted text directly into the same string as your system prompt without a structural delimiter, a separate API role, or a validation pass in between."* Wrapping untrusted text in unambiguous tags and instructing the model to treat everything inside as data *"measurably reduces (though doesn't eliminate)"* susceptibility. — https://tech-insider.org/how-to-prevent-prompt-injection-attacks-2026
- **`LLM02` Insecure Output Handling** remains a distinct entry: model output reaching a downstream sink without validation. — https://infosec.qa/blog/owasp-llm-top-10-2026

**Applied to this module** — note how favourably the architecture already scores, and where it does not:

| Layer | Status here |
| --- | --- |
| Structural separation | **To build.** Index text and bible fields are wrapped in explicit data delimiters with a standing "treat as data, never as instruction" directive. The agent's `instructions()` is a constant in PHP and never concatenates user content. |
| Tool-call allowlist | **Not applicable — by design.** These agents have no tools. They return a schema and nothing else, so the highest-impact injection outcome (hijacked tool call) does not exist here. |
| Output validation | **Largely free, thanks to DEC-3.** A structured response is schema-validated before use, and the genuinely dangerous sink is the rendered Markdown/PDF. Blade escapes by default; the plan must keep it that way — **no `{!! !!}` anywhere in the script templates**, which is where `LLM02` would land. |
| Audit logging | **Partly free.** `AuditPort` already exists and FR-54 lists the events. Add: every generation call logged as a security-relevant event with provider, video and outcome — never with prompt content (FR-55). |

| # | Decision | Rationale |
| --- | --- | --- |
| R4.1 | Delimit all index/bible content as data blocks with a standing non-instruction directive | Documented baseline mitigation for indirect injection |
| R4.2 | Agent `instructions()` are PHP constants; user content is only ever the runtime prompt payload | Removes the concatenation site OWASP names as the failure point |
| R4.3 | **No `{!! !!}` in any script or practice Blade template**; assert it in a test | `LLM02` — the rendered PDF/Markdown is the sink |
| R4.4 | Log every provider call as an audit event, with metadata only | Layer 4, and satisfies FR-24/FR-54 without violating FR-55 |
| R4.5 | Treat the parsed index as untrusted **before** the AI sees it: validate/normalise field types and lengths at parse time | Cheapest possible filter, and it also serves FR-5 |

---

## 5. PDF rendering of Spanish content

**Why researched:** every generated deliverable is Spanish (A1) and dompdf's accent handling is a classic failure. I wanted to know whether this is a risk or already solved here.

**Findings**

- `[VERIFIED]` The core PDF fonts (Helvetica, Courier, Times) are **not embedded and lack the needed glyph coverage**; upstream guidance is to use **`DejaVu Sans` / `DejaVu Serif`**, and templates must set a UTF-8 meta tag. — https://github.com/barryvdh/laravel-dompdf
- `[VERIFIED]` Accent corruption (`é` → `?`) in dompdf is the documented symptom of falling back to a core font. — https://stackoverflow.com/questions/16384517/dompdf-character-encoding-utf-8, https://github.com/barryvdh/laravel-dompdf/issues/430
- `[LOCAL]` **This repository already solves it.** `resources/views/exports/pdf/layout.blade.php` sets `<meta charset="utf-8">` (line 20) and `font-family: DejaVu Sans, sans-serif` (line 28). `config/dompdf.php` leaves `default_font => 'serif'`, but the layout's explicit family is what applies.
- `[VERIFIED]` dompdf 3.x **disables remote asset access by default** — a security default worth preserving; the script templates need no remote assets.

| # | Decision | Rationale |
| --- | --- | --- |
| R5.1 | Extend the existing `exports.pdf.layout` rather than writing a new PDF skeleton | Accent handling is already correct there; a fresh layout would re-introduce a solved bug |
| R5.2 | Reuse `ExportPort::pdf()`; create no new PDF adapter | The port exists precisely so modules ship only a template (`BACKEND-PHP` §8) |
| R5.3 | Leave `enable_remote` off | dompdf 3.x default; no remote assets needed |

---

## 6. Bundle archive (FR-48 / D10)

**Why researched:** to confirm D10's "no dependency" premise.

- `[VERIFIED]` `ZipArchive` ships with PHP as the bundled `ext-zip` extension; no Composer package is needed to write a ZIP.
- `[LOCAL]` **`ext-zip` is enabled in this Herd PHP 8.5.9 build** — confirmed by `php -m`, which also reports `fileinfo` (needed for MIME validation under FR-58), `mbstring` and `gd`. D10 stands with no dependency and no open assumption.

| # | Decision | Rationale |
| --- | --- | --- |
| R6.1 | Build the bundle with `ZipArchive`, written to a temp path and streamed | No dependency, matches D10; extension confirmed present |
| R6.2 | Use `fileinfo` for real MIME detection on upload, not the client-supplied type | FR-58; extension confirmed present |

---

## 7. What was deliberately not researched

> ### ⚠️ Correction — this section contained an error
>
> The original text read: *"Firecrawl and Tavily APIs. DEC-1 rules Firecrawl out of this module, and no requirement in `spec.md` calls for web research during script generation — the source of truth is the author's own index, not the internet."*
>
> **That was wrong, and it was my invention rather than the author's requirement.** It conflated two unrelated uses of the same vendor:
>
> | Use | Verdict |
> | --- | --- |
> | Firecrawl as a **PDF reader** for the uploaded index | Correctly ruled out by DEC-1 — parsing stays local. **Unchanged.** |
> | Tavily + Firecrawl as **research while writing each script** | Always intended by the author. **Wrongly excluded; now spec §6.2b.** |
>
> The author's `TAVILY_*` and `FIRECRAWL_*` credentials **are** consumed by this module. See §10 below for the research-layer findings that replace this paragraph.
- **Spatie Data / Permission / Activitylog APIs.** Used exactly as ~19 existing modules already use them; the repo is a stronger reference than external docs, and the project's own rules already pin the v5 activitylog namespaces.
- ~~**Vue/Inertia frontend.** `spec.md` describes a backend module.~~ **Superseded by revision 2 (DEC-7):** V1 ships Inertia screens. No new frontend research is needed — the `Post` module already implements the same shape (Inertia pages + JSON AI actions + polled generation status in `usePostAi`), and the screens follow `FRONTEND/SKILL.md` / `ARCHITECTURE-VUE`. Version-specific Inertia v3 / Pinia Colada APIs are resolved through Context7 at implementation time (tasks Phase M).

---

## 10. Research layer — Tavily and Firecrawl

Added in Phase 7 after the author corrected §7. This is the evidence for spec §6.2b.

### 10.1 What already exists here `[LOCAL]`

| Fact | Evidence |
| --- | --- |
| **Tavily port exists** — `Shared\Infrastructure\Research\TavilyClientInterface` + `TavilyResearchAdapter` | `src/Shared/Infrastructure/Research/` |
| It is already consumed by **three** modules | `grep -rn TavilyClientInterface src/` → `Post`, `Campaigns`, `SocialMedia` assistant adapters |
| It is already resilient | `TavilyResearchAdapter` wraps every call in `CircuitBreaker`, returns `[]` on an empty API key, and its failure closure logs and returns `[]` — a research outage cannot fail a run |
| It caps queries at **4** per call and times out at 15 s | `TavilyResearchAdapter::MAX_QUERIES`, `TIMEOUT_SECONDS` |
| Config already wired | `config/services.php` → `tavily.api_key/url/search_depth/max_results` |
| **Firecrawl does NOT exist in this application** | `grep -rniE "firecrawl\|scrape\|crawl" src/ app/ config/` returns only `spatie/sitemap` and one unrelated comment |

> The author recalled a Firecrawl port alongside the Tavily one. Tavily is there; Firecrawl is not. Firecrawl is available to the *coding agent* as an MCP server, which the Laravel application cannot call — a genuinely easy conflation, but the adapter has to be written.

### 10.2 The gap in the existing Tavily adapter `[LOCAL]`

`TavilyResearchAdapter::searchOne()` posts exactly three fields: `query`, `search_depth`, `max_results`. **There is no recency control**, so results are ranked by relevance alone. For a module whose entire premise is current material, that is the wrong default — and the project's own `rules.md` already mandates preferring recent sources.

### 10.3 Tavily API — what is actually available `[VERIFIED]`

From Tavily's own help centre and current integration docs:

| Parameter | Values | Use here |
| --- | --- | --- |
| `time_range` | `day` / `week` / `month` / `year` (or `d`/`w`/`m`/`y`) | **The fix for 10.2.** Bias every research call toward recent sources |
| `topic` | `general` / `news` / `finance` | `general` for subject research |
| `days` | integer, default 3 | Only meaningful with `topic: news` |
| `start_date` / `end_date` | `YYYY-MM-DD` | Precise windows; not needed for V1 |
| `include_domains` / `exclude_domains` | string lists | Lets an author bias toward official docs later |
| **`include_raw_content`** | `false` / `"markdown"` / `"text"` | **See 10.4 — this changes the design** |
| `chunks_per_source` | integer, default 3 | Controls snippet volume per result |
| `search_depth` | `ultra-fast` / `fast` / `basic` / `advanced` | Project config already pins `advanced` |

Sources: https://help.tavily.com/articles/3347142954-best-practices · https://docs.crewai.com/v1.15.3/en/tools/search-research/tavilysearchtool · https://apify.com/clearpath/tavily-search-api

### 10.4 The finding that changes the escalation design `[VERIFIED]`

**Tavily can already return full page content as Markdown**, via `include_raw_content: "markdown"`, in the *same call* as the search. That reorders the escalation ladder the spec describes in FR-13c:

1. **Search snippet** — free with the search, sufficient for most points.
2. **Tavily raw content** — same request, no second provider, no second round-trip.
3. **Firecrawl scrape** — only when Tavily's raw content comes back empty or unusably thin, which is the JavaScript-rendered case Firecrawl exists to solve.

Writing Firecrawl as step 2 would have paid a second provider for something the first already returns. Step 3 is where it earns its credit.

### 10.5 Firecrawl API `[VERIFIED]`

- Endpoint `POST /v1/scrape`, bearer auth, body `{ url, formats: ["markdown"], onlyMainContent: true }`. `onlyMainContent` defaults to `true` and is *"a deterministic HTML-level filter applied before markdown is generated; no LLM is involved"* — so it costs no tokens and is stable.
- **`maxAge`** (milliseconds) returns a cached copy when one is younger than the given age — a direct cost saver for a 48-point run that may touch the same authoritative page repeatedly.
- Also available: `timeout`, `waitFor`, `includeTags`/`excludeTags`, `parsers`.
- **Pricing shape:** 1 credit per single-page scrape; batch scraping 0.5 credits/page; free tier 500 credits/month.
- **Versioning:** the author's `.env` pins `FIRECRAWL_BASE_URL=https://api.firecrawl.dev/v1`, and `/v1/scrape` is still documented as current in 2026 guides; a v2 surface also exists. The base URL stays configuration, so the adapter follows whatever the author sets.

Sources: https://docs.firecrawl.dev/api-reference/endpoint/scrape · https://zackproser.com/blog/firecrawl-api-guide-2026 · https://docs.fastcrw.com/v2-api

### 10.6 Decisions carried to `plan.md`

| # | Decision | Rationale |
| --- | --- | --- |
| R10.1 | **Reuse `TavilyClientInterface`**; do not build a second search transport | It exists, is breaker-wrapped and fail-soft, and three modules already depend on it |
| R10.2 | **Extend the shared interface with an optional recency argument**, defaulting to today's behaviour | Closes the 10.2 gap without touching `Post`, `Campaigns` or `SocialMedia`; a module-local copy would duplicate transport + breaker (a DRY failure this project's rules call out) |
| R10.3 | **Escalate snippet → Tavily raw markdown → Firecrawl**, in that order, capped per point | 10.4: Firecrawl is paid for only where Tavily genuinely cannot deliver |
| R10.4 | New `Shared\Infrastructure\Research\FirecrawlClientInterface` + adapter, breaker-wrapped and fail-soft, mirroring the Tavily adapter exactly | It does not exist (10.1); Shared is correct because it is cross-cutting, and the `Research/` folder already holds its sibling |
| R10.5 | Pass Firecrawl `maxAge` so repeat URLs inside one run serve from cache | 10.5 — a 48-point run will revisit authoritative pages |
| R10.6 | **Two research tiers**: one subject-level pass stored on the course and reused by every point, plus one focused pass per point | FR-13j; re-discovering the subject's basics 48 times is waste, but a per-point pass is what makes each script specific |
| R10.7 | Research findings are **untrusted content**, delimited exactly like the uploaded index | Retrieved pages are the textbook indirect prompt-injection vector (§4); FR-13h |
| R10.8 | Research calls counted and reported **separately** from writing calls | FR-13i — they are a different provider and a different cost curve |

---

## 8. Open risks carried into `plan.md`

| # | Risk | Severity | Mitigation |
| --- | --- | --- | --- |
| ~~RK-1~~ | ~~`ext-zip` may be absent from this PHP build~~ | — | **Closed in Phase 3** — `php -m` confirms `zip` and `fileinfo` are both enabled |
| RK-2 | `smalot/pdfparser` is under self-declared "limited maintenance" | Low | Isolated behind a module port (R1.2); swappable in one class |
| RK-3 | Two-stage generation raises per-video call count 3–4× | **Medium** | Exactly what DEC-2's call ceiling and pre-run estimate exist to bound; the estimate formula is a plan deliverable |
| RK-4 | Spanish-language output quality varies by provider and is untested here | Medium | The reviewer gate (FR-40) scores structure objectively; the author picks the provider and sees the scores |
| RK-5 | Reference-format fidelity is judged against only 3 exemplar scripts, one of which (39) is structurally older than 43/46 | Medium | Treat **43 and 46 as the format target** (they carry the `Continuidad:` field and a richer technical header); 39 is a voice reference only |
| RK-6 | A 48-video course's continuity summaries could still grow past a usable prompt for late videos | Low | A7/D7 already bounds context to immediate predecessors plus block position, consuming stored summaries rather than full scripts |

---

## 9. Traceability — every finding lands in the plan

| Research | Feeds |
| --- | --- |
| R1.1–R1.4 | Stack table (new dependency), ingestion adapter + port, FR-1/FR-7 error paths |
| R2.1–R2.6 | Run orchestration, job design, persistence of runs and per-video outcomes |
| R3.1–R3.5 | Agent design, two-stage pipeline, call-estimate formula, schema shape |
| R4.1–R4.5 | Security section, agent instruction design, Blade escaping test |
| R5.1–R5.3 | Deliverable rendering, template placement |
| R6.1–R6.2 | Bundle export task, pre-implementation check |
| RK-1…RK-6 | Plan risk register |

---

## 11. Reference-format analysis — revision 2 `[LOCAL]`

Added after the author confirmed (clarify Q6) that `GUIDE/MODULE-VIDEOS` is their manual Claude-web output and therefore the deliverable target. Revision 1 read the scripts for header and continuity only; this pass inventories **every** structural part.

### 11.1 Structural inventory

| Part (as written in the scripts) | 39 | 43 | 46 | Spec rev. 2 |
| --- | --- | --- | --- | --- |
| `INFORMACIÓN TÉCNICA` — Duración, Bloque, Formato, "Vídeo N de M del bloque" | ✅ | ✅ | ✅ | FR-26 |
| `OBJETIVOS DE APRENDIZAJE` | ✅ | ✅ | ✅ | FR-27 |
| `Continuidad:` at the top | ❌ (at the end) | ✅ | ✅ | FR-28 |
| Numbered sections with `(N minutos)` | ✅ | ✅ | ✅ | FR-29 |
| Sub-sections `2.1`, `2.2`, `4.1`, `4.2` | ✅ | — | — | FR-29 |
| Quoted verbatim narration `"…"` | ✅ | ✅ | ✅ | FR-30 `narration` |
| `PROMPT:` | ✅ | ✅ | ✅ | FR-30 `on_screen_prompt` |
| `ACCIONES EN PANTALLA:` | ✅ | ✅ | ✅ | FR-30 `on_screen_actions` |
| `MOSTRAR EN PANTALLA – … (leer en voz alta)` | ✅ | — | — | FR-30 `show_on_screen` (+ read-aloud, + practice block) |
| `TABLA EN PANTALLA` | ✅ | — | — | FR-30 `on_screen_table` |
| Incorrect vs. correct case | — | ✅ ("CASO INCORRECTO VS. CASO CORRECTO") | — | FR-30b `comparison` |
| `RESUMEN` bullets | ✅ | ✅ | ✅ | FR-33b |
| `PRÓXIMO VÍDEO: … (Vídeo N)` | ✅ | ✅ | ✅ | FR-33b |
| `NOTAS TÉCNICAS PARA LA GRABACIÓN` → Preparación previa / Durante la grabación / Conectores necesarios / Continuidad / Empresa ficticia | ✅ | ✅ | ✅ | FR-33b |
| `VERIFICACIÓN FINAL` checklist | ✅ | ✅ | ✅ | FR-33b |
| Footer `FIN DEL GUIÓN VÍDEO N Versión: 1.0 · Mes Año` | ✅ | ✅ | ✅ | renderer (from version + date) |

### 11.2 How scripts reference practice material

- Script 39: "Bloque de contexto previo escrito y listo para pegar — **Practica_Reunion_Guion_39.pdf, Bloque A**"; "Notas de reunión … — **Bloque B**"; "El documento de práctica adjunto contiene los dos bloques de material necesarios para las demos."
- Script 43: "Tener preparados los dos escenarios (incorrecto y correcto) **del documento de práctica**"; named `Practica_Seguridad_Guion_43`.
- The `Practica_*` files themselves are **not in the repository**.

**Finding R11.1:** references are by **document name + block label**, and blocks map to specific demo sections. That is sufficient to define a structured practice document (clarify D12) and a two-way integrity check (FR-36a): script → block exists; block → referenced.

### 11.3 How prompts are prepared

Script 39, Notas técnicas → Preparación previa: "Prompts de las cuatro fases preparados para copiar y pegar en secuencia sin pausas."

**Finding R11.2:** the presenter already works from a separate ordered prompt list. Because prompts are stored as typed segments (DEC-3), the sheet is a pure projection — ordered `on_screen_prompt` segments, each preceded by the practice block its section shows. No generation, no drift (FR-35c, SC-7).

### 11.4 Index notes

`pildoras_video_claude_usuarios.md` has a TOC plus a `DETALLE COMPLETO DE VÍDEOS` section whose bodies contain only brief fields. An author's own course will typically add free notes under each video (and preamble notes).

**Finding R11.3:** `MarkdownIndexParser::extractDetailBodies()` already isolates each video's body, and `briefField()` / `briefList()` consume the labelled parts. The residue — body minus consumed spans — is exactly the video's notes, so FR-4b is an extension of existing code, not a new parser. Text before the first video/after the TOC is course notes (FR-4c).

### 11.5 Output-size check for the expanded contract

Revision 1 sized sections at ~10–12k output tokens per 9-minute script (§3.3). The closing parts (summary, next video, recording notes, verification checklist) add roughly 600–1,000 tokens for script 39. Asking the outline call to produce them would push it past its budget and couple checkable structure to prose.

**Finding R11.4:** generate closing parts in **one additional call after the sections**, when the full section list (and therefore the checklist's "what must visibly happen") exists. The practice document is a further call only when warranted. Per-video AI calls: `1 outline + S sections + 1 closing + (1 practice)`.

### 11.6 Decisions carried to `plan.md`

| # | Decision |
| --- | --- |
| R11.1 | Practice document = labelled blocks; script segments carry `practice_block` labels; two-way integrity validator |
| R11.2 | Prompts sheet is a renderer over stored segments (`PromptsSheetRenderer`), never an agent |
| R11.3 | Notes = detail body minus consumed brief spans; course notes = unassigned text; parser extended, not replaced |
| R11.4 | Closing parts in a dedicated `GenerateScriptClosingAgent` call after sections; estimator formula updated |

---

## 12. Practice pack sample — revision 3 `[LOCAL]`

Source: `GUIDE/MODULE-VIDEOS/Propuestas_Logistica_Heliantia.md` (141 lines, 7,952 bytes) and its `.pdf`, supplied by the author as the reference for how they built practice material. It is the practice pack for **video 22 — "Integración con Google Workspace: documentos y Drive"** (block 4). The PDF could not be rasterised here (no poppler); the analysis uses the Markdown, which is a conversion of the PDF.

### 12.1 Structure

| Part | Sample text | Spec |
| --- | --- | --- |
| Header | `DOCUMENTO DE PRÁCTICA · VÍDEO 22 — Integración con Google Workspace: documentos y Drive` | FR-36 header |
| Files summary | `Archivos: Propuesta_Logistica_ProveedorA_2026.pdf y Propuesta_Logistica_ProveedorB_2026.pdf` | FR-36 files, FR-36a file name |
| Setup instruction | `Subir ambos documentos a Google Drive` | FR-36 setup |
| Usage | `Usar en: DEMO 2 (Sección 4 del guión) — Comparativa de propuestas` | FR-29a demo label, FR-36 usage |
| Bundling note | `Este PDF contiene las dos propuestas en un solo archivo para facilitar la descarga` | FR-47 one PDF + FR-36e separate files |
| Instructor note | `NOTA PARA EL INSTRUCTOR: … diferencias deliberadas en precio, cobertura y condiciones para que la tabla comparativa generada por Claude resulte informativa y no trivial. El Proveedor A es más económico pero tiene más restricciones de cobertura. El Proveedor B tiene mejor cobertura pero mayor precio y penalizaciones más estrictas. Ambas son deliberadamente imperfectas para favorecer una recomendación con matices.` | FR-36c, designed contrasts |
| Artifact 1 | `PROPUESTA DE SERVICIO LOGÍSTICO — PROVEEDOR A · TRANSPORTES MERIDIONAL S.L.` | FR-36a genre `proposal` |
| Artifact 2 | `PROPUESTA DE SERVICIO LOGÍSTICO — PROVEEDOR B · LOGÍSTICA PENINSULAR NORTE S.A.` | same |

### 12.2 Anatomy of an artifact (genre `proposal`)

Both artifacts share the same skeleton, which is what makes the demo's comparison table possible:

1. Title block — document type, issuer, subtitle, `Dirigida a`, `Referencia`, `Fecha`.
2. `1. PRESENTACIÓN` — years active, fleet, platforms, coverage, the client's stated volume (2.800 envíos/mes — **the same figure in both**, so they are comparable).
3. `2. CONDICIONES ECONÓMICAS` → `2.1 Tarifa base mensual` (table: Concepto · Precio unitario · Volumen estimado · Importe mensual, with a TOTAL row) + note; `2.2 Cobertura geográfica`.
4. `3. PENALIZACIONES POR INCIDENCIA` (table: Tipo de incidencia · Compensación · Condición).
5. `4. PLAZO DE PREAVISO Y CONDICIONES DE CANCELACIÓN`.
6. `5. CONDICIONES ADICIONALES` (bullets).
7. Contact line and legal footer (company, CIF, address, validity date).

**Finding R12.1 — artifacts in one pack share a skeleton when the demo compares them.** The writer must plan a common structure first and vary only the designed dimensions. Plan: the practice plan (outline call) declares a `shared_skeleton` per comparable group; each artifact call receives it.

### 12.3 Designed contrasts are concrete and checkable

| Dimension | Proveedor A | Proveedor B |
| --- | --- | --- |
| Monthly total | 28.856 € | 32.252 € |
| Coverage | 34 provinces own fleet; rest subcontracted +48–72 h, +35 % | all 50 + islands, own fleet |
| Minimum volume | 2.500 envíos or +0,80 €/envío | none |
| Delay penalty | 15 %, client must claim within 5 days | 25 % + 2 €/envío, automatic |
| Loss / damage cap | 300 € / 150 € | 600 € / 400 € |
| Term / notice | 12 months, 60 days, 2 months' fee | 6 months, 30 days |
| Tariff review | IPC + 1,5 % | IPC |

**Finding R12.2 — contrasts are declared by dimension with both values.** Store `designed_contrasts[] = {dimension, values_by_artifact{}, intended_effect}`. FR-36c becomes checkable: every declared dimension appears in the instructor note; the reviewer (when on) checks the values really appear in the artifacts.

### 12.4 Arithmetic is exact

A: 16.280 + 9.072 + 1.704 + 1.800 = **28.856** ✔ (2.200 × 7,40 = 16.280; 480 × 18,90 = 9.072; 120 × 14,20 = 1.704).
B: 19.580 + 10.320 + 2.016 + 336 = **32.252** ✔.

**Finding R12.3 — a comparison demo is ruined by a wrong total.** Tables carry an optional `total_row` flag; `TableArithmeticValidator` checks numeric columns (Spanish number format: `.` thousands, `,` decimals, `€`) within a rounding tolerance (FR-36d).

### 12.5 Conversion artefacts in the sample

The Markdown splits long cells across rows (`| Tarifa plataforma mensual (gestión y |` / `| handling) |`) and loses the first penalty table's header (`Tipo de incidencia | Compensación` survive only as an orphan `Condición`).

**Finding R12.4 — do not learn the format from the `.md` layout.** Store artifacts as structured blocks and render tables from rows (clarify D20).

### 12.6 Relationship to the scripts

- The sample names **files** and a **demo** (`DEMO 2`, `Sección 4`); script 39 names a **document + block** (`Practica_Reunion_Guion_39.pdf, Bloque A`). Both reduce to "reference = practice file + demo + section" once artifacts have file names; revision 2's "Bloque" label model is superseded.
- The client `Heliantia Group` ≠ `Tecnoform S.A.` (videos 38/39/43/46). The fiction has **several** organisations → bible registry (clarify D18).
- Suppliers and contacts are invented but realistic (clarify D19).

### 12.7 Size and call budget

Two artifacts ≈ 7,000 characters ≈ 2,000–2,500 output tokens, well inside one call each, but a pack with 3–4 artifacts (e.g. email thread + dataset + policy) in one call would crowd the structured-output ceiling (§3.3).

**Finding R12.5 — one call per artifact.** Practice generation = plan (inside the outline call: files, setup, usage, contrasts, shared skeleton, instructor note) + 1 call per artifact. Estimator: `practice_ratio × avg_artifacts_per_pack` (default 0.6 × 2).

### 12.8 Decisions carried to `plan.md`

| # | Decision |
| --- | --- |
| R12.1 | Practice plan declares files, setup, usage, designed contrasts, shared skeleton and instructor note; generated in the outline call |
| R12.2 | `GeneratePracticeArtifactAgent` — one call per artifact, receiving plan + skeleton + bible + demo context |
| R12.3 | Artifact content = typed blocks (heading, paragraph, list, table{header, rows, total_row}, key_values, footer) |
| R12.4 | Validators: `PracticeReferenceValidator` (files/demos two-way), `InstructorNoteCoverageValidator`, `TableArithmeticValidator` |
| R12.5 | Renderers: one practice PDF (artifact per page) + one Markdown/PDF per artifact under its file name |
| R12.6 | Bible organisations registry; artifacts' new organisations appended (FR-13k) |
