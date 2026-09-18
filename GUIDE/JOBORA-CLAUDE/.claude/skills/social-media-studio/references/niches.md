# Niches + language (canonical gate)

Use this list for **step 0**. Prefer a preset; allow **Custom** when the user
types a free-form niche (max 255 chars). Persist both `niche_name` and
`research_niche` (string used in Tavily queries).

## Niche presets

| # | `niche_name` | `research_niche` |
|---|--------------|------------------|
| 1 | Laravel & PHP backends | Laravel PHP backend development engineering |
| 2 | NestJS / Node backends | NestJS Node.js backend APIs microservices |
| 3 | Angular frontend | Angular frontend development TypeScript |
| 4 | AI integration | AI integration RAG agents Laravel AI developer tools |
| 5 | Custom | *(user-provided one-line niche)* |

Source shape mirrors Laravel `SuggestSocialMediaTopicsData.niche` (free string)
and the Argenis personal stack in
`SOCIAL-MEDIA/prompt-contenido-social-argenis-TOFU-BOFU-MOFU.md`.

## Language (mandatory)

| Code | Label | Output instruction |
|------|-------|--------------------|
| `es` | Español (LatAm neutro) | Write all content in Spanish (neutral Latin American). |
| `en` | English | Write all content in English. |
| `pt-PT` | Português (Portugal) | European Portuguese (Portugal). Use Portugal orthography (`telemóvel`, `equipa`, `utilizador`) — NOT Brazilian Portuguese. |

Never infer language. Ask if missing.

## Optional gates (ask once, store in cache)

**Business goal:** `awareness` | `engagement` | `viral` | `leads` | `sales` | `community`

**Brand voice:** `professional` | `conversational` | `trendy` | `inspirational` | `humorous`

Default if user skips: `business_goal=awareness`, `brand_voice=conversational`.
