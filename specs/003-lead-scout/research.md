# Investigación en tiempo real — 003-lead-scout

> Fase 3 · RESEARCH — Fecha: 2026-09-16
>
> ⚠️ **Estado de verificación:** la herramienta Tavily falló en las 4 primeras consultas con
> `HTTP 432 — "This request exceeds your plan's set usage limit"`. Según la regla de la skill,
> se usó la alternativa: **WebFetch sobre las páginas oficiales** y **WebSearch**.
> Los hallazgos de WebFetch sobre documentación oficial se consideran de confianza alta; los de
> WebSearch (resúmenes de terceros), de confianza media.
>
> **Pase de verificación con Tavily (16-09-2026, a petición del operador):** Tavily respondió a
> **2 peticiones** (R1 con `tavily_search` y R2 con `tavily_extract`), ambas **coincidentes** con
> la investigación previa. Las siguientes 7 volvieron a dar `432 — plan's set usage limit`. El
> resto de secciones sigue **sin verificar con Tavily** (marcado por sección).
>
> **6ª pasada (16-09-2026, tarde):** Tavily **volvió a responder** (9 búsquedas `advanced`/`basic`
> sin 432). Se añaden R12 (Supabase, vía Context7 sobre la documentación oficial), R13 (marco legal
> del scraping: LinkedIn, CAPTCHAs, anti-bots, verificado con Tavily) y R14 (hallazgos en el código
> de argenis-hub).
>
> **7ª pasada (17-09-2026):** R15 — verificación con Tavily y Firecrawl de proxies y caché de
> Firecrawl v2, propiedad y privacidad de Tavily, coste de WebSearch/WebFetch de Claude, estado de
> las EDPB 03/2026 y del DPF, y base legal de los datos públicos de empresa.

---

## R1 — Tavily: precios y créditos

- Free: **1.000 créditos/mes**, sin tarjeta. Pay-as-you-go: **$0,008/crédito**. Planes
  mensuales desde **$30 (4.000 créditos)** hasta **$500 (100.000 créditos)**, con un precio por
  crédito de $0,0075 a $0,005. Enterprise a medida.
- Coste por endpoint: search **basic 1 crédito**, **advanced 2 créditos**; extract basic 1
  crédito / 5 URLs (advanced 2 / 5); map 1 crédito / 10 páginas (2 con instrucciones); crawl =
  map + extract; research `mini` 4-110 créditos y `pro` 15-250 (dinámico). Las extracciones
  fallidas no se cobran.
- **Hallazgo operativo:** la cuota del plan conectado a esta sesión está agotada hoy (432).
- **Implicación para el plan:** usar `search_depth=basic` por defecto (el adaptador actual usa
  `advanced` → doble coste); no usar `research` en el pipeline; presupuesto + corte al agotar
  la cuota.
- ✅ **Verificado con Tavily (`tavily_search`, 16-09-2026, request `fbd777c2`)** sobre
  docs.tavily.com: basic = 1 crédito, advanced = 2; free 1.000/mes; PAYG $0,008. Planes completos:
  Researcher (free, 1.000) · **Project $30 / 4.000** · **Bootstrap $100 / 15.000** ·
  **Startup $220 / 38.000** · **Growth $500 / 100.000**. → El escenario D (15.000 créditos) cabe
  justo en Bootstrap ($100): coincide con la estimación de «~$75-110».
- Fuentes: https://www.tavily.com/pricing · https://docs.tavily.com/documentation/api-credits
  (WebFetch, oficial)

## R2 — Firecrawl: precios, créditos y concurrencia

- Free **1.000 créditos/mes**. Hobby **$16/mes (5.000)** · Standard **$83/mes (100.000)** ·
  Growth **$333/mes (500.000)** · Scale **$599/mes (1.000.000)** — precios con facturación
  anual.
- Concurrencia: Free 2 · Hobby 5 · Standard 25 · Growth 50 · Scale 100.
- Scrape **1 crédito/página** · Crawl 1/página · Search **2 créditos / 10 resultados** ·
  Monitor 1/página/check · Interact 2/minuto de navegador.
- **Los formatos JSON, Question y Highlight suman +4 créditos por página** (5 en total).
- La página no desglosa el coste de proxy stealth/enhanced → `[UNVERIFIED]`. **Resuelto en R15
  (17-09-2026):** `enhanced` cuesta ya **1 crédito**, igual que `basic`.
- **Implicación:** pedir markdown (1 crédito) y extraer con el LLM propio (~$0,02/empresa con
  Haiku) es más barato que el modo JSON (5 créditos ≈ $0,016-0,04 por página según plan).
  El adaptador actual apunta a `https://api.firecrawl.dev/v1`; la compatibilidad con la
  versión vigente de la API no está verificada → `[UNVERIFIED]`, revisar en el plan.
- Fuente: https://www.firecrawl.dev/pricing (WebFetch, oficial)
- ✅ **Verificado con Tavily (`tavily_extract`, 16-09-2026, request `73d92ccd`)**: 1.000
  créditos/mes gratis; scrape/crawl/map/monitor = 1 crédito/página; search = 2 créditos/10
  resultados; «Advanced features (JSON format, etc.) cost additional credits»; Growth $333/mes
  (anual, 500.000); Scale $599/mes (anual, 1.000.000). **Hallazgos nuevos:** Firecrawl **no tiene
  pago por uso** («We currently do not offer a pay-per-use plan») y **los créditos no se
  acumulan** en los planes self-serve. → Al agotar los 1.000 gratis, no se puede pagar solo lo
  que se usa: hay que subir a Hobby. Refuerza la escalera de coste (HTTP directo primero) y el
  corte por presupuesto. El precio mensual de Hobby **sin** facturación anual sigue sin verificar.

## R3 — Laravel AI SDK (`laravel/ai`) — estado para Laravel 13

- Documentado oficialmente en `laravel.com/docs/13.x/ai-sdk`. Capacidades relevantes:
  - **Agentes** (`make:agent`, `prompt()`, `stream()`, **`queue()`** con `then`/`catch`).
  - **Salida estructurada**: `HasStructuredOutput` + `schema(JsonSchema $schema)`.
  - **Tools** (`make:tool`, validación de argumentos, `@RepairToolCalls`, aprobación humana).
  - **Provider tools** `WebSearch` / `WebFetch` (los ejecuta el proveedor del modelo).
  - **Failover**: `#[Provider(Lab::OpenAI, Lab::Anthropic, ...)]`.
  - Atributos `#[Model]`, `#[MaxTokens]`, `#[Timeout]`, `#[UseCheapestModel]`,
    `#[CacheInstructions]`.
  - **Embeddings** + columna `vector` + `whereVectorSimilarTo`; **reranking** (Cohere, Jina,
    VoyageAI, Bedrock).
  - **Testing**: `AgentFake::shouldFake(...)`, `EmbeddingsFake`, `RerankingFake`.
  - **MCP client**: `Laravel\Mcp\Client::web(...)->tools()`.
- Proveedores de texto: OpenAI, Anthropic, Gemini, Azure, Bedrock, Groq, xAI, DeepSeek, Mistral,
  Ollama, OpenRouter, compatibles con OpenAI.
- Versión instalada en el proyecto: `laravel/ai ^0.11.0` (composer.json). Versión 0.x → la API
  puede cambiar entre minors.
- **Implicación:** encaja como capa de **extracción/clasificación** con esquema, en cola y
  testeable con fakes. No hace falta como orquestador de crawling. Tampoco sustituye a
  Tavily/Firecrawl: sus provider tools de búsqueda las cobra el proveedor del modelo y no dan
  control de caché ni de presupuesto por fuente.
- Fuente: https://laravel.com/docs/13.x/ai-sdk (WebFetch, oficial)

## R4 — Crawl4AI: estado de releases

- **0.9.3 (2026-08-31):** parche de seguridad con **5 vulnerabilidades de divulgación
  coordinada** (escritura arbitraria de archivos vía PDF CWE-22, SSRF por redirecciones PDF
  CWE-918, DoS por PDF sin límite CWE-400, XSS CWE-79, XSS en la UI del Playground de Docker) +
  33 bug fixes.
- **0.9.0 (2026-06-18):** servidor Docker «secure-by-default» con **breaking changes**: auth
  Bearer obligatoria (`CRAWL4AI_API_TOKEN`), bind a loopback por defecto, rechazo de
  `js_code`/`proxy`/`extra_args` en las peticiones, hooks declarativos.
- **0.8.9 (2026-06-04):** fix SSRF en la configuración de proxy.
- **Implicación:** el proyecto está activo y es potente, pero exige **Python + Docker +
  mantenimiento de seguridad** (3 parches de seguridad en 3 meses). El entorno local del
  proyecto es Laravel Herd nativo en Windows, **sin Docker** (regla ABSOLUTE del router). Solo
  compensa con un volumen alto medido.
- Fuente: https://raw.githubusercontent.com/unclecode/crawl4ai/main/CHANGELOG.md (WebFetch,
  oficial). *La página `/releases` devolvió años incorrectos en el resumen; se usó el
  CHANGELOG.*

## R5 — Fuentes de ofertas con acceso programático/público (coste 0)

| Fuente | Acceso | Notas |
|---|---|---|
| ITJobs.pt | API REST (`api.itjobs.pt/job/get.json`, JSON/XML, sandbox `api.sandbox.itjobs.pt`) | Requiere API key; términos por revisar `[UNVERIFIED]` |
| Landing.jobs | API pública (`landing.jobs/api/v1/companies/[id]/jobs.json`) | Repo de documentación en GitHub |
| Greenhouse | `boards-api.greenhouse.io/v1/boards/{slug}/jobs` | Público, sin auth |
| Lever | `api.lever.co/v0/postings/{company}?mode=json` | Público, sin auth |
| Ashby | `api.ashbyhq.com/posting-api/job-board/{slug}` | Público, sin auth |
| Remotive | `remotive.com/api/remote-jobs` + RSS | Pública |
| Arbeitnow | API paginada de 250 en 250 | Pública (resumen de terceros) |
| We Work Remotely | RSS por categoría | Pública (resumen de terceros) |
| LaraJobs | RSS `larajobs.com/feed` | Oficial del ecosistema Laravel |
| InfoJobs | API para desarrolladores | `[UNVERIFIED — no investigado hoy]` |

- Las ATS (Greenhouse/Lever/Ashby) requieren conocer el *slug* de cada empresa → útil para las
  empresas ya descubiertas, no para descubrirlas.
- Fuentes: https://www.itjobs.pt/api/docs · https://github.com/LandingJobs/LandingJobs-api ·
  https://dev.to/neverempty/greenhouse-lever-ashby-workable-4-job-apis-1-dangerous-bug-5b5h ·
  https://github.com/remotive-com/remote-jobs-api · https://remotive.com/remote-jobs/api ·
  https://larajobs.com/ (WebSearch + WebFetch)

## R6 — Volumen real de ofertas «laravel» (foto del 16-09-2026)

| Portal | Resultados | Detalle |
|---|---|---|
| ITJobs.pt | **4** | TBFiles (Porto, híbrido, 16-sep) · Eurotux junior (Braga, híbrido, 3-sep) · Neotalent Conclusion PHP Software Engineer (**remoto**, 10-sep) · Shopkit prácticas (Castelo Branco, presencial, 7-sep) |
| Tecnoempleo | **10** (7 de sept. 2026, 3 antiguas de 2023-2024) | Barcelona híbrido ×3 · IN MANAS remoto €39-42k · cdmon €48-72k · HAYS Girona €30-42k · **Michael Page «PHP Freelance Developer (remoto Spain)», contrato temporal** |
| LaraJobs | ~10 recientes | Mayoría EE. UU./full-time; 8 remotas; **1 contractor**; CRAE Group (Chipre) €100k |

- **Limitación:** es una foto de un día con un único término de búsqueda; no mide duplicados
  ni ofertas que mencionan Laravel solo en la descripción. Sirve para dimensionar el orden de
  magnitud, no como serie.
- Fuentes: https://www.itjobs.pt/emprego?q=laravel ·
  https://www.tecnoempleo.com/busqueda-empleo.php?te=laravel · https://larajobs.com/ (WebFetch)

## R7 — Tarifas y benchmarks de respuesta en frío

- **Tarifas Portugal 2026 (Lemon.io):** desarrolladores de software $29-53/h; **mid $29-35/h**,
  **senior contractor $40-45/h**, lead $44-53/h. España a la par con Portugal en senior
  (resúmenes de Arc.dev, Acquaint, Halsoft). Confianza media.
- **Cold email B2B 2026:** reply medio **3,43 %** (5,1 % en 2024); rango 3,43-5,8 % según el
  dataset; **respuesta positiva «fuerte» 1,5-3 %**, top 4-5 %; campañas con ~100 prospectos
  investigados y copy personalizado **hasta 10-18 %**. Confianza media (proveedores de
  herramientas de email, con sesgo comercial).
- Fuentes: https://lemon.io/rate-calculator/portugal/ ·
  https://arc.dev/en-es/hire-developers/laravel ·
  https://woodpecker.co/blog/cold-email-statistics/ ·
  https://snov.io/blog/cold-email-statistics/ ·
  https://martal.ca/b2b-cold-email-statistics-lb/ ·
  https://www.cleanlist.ai/blog/2026-02-18-cold-email-response-rate-statistics ·
  https://leadhaste.com/blog/cold-email-reply-rate-benchmarks-2026 (WebSearch)

## R8 — Marco legal y fiscal

### R8.1 Comunicaciones comerciales electrónicas
- **España — LSSI-CE art. 21:** prohíbe el email comercial no solicitado ni autorizado
  expresamente, **incluidas las personas jurídicas**. Se describe como uno de los regímenes más
  estrictos de la UE; en la práctica exige consentimiento previo para B2B fuera de una relación
  comercial previa. La AEPD sanciona activamente.
- **Portugal — DL 7/2004 art. 22:** opt-in para personas singulares y **opt-out para personas
  colectivas**; el remitente debe mantener una lista de oposición y no escribir a quien figure
  en ella.
- **Portugal — Lei 41/2004 arts. 13.º-A/13.º-B** (republicada por la Lei 46/2012) + **CNPD
  Diretriz/2022/1**, punto 2 (leído con Firecrawl el 16-09-2026): la directriz **no aborda** el
  marketing a personas colectivas, y para personas singulares **sin relación previa** exige
  «consentimiento prévio e expresso». → El email **nominativo** de un decisor es **zona gris**;
  ver `NORMATIVA-RGPD.md` §4 (corrige la lectura inicial de Q16).
- **Interpretación (no es asesoría legal) `[UNVERIFIED]`:** responder a una oferta de empleo o
  contrato publicada (candidatura solicitada), usar el formulario de contacto de la empresa o un
  mensaje en una red profesional no son equivalentes al email comercial masivo, pero conviene
  validarlo.
- Fuentes: https://www.dlapiperdataprotection.com/?t=electronic-marketing&c=ES ·
  https://cms.law/en/int/expert-guides/cms-expert-guide-to-data-protection-and-cyber-security-laws/spain ·
  https://diariodarepublica.pt/dr/detalhe/decreto-lei/7-2004-240775 ·
  https://ffms.pt/pt-pt/direitos-e-deveres/e-permitido-o-contacto-telefonico-ou-envio-de-sms-e-email-para-marketing-directo-nao-solicitado
  (WebSearch)

### R8.2 Recibos verdes 2026
- Seguridad Social: **21,4 % sobre el 70 %** del rendimiento relevante, declaración trimestral;
  **IAS 2026 = €537,13**; exención los primeros 12 meses de actividad (no aplica: llevas 3
  años).
- IVA: exención del art. 53 CIVA hasta **€15.000** de facturación anual.
- Retención de IRS: **23 %** (verificado en R11: Lei 45-A/2024, vigente en 2026). Solo aplica
  cuando el pagador tiene contabilidad organizada en Portugal; los clientes de España u otros
  países no retienen IRS portugués.
- Coeficiente del régimen simplificado (0,75) e IVA en servicios B2B a clientes de otros países
  de la UE (autoliquidación/VIES): **no confirmados en esta investigación** `[UNVERIFIED —
  confirmar con contabilista]`.
- Fuentes: https://4gnews.pt/recibos-verdes-em-2026-guia-para-a-seguranca-social/ ·
  https://www.omeusalarioliquido.pt/2026/02/09/recibos-verdes-2026-o-guia-essencial-para-trabalhadores-independentes/
  (WebSearch + WebFetch)

## R9 — Precios de LLM (para el coste de extracción/clasificación)

| Modelo | Input $/MTok | Output $/MTok | Notas |
|---|---|---|---|
| Claude Haiku 4.5 (`claude-haiku-4-5`) | $1,00 | $5,00 | Contexto de 200K |
| Claude Sonnet 5 (`claude-sonnet-5`) | $2,00 | $10,00 | Contexto de 1M |
| Gemini 3.7 Flash | **$0,75** (hasta el 31-12-2026) → **$1,50** desde el 01-01-2027 | **$3,75** → **$7,50** desde el 01-01-2027 | Contexto de 1M, salida máx. 65.536 · Batch/Flex −50 % · caché de lectura $0,075 |

- Batch API: −50 %. Lectura de caché ≈ 0,1× input.
- **Coste por empresa** (~12k tokens de entrada + 1,5k de salida):

  | Modelo | Por empresa | 150 empresas/mes |
  |---|---|---|
  | Gemini 3.7 Flash (2026) | ~$0,015 | ~$2,3 |
  | Gemini 3.7 Flash (2027) | ~$0,029 | ~$4,4 |
  | Haiku 4.5 | ~$0,02 | ~$3 |
  | Sonnet 5 | ~$0,039 | ~$5,9 |

- **Coste por borrador** (primera frase: ~2k tokens de entrada + 150 de salida): Sonnet 5
  ≈ $0,0055 → 300 borradores/mes ≈ $1,7.
- Precios de OpenAI no investigados → `[UNVERIFIED]`.
- **Hallazgo en el código:** `Shared\Infrastructure\AI\AIClientInterface::generateStructured(
  $agentClass, $prompt, ?$provider, ?$model, ?$timeoutSeconds)` ya permite elegir proveedor y
  modelo en tiempo de ejecución, y Campaigns valida `provider` con
  `Rule::in(['openai','anthropic','gemini'])`; `config/course-scripts.php` expone
  `providers.selectable_writers`. → El selector de IA de LeadScout reutiliza ese patrón y no
  necesita una API nueva de `laravel/ai`.
- Fuentes: tabla de modelos de la skill `claude-api` (caché oficial 2026-06-24) ·
  https://openrouter.ai/google/gemini-3.7-flash ·
  https://artificialanalysis.ai/models/gemini-3-7-flash/providers ·
  https://www.morphllm.com/gemini-api-pricing · https://benchlm.ai/google/api-pricing
  (WebSearch; confianza media, agregadores de terceros — **confirmar en la página oficial de
  precios de Google antes de fijar el presupuesto**).

---

## R10 — RGPD y datos públicos (resumen; detalle en `NORMATIVA-RGPD.md`)

- Los datos públicos siguen siendo datos personales; hacerlos visibles no es base legal (EDPB
  Guidelines 03/2026, 07-07-2026, en consulta hasta el 30-10-2026).
- Interés legítimo con test de 3 pasos documentado (EDPB Guidelines 1/2024).
- **Art. 14**: informar directamente, como máximo en la primera comunicación; un aviso web no
  basta y el coste no justifica omitirlo (UODO vs Bisnode, 2019).
- Art. 30: la exención de < 250 empleados no aplica a tratamientos no ocasionales.
- España: LOPDGDD art. 19 no ampara marketing/prospección; LSSI art. 21 exige consentimiento.
- Bases de datos de empleo: TJUE C-762/19 (derecho sui generis) → APIs/RSS oficiales, sin
  republicar.
- EU-US DPF vigente en 2026 (Tribunal General, sept. 2025); recurso C-703/25 P pendiente.
- Fuentes: listadas en `NORMATIVA-RGPD.md` §9 (WebSearch/WebFetch/Firecrawl; **no verificadas con
  Tavily**).

## R11 — Registro del pase de verificación (16-09-2026)

Tavily solo aceptó 2 peticiones (límite de uso de la cuenta). Por decisión del operador, el resto
se verificó con **Firecrawl** (modo `query` con citas literales, ~69 créditos del plan gratuito)
y **WebFetch/WebSearch**, priorizando **fuentes primarias**.

| Afirmación del análisis | Herramienta | Fuente | Resultado |
|---|---|---|---|
| Tavily: basic 1 crédito, free 1.000/mes, PAYG $0,008 | **Tavily search** | docs.tavily.com | ✅ Coincide · ➕ planes Bootstrap $100/15k y Startup $220/38k |
| Firecrawl: 1 crédito/página, extras con JSON, free 1.000 | **Tavily extract** | firecrawl.dev/pricing | ✅ Coincide · ➕ **sin pago por uso** y **sin acumulación de créditos** |
| Gemini 3.7 Flash $0,75/$3,75 → $1,50/$7,50 el 01-01-2027 | Firecrawl | ai.google.dev (oficial) | ✅ Coincide (caché $0,075 → $0,15) |
| LSSI art. 21: sin email comercial no solicitado | Firecrawl | BOE-A-2002-13758 | ✅ Coincide · ➕ **21.2: cada comunicación debe incluir una dirección de email válida para oponerse** |
| LOPDGDD art. 19: presunción solo para mantener relaciones con la persona jurídica | Firecrawl | BOE-A-2018-16673 | ✅ Coincide (texto literal) |
| Lei 41/2004: consentimiento para personas singulares; régimen propio para colectivas | Firecrawl | Parlamento (texto del art. 13.º-B) | ✅ Coincide · ➕ **13.º-B n.º 2 y 5: la DGC mantiene una lista nacional de personas colectivas que se oponen, actualizada cada trimestre, y es obligatorio consultarla** |
| CNPD Diretriz/2022/1 no cubre personas colectivas | Firecrawl (pase anterior) | cnpd.pt | ✅ Coincide |
| RGPD art. 14.3: máx. 1 mes o en la primera comunicación | WebFetch | gdpr-info.eu | ✅ Coincide (texto literal); la excepción del 14.5.b se orienta a archivo/investigación/estadística |
| Bisnode: multa de 220.000 € por no informar (art. 14) | WebSearch | IAPP, UODO | ⚠️ **Matizado:** el tribunal de Varsovia **anuló la multa** (dic. 2019) pero **confirmó** que un aviso en la web no basta; el Tribunal Supremo Administrativo desestimó la casación de Bisnode |
| LinkedIn prohíbe el scraping | WebSearch | Resúmenes que citan la User Agreement §8.2 (la página bloqueó a Firecrawl) | ✅ Coincide (confianza media: cita secundaria) |
| Retención IRS 23 % o 25 % | WebSearch | Doutor Finanças, CGD, Santander, DECO | ✅ **Resuelto: 23 %** (Lei 45-A/2024, OE2025, se mantiene en 2026) para actividades del art. 151 pagadas por entidades con contabilidad organizada en PT |
| Tarifas PT: mid $29-35/h, senior $40-45/h | WebFetch | lemon.io | ✅ Coincide |
| Cold email: 3,43 % de reply medio; hasta 18 % con personalización | WebFetch | woodpecker.co (20M+ emails, actualizado 23-06-2026) | ✅ Coincide · ➕ < 50 destinatarios = 5,8 % vs 2,1 % con 1.000+; el 42 % de las respuestas llega en seguimientos; 1 seguimiento = +66 % |
| Remotive API pública | Firecrawl | github.com/remotive-com | ✅ Existe · ➕ **términos:** no enviar sus ofertas a otros portales, **enlazar y citar a Remotive**, retraso de 24 h, **máx. recomendado 4 peticiones/día**, bloqueo si > 2/min |
| Arbeitnow API | WebFetch | arbeitnow.com | ✅ Gratis y sin key; UE/UK; ofertas de ATS (Greenhouse, SmartRecruiters, Recruitee…) |
| Landing.jobs API | WebFetch | GitHub LandingJobs-api | ⚠️ Documentación con ejemplos de **2015**: el endpoint `/api/v1/jobs` puede estar obsoleto → comprobar en T022 antes de construir |
| ITJobs API: key y términos | Firecrawl | itjobs.pt/api/docs | ❓ La página no devolvió contenido legible → sigue **sin verificar** (D4/OP-5) |
| EDPB 03/2026, laravel/ai, Crawl4AI, DPF | — | Pase anterior (WebFetch oficial / WebSearch) | Sin re-verificar en este pase |

## R12 — Supabase como base de datos (16-09-2026)

Verificado con **Context7** sobre el repositorio oficial `supabase/supabase` (documentación y
código de precios). Confianza alta.

| Tema | Hallazgo | Implicación |
|---|---|---|
| Conexión | Directa `db.[ref].supabase.co:5432` = **solo IPv6** (salvo add-on IPv4). Pooler compartido `aws-[INDEX]-[REGION].pooler.supabase.com`: **5432 = modo sesión**, **6543 = modo transacción**, ambos IPv4; usuario `postgres.[PROJECT-REF]`; el host se copia del diálogo *Connect*. El modo transacción exige desactivar prepared statements | Laravel por el pooler en **modo sesión**; transacción solo con `PDO::ATTR_EMULATE_PREPARES` y nunca para migraciones |
| Exposición | «A table in an exposed schema without RLS is readable and writable by any role with a grant on it. Enable RLS on every table in an exposed schema»; el esquema `public` se expone por defecto por la Data API | `ENABLE ROW LEVEL SECURITY` + `REVOKE` a `anon`/`authenticated` en todas las `scout_*`; desactivar la Data API si no se usa |
| Plan Free | 500 MB de base de datos por proyecto · pausa tras 1 semana de inactividad · **sin backups automáticos** · límite de 2 proyectos activos | Poda de markdown; `pg_dump` semanal; el límite se comparte con todo argenis-hub |
| Plan Pro | 8 GB incluidos · backups de 7 días · sin pausa | T077 no aplica |
| RGPD | Alojamiento regional (p. ej. Frankfurt), DPA y evaluaciones de transferencia disponibles | Región UE + Supabase en RoPA/DPA (OP-10, OP-12) |

- Fuentes: `supabase/supabase` → `apps/docs/content/guides/database/connecting-to-postgres.mdx`,
  `…/troubleshooting/supavisor-and-connection-terminology-explained`,
  `…/guides/database/postgres/row-level-security.mdx`, `packages/shared-data/plans.ts`,
  `packages/shared-data/pricing.ts`, `…/guides/platform/free-project-pausing.mdx`,
  `apps/www/_blog/2025-05-17-simplify-backend-with-data-api.mdx` (Context7).

## R13 — Marco legal del scraping: LinkedIn, CAPTCHAs, anti-bots y datos públicos (Tavily, sept. 2026)

> **No es asesoría legal.** Resumen de fuentes para decidir el diseño; la validación final es OP-15.

| Tema | Hallazgo verificado | Fuente (Tavily, 16-09-2026) | Consecuencia en LeadScout |
|---|---|---|---|
| **EDPB Guidelines 03/2026** | Adoptadas el 07-07-2026, en consulta hasta el **30-10-2026**. Su ámbito es el scraping **para entrenar IA generativa**, pero sus principios valen por analogía: el consentimiento no es viable a escala; el interés legítimo exige el test de 3 pasos; **que los datos sean públicos no equivale a consentimiento**; **la ausencia de `robots.txt` tampoco**; la exención del art. 14.5.b se evalúa caso a caso | edpb.europa.eu (PDF de las Guidelines) · Hogan Lovells (JD Supra) · Reed Smith · Slaughter and May | LeadScout no entrena modelos; aun así se aplican minimización, LIA, transparencia (FR-26) y OP-14 |
| **LinkedIn — términos** | El User Agreement prohíbe el scraping, el crawling y la recogida automatizada sin permiso escrito, y las cuentas falsas | Resúmenes de 2026 que citan el User Agreement (linkedrent.com, pin.com) — confianza media | Ninguna petición a linkedin.com; solo la URL si la web de la agencia la enlaza |
| **LinkedIn — litigios** | *hiQ v. LinkedIn* (EE. UU.): el scraping de datos públicos no infringió la CFAA, **pero sí el contrato** (ToS). *LinkedIn v. Nubela/Proxycurl* (N.D. Cal. 3:25-cv-00828, enero 2025): cierre de Proxycurl el 04-07-2025 y sentencia registrada el 25-07-2025. *LinkedIn v. ProAPIs* (N.D. Cal., octubre 2025): más de un millón de cuentas falsas; acuerdo de principio en febrero de 2026 | Bloomberg Law · The Record vía pin.com · leadsforlinked.com (docket) | Las demandas apuntan a cuentas falsas y a datos tras login; el riesgo contractual existe incluso con datos «públicos» |
| **UE — contactos extraídos de LinkedIn** | **CNIL, KASPR, 240.000 €** (dic. 2024, en cooperación con las demás autoridades de la UE): recogía datos de contacto que los usuarios habían restringido, los conservaba demasiado tiempo, no informaba a las personas y respondía mal a las solicitudes de acceso | cnil.fr · edpb.europa.eu | Confirma FR-24 a FR-29: solo datos publicados por la empresa, 30 d / 12 m, aviso del art. 14, derechos atendidos |
| **España — acceso saltando protecciones** | **Código Penal art. 197 bis.1**: quien, «vulnerando las medidas de seguridad establecidas para impedirlo, y sin estar debidamente autorizado», accede o se mantiene en un sistema de información → **prisión de 6 meses a 2 años**. El elemento clave es **vulnerar medidas de seguridad** | Iberley (revisión 23-02-2026) · Alonso Sala Abogados | No hay jurisprudencia localizada que califique un CAPTCHA o un anti-bot como «medida de seguridad» `[UNVERIFIED]` → se adopta la lectura conservadora: **no se sortean** |
| **Portugal — acceso ilegítimo** | **Lei 109/2009 (Cibercrime) art. 6**: acceso sin autorización → prisión hasta 1 año o multa; **n.º 3: hasta 3 años si se consigue violando reglas de seguridad**; **n.º 2: misma pena para quien produzca o distribuya programas destinados a ese acceso**; procedimiento dependiente de queja en los n.º 1, 3 y 5 | pgdlisboa.pt · Diário da República (texto de 2009) | El módulo no puede contener funciones de evasión (también importa si el código se publica) |
| **UE — marco común** | Directiva 2013/40/UE: los Estados deben tipificar el acceso intencionado «sin derecho» a sistemas de información y las herramientas para ello | Lexology · análisis doctrinales | Coherente con A11 |
| **Guías prácticas 2026** | Legal en general: visitar páginas públicas por HTTP, API oficial, límites de peticiones, `robots.txt`. Riesgo: sortear CAPTCHAs, logins o bloqueos, falsear User-Agent/cookies, emular dispositivos, sobrecargar el sitio | Octo Browser Blog (fuente comercial, confianza media) | User-Agent honesto, sin emulación, rate limiting |
| **España — email comercial** | LSSI art. 21.1 sigue vigente en 2026 (prohíbe el email comercial no solicitado ni autorizado); guías de 2026 citan sanciones leves hasta 30.000 € y graves de 30.001 a 150.000 € | cardeseo.com · overloop.com (secundarias, confianza media) | Confirma Q4: sin email en frío en España |
| **Firecrawl — proxies** | Parámetro `proxy` con **valor por defecto `auto`**: «retry scraping with **enhanced** proxies if the basic proxy fails»; `enhanced` = «for scraping sites with **advanced anti-bot solutions**». La página de Enhanced Mode indica que el parámetro está *deprecated* y recomienda `auto`. «By default, Firecrawl routes all requests through proxies» | docs.firecrawl.dev (API reference scrape, Enhanced Mode, Proxies) | Cliente propio con `proxy: "basic"` y sin escalar tras un bloqueo; vigilar la deprecación (plan §9) |

## R14 — Hallazgos en el código de argenis-hub (`C:\Users\Lenovo\Herd\argenis-hub`, 16-09-2026)

| Hallazgo | Evidencia | Impacto |
|---|---|---|
| Tabla **`cvs`** (módulo `Cvs`): `uuid`, `user_id`, `title`, `niche` (fullstack\|other), `is_primary`, `file_path`, `file_type` (pdf\|md), `original_filename`, **`raw_text` longText nullable**, soft deletes | `database/migrations/2026_07_25_000100_create_cvs_table.php` | Fuente del perfil (A9). La migración anuncia «AI/RAG/jobs tables land in Module 2 later» |
| **CV de origen confirmado: `Argenis_Gonzalez_CV_2026.md`** (11,5 KB, en inglés, «ATS export»; idéntico por md5 a `GUIDE/LeadScout-MODULE/` de argenis-hub, a la copia de esta carpeta de docs y a la de `JOBORA-CLAUDE`; la de `JOBORA` es otra versión). Estructura: `## **PROFESSIONAL SUMMARY**`, `**TECHNICAL SKILLS**` (líneas `**Etiqueta:** a · b · c`), `**WORK EXPERIENCE**` (`### **Puesto - Empresa**` + línea de lugar y fechas), `**EDUCATION**`, `**COURSES DELIVERED**`, `**PROJECTS**` (Vidula, AquaShield, Servispin, Tutorial Cleanup API, con URL), `**LANGUAGES**`. Contiene teléfono, email y redes en la cabecera. Confirma PHPUnit (no Pest), Supabase, Cloudflare, Railway, React/Astro; **no** menciona AWS ni «recibos verdes»; incluye un CRM legacy en PHP sin framework (Restoration Control, 2022-2024) | Lectura del archivo (16-09-2026) | Parser determinista por secciones (T017); fixture anonimizado; prueba para la variante `legacy_maintenance` |
| 51 tablas creadas por las migraciones de argenis-hub (sin contar las de permisos) | `database/migrations/*.php` | Las 22 de LeadScout (A15) se suman a esas → ~73 en total |
| `CvData` excluye a propósito `id`, `user_id` y **`raw_text`** («the most sensitive PII the module holds») | `src/Modules/Cvs/Application/DTOs/CvData.php` | LeadScout no debe exponerlo ni copiarlo (FR-35) |
| `CvRepositoryPort` no tiene «CV principal del usuario» (solo `paginate`, `findByUuidForUser`, create/update/delete/restore) | `src/Modules/Cvs/Domain/Ports/CvRepositoryPort.php` | `CvSourcePort` propio de LeadScout en solo lectura (T017) |
| `FirecrawlScrapeAdapter` envía `formats: ["markdown"]` y `onlyMainContent`, **sin `proxy`** → Firecrawl aplica `auto` | `src/Shared/Infrastructure/Research/FirecrawlScrapeAdapter.php` | Cliente propio con `proxy: "basic"` (T039) |
| `FirecrawlClientInterface` solo expone `scrape(string $url)` | `src/Shared/Infrastructure/Research/FirecrawlClientInterface.php` | Sin búsqueda de respaldo (T042) |
| `TavilyClientInterface::search(array $queries, ?string $timeRange = null): array` | `src/Shared/Infrastructure/Research/TavilyClientInterface.php` | Sin `exclude_domains` → filtrar resultados con la denylist |
| `phpunit.xml`: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` | `phpunit.xml` | Hoy la suite no toca Supabase; grupo `pgsql` para lo específico de PostgreSQL (T003) |
| `composer.json`: `laravel/framework ^13.17`, `laravel/ai ^0.11.0` | `composer.json` | Coincide con el plan §2 |
| El proyecto `VIDULA` tiene la misma migración de `cvs` y un módulo `AiResumeStudio` (`job_search_configs`, `job_matches`, `outreach_drafts`) con otra búsqueda de empleo vía Tavily | `PROYECTOS/VIDULA/database/migrations/2026_07_27_000100_create_resume_studio_tables.php` | Solapa con LeadScout en otra app; no se reutiliza en esta spec (el destino es argenis-hub) |

## R15 — Verificación del 17-09-2026 (Tavily + Firecrawl, 7ª pasada, `clarify.md` A16)

Tavily respondió sin 432 en todas las consultas. Firecrawl, ~14 créditos.

| Tema | Hallazgo verificado | Herramienta · fuente | Consecuencia |
|---|---|---|---|
| **Firecrawl — proxy (API v2)** | `proxy` por defecto **`auto`** en `/v2/scrape` (en `/v1` el defecto era `basic`); el parámetro figura como **deprecated** («We recommend `auto`»); **`enhanced` cuesta ya lo mismo que `basic` (1 crédito)**: el coste ya no frena la escalada | Tavily search · docs.firecrawl.dev (API reference v1 y v2, Enhanced Mode) | La única barrera es el código: `proxy: "basic"` explícito + comprobar la respuesta |
| **Firecrawl — `metadata.proxyUsed`** | Cada respuesta de scrape incluye `proxyUsed` (p. ej. `"basic"`), `cacheState` (`hit`/`miss`) y `cachedAt` | Firecrawl scrape (respuestas reales de gdpr-info.eu y boe.es) | `FirecrawlPageFetcher` verifica `proxyUsed == "basic"`; si no → descarta el contenido, registra `proxy_mismatch` y desactiva Firecrawl en LeadScout |
| **Firecrawl — caché/índice** | `storeInCache` por defecto `true`: «the page will be stored in the Firecrawl index and cache. Setting this to false is useful if your scraping activity may have data protection concerns». `maxAge` por defecto 2 días. `zeroDataRetention` y `lockdown` existen; ZDR es opción **Enterprise** | Firecrawl search · docs.firecrawl.dev (scrape, fast-scraping, lockdown) · firecrawl.dev/enterprise | `storeInCache: false` en todas las peticiones de LeadScout (páginas con nombres) |
| **Firecrawl — cumplimiento** | SOC 2 Type II, RGPD y DPA disponibles; ZDR y SLA en Enterprise | firecrawl.dev (alternatives/bright-data, enterprise) | OP-12 |
| **Firecrawl — versión de la API** | La documentación vigente es `/v2`; `config/services.php:83` apunta a `https://api.firecrawl.dev/v1` (adaptador compartido de CourseScripts) | Código + docs | El cliente de LeadScout usa `/v2` sin tocar el compartido |
| **Tavily — propiedad** | **Nebius** (NASDAQ: NBIS, Ámsterdam) anunció la compra el 10-02-2026 (~$275-400 M según la prensa); Tavily sigue con su marca y clientes | Tavily search · nebius.com, PYMNTS, Calcalist | RoPA/DPA: registrar el cambio de grupo |
| **Tavily — privacidad** | Responsable: AlphaAI Technologies Inc. (Nueva York). «We may use certain portions of your query data to improve our responses» (interés legítimo, con derecho de oposición) | tavily.com/privacy | **Nunca nombres de personas en las consultas** (FR-25) |
| **Tavily — seguridad** | SOC 2 Type II (informe de 17-06-2026) + ISO/IEC 27001:2022; lista de subencargados (dic. 2025) en trust.tavily.com | Tavily search · trust.tavily.com | OP-12: pedir DPA e informe |
| **Tavily — parámetros** | `exclude_domains` (hasta 150), `include_domains`, `country` (p. ej. `portugal`, `spain`; solo `topic=general`), `search_depth` `basic`/`advanced`/`fast`/`ultra-fast` (`fast` y `ultra-fast` = 1 crédito, beta desde dic. 2025); `auto_parameters` puede subir a `advanced` (2 créditos) | docs.tavily.com (changelog, best practices, integraciones) | La denylist va en `exclude_domains` (no gasta resultados); `country` por ola; `auto_parameters` desactivado |
| **Claude WebSearch / WebFetch como pieza del pipeline** | Web search: **$10 / 1.000 búsquedas** + tokens; web fetch: sin cargo extra, pero el contenido entra como tokens de entrada | Tavily search · platform.claude.com (pricing, web search tool) | **No se usan en el pipeline:** decide el modelo qué leer (rompe FR-10), sin `robots.txt` ni parada por bloqueo propios (FR-13) y el contenido con nombres llega a la IA (FR-25). Útiles solo en desarrollo e investigación |
| **EDPB Guidelines 03/2026** | **Borrador** adoptado el 07-07-2026, consulta hasta el 30-10-2026; salvaguardas: criterios de recogida acotados, exclusión de fuentes, **respeto de medidas anti-scraping**, filtrado, avisos públicos, oposición | Tavily search · Clark Hill, Mondaq, Gibson Dunn | Corrige «adoptadas» → «borrador»; el diseño ya las cumple |
| **EU-US DPF** | El 31-07-2026 la presidenta del EDPB pidió a la Comisión evaluar el efecto de *Trump v. Slaughter* (independencia de la FTC) sobre la decisión de adecuación; no pide suspenderla | Tavily search · Pearl Cohen | DPF **y** cláusulas tipo en cada DPA (OP-12) |
| **Datos de empresa** | Considerando 14 RGPD: el Reglamento no cubre los datos de personas jurídicas (nombre, forma y datos de contacto). LSSI art. 10.1: denominación social, domicilio, email y datos registrales de publicación obligatoria. DL 7/2004 art. 10.º: registros públicos y NIF | Firecrawl scrape (gdpr-info.eu, BOE con cita literal) · Firecrawl search (Diário da República) | Allowlist de `NORMATIVA-RGPD.md` §11 (FR-38); autónomo = persona física (FR-39) |

## Contradicciones con supuestos previos (a llevar al plan como decisiones abiertas)

1. **Email en frío a agencias españolas** (`EMAIL-OUTREACH-AGENCIAS.md`) ↔ LSSI art. 21 (R8.1).
2. **Crawl4AI como fallback desde el día 1** (prompt maestro) ↔ entorno sin Docker +
   mantenimiento de seguridad (R4).
3. **Proxies residenciales** (`PROXIES.md`) ↔ restricciones 14-15 del prompt maestro y ToS.
4. **«Tavily + Firecrawl solo permiten 30 búsquedas diarias»** (otra IA, citado en `PROXIES.md`):
   parcialmente cierto para los free tiers (1.000 créditos/mes ≈ 33/día), falso como límite
   del producto (hay PAYG y planes) (R1, R2).
5. **El adaptador Tavily usa `advanced` por defecto** → consume el doble de créditos (R1).
6. **Tasas de respuesta del 5-20 %** en los docs ↔ benchmarks de 2026: 3,4 % de reply medio y
   1,5-5 % positivo (R7).
7. **«Firecrawl = extracción respetuosa»** ↔ su valor por defecto `proxy: auto` escala a proxies
   para anti-bots avanzados, y el adaptador compartido no lo cambia (R13, R14) → A11.
8. **«MVP solo con ofertas como señal» (Q1)** ↔ volumen real de ofertas (R6) → A1.
9. **«Tests en PostgreSQL local» (plan inicial de la 6ª pasada)** ↔ `phpunit.xml` en sqlite
   `:memory:` (R14) → suite en sqlite + grupo `pgsql`.
10. **RAG sobre el CV** (sugerido por el operador y por el comentario de la migración `cvs`) ↔ un
    único documento corto y FR-10 (determinismo) → A10, sin RAG en el MVP.
11. **«`enhanced` cuesta 5 créditos, así que `auto` es caro»** ↔ hoy cuesta 1 crédito, igual que
    `basic` (R15) → el coste ya no protege; solo el código (`proxy: "basic"` + `proxyUsed`) → A16.
12. **«Firecrawl no guarda lo que leemos»** (supuesto implícito) ↔ `storeInCache: true` por defecto
    guarda la página en su índice compartido (R15) → `storeInCache: false` → A16.
13. **«EDPB 03/2026 adoptadas»** ↔ son un borrador en consulta (R15) → corregido en `NORMATIVA-RGPD.md`.
14. **«Filtrar la denylist después de buscar»** (T042) ↔ Tavily acepta `exclude_domains` (R15) → la
    denylist va en la petición y el filtro local queda como segunda barrera.
15. **«Solo se ocultan los nombres de decisores antes de la IA»** ↔ testimonios, autores y firmas
    también contienen nombres → `PersonalDataScrubber` (T083) → A16.

## R16 — Addendum de implementación T001 + verificación T002 (17-09-2026)

**T001 — firmas verificadas con Context7 (`laravel/ai` SDK, `/laravel/ai`):**

| Capacidad | Firma verificada | Uso en LeadScout |
|---|---|---|
| Agente con salida estructurada | `Agent + HasStructuredOutput`, `schema(JsonSchema $schema): array`, acceso `$response['field']` | `ExtractCompanySignalsAgent`, `WriteOutreachOpenerAgent` (T055, T062) |
| Invocación | `AIClientInterface::generateStructured(agentClass, prompt, provider, model, timeoutSeconds)` (ya existe en `Shared`, con circuit breaker y sin PII en logs) | `LaravelAiSignalExtractor`, `LaravelAiDraftWriter` (T056, T062) |
| Tests | `Ai::fakeAgents([...])` (fake por agente) | Todos los tests con IA usan fakes; ningún test llama a un proveedor real |
| Respaldo | Excepción `FailoverableException` → reintento con el proveedor/modelo de respaldo del catálogo | T056, T062 (respaldo registrado en `ai_provider`/`ai_model`) |
| Versión | `laravel/ai ^0.11.0` en `composer.json` (0.x → minor fijado por riesgo de breaking changes) | Plan §9 |

**T002 — verificación del entorno (17-09-2026, sin copiar credenciales):**

| Tema | Hallazgo | Consecuencia |
|---|---|---|
| Base de datos | `database.default = pgsql` (Supabase en `.env`); `QUEUE_CONNECTION = redis`; `CACHE_STORE = redis` | Coherente con plan §2 (pooler sesión + Redis); tests en sqlite por `phpunit.xml` + grupo `pgsql` (T003) |
| Claves | `GEMINI_API_KEY`, `ANTHROPIC_API_KEY`, `TAVILY_*`, `FIRECRAWL_*` existen en `.env.example`; presencia en `.env` no copiada aquí | El catálogo marca `available=false` sin clave (T054) |
| Firecrawl | Adaptador compartido en `/v1`, sin `proxy` y con `storeInCache` por defecto | Cliente propio v2 en LeadScout (T039); compartido intacto |
| `proxy: "basic"` | Aceptado (figura *deprecated* pero funcional, R15) | Se envía + se verifica `proxyUsed` en cada respuesta |
| Gemini 3.7 Flash | Precio `[confirmar en la página oficial de Google]`; dobla el 01-01-2027 (plan §9) | `price_valid_until` en config; aviso al vencer |
