# Plan técnico: LeadScout

> Fase 4 · PLAN — Define CÓMO se construye, verificado contra `research.md`.
> Cada decisión es trazable a `spec.md` (requisito) o a `research.md` (hallazgo).

**Feature ID:** 003-lead-scout
**Basado en:** `spec.md`, `clarify.md` (Q1-Q21, D1-D5 y **A1-A15 de la 6ª pasada**), `research.md`
(incluidos R12 Supabase, R13 legal y R14 código), `ANALISIS-LEADSCOUT.md` y los aportes de la variante
`003-leadscout-pipeline-MUSE-SPARK` (resumen ejecutivo, gates, «qué no hacer», lectura de muestra,
descubrimiento de agencias, `needs_research`)
**Fecha:** 2026-09-16 · **Actualizado:** 2026-09-17 (7ª pasada: datos públicos básicos de empresa y
verificación de Tavily/Firecrawl, `clarify.md` A16, `research.md` R15) · **Estado:** propuesto,
pendiente de revisión

## 0. Decisión ejecutiva (para leer en 60 segundos)

0. **Principio rector (A18): DESCUBRIMIENTO AUTOMÁTICO ≠ CONTACTO AUTOMÁTICO.** Descubrimiento,
   enriquecimiento, scoring y borrador son automáticos; **enviar es humano** y el módulo no tiene
   ningún medio para enviar. Las reglas de contacto por país están **pendientes de verificación
   legal**: las que bloquean se aplican, las que permiten no hasta verificarse.
1. **Qué:** un módulo `LeadScout` dentro de argenis-hub (Laravel 13) que encuentra agencias de
   PT/ES que pueden comprar capacidad Laravel/Vue en marca blanca, las puntúa con evidencia y te
   prepara el contacto. **Tú envías a mano**; el sistema mide el funnel.
2. **De dónde salen los leads (3 entradas):** ofertas de empleo por API/RSS (la vacante es una
   **señal de compra** que sube la prioridad) · **descubrimiento de agencias en tres olas** (PT/ES
   ~60 %, resto de la UE + UK/IE en inglés ~30 %, resto del mundo ~10 %) · **importación** de tu
   lista ICP. Así el MVP no depende del poco volumen de ofertas Laravel (R6).
   **Solo pasan agencias vivas, con equipo real (no freelancers de una persona), con trabajo
   recurrente y donde tu inglés B1 no sea un freno** (§3.3, A14).
3. **Cómo:** orquestación determinista (jobs + scheduler) · HTTP directo antes que Firecrawl ·
   IA solo para extraer señales ambiguas y la primera frase · score por reglas con confianza
   separada · decisores y canales **sin IA** · perfil leído del **CV guardado en la tabla `cvs`**
   (sin RAG).
4. **Qué NO hace (y no puede hacer):** no entra en LinkedIn ni en redes profesionales, no salta
   CAPTCHAs, anti-bots ni logins, no usa proxies para esquivar bloqueos, no rellena formularios y
   no envía mensajes. Solo lee **APIs/RSS oficiales y páginas públicas** que no le bloquean y que
   `robots.txt` permite (§8, research R13).
5. **Dónde:** base de datos **Supabase (PostgreSQL en la nube, región UE)** con RLS en todas las
   tablas; scheduler y workers en Herd local; tests en PostgreSQL local, **nunca en Supabase**.
6. **Tamaño y plazo:** **22 tablas** del módulo (A15; argenis-hub ya tiene 51), ~24
   endpoints, 85 tareas técnicas (4 diferidas). Con un **colchón de 2 meses** (A12): prospección
   manual desde el día 1, sistema operable al final de la semana 2, bandeja en la semana 3, y en
   paralelo empleo/freelance directo y consultoras nearshore PT.
7. **Coste:** herramientas ≤ €30/mes (en la práctica €0-10 durante el test). El riesgo no es el
   dinero: es **quemar la lista corta de agencias** con mensajes genéricos o ilegales.
8. **Antes del primer contacto real:** los gates de §12 (landing, regla bloqueada, validación
   legal, política de privacidad, lista DGC si es PT, one-pager de no captación).

### 0.1 ¿Compensa? (resumen de `ANALISIS-LEADSCOUT.md` §4)

- Con 2-5 % de respuestas positivas hacen falta **~150-200 contactos cualificados para 1 cliente
  recurrente** (escenario base).
- Base a 6 meses: 1 recurrente de 40 h × €35 desde el mes 3 + 1 prueba ≈ **€6.600** sobre ~260 h
  (≈ €25/h); break-even del tiempo ≈ 1 cliente de 40 h desde el mes 2.
- Las herramientas son ruido (€0-30/mes). **Basta un «sí» recurrente** para pagar años de
  infraestructura; por eso el diseño protege la calidad del contacto antes que el volumen.
- Un resultado con muestra pequeña **no concluye nada**: 0 positivas en 30 contactos es lo
  esperable (0,6-1,5 con 2-5 %). Las métricas lo dicen explícitamente (FR-33).

## 1. Resumen técnico

Módulo **`src/Modules/LeadScout`** dentro del monolito Laravel 13 existente. La orquestación
es **determinista**: comandos programados + jobs en cola. La IA (`laravel/ai`) se usa en dos
puntos acotados: **extraer señales con salida estructurada** y **redactar la primera frase del
borrador**. Nunca decide qué rastrear ni calcula scores.

```
Fuentes API/RSS ─► normalizar + fingerprint + dedupe ─┐
Descubrimiento (consultas PT/ES, caché 30 d) ─────────┼─► empresa (dominio canónico, supresión)
Importación ICP / alta manual ────────────────────────┘                 │
                          escalera de coste: caché → robots → HTTP directo → Firecrawl (presupuesto)
                                                                        ▼
               decisores (sin IA, en memoria) · canales de contacto (sin IA, nunca envía formularios)
                                                                        ▼
               reglas deterministas (taxonomía CV) + LLM solo para lo que falta (nombres ocultados)
                                                                        ▼
               señales con evidencia verificada (fragmento literal) → score por reglas → tier
                                         (needs_research → 1 ronda extra barata → re-score)
                                                                        ▼
               bandeja web → canal legal por país → borrador + aviso art. 14 → ENVÍO MANUAL
                                                                        ▼
                               funnel con historial de etapas → métricas con lectura de muestra
```

Nivel de arquitectura: **baseline intermedio (`ARCHITECTURE-PHP/SKILL.md`) en modo Lean**,
porque se cumple el criterio de promoción nº 2 del router (≥ 2 integraciones de terceros: APIs
de empleo, Tavily, Firecrawl, proveedor LLM). Lean: sin `Domain/Entities`, `Mappers`,
`ReadRepositories`, bus ni `UnitOfWork`. Hay `Domain/Services` puros (scoring, tiers, escalera
de coste), que justifican `Tests/Unit`. **Crawl4AI, FastAPI, proxies, MCP en backend y
embeddings quedan fuera** (research R4, `ANALISIS` §5, §13 de este plan).

## 2. Stack tecnológico

| Componente | Elección | Versión verificada | Fuente / justificación |
|---|---|---|---|
| Framework | Laravel | `^13.17` (composer.json) | Stack del proyecto |
| PHP | PHP 8.5 | 8.5 (CLAUDE.md; composer declara `^8.3`) | Router ABSOLUTE |
| **Base de datos** | **Supabase — PostgreSQL gestionado en la nube**, región UE. Conexión `pgsql` por el **pooler compartido en modo sesión** (`aws-…pooler.supabase.com:5432`, usuario `postgres.[PROJECT-REF]`, IPv4, `sslmode=require`) para la app, los workers y las migraciones | Versión mayor `[UNVERIFIED: select version() — OP-19/T002]` | research R12 · decisión A5 |
| Base de datos de tests | Suite actual en **sqlite `:memory:`** (verificado en `phpunit.xml`); grupo Pest `pgsql` de LeadScout contra PostgreSQL **local** con la misma versión mayor que Supabase; guard que impide ejecutar tests contra Supabase | Verificado en el código | T003 · research R12, R14 |
| IA | `laravel/ai` — agentes con `HasStructuredOutput`, `#[Provider]` failover, `queue()`, `AgentFake` | `^0.11.0` (0.x → fijar minor) | research R3 |
| Modelos de IA (D3 + US-10) | Lista cerrada en config. Por defecto (desde `.env`): extracción = **Gemini 3.7 Flash** ($0,75/$3,75 por MTok hasta el 31-12-2026) con respaldo Sonnet 5; borradores = **Claude Sonnet 5** ($2/$10) con respaldo Gemini 3.7 Flash. **Selector en el editor de borrador** para elegir otro de la lista | — | research R9 · A2 · precios de Gemini `[confirmar en la página oficial de Google]` |
| Cliente de IA | `Shared\Infrastructure\AI\AIClientInterface::generateStructured(agentClass, prompt, provider, model, timeout)` (ya existe; circuit breaker, timeout y auditoría) | — | research R9 (hallazgo en código) · OWASP §16 |
| Fuentes de ofertas | ITJobs.pt API · Landing.jobs API · RSS de LaraJobs, Remotive y We Work Remotely · Arbeitnow API | — | research R5 · `[UNVERIFIED: términos de ITJobs/InfoJobs pendientes]` |
| **Fuente del perfil** | Tabla **`cvs`** del módulo `Cvs` (migración `2026_07_25_000100_create_cvs_table.php`): se lee en solo lectura el `raw_text` del CV **principal en markdown ATS** del operador a través de un puerto propio (`CvSourcePort`); sin subida de archivos en LeadScout | Verificado en el código (16-09-2026) | research R14 · A9 |
| RAG / embeddings | **No en el MVP.** Recuperación **determinista** de «pruebas» del CV (`proof_points`) por solapamiento de tecnologías y sector con las señales de la empresa | — | §3.4 · A10 |
| Búsqueda (descubrimiento + resolución) | `SearchPort`: Tavily `search` **advanced** (2 créditos, más relevancia) para el descubrimiento y **basic** (1 crédito) para resolver empresas, vía `TavilyClientInterface` existente (`search(array $queries, ?string $timeRange)`). **A16:** la denylist viaja en **`exclude_domains`** (hasta 150; el filtro local queda como segunda barrera), `country` por ola (`portugal`, `spain`…), `auto_parameters` desactivado y **nunca nombres de personas en la consulta** (la política de Tavily permite usar las consultas para mejorar su servicio). **Sin respaldo**: `FirecrawlClientInterface` solo expone `scrape` → si Tavily falla, el descubrimiento se detiene con motivo. **Claude WebSearch/WebFetch no se usan en el pipeline** ($10/1.000 búsquedas; el modelo decidiría qué leer, sin robots ni parada por bloqueo, y con nombres hacia la IA) | Verificado en el código y con Tavily (17-09-2026) | research R1, R14, **R15** · A1, A16 |
| Caché de búsquedas | Tabla `scout_search_queries` (resultados guardados 30 días + familia, ola, estado y coste) | — | FR-12 · A15 |
| Extracción de páginas | HTTP directo (`Http::timeout()->retry()`) → Firecrawl **API v2** `POST /v2/scrape` **markdown** (1 crédito) con un cliente propio de LeadScout que envía **`proxy: "basic"`** y **`storeInCache: false`** (el `FirecrawlScrapeAdapter` compartido apunta a `/v1`, no fija `proxy` y deja `storeInCache` en `true`; en v2 el defecto es `auto`, que **escala a proxies para anti-bots avanzados**, y `enhanced` ya cuesta lo mismo que `basic`). Cada respuesta se valida: **`metadata.proxyUsed` debe ser `"basic"`**; si no → contenido descartado, intento `proxy_mismatch` y Firecrawl desactivado en LeadScout hasta revisión manual. **Nunca** se pasa a Firecrawl una URL que respondió 401/403/429 o una página de desafío (CAPTCHA / anti-bot). Firecrawl **no tiene pago por uso** y los créditos no se acumulan → corte por presupuesto obligatorio | Verificado con Tavily y Firecrawl en docs.firecrawl.dev y en respuestas reales (17-09-2026): `proxy` *deprecated*, `proxyUsed` presente en `metadata` | research R2, R13, **R15** · A11, A16 |
| Crawler self-hosted | **Ninguno en el MVP** (Crawl4AI/FastAPI evaluados, también en MUSE-SPARK) | Crawl4AI 0.9.3 evaluado | research R4 · §13 |
| Proxies | **Ninguno** | — | spec FR-13 · prompt maestro R14-15 |
| Colas | Laravel queue (`QUEUE_CONNECTION`, default `database` → tabla `jobs` en Supabase); colas `lead-scout` y `lead-scout-llm` | — | config/queue.php · Horizon **no** está en composer |
| Planificador | Laravel Scheduler (`routes/console.php`) en Herd local (D2) | — | §9 |
| DTOs / respuestas | Spatie Data `^4.23` + `typescript:transform` | — | Router (Response Shape Rule) |
| Frontend | Inertia v3 + Vue 3.5 + PrimeVue DataTable `:lazy` | Inertia `3.0` | Router · `[detallar con FRONTEND/SKILL.md en /frontend-new]` |
| Tests | Pest 5 (`Http::fake`, `AgentFake`, `Queue::fake`) | `^5.1` | Router |
| Resiliencia | `CircuitBreakerInterface` existente (adaptadores Tavily/Firecrawl) | — | BACKEND-PHP §9.1 |
| Auditoría | `LogsActivity` v5 (`logOnly` explícito) en Company, Outreach y Suppression; también registra las solicitudes de privacidad (sin PII) | spatie/laravel-activitylog ^5.1 | Router · A3 |

> **Context7 obligatorio en la implementación** para las firmas exactas de `laravel/ai`, Spatie
> Data e Inertia v3 (regla ABSOLUTE). Este plan cita capacidades, no firmas.

## 3. Arquitectura

### 3.1 Componentes (Hexagonal Lean)

| Capa | Componente | Responsabilidad | Historia |
|---|---|---|---|
| Domain/Services | `RuleBasedSignalExtractor` | Texto → señales `fact` deterministas (tecnologías, remoto, contrato, idioma, inglés exigido, CET/WET, nº de vacantes, vacante activa, precios bajos públicos) con fragmento literal; PT/ES/EN | FR-6, FR-10 |
| Domain/Services | `DecisionMakerExtractor` | HTML/markdown de la web de la empresa + oferta → decisores (nombre, cargo, categoría, email publicado, URL de perfil enlazada, evidencia) + `team_size_observed`. **Puro, sin IA** (Q17) | US-11, FR-23, FR-24 |
| Domain/ValueObjects | `RoleTaxonomy` | Cargos permitidos por categoría (fundador · dirección · liderazgo técnico) y excluidos, en PT/ES/EN; prioridad según tamaño | US-11 |
| Domain/Services | `ScoringEngine` | Señales + perfil + versión de reglas → subscores, Lead Score, confianza y razones. **Puro y determinista** | US-4 |
| Domain/Services | `TierClassifier` | Reglas A/B/C/Descartar con umbrales configurables + marca `needs_research` | US-4 |
| Domain/Services | `FetchLadder` | Decide el siguiente método (caché → robots → HTTP → Firecrawl → fallo) según el estado y el presupuesto. **Un bloqueo es una respuesta, no un obstáculo:** 401/403/429, desafío CAPTCHA/anti-bot o muro de login → `blocked`, sin escalar a Firecrawl ni reintentar por otra vía; Firecrawl solo para páginas que no bloquearon pero no se pudieron leer (SPA vacía, timeout) | FR-11, FR-13, US-8 |
| Domain/Services | `ContactChannelDetector` | Páginas (markdown + resumen de formularios) + oferta → canales: `job_posting_apply`, `freelance_call`, `partner_page`, `contact_form`, `careers_form`, `generic_email`, `company_network_page`, con destinatario probable y evidencia. **Puro, sin IA**; no extrae teléfonos | US-12, FR-31, FR-32 |
| Domain/Services | `ChannelAdvisor` | País + canales detectados + decisor + lista DGC + **reglas por país con estado legal** (config, A18) → canal recomendado **ordenado** (legal × comercial) + motivo de cada descarte + advertencia legal. Asimetría: regla que bloquea → se aplica aunque esté `pending_verification`; regla que permite → solo si `verified`, si no «verificación legal pendiente» | US-5, US-12, Q4, FR-44 |
| Domain/Services | `SuppressionGate` | Punto único que responde «¿se puede tocar esta empresa?» (supresión por dominio, NIPC o nombre + oposición de persona). Lo consultan descubrimiento, importación, alta manual, ingesta de ofertas, enriquecimiento, score, borrador y `sent`. Una baja prevalece sobre todo (FR-43) | FR-17, FR-43 |
| Domain/Services | `DecisionRuleEvaluator` | Muestra + umbrales bloqueados → rama o «no concluyente» | US-6, FR-19, FR-33 |
| Domain/Services | `BudgetLedger` | Límite y gasto del mes por categoría (`scout_budgets`, incremento atómico en cada llamada de pago: búsqueda, extracción e IA) → permitido / agotado con motivo | US-8, FR-14 |
| Domain/ValueObjects | `CanonicalDomain` | Normaliza el host (sin `www`, IDN → punycode, sin path) con invariantes | FR-4 |
| Domain/ValueObjects | `PostingFingerprint` | Hash de empresa normalizada + título normalizado + ubicación + semana ISO | FR-4 |
| Domain/ValueObjects | `SkillTaxonomy` | Términos y sinónimos (Laravel, PHP, Vue, Inertia…) desde el perfil confirmado | US-1, FR-5 |
| Domain/Services | `PublicCompanyDataExtractor` | Páginas ya obtenidas + JSON-LD `Organization` → **datos públicos de la empresa** de la allowlist (`NORMATIVA-RGPD.md` §11): URLs por tipo de página, denominación social, forma jurídica, NIF/NIPC, registro, ciudad/país, año de fundación, servicios, sectores, idiomas de la web, clientes como empresas; detecta **persona física** (forma jurídica de autónomo/ENI, NIF de persona física, primera persona) → no guarda identificación y marca `solo_freelancer`. **Puro, sin IA**; nunca teléfonos, dirección completa ni imágenes | FR-38, FR-39 |
| Domain/Services | `PersonalDataScrubber` | Texto → texto sin emails, teléfonos ni bloques de persona (testimonios, autores, firmas, tarjetas de equipo) + nombres de decisores → `[PERSONA]`. Se aplica a todo contenido que va a la IA | FR-25 |
| Domain/Services | `ProofPointMatcher` | Señales de la empresa + `proof_points` del perfil → la prueba del CV más afín (solapamiento de tecnologías y sector, desempate por recencia). **Puro, sin IA ni embeddings** | US-5, §3.4 |
| Domain/Ports | `CvSourcePort` | `primaryMarkdownCv(int $userId): ?CvSnapshot` y `cvForUser(string $uuid, int $userId): ?CvSnapshot` ({uuid, fileType, rawText, contentHash, updatedAt}); solo lectura | US-1 |
| Domain/Ports | `JobSourcePort` | `fetchSince(Source, cursor): iterable<RawPosting>` | US-2 |
| Domain/Ports | `SearchPort` | `search(query, maxResults): SearchResult[]` (Tavily; respaldo opcional) | US-3, US-7 |
| Domain/Ports | `PageFetcherPort` | `fetch(url, method): FetchResult` | FR-11 |
| Domain/Ports | `SignalExtractorPort` | Texto delimitado → señales candidatas | FR-6, FR-10 |
| Domain/Ports | `DraftWriterPort` | Evidencia verificada + variante → primera frase | US-5 |
| Domain/Ports | `AiModelCatalogPort` | Opciones permitidas, disponibilidad, coste estimado, resolución propósito (default guardado en `scout_ai_settings` + override por borrador) → {provider, model, fallback} | US-10 |
| Domain/Ports | `CompanyRepositoryPort`, `JobPostingRepositoryPort`, `OutreachRepositoryPort` | Persistencia (obligatorios en el baseline intermedio) | — |
| Application/Commands | `IngestSourceHandler`, `DiscoverAgenciesHandler`, `ResolveCompanyHandler`, `EnrichCompanyHandler`, `ExtractDecisionMakersHandler`, `DetectContactChannelsHandler`, `ExtractSignalsHandler`, `ScoreCompanyHandler`, `GenerateDraftHandler`, `UpdateOutreachStageHandler`, `UpsertOpportunityHandler`, `UpdateAiSettingsHandler`, `UpdateBudgetsHandler`, `ImportCvProfileHandler`, `CreateManualLeadHandler`, `ImportLeadsHandler`, `SuppressCompanyHandler`, `ImportDgcListHandler`, `UpsertContactHandler`, `ObjectContactHandler`, `HandlePrivacyRequestHandler`, `UpdateChannelStatusHandler`, `LockDecisionRuleHandler`, `ExpirePostingsHandler`, `PrunePersonalDataHandler` | Un caso de uso por archivo (Flatness Rule) | Todas |
| Application/Queries | `ListLeadsHandler`, `GetLeadHandler`, `GetFunnelMetricsHandler`, `GetBudgetStatusHandler`, `ListSourcesHandler`, `GetAiSettingsHandler` (valor actual por propósito + opciones del catálogo) | Lecturas con eager loading explícito | US-5, US-6, US-8, US-10 |
| Infrastructure/JobSources | `ItJobsApiSource`, `LandingJobsApiSource`, `RssFeedSource` (LaraJobs/Remotive/WWR), `ArbeitnowApiSource` | Adaptadores del puerto (≥ 2 → puerto justificado) | US-2 |
| Infrastructure/Cvs | `EloquentCvSource` | Implementa `CvSourcePort` leyendo `cvs` en solo lectura (filtra por `user_id`, `deleted_at IS NULL`, `is_primary`, `file_type = md`); el `raw_text` nunca sale hacia la IA, logs ni props de Inertia | US-1 |
| Infrastructure/Search | `TavilySearchAdapter` | Reutiliza `TavilyClientInterface`; caché de 30 días en `scout_search_queries` (con familia, ola, estado y coste); descarta resultados de la denylist | US-7, FR-12 |
| Infrastructure/Fetching | `DirectHttpPageFetcher` (detecta bloqueo: 401/403/429, cabeceras y marcadores de desafío de CAPTCHA/anti-bot), `FirecrawlPageFetcher` (cliente propio de LeadScout con `proxy: "basic"`, formatos `markdown` + `html`), `RobotsTxtPolicy`, `OutboundUrlGuard` (SSRF + denylist) | Obtención de páginas **sin evasión** | FR-11, FR-13 |
| Infrastructure/Ai | `ExtractCompanySignalsAgent`, `WriteOutreachOpenerAgent`, `LaravelAiSignalExtractor`, `LaravelAiDraftWriter`, `ConfigAiModelCatalog` | Salida estructurada, sin tools; proveedor/modelo resuelto por el catálogo; respaldo si falla el principal | FR-10, FR-22 |
| Infrastructure/Persistence | `SupabaseRls` (helper de migraciones) | `ENABLE ROW LEVEL SECURITY` + `REVOKE` a `anon`/`authenticated` si existen | A5, NFR Seguridad |
| Infrastructure/Queue | `IngestSourceJob`, `DiscoverAgenciesJob`, `EnrichCompanyJob`, `ExtractSignalsJob`, `ScoreCompanyJob` | Encadenados, idempotentes, rate-limited | NFR |
| Infrastructure/Console/Commands | `lead-scout:ingest`, `:discover`, `:score`, `:expire`, `:prune`, `:import-leads`, `:import-dgc`, `:privacy`, `:backup` | Planificados en `routes/console.php` o manuales | US-2, US-7, FR-17, FR-29, NFR |
| Infrastructure/Http | `LeadController`, `OutreachController`, `ContactController`, `ChannelController`, `ProfileController`, `SourceController`, `MetricsController`, `DecisionRuleController`, `BudgetController`, `AiSettingsController`, `OpportunityController`, `SuppressionController`, `LeadExportController` | Delgados; delegan en handlers | §5 |

### 3.2 Flujo de una ejecución programada

**Antes de todo — perfil desde el CV guardado (A9):** el operador pulsa «Importar desde mi CV» y
elige uno de sus CVs (por defecto, el principal en markdown ATS). `ImportCvProfileHandler` lee el
`raw_text` con `CvSourcePort` (sin copiar el texto completo en LeadScout), lo parsea **sin IA**
(FR-10) → capacidades confirmadas/potenciales + `proof_points` (proyectos con tecnologías, sector,
resultado y URL pública) → nueva versión de `scout_profiles` con `source_cv_uuid` y `cv_hash`.
Si el CV cambia en el módulo Cvs (hash distinto), la bandeja muestra «perfil desactualizado» y
los scores nuevos no se calculan hasta reimportar o confirmar. Un CV en PDF sin `raw_text` → 422.

0. **Entradas de empresas sin oferta (A1):**
   - `lead-scout:discover` (semanal) → `DiscoverAgenciesJob` por familia de consultas de
     `config('lead-scout.discovery.waves')`, **en tres olas dentro del MVP** (A14). El reparto es
     de consultas por semana y se ajusta con la métrica «consulta → lead A/B» (T066):

     | Ola | Mercado | % de consultas | Por qué | Familias de ejemplo (todas con variante «recurrente»: mantenimiento, soporte, partner, marca blanca) |
     |---|---|---|---|---|
     | **1** | España + Portugal | ~60 % | Idioma nativo/residente, UE, mismo huso | `agencia desarrollo Laravel {Madrid\|Barcelona\|Valencia\|Sevilla\|Málaga\|Bilbao}` · `mantenimiento y soporte Laravel agencia` · `subcontratación desarrollo Laravel marca blanca` · `agência desenvolvimento Laravel {Lisboa\|Porto\|Braga\|Coimbra}` · `outsourcing Laravel white label Portugal` |
     | **2** | Resto de la UE + Reino Unido e Irlanda, en inglés | ~30 % | Facturación UE (o sencilla con UK/IE), solape horario ≥ 4 h, cultura remota y asíncrona frecuente | `Laravel development agency {Netherlands\|Germany\|Belgium\|Ireland\|United Kingdom}` · `white label Laravel development partner Europe` · `Laravel maintenance support agency` · `remote-first Laravel agency Europe` |
     | **3** | Resto del mundo (EE. UU./Canadá, LatAm hispanohablante y otros) | ~10 % + ofertas remotas globales ya ingeridas (Remotive, WWR, LaraJobs, Arbeitnow) | Más volumen, pero menos solape horario (EE. UU.) o tarifas más bajas (LatAm) | `white label Laravel agency remote contractors` · `Laravel agency hiring remote contractors Europe timezone` · `agencia desarrollo Laravel {México\|Colombia\|Argentina\|Chile} mantenimiento` |

     Cada consulta pasa por `SearchPort` (Tavily `advanced`, 10 resultados, caché de 30 días: una
     combinación ya ejecutada en la ventana no se repite). Los resultados se filtran con la
     **denylist** (portales de empleo, directorios como Sortlist/Clutch/GoodFirms, marketplaces
     freelance, redes sociales, Wikipedia, medios) → `CanonicalDomain` → deduplicación →
     supresión → `scout_companies` con `origin=discovery`, `origin_ref=<consulta>` y
     `discovery_wave` → `EnrichCompanyJob`. **Coste:** ~60 consultas/mes × 2 créditos ≈ 120
     créditos (dentro del free tier de 1.000).
     Los directorios (partners.laravel.com, Sortlist, Clutch) **no se rastrean**: el operador los
     consulta en el navegador y los importa.
   - `lead-scout:import-leads {csv}` y `POST leads` (alta manual): `origin=import|manual`, mismo
     camino desde el paso 4.
1. `lead-scout:ingest` (cada N min según la `frequency` de cada fuente activa) → `IngestSourceJob`
   por fuente. El circuit breaker por proveedor aísla fallos (US-2 CA-4).
2. `IngestSourceHandler`: normaliza → `PostingFingerprint` → upsert en `scout_job_postings` +
   pivot `scout_job_posting_sources` (US-2 CA-2). Filtro de relevancia **determinista** con
   `SkillTaxonomy` (FR-5); lo que no casa con ningún término se descarta sin coste.
3. `ResolveCompanyHandler`: dominio desde el payload de la fuente (web de la empresa en la
   API) → `CanonicalDomain`. **Solo si falta**, `SearchPort` `"{empresa} {país} sitio oficial"`
   (1 crédito, caché de 30 días). Si sigue sin resolverse → `unresolved`, sin gasto adicional
   (US-3 CA-1).
4. `EnrichCompanyJob` (solo empresas resueltas y no suprimidas): `sitemap.xml` / enlaces de la
   home → selección de ≤ 4 URLs por palabras clave multilingües (servicos/servicios/services,
   sobre/nosotros/about, equipa/equipo/team, carreiras/empleo/careers, contactos/contacto) →
   `FetchLadder` por URL. Se detiene al llegar a evidencia suficiente (US-7 CA-3). Si una URL
   responde con bloqueo (401/403/429, desafío CAPTCHA/anti-bot, muro de login) se registra
   `blocked` y **no se vuelve a intentar por Firecrawl ni por otra vía** (FR-13); la empresa sigue
   con las páginas que sí se pudieron leer.
   **Si la empresa tiene `needs_research`** (paso 6): una sola ronda extra con páginas aún no
   obtenidas (casos, clientes, blog), priorizando HTTP directo; después se re-puntúa y la marca
   se retira aunque no mejore (sin bucles).
4b. `ExtractDecisionMakersHandler` (sin IA, Q17) sobre las páginas guardadas + la oferta:
   1) JSON-LD `schema.org` (`Organization.founder`, `Person` con `jobTitle`);
   2) bloques de la página de equipo (nombre + cargo en el mismo contenedor);
   3) frases de «nosotros» («fundada por X», «founded by X», «CEO: X», «sócio-gerente X»);
   4) contacto nombrado en la oferta.
   Cada cargo se clasifica con `RoleTaxonomy`: **permitido → candidato** con evidencia;
   **excluido o ambiguo → no se guarda nada**; de las demás personas solo se cuenta
   `team_size_observed` (señal de tamaño). **Los candidatos se mantienen en memoria**: sirven para
   ocultar nombres en 5b y **solo se persisten en el paso 6b si el lead queda Tier A/B** (FR-27).
   Las imágenes nunca se procesan ni se guardan (FR-30).
   - **Email:** solo si aparece en el mismo bloque que el nombre y en el dominio canónico de la
     empresa (nunca gmail/outlook ni buzones genéricos).
   - **URL de perfil:** solo si la web la enlaza; el sistema no la visita (`OutboundUrlGuard`
     deniega linkedin.com).
   - **Contacto principal:** según tamaño (≤ 25: fundador/CEO; 26-50: CTO/head of
     engineering o delivery). Sin decisor → `has_decision_maker=false` (el lead **no** se
     descarta).
   - **Oposición:** se omiten las personas cuyo hash (nombre normalizado + dominio) esté en
     `scout_contact_objections`.
4c. `DetectContactChannelsHandler` (sin IA, US-12) sobre las mismas páginas + la oferta:
   - **Formularios:** `DirectHttpPageFetcher` y `FirecrawlPageFetcher` (formatos `markdown` +
     `html` = 1 crédito) generan `forms_summary` (nº de formularios, nombres de campos, si hay
     `textarea`, si hay CAPTCHA, host del `action`) y **descartan el HTML**. Un formulario con
     email + mensaje en una página contact/contacto/contactos → `contact_form`; en empleo,
     carreiras, «trabaja con nosotros», join-us o careers → `careers_form` (audiencia
     `hr_recruiting`).
   - **Invitaciones:** patrones PT/ES/EN («trabajamos con freelancers», «colaboradores externos»,
     «parceiros», «partner program», «white label / marca blanca», «subcontratación») →
     `freelance_call` o `partner_page` + señal comercial `fact` (+35, §3.3).
   - **Oferta:** URL o email de candidatura de la propia oferta → `job_posting_apply` (nunca en
     empresas sin oferta).
   - **Emails:** solo buzones **genéricos** del dominio de la empresa (info@, hola@, geral@,
     contact@) → `generic_email`; los nominativos siguen en `scout_contacts` (4b).
   - **Red profesional:** la página **de empresa** enlazada desde su web → `company_network_page`
     (nunca se visita).
   - Nunca se rellena, envía ni invoca un formulario, ni se resuelve un CAPTCHA (FR-32).
     «Trabaja con nosotros» **sí se extrae** aunque sea de baja prioridad (Q21).
4d. `PublicCompanyDataExtractor` (sin IA, A16) sobre las mismas páginas: rellena los datos públicos
   de la empresa de la allowlist (`legal_name`, `legal_form`, `tax_id`, `registry_info`, `city`,
   `founded_year`, `services`, `sectors`, `site_languages`, `client_companies`, `public_urls`) con
   evidencia en `public_data_evidence`. Prioridad: JSON-LD `Organization` → página de aviso legal /
   *termos* / privacidad → pie de página. Si la forma jurídica o el NIF indican **persona física**
   → no se guardan esos campos y el lead se descarta como `solo_freelancer` (FR-39). El NIPC sirve
   para cruzar con la lista DGC (FR-17). Coste 0: no hay peticiones nuevas.
5. `ExtractSignalsHandler`:
   a) **reglas deterministas** sobre el texto de la oferta y las páginas (tecnologías, modalidad
      remota, tipo de contrato, idioma, vacante activa, precios públicos bajos) → señales `fact`
      con `extraction_method=rule`; y, **sin IA**, las de **vitalidad y tamaño** (A14): fechas del
      contenido (blog, casos, noticias), `lastmod` del `sitemap.xml` ya descargado, año del
      copyright, recuento del equipo (`team_size_observed`, 4b), primera persona del singular en
      ES/PT/EN (freelancer individual), dominio aparcado o redirección a otra empresa; y las de
      **B1**: remote-first, equipo distribuido, procesos asíncronos, «fluent/native English» o
      «daily client calls» exigidos, y el país/huso de la empresa → `activity_status`,
      `last_activity_at` y solape horario;
   b) **LLM solo si faltan dimensiones** (comercial, recurrente, tipo de empresa, comunicación)
      → `ExtractCompanySignalsAgent` con el proveedor/modelo por defecto de `extraction`
      (respaldo si el principal falla); cada señal registra `ai_provider` y `ai_model`, y la
      coste se suma a `scout_budgets` (categoría IA). **Antes de enviar**: las páginas
      `team` no se envían, y `PersonalDataScrubber` quita emails, teléfonos y bloques de persona
      (testimonios, autores, firmas) y sustituye los nombres detectados en 4b por `[PERSONA]` (FR-25);
   c) **verificación anti-alucinación**: cada `evidence_excerpt` debe aparecer **literalmente**
      en el contenido guardado y el `signal_key` debe pertenecer a la allowlist; si no, se
      descarta y se registra.
6. `ScoreCompanyHandler` → `ScoringEngine` + `TierClassifier` → nuevo `scout_score_results`
   (`is_current`) + `scout_score_reasons` y marca `needs_research` en la empresa si procede.
6b. Si el tier es A/B → se persisten los decisores candidatos (`scout_contacts`,
   `contact_deadline_at = captured_at + 30 días`). Si baja a C o Descartar → se anonimizan los
   decisores guardados de esa empresa.
7. La bandeja muestra los Tier A/B (y, en un filtro aparte, los `needs_research`). El operador
   pide un borrador (opcionalmente elige otro proveedor/modelo en el **selector** del editor) →
   `GenerateDraftHandler` (plantilla determinista + `ProofPointMatcher` elige la prueba del CV más
   afín a la señal + `WriteOutreachOpenerAgent` para la primera frase, que recibe **solo** la
   evidencia de la empresa y el resumen público de esa prueba, nunca el CV completo + **aviso breve
   del art. 14 y línea de baja** insertados por la plantilla, FR-26; si la
   captura tiene > 90 días, antes se re-verifica la fuente, FR-28) → `ChannelAdvisor` →
   validación de claims contra el perfil (US-5 CA-5). **No hay envío** (FR-16).

### 3.2.1 Flujo de descubrimiento validado y países por ola (A17)

```
                    DISCOVERY  (lead-scout:discover, semanal)
                         │
        guard de consulta: sin linkedin/xing ni intermediarios de datos
                         │
                 Tavily (exclude_domains + country)
                         │
        ┌────────────────┼────────────────┐
     ola 1 PT/ES     ola 2 UE+UK      ola 3 resto        ← en paralelo por peso
       ~60 %            ~30 %            ~10 %              (se reajusta con T066)
        └────────────────┼────────────────┘
                         ▼
       denylist → dominio canónico      ◄── ofertas (API/RSS) · CSV ICP · alta manual
                         ▼
          ¿existe o está suprimido? ── sí ──► STOP (una oferta nueva sí re-puntúa)
                         │ no
                         ▼
     enriquecimiento: robots → HTTP directo → Firecrawl basic (bloqueo = STOP)
                         ▼
     web de la empresa: servicios · nosotros · equipo · empleo · contacto · aviso legal
                         ▼
     sin IA: datos públicos de empresa · decisores (en memoria) · canales · vitalidad · B1
                         ▼
     PersonalDataScrubber (emails, teléfonos, testimonios, autores, nombres)
                         ▼
     IA solo para dimensiones que faltan (fragmento literal verificado)
                         ▼
     score + tier (needs_research → 1 ronda extra) → decisores persistidos solo si A/B
                         ▼
     ¿Tier A/B? ── no ──► sin borrador (C: bandeja aparte · Descartar: motivo)
                         │ sí
                         ▼
     reglas por país (config con estado legal, A18):
        bloquea (aunque esté pendiente) → canal descartado con motivo
        permite + verified              → canal recomendado
        permite + pending_verification  → «⚠️ verificación legal pendiente»
                         ▼
     borrador + aviso art. 14 + baja                  ══ fin de lo AUTOMÁTICO ══
                         ▼
     ENVÍO HUMANO (10-15/día, buzón del dominio; el módulo no puede enviar)
                         ▼
     registrar «sent»: lead · send_medium · canal · sent_at · operator_id · plantilla+versión
                         (+ confirmación explícita si la regla estaba pendiente)
                         ▼
     respuesta: interested → positive · not_interested → lost · unsubscribe → do_not_contact
                         ▼
     unsubscribe = supresión con PRECEDENCIA ABSOLUTA (todas las entradas y canales)
```

**DESCUBRIMIENTO AUTOMÁTICO ≠ CONTACTO AUTOMÁTICO (A18, FR-40):** todo lo que está por encima de la
línea «fin de lo automático» lo hacen jobs; todo lo que está por debajo lo hace el operador. El
módulo no incluye clases de Mail, Notification, SMTP ni clientes de mensajería, no invoca el
`action` de ningún formulario y no automatiza LinkedIn; un test de arquitectura lo impide (T085).

**Catálogo de países por ola** (`config('lead-scout.discovery.countries')`; el orden dentro de la
ola es la prioridad inicial). El solape se calcula en tiempo de ejecución con la zona horaria IANA
y la fecha; la columna es orientativa para una jornada de 09:00-18:00 en Portugal en septiembre
(horario de verano; cambia ±1 h con los cambios de hora).

| Ola | País (ISO) | Zona horaria IANA | Solape aprox. | Idioma de consulta | Nota |
|---|---|---|---|---|---|
| **1** | PT | `Europe/Lisbon` | 9 h | pt | Residencia fiscal, recibos verdes |
| **1** | ES | `Europe/Madrid` | 8 h | es | Sin email en frío (LSSI art. 21) |
| **2** | IE, GB | `Europe/Dublin`, `Europe/London` | 9 h | en | IE en la UE; GB fuera |
| **2** | NL, DE, BE, FR, IT, AT, DK, SE, PL, CZ | `Europe/Amsterdam` … | 8 h | en (+ idioma local en consultas) | UE |
| **2** | GR, LT, LV, EE, RO, FI | `Europe/Athens`, `Europe/Vilnius` … | 7 h | en | UE; parte de estas agencias **venden** capacidad barata (reglas −25/−30) |
| **2** | CH, NO | `Europe/Zurich`, `Europe/Oslo` | 8 h | en | Fuera de la UE (NO en el EEE) |
| **3** | US (este), CA (Toronto) | `America/New_York`, `America/Toronto` | ~4 h | en | Geo +10 si ≥ 4 h |
| **3** | US (centro/oeste), CA (Vancouver) | `America/Chicago`, `America/Los_Angeles`, `America/Vancouver` | 1-3 h | en | Geo 0 |
| **3** | AR, UY | `America/Argentina/Buenos_Aires`, `America/Montevideo` | ~5 h | es | Tarifas más bajas |
| **3** | CL, CO, MX | `America/Santiago`, `America/Bogota`, `America/Mexico_City` | 2-5 h | es | Tarifas más bajas |
| **3** | UA | `Europe/Kyiv` | 7 h | en | Fuera de la UE; mayoría **vende** capacidad → rara vez A/B |
| **3** | AU, NZ | `Australia/Sydney`, `Pacific/Auckland` | ~0 h | en | Geo −15; solo agencias 100 % asíncronas |

Todos los países de las olas 2 y 3 quedan **sin email en frío** hasta que OP-26 verifique su regla
en config. El RGPD se aplica igualmente a los decisores de empresas fuera de la UE, porque el
responsable está establecido en Portugal (art. 3.1).

### 3.3 Modelo de scoring (determinista, versionado `rules_version = 2026.09.3`)

**Qué es una «agencia buena» para este perfil (A14):** está **viva** (publica, contrata o actualiza
su web), tiene **equipo real** (no es un freelancer de una persona), **compra capacidad de forma
recurrente** (mantenimiento, soporte, retainer, partners, staff augmentation) y **trabaja de forma
que un inglés B1 no sea un freno** (idioma ES/PT, o inglés escrito y asíncrono). Pesos y reglas
salen de esa definición.

**Lead Score** = Σ(subscore × peso) / 100:

| Dimensión | Peso | Por qué |
|---|---|---|
| Ajuste técnico | 20 | Tu stack confirmado es la base, pero no basta |
| Ajuste comercial | 20 | Sin disposición a contratar externos no hay venta |
| **Potencial recurrente** | **20** | El objetivo es el retainer, no el proyecto suelto |
| **Vitalidad y tamaño** | **15** | Nuevo: descarta agencias paradas y prioriza equipos de 5-50 personas |
| Ajuste de comunicación (B1) | 10 | Idioma de trabajo y forma de trabajar: penaliza pero no excluye |
| Ajuste geográfico/contractual | 10 | Recibos verdes, UE y solape horario |
| Ajuste remoto | 5 | Lo presencial fuera de PT ya se descarta por regla; aquí solo matiza |

**Reglas por dimensión** (puntos por señal; `inference` × 0,6; subscore limitado a 0-100):

| Dimensión | Señales (+/−) |
|---|---|
| Técnico | Laravel +40 · PHP sin Laravel +20 · Vue/Inertia +20 · Livewire +15 (tope frontend +20) · PostgreSQL/MySQL/Redis/Docker/CI +5 c/u (tope +15) · APIs/integraciones/IA +5 (tope +10) · **tecnología exigida no confirmada en el perfil** −10 c/u (tope −30) |
| Comercial | Contrato/freelance +35 · acepta freelancers/subcontratistas/marca blanca/partners +35 · tipo agencia de software +25 · consultora/nearshore +15 · empresa de producto +10 · reclutadora 0 (señal de mercado) · outsourcer grande −30 · ≥ 2 vacantes dev abiertas +15 · oferta de empleo fijo +10 · precios públicos bajos −25 |
| Recurrente | Staff augmentation / team extension +35 · planes de mantenimiento, soporte o SLA publicados / retainer +30 · «long-term/ongoing» +25 · ≥ 5 casos o clientes activos +20 · clientes o casos con relación de varios años +15 · **vacante dev activa (no caducada) +15** · proyecto puntual +5 |
| **Vitalidad y tamaño** | **Contenido fechado más reciente** (blog, casos, noticias, portfolio): ≤ 6 meses +40 · 6-12 meses +25 · 12-24 meses +5 · > 24 meses −30 · **`lastmod` más reciente del sitemap**: ≤ 6 meses +20 · > 24 meses −20 (**no cuenta** si todas las URLs comparten el mismo `lastmod`: suele ser una fecha autogenerada por el CMS) · **vacante dev activa** +20 · **año del copyright** ≥ año actual − 1 +5 · ≤ año actual − 3 −10 (siempre `inference`) · **tamaño observado**: 5-50 +30 · 51-200 +15 · 2-4 0 · desconocido 0 (baja confianza) |
| Comunicación (B1) | Idioma de trabajo ES/PT = 100 · **inglés escrito/asíncrono demostrado** (remote-first, equipo distribuido, procesos documentados, trabajo por tickets/chat) = 80 · inglés sin más datos = 55 (baja confianza) · reuniones diarias en inglés, trato directo con el cliente o «fluent/native English» exigido = 25 |
| Geo/contrato | Empresa PT/ES +50 · resto de la UE +35 · UK/IE +30 · US/CA +15 · resto +10 · acepta contractors de la UE/extranjeros +30 · **solape horario con Portugal** ≥ 4 h +10 · 1-3 h 0 · < 1 h −15 · exige contrato local o residencia fuera de PT −40 |
| Remoto | Remoto = 100 · híbrido = 30 · presencial = 0 · desconocido = 40 (baja confianza) |

**Confianza de la evidencia (0-100)** = media de `confidence` × 100 de las señales usadas
(hechos × 1,0, inferencias × 0,6) + 10 por cada fuente independiente adicional (tope +20) − 20
si la evidencia más reciente tiene > 90 días.

**Tiers** (umbrales configurables; calibrar tras revisar a mano los primeros 50 leads):

| Tier | Regla |
|---|---|
| **Descartar** (con motivo guardado) | Suprimida · outsourcer grande (> 200) · Técnico < 30 · Remoto = 0 y empresa fuera de PT · **freelancer individual** (web en primera persona del singular —«soy», «sou», «I'm a freelance developer»—, portfolio personal o equipo observado de 1 persona): compite contigo, no compra capacidad · **agencia parada**: ninguna señal de actividad en 24 meses (contenido fechado, `lastmod`, vacantes) **y** copyright ≤ año actual − 3 · **web muerta o absorbida**: dominio aparcado o en venta, redirección permanente a otra empresa o error persistente |
| **A** | Lead Score ≥ 80 **y** Confianza ≥ 70 **y** ≥ 1 señal comercial con `nature=fact` **y** Técnico ≥ 60 **y** actividad en los últimos 12 meses (`activity_status = active`) **y** equipo ≥ 5 personas (o 2-4 con vacante activa o llamada a freelancers/partners como `fact`) |
| **B** | Lead Score ≥ 65 **y** Confianza ≥ 50 |
| **C** | El resto no descartado |

**`activity_status`:** `active` = contenido fechado, vacante o `lastmod` válido de ≤ 12 meses ·
`stale` = la señal más reciente tiene 12-24 meses · `inactive` = > 24 meses (con copyright ≤ año
actual − 3 → Descartar) · `unknown` = no se encontró ninguna fecha.

**Marca `needs_research`** (aporte MUSE-SPARK): Lead Score ≥ 80 **y** Confianza < 70, **o** sin
ninguna fecha encontrada (`activity_status = unknown`) con Lead Score ≥ 65. No es un tier: el lead
conserva el suyo y recibe **una** ronda extra de enriquecimiento barato (paso 4: casos, blog,
sitemap). Evita que un buen encaje con poca evidencia muera en C o se contacte sin pruebas.
**Tamaño:** 1 persona descarta; 2-4 limita a Tier B salvo señal de compra fuerte; 5-50 suma más
en Vitalidad; > 200 descarta. Sin dato de tamaño **no** se descarta (baja la confianza).

**Ejemplo 1 («¿por qué 87?»)** — agencia hipotética en Sevilla, 11-50 personas, con una oferta
activa «Desarrollador Laravel + Vue, freelance, remoto» y casos publicados en 2026:

| Dimensión | Cálculo | Subscore | × peso |
|---|---|---|---|
| Técnico | Laravel +40 (hecho) · Vue +20 (hecho) · MySQL/Redis/Docker +15 (tope) · APIs +5 | 80 | 16,00 |
| Comercial | freelance +35 · agencia +25 · 2 vacantes +15 · «trabajamos con partners» +35 inferencia × 0,6 = +21 | 96 | 19,20 |
| Recurrente | mantenimiento +30 (hecho) · «long-term» +25 × 0,6 = +15 · ≥ 5 casos +20 · vacante activa +15 | 80 | 16,00 |
| Vitalidad y tamaño | caso fechado hace 3 meses +40 · `lastmod` hace 2 semanas +20 · vacante activa +20 · 11-50 personas +30 (tope 100) | 100 | 15,00 |
| Comunicación (B1) | idioma de trabajo ES | 100 | 10,00 |
| Geo/contrato | ES +50 · solape horario ≥ 4 h +10 | 60 | 6,00 |
| Remoto | remoto | 100 | 5,00 |
| **Lead Score** | | | **87** |

Confianza 78 (5 hechos, 2 inferencias, 2 fuentes independientes) · señal comercial `fact` sí ·
Técnico ≥ 60 · actividad hace 3 meses · equipo ≥ 5 → **Tier A**. Si la misma agencia solo tuviera
inferencias de una página, la Confianza bajaría a ~50 → **Tier B + `needs_research`**.

**Ejemplo 2 («¿por qué 75?»)** — agencia hipotética remote-first en Países Bajos, 11-50 personas,
sin vacante, con página de partners y procesos asíncronos documentados:

| Dimensión | Cálculo | Subscore | × peso |
|---|---|---|---|
| Técnico | Laravel +40 · Vue +20 · stack +15 · APIs +5 | 80 | 16,00 |
| Comercial | agencia +25 · página de partners/marca blanca +35 | 60 | 12,00 |
| Recurrente | planes de soporte con SLA +30 · ≥ 5 casos +20 · clientes de varios años +15 | 65 | 13,00 |
| Vitalidad y tamaño | blog hace 5 meses +40 · `lastmod` reciente +20 · 11-50 personas +30 | 90 | 13,50 |
| Comunicación (B1) | inglés escrito/asíncrono demostrado | 80 | 8,00 |
| Geo/contrato | UE +35 · acepta contractors UE +30 · solape ≥ 4 h +10 | 75 | 7,50 |
| Remoto | remoto | 100 | 5,00 |
| **Lead Score** | | | **75** |

→ **Tier B**: buen candidato en inglés, pero sin señal de compra urgente. Pasaría a A con una
vacante activa o una llamada explícita a freelancers.

**Qué lo deja fuera (ejemplos):** «Hola, soy Juan, desarrollador Laravel freelance» → freelancer
individual · web con el último caso de 2021, sitemap de 2022 y © 2022 → agencia parada · dominio
que redirige a otra consultora → absorbida · «Senior dev, daily client calls, native English» →
no se descarta, pero Comunicación = 25 y rara vez llega a A.

### 3.4 ¿RAG sobre el CV? → no en el MVP (A10)

| Pregunta | Respuesta |
|---|---|
| ¿Qué haría RAG aquí? | Trocear el CV, generar embeddings (pgvector en Supabase + `laravel/ai` embeddings) y recuperar los fragmentos más parecidos a cada empresa u oferta para dárselos a la IA |
| ¿Por qué no ahora? | (1) Es **un solo documento corto** (~1-2k tokens): cabe entero en cualquier prompt, no hay nada que «recuperar». (2) El perfil debe ser **determinista** (FR-10): mismo CV → mismas capacidades y mismo score; la similitud vectorial no lo garantiza. (3) El `raw_text` es el dato más sensible del módulo Cvs: embeberlo lo envía a otro proveedor y crea una copia más que proteger y borrar. (4) Añade extensión, proveedor, coste y tests sin mejorar el score |
| ¿Qué se hace en su lugar? | **Recuperación determinista**: al importar, cada proyecto del CV se guarda como `proof_point` (título, resumen público, tecnologías, sector, resultado medible, URL). `ProofPointMatcher` elige la prueba con más solapamiento con las señales de la empresa (p. ej. agencia de reservas → Servispin; landing de rendimiento → AquaShield; SaaS modular Laravel 13 + Inertia → Vidula). Explicable y testeable |
| ¿Cuándo sí? | Fase 3, si se cumple alguna: varias versiones del CV por nicho/idioma que elegir automáticamente, > 1.000 ofertas/mes que emparejar con el CV, o sinónimos que la taxonomía no resuelve. Supabase ya soporta pgvector y `laravel/ai` incluye embeddings + `whereVectorSimilarTo` (research R3) |

### 3.5 ¿Hace falta FastAPI + Crawl4AI + proxies residenciales para buscar y extraer mejor? → no (A13)

**Dónde se gana o se pierde efectividad en este módulo:**

| Eslabón | Qué limita el resultado | Qué lo mejora (sin infra nueva) |
|---|---|---|
| **1. Encontrar las agencias correctas** | Calidad de las consultas y de las fuentes; es el cuello de botella real (R6) | Tavily `advanced` en el descubrimiento (~120 créditos/mes en 3 olas, gratis) · familias de consulta PT/ES afinadas con la métrica «consulta → lead A/B» · importación de la lista ICP y de directorios revisados a mano |
| **2. Leer sus páginas** | Webs corporativas pequeñas: la mayoría responde a HTTP directo; las hechas con JS necesitan renderizado | HTTP directo (gratis) → Firecrawl `basic` para SPA vacías (1 crédito) · sitemap y ≤ 4 páginas por palabras clave PT/ES/EN |
| **3. Entender la evidencia** | Reglas + IA verificada, decisores y canales | Reglas primero; IA solo para lo que falta, con fragmento literal verificado |
| **4. Decidir a quién escribir** | Score, confianza y `needs_research` | Una ronda extra barata antes de descartar |

**Comparación para el eslabón 2 (el único que cambiaría con Crawl4AI o proxies):**

| Opción | Qué añade | Coste y riesgo | Veredicto |
|---|---|---|---|
| HTTP directo + Firecrawl `basic` (**elegida**) | Renderizado de JS gestionado, markdown listo para la IA, sin servidores | 1.000 créditos/mes gratis (~250 empresas a 4 páginas, y la mayoría no llegan a Firecrawl); ya integrado en argenis-hub | Suficiente para webs públicas de agencias |
| FastAPI + Crawl4AI (autoalojado) | Lo mismo que Firecrawl (navegador que renderiza JS) pero operado por ti | Python + Playwright + Docker + Railway (~€5-10/mes siempre encendido), 3 parches de seguridad en 3 meses (R4), ~7 tareas más (MUSE-SPARK Fase G). **No lee más páginas públicas que Firecrawl** | Solo compensa por **coste** a gran volumen, no por efectividad |
| Proxies residenciales | Hacer pasar las peticiones por IPs de usuarios domésticos | €/GB, y su finalidad es **esquivar bloqueos anti-bot**: exactamente lo que FR-13 y la ley (CP 197 bis, Lei 109/2009) desaconsejan (NORMATIVA §10) | **No**, ni en MVP ni después |

**Una web que bloquea no es un fallo de extracción que haya que arreglar:** es la empresa diciendo
que no quiere bots. Esas agencias se revisan a mano en el navegador (dos minutos) y se añaden con el
alta manual si interesan. Con un colchón de 2 meses (A12), cada semana dedicada a infraestructura es
una semana menos de contactos.

**Criterios medidos para reconsiderar (T066, revisión al final de la semana 2 de operación):**

| Métrica | Umbral | Acción |
|---|---|---|
| % de empresas candidatas sin ninguna página leída | > 20 % | Mirar el motivo en `scout_fetch_attempts`: si son SPA sin rescatar → ajustar Firecrawl (`waitFor`, páginas elegidas); si son `blocked` → revisión manual, **nunca proxies** |
| Créditos de Firecrawl consumidos | > 800/mes de forma sostenida | Pasar a Hobby ($16) antes que autoalojar |
| Páginas/mes que requieren renderizado | > 20.000, o Firecrawl > €80/mes | Entonces sí evaluar Crawl4AI autoalojado (Fase 3), **sin proxies residenciales** |
| Consultas de descubrimiento con 0 leads A/B tras 2 ejecuciones | cualquier familia | Reescribirla o retirarla |

## 4. Modelo de datos (22 tablas)

Todas las tablas llevan el prefijo `scout_` para no chocar con los módulos `Company` y
`Clients`. PK `id` UUIDv7 (`HasUuids`), `timestamps`. Importes en céntimos o micro-USD
(enteros). **Todas las migraciones activan RLS** (§4.2). **A15:** se restauran las 9 tablas que la
6ª pasada había fusionado; `scout_provider_calls` desaparece porque solo existía para sustituir a
`scout_fetch_attempts`, `scout_search_queries` y `scout_budgets`.

```
── Configuración (6) ─────────────────────────────────────────────────────────────────────────
scout_profiles
- id uuid PK · version int · source_cv_uuid string (uuid del registro en `cvs`; sin FK entre módulos)
- cv_hash char(64) (sha256 del raw_text importado; detecta «perfil desactualizado») · confirmed_skills jsonb · potential_skills jsonb
- proof_points jsonb   ([{key, title, summary, skills[], sectors[], outcome, url}] — solo información pública del portfolio)
- languages jsonb · min_hourly_rate_cents int · target_countries jsonb · weights jsonb · thresholds jsonb
- is_active bool
  idx: (is_active)
  (el texto completo del CV NO se copia: se sigue leyendo de `cvs` cuando hace falta reimportar)

scout_sources
- id uuid PK · key string UNIQUE · name · type enum(api,rss,ats_board,search,web) · country char(2) null
- base_url · config jsonb (sin secretos) · frequency_minutes int · priority smallint
- status enum(active,paused,quota_exhausted,failing) · terms_reviewed_at timestamp null
- last_run_at null · last_cursor string null · last_error text null
  idx: (status, priority) · CHECK: status=active ⇒ terms_reviewed_at NOT NULL  (FR-13)

scout_suppressions          (FR-17; empresas — prevalece sobre todo)
- id uuid PK · canonical_domain string null · company_tax_id string null (NIPC, para la lista DGC)
- company_name_normalized string null · source enum(manual,objection,dgc_list) · reason · list_period char(7) null · created_at
  partial UNIQUE(canonical_domain) WHERE canonical_domain IS NOT NULL · idx: (company_tax_id) · (company_name_normalized)

scout_decision_rules        (FR-19)
- id uuid PK · version int · sample_size int · window_days int · thresholds jsonb
- period_start date · locked_at timestamp null · outcome enum(no_signal,lukewarm,works) null · evaluated_at null

scout_ai_settings           (US-10; una fila por propósito)
- id uuid PK · purpose enum(extraction,drafting) UNIQUE · provider string · model string
- fallback_provider string null · fallback_model string null
  (se siembra con los defaults de `.env`; valores validados contra config('lead-scout.ai.catalog'); nunca texto libre)

scout_budgets               (US-8; Q3 = €30)
- id uuid PK · period char(7) · category enum(search,extraction,ai) · limit_micros bigint · spent_micros bigint
  UNIQUE(period, category)
  (el límite se siembra desde config y es editable; el gasto se incrementa de forma atómica —UPDATE … SET spent_micros = spent_micros + ?— en cada llamada de pago)

── Captación (4) ─────────────────────────────────────────────────────────────────────────────
scout_companies  [LogsActivity: company_type, resolution_status]
- id uuid PK · name · canonical_domain string UNIQUE null · aliases jsonb · country char(2) null · city null
- company_type enum(software_agency,consultancy_nearshore,product_company,recruiter,large_outsourcer,other,unknown)
- employee_range enum(solo,2_4,5_10,11_50,51_200,201_plus,unknown) · resolution_status enum(resolved,unresolved)
- origin enum(job_posting,discovery,manual,import) · origin_ref string null (consulta, fuente o archivo) · discovery_wave smallint null (1-3)
- team_size_observed smallint null · has_decision_maker bool default false · needs_research bool default false
- activity_status enum(active,stale,inactive,unknown) default unknown · last_activity_at date null   (A14: vitalidad)
- timezone_overlap_hours smallint null   (solape con Portugal; B1 y geo)
- **datos públicos de empresa (A16, FR-38; nunca de persona física, FR-39):** legal_name string null · legal_form string null
  · tax_id string null (NIF/NIPC) · registry_info string null · founded_year smallint null · services jsonb · sectors jsonb
  · site_languages jsonb · client_companies jsonb · public_urls jsonb ({page_type: url}) · public_data_evidence jsonb
  ([{field, url, excerpt, captured_at, method}])
- first_seen_at · last_enriched_at null · softDeletes
  idx: (company_type, country) · (tax_id) · (resolution_status) · (origin) · (needs_research) · (activity_status) · (discovery_wave)

scout_job_postings
- id uuid PK · company_id FK→scout_companies null · fingerprint char(64) UNIQUE · canonical_url
- title · description_text text · country char(2) · city null
- remote_mode enum(remote,hybrid,onsite,unknown) · contract_type enum(employment,contract,freelance,internship,unknown)
- language char(2) null · salary_min_cents null · salary_max_cents null · currency char(3) null
- matched_terms jsonb · published_at · expires_at null · status enum(active,expired)
  idx: (status, published_at desc) · (company_id)

scout_job_posting_sources   (pivot)
- job_posting_id FK · source_id FK · external_id string · source_url · fetched_at
  UNIQUE(source_id, external_id)

scout_search_queries        (FR-12: caché de búsquedas de 30 días + efectividad por familia y ola)
- id uuid PK · query_hash char(64) UNIQUE · provider · query text · search_depth enum(basic,advanced)
- purpose enum(discovery,resolution) · discovery_wave smallint null · family string null
- results jsonb · result_count int · new_companies_count int default 0
- status enum(success,failed,quota_exhausted,budget_exceeded) · estimated_cost_micros bigint · executed_at
  idx: (executed_at) · (discovery_wave, family)
  (una consulta con executed_at < 30 días se sirve desde aquí sin llamar a Tavily)

── Evidencia y score (5) ─────────────────────────────────────────────────────────────────────
scout_fetched_pages
- id uuid PK · company_id FK · url · url_hash char(64) UNIQUE · page_type enum(home,services,about,team,careers,contact,case_studies,blog,partners,legal,privacy,other)
- content_markdown text null · content_hash char(64) · content_pruned_at timestamp null · http_status smallint · fetched_at
- forms_summary jsonb null   (US-12: [{fields:[…], has_textarea, has_captcha, action_host}]; nunca HTML completo)
  idx: (company_id, page_type) · (fetched_at)

scout_fetch_attempts        (US-8 CA-3; append-only)
- id uuid PK · company_id FK null · url_hash char(64) · method enum(cache,api,rss,sitemap,direct_http,firecrawl)
- attempt_no smallint · status enum(success,failed,skipped_robots,quota_exhausted,blocked,budget_exceeded,proxy_mismatch)
- http_status null · duration_ms int · estimated_cost_micros bigint · failure_reason null · created_at
  idx: (method, created_at) · (url_hash) · (company_id, status)

scout_signals
- id uuid PK · company_id FK · job_posting_id FK null · fetched_page_id FK null
- dimension enum(technical,commercial,remote,communication,recurring,geo_contract,vitality) · signal_key string (allowlist)
- value jsonb · nature enum(fact,inference) · confidence decimal(3,2)
- evidence_url · evidence_excerpt varchar(500) · extraction_method enum(rule,llm,manual) · extractor_version · captured_at
- ai_provider string null · ai_model string null            (FR-22: qué IA produjo la señal)
  UNIQUE(company_id, signal_key, evidence_url) · idx: (company_id, dimension)

scout_score_results
- id uuid PK · company_id FK · profile_id FK · rules_version
- technical, commercial, remote, communication, recurring, geo_contract, vitality smallint
- lead_score smallint · evidence_confidence smallint · tier enum(A,B,C,reject) · is_current bool · computed_at
- discard_reason enum(suppressed,large_outsourcer,low_technical,onsite_abroad,solo_freelancer,inactive,dead_or_acquired) null
  idx: (is_current, tier, lead_score desc) · partial UNIQUE(company_id) WHERE is_current

scout_score_reasons
- id uuid PK · score_result_id FK · signal_id FK null · dimension · points smallint · nature enum(fact,inference) null · explanation
  idx: (score_result_id)

── Personas y canales (4) ────────────────────────────────────────────────────────────────────
scout_contacts              (SOLO decisores; datos personales mínimos — US-11, Q11, Q16, Q17)
- id uuid PK · company_id FK · full_name · role_title
- role_category enum(founder,executive,technical_lead) · is_primary bool
- published_email string null            (solo publicado en el dominio de la empresa, junto al nombre)
- public_profile_url string null         (solo si la web de la empresa lo enlaza; nunca se visita)
- source enum(structured_data,team_page,about_page,job_posting,manual)
- evidence_url · evidence_excerpt varchar(500) · captured_at
- email_kind enum(nominative,generic) null   (nominativo en PT = zona gris; ES = no usar en frío)
- legal_basis enum(legitimate_interest) · retention_until date · anonymized_at timestamp null
- contact_deadline_at timestamp          (captured_at + 30 d; si no hubo contacto → anonimizar, FR-27)
- notified_at timestamp null             (art. 14: aviso incluido en el primer contacto enviado, FR-26)
- last_verified_at timestamp             (re-verificación si > 90 d antes del borrador, FR-28)
  idx: (company_id, is_primary) · (retention_until) · (contact_deadline_at) · partial UNIQUE(company_id) WHERE is_primary

scout_contact_channels      (US-12, FR-31; canales de la EMPRESA, no de personas)
- id uuid PK · company_id FK · job_posting_id FK null
- type enum(job_posting_apply,freelance_call,partner_page,contact_form,careers_form,generic_email,company_network_page)
- url · generic_email string null (solo buzones genéricos del dominio de la empresa)
- form_fields jsonb null · has_captcha bool null
- audience enum(founder_or_sales,hr_recruiting,unknown)
- evidence_excerpt varchar(500) · detected_at · last_verified_at · status enum(active,broken,used)
  UNIQUE(company_id, type, url) · idx: (company_id, status)

scout_contact_objections    (FR-25; sin PII en claro)
- id uuid PK · person_hash char(64) UNIQUE (sha256 de nombre normalizado + dominio canónico) · created_at

scout_privacy_requests      (FR-29; registro de responsabilidad proactiva, sin copiar PII — lo escribe `lead-scout:privacy`)
- id uuid PK · type enum(access,erasure,objection,rectification) · subject_ref char(64) (hash)
- received_at · resolved_at null · outcome enum(fulfilled,no_data_found,rejected) null · notes text null

── Funnel (3) ────────────────────────────────────────────────────────────────────────────────
scout_outreaches  [LogsActivity: stage, send_medium, sent_at, reply_outcome]   (registro de envío manual, A18)
- id uuid PK · company_id FK (lead) · contact_id FK null · job_posting_id FK null · signal_id FK null
- contact_channel_id FK null      (US-12: canal detectado usado → tasa de respuesta por tipo de canal)
- send_medium enum(email,contact_form,job_posting,linkedin_manual) null   (obligatorio al pasar a `sent`)
- outreach_kind enum(contractor_offer,employment_application) default contractor_offer
  (employment_application = candidatura clásica a empleo fijo, medida aparte — clarify Q6)
- message_variant enum(hiring_signal,stack_match,legacy_maintenance,shared_sector) · language char(2)
- template_key string · template_version string   (plantillas versionadas en config/lead-scout.php; sin tabla)
- draft_body text · ai_provider string null · ai_model string null
- stage enum(draft,ready,sent,replied,positive,call,trial,won,recurring,lost,do_not_contact)
- operator_id FK→users null (obligatorio al pasar a `sent`) · sent_at null · stage_changed_at · notes text null
- sender_kind enum(personal_mailbox,business_domain) null   (A19: obligatorio al pasar a `sent`; default en config; segmenta métricas)
- legal_rule_status enum(verified_allowed,pending_acknowledged) null · legal_ack_at timestamp null
  (estado de la regla del país para ese medio al enviar; `pending_acknowledged` exige `legal_ack_at`)
- reply_outcome enum(interested,not_interested,unsubscribe) null · replied_at null
  idx: (stage, sent_at) · (company_id) · (send_medium, sent_at) · (reply_outcome)
  CHECK: stage=sent ⇒ send_medium, sent_at, operator_id y sender_kind NOT NULL

scout_outreach_stage_events (histórico para métricas; append-only)
- id uuid PK · outreach_id FK · from_stage · to_stage · reply_outcome null · operator_id FK→users · occurred_at
  idx: (outreach_id, occurred_at) · (to_stage, occurred_at)

scout_opportunities  [LogsActivity: status, hourly_rate_cents, hours_per_month]
- id uuid PK · outreach_id FK · type enum(trial,project,retainer,staff_augmentation)
- hours_per_month null · hourly_rate_cents null · amount_cents null · currency char(3)
- status enum(open,won,lost,active,ended) · started_at null · ended_at null
  idx: (outreach_id) · (status)   (un contacto puede tener varias oportunidades)
```

**Relaciones bidireccionales** (regla del proyecto): Company hasMany JobPostings, FetchedPages,
FetchAttempts, Signals, ScoreResults, Contacts, ContactChannels, Outreaches · ScoreResult hasMany
ScoreReasons · Outreach hasMany StageEvents y Opportunities · Signal hasMany ScoreReasons.
**N+1:** `ListLeadsHandler` usa
`with('currentScore:id,company_id,lead_score,evidence_confidence,tier', 'latestPosting:id,company_id,title,published_at')`
+ `withCount('jobPostings')`; el detalle carga `currentScore.reasons.signal` con columnas explícitas.

### 4.1 22 tablas: por qué se mantienen (A15)

Para PostgreSQL/Supabase **22 tablas no son un problema**: el plan gratuito limita el **tamaño**
(500 MB para todo el proyecto), no el número de tablas; con ellas argenis-hub pasa de 51 a ~73.
El operador eligió conservar el diseño completo. Lo que aporta cada tabla restaurada frente a la
versión fusionada:

| Tabla | Qué aporta |
|---|---|
| `scout_ai_settings` | Cambiar el proveedor/modelo por defecto de cada propósito desde la web, sin tocar `.env` |
| `scout_budgets` | Límites editables desde la web y gasto acumulado por mes y categoría (búsqueda, extracción, IA) |
| `scout_search_queries` | Caché de búsquedas con sus resultados guardados y métricas por familia y ola (resultados → empresas nuevas) |
| `scout_fetch_attempts` | Registro de cada intento de descarga (método, estado, bloqueos, coste) para la escalera de coste y las métricas de extracción |
| `scout_score_reasons` | Razones del score con clave foránea real a la señal; consultables por separado |
| `scout_contact_objections` | Oposiciones de personas separadas de las supresiones de empresas |
| `scout_privacy_requests` | Registro propio y consultable de las solicitudes RGPD (sigue gestionándose por comando) |
| `scout_outreach_stage_events` | Historial completo de cambios de etapa, incluidas idas y vueltas |
| `scout_opportunities` | Varias oportunidades por contacto (prueba, luego retainer…) con su propio historial |

**Coste de restaurarlas:** 8 migraciones/modelos/factories más, la pantalla de ajustes de IA y la
edición de presupuestos en la bandeja. Con el colchón de 2 meses (A12) suma ~1-2 días al MVP; entra
en las semanas 1 y 3 sin mover el checkpoint de la semana 2.

**Estimación de tamaño en Supabase:** markdown medio ~25 KB × 4 páginas × ~300 empresas/mes ≈
30 MB/mes; con la poda de markdown a los 30 días (T070) se estabiliza en ~30-40 MB. `results` de
`scout_search_queries` (~60 consultas/mes) y el resto de tablas son filas pequeñas → **< 100 MB**.
Ojo: los 500 MB del plan Free son **para todo argenis-hub**, no solo para LeadScout.

### 4.2 Supabase: conexión, seguridad y operación (research R12)

| Tema | Decisión | Motivo |
|---|---|---|
| Conexión | **Pooler compartido, modo sesión** (`aws-[INDEX]-[REGION].pooler.supabase.com:5432`, usuario `postgres.[PROJECT-REF]`, `sslmode=require`) para web, workers, scheduler y migraciones. Copiar el host del diálogo *Connect* | La conexión directa (`db.[ref].supabase.co`) es **solo IPv6** sin el add-on de IPv4; el pooler es IPv4 en todos los planes |
| Modo transacción (6543) | **No usar** salvo necesidad; si se usa, `PDO::ATTR_EMULATE_PREPARES => true` y nunca para migraciones | Exige desactivar prepared statements |
| **Exposición de datos** | Cada migración `scout_*` ejecuta `ENABLE ROW LEVEL SECURITY` **sin políticas** y `REVOKE ALL … FROM anon, authenticated` (si existen). Si argenis-hub no usa la Data API → **desactivarla** (OP-19) | Supabase expone el esquema `public` por la Data API: una tabla sin RLS es legible y **escribible** por cualquiera con la clave pública. `scout_contacts` tiene datos personales. Laravel conecta como `postgres`, propietario de las tablas, así que RLS no le afecta |
| Tests | Suite en sqlite `:memory:` (como hoy) + grupo `pgsql` en PostgreSQL **local**; guard que aborta si `DB_HOST` apunta a Supabase (T003) | Un `.env.testing` mal copiado haría que `RefreshDatabase` borrara la base en la nube. El grupo `pgsql` cubre el riesgo sqlite ≠ PostgreSQL (antes R4) |
| Migraciones en la nube | Solo tras el pase de trazabilidad (T081) y con confirmación del operador | Evitar esquemas a medias en la base real |
| Región y RGPD | Región **UE**; Supabase como **encargado** en el RoPA y DPA verificado (OP-10, OP-12, OP-19) | Los decisores son datos personales alojados en la nube |
| **Plan Free (confirmado por el operador)** | 500 MB (todo el proyecto) · **pausa tras 1 semana de inactividad** · **sin backups automáticos** → poda de markdown (T070) + `lead-scout:backup` semanal con `pg_dump` (T077, **obligatoria**, adelantada a la semana 2) · anotar el tamaño actual de la base (OP-19) | Si el PC está apagado ≥ 7 días, el proyecto se pausa y hay que reanudarlo en el panel |
| Colas | `database` sirve (tabla `jobs` en Supabase), con latencia de red en cada sondeo. Si molesta, Redis local en Herd solo para colas | Volumen bajo (cientos de jobs/día) |
| Latencia | Bandeja con ≤ 5 consultas por página, columnas explícitas y paginación (T060) | Cada viaje a la nube cuesta decenas de ms |

## 5. Contratos HTTP (web, sesión; sin `api.php` en el MVP)

Todas bajo `auth` + `permission:*` + `throttle`. Listas paginadas (15 por defecto, máx. 100,
OWASP §14). Respuestas = Spatie Data.

| Método y ruta | Historia | Request | Response 200 | Errores | Permiso |
|---|---|---|---|---|---|
| `GET /lead-scout` | US-5 | — | Página Inertia (bandeja) | 403 | `VIEW_ANY_LEAD_SCOUT` |
| `GET /data/admin/lead-scout/leads` | US-5 | `LeadFilterData` (tier[], country[], company_type[], signal_type[], stage[], origin[], needs_research?, search, per_page) | `LeadListItemData` paginado | 422 | `VIEW_ANY_LEAD_SCOUT` |
| `GET /data/admin/lead-scout/leads/export` | FR-21 | mismo filtro + `dataset=leads\|funnel` | CSV/XLSX de la bandeja o del funnel (outreaches + etapas + acuerdos) | 422 | `EXPORT_LEAD_SCOUT` · throttle export |
| `GET /data/admin/lead-scout/leads/{uuid}` | US-3, US-4 | — | `LeadDetailData` (empresa, origen, ofertas, score, razones con evidencia, decisores con su evidencia y `cold_email_allowed`, **canales ordenados** con `rank`, `allowed`, `blocked_reason` y aviso de audiencia) | 404 | `VIEW_LEAD_SCOUT` |
| `POST /data/admin/lead-scout/leads` | FR-20 | `CreateManualLeadData` (name, url, note) | `LeadDetailData` 201 | 422, 409 si está suprimida | `CREATE_LEAD_SCOUT` |
| `POST /data/admin/lead-scout/leads/{uuid}/rescore` | US-4 | — | `ScoreResultData` | 404 | `UPDATE_LEAD_SCOUT` |
| `POST /data/admin/lead-scout/leads/{uuid}/drafts` | US-5, US-10 | `GenerateDraftData` (job_posting_id?, variant?, language?, **provider?**, **model?** — elegidos en el selector, validados contra el catálogo) | `OutreachData` 201 (+ `channel_warning`, `ai_provider`, `ai_model`) | 404, 409 si está suprimida, 422 proveedor/modelo fuera del catálogo o sin credenciales, 429 LLM, 402 presupuesto IA agotado | `UPDATE_LEAD_SCOUT` · throttle `lead-scout-llm` |
| `GET` / `PUT /data/admin/lead-scout/ai-settings` | US-10 | `UpdateAiSettingsData` (purpose, provider, model, fallback_provider?, fallback_model?) | `AiSettingsData` (valor actual por propósito + `options[]` {provider, model, label, available, unavailable_reason, est_cost_per_100_usd}; las mismas opciones alimentan el selector del borrador) | 422 fuera del catálogo o proveedor sin credenciales | `UPDATE_LEAD_SCOUT` |
| `POST /data/admin/lead-scout/leads/{uuid}/contacts` · `PATCH /data/admin/lead-scout/contacts/{uuid}` | US-11 | `UpsertContactData` (full_name, role_title, role_category — `Rule::in`, published_email?, public_profile_url?, is_primary?) | `ContactData` (+ `cold_email_allowed` según país) | 404, 422 (cargo de la lista de exclusión; email fuera del dominio de la empresa), 409 (persona con oposición registrada) | `UPDATE_LEAD_SCOUT` |
| `POST /data/admin/lead-scout/contacts/{uuid}/objection` | US-11, FR-25 | — | 204 (anonimiza + `person_hash` en `scout_contact_objections` + fila en `scout_privacy_requests` de tipo `objection`) | 404 | `DELETE_LEAD_SCOUT` |
| `POST /data/admin/lead-scout/outreaches/{uuid}/opportunities` · `PATCH /data/admin/lead-scout/opportunities/{uuid}` | US-6 (horas y €) | `OpportunityData` (type, hours_per_month?, hourly_rate_cents?, amount_cents?, currency, status, started_at?, ended_at?) | `OpportunityData` (201 al crear) | 404, 422 | `UPDATE_LEAD_SCOUT` |
| `PATCH /data/admin/lead-scout/channels/{uuid}` | US-12 | `UpdateChannelData` (status: active\|broken) | `ContactChannelData` | 404, 422 | `UPDATE_LEAD_SCOUT` |
| `PATCH /data/admin/lead-scout/outreaches/{uuid}` | US-5, US-6, US-12, FR-41 | `UpdateOutreachData` (draft_body?, stage?, notes?; al pasar a `sent`: **contact_channel_id**, **send_medium** — `Rule::in(email, contact_form, job_posting, linkedin_manual)` —, `acknowledge_pending_legal` si la regla está pendiente; `operator_id` lo pone el servidor con el usuario autenticado, nunca el cliente) | `OutreachData` | 404, 409 (empresa suprimida), 422 (transición inválida, claim no confirmado, medio no válido, regla pendiente sin confirmación, regla que bloquea) | `UPDATE_LEAD_SCOUT` |
| `POST /data/admin/lead-scout/outreaches/{uuid}/reply` | US-6, FR-42, FR-43 | `RecordReplyData` (outcome — `Rule::in(interested, not_interested, unsubscribe)`, replied_at?, notes?) | `OutreachData` (etapa `positive` \| `lost` \| `do_not_contact`) · `unsubscribe` además crea la supresión de la empresa y, si el buzón era nominativo, la oposición de la persona | 404, 422 (outreach no enviado) | `UPDATE_LEAD_SCOUT` |
| `POST /data/admin/lead-scout/suppressions` | FR-17 | `SuppressData` (domain, reason) | 201 | 422 | `DELETE_LEAD_SCOUT` |
| `GET` / `PUT /data/admin/lead-scout/profile` | US-1 | `ProfileData` | `ProfileData` (versión nueva al guardar) | 422 | `UPDATE_LEAD_SCOUT` |
| `GET /data/admin/lead-scout/profile/cvs` | US-1 | — | `CvOptionData[]` (uuid, title, niche, file_type, is_primary, updated_at, `importable`; **sin** `raw_text`) de los CVs del operador | — | `UPDATE_LEAD_SCOUT` |
| `POST /data/admin/lead-scout/profile/import-cv` | US-1 | `ImportCvData` (`cv_uuid` — `Rule::exists` limitado a los CVs del usuario autenticado) | `ProfileData` (confirmadas/potenciales, `proof_points`, `stale=false`) | 404 CV ajeno o borrado, 422 CV sin `raw_text` (PDF sin texto), 429 | `UPDATE_LEAD_SCOUT` |
| `GET /data/admin/lead-scout/sources` · `PATCH /{uuid}` · `POST /{uuid}/run` | US-2 | `UpdateSourceData` (status, frequency, terms_reviewed_at) | `SourceData` | 422 (activar sin términos revisados), 409 (ya en ejecución) | `UPDATE_LEAD_SCOUT` |
| `GET /data/admin/lead-scout/metrics` | US-6 | `MetricsFilterData` (from, to, group_by) | `FunnelMetricsData` (+ lectura de muestra: esperado, observado, `conclusive`) | 422 | `VIEW_ANY_LEAD_SCOUT` |
| `POST /data/admin/lead-scout/decision-rules` · `POST /{uuid}/lock` | FR-19 | `DecisionRuleData` | `DecisionRuleData` | 409 si ya está bloqueada | `UPDATE_LEAD_SCOUT` |
| `GET` / `PUT /data/admin/lead-scout/budgets` | US-8 | `BudgetData[]` (category, limit_micros) | `BudgetStatusData` (límite, gasto del mes, % y estado por categoría) | 422 | `UPDATE_LEAD_SCOUT` |

**Comandos de administración (sin endpoint, A3/A4):**

| Comando | Historia | Qué hace |
|---|---|---|
| `lead-scout:ingest {--source=}` | US-2 | Ingesta programada |
| `lead-scout:discover {--country=} {--family=}` | US-7 | Descubrimiento semanal |
| `lead-scout:import-leads {file}` | FR-20 | Importa la lista ICP en CSV |
| `lead-scout:score` · `:expire` · `:prune` | US-4, US-2, FR-27 | Re-score, caducidad, retención y poda de markdown |
| `lead-scout:import-dgc {file} {--period=}` | FR-17 | Lista DGC trimestral a supresión |
| `lead-scout:privacy {search\|export\|erase\|object\|lift-suppression} {--name=} {--email=} {--domain=} {--evidence=}` | FR-29, FR-43 | Derechos de las personas, registrados en `scout_privacy_requests` sin PII. `lift-suppression` solo si la propia empresa vuelve a iniciar el contacto: exige `--evidence` y queda en el activity log; es la **única** vía para levantar una baja |
| `lead-scout:backup` | NFR (Supabase Free) | `pg_dump` semanal de `scout_*` |

Sin selección de filas en la bandeja del MVP → **no** se añaden BulkDelete/BulkRestore (la
regla solo aplica cuando hay selección). Rutas `/export` antes de `/{uuid}`; `->whereUuid('uuid')`.

## 6. Estructura de carpetas propuesta

```
src/Modules/LeadScout/
├── Providers/LeadScoutServiceProvider.php        (bindings de puertos, registerWebRoutes, rate limiters)
├── Domain/
│   ├── Enums/                (CompanyType, CompanyOrigin, RemoteMode, ContractType, SignalDimension, SignalNature, Tier, OutreachStage, OutreachChannel, MessageVariant, ProviderName, ProviderOperation, CallStatus, BudgetCategory, SuppressionKind, SuppressionSource, DealType, DealStatus…)
│   ├── ValueObjects/         (CanonicalDomain, PostingFingerprint, SkillTaxonomy, RoleTaxonomy)
│   ├── Services/             (RuleBasedSignalExtractor, DecisionMakerExtractor, ContactChannelDetector, PublicCompanyDataExtractor, PersonalDataScrubber, ScoringEngine, TierClassifier, FetchLadder, ChannelAdvisor, SuppressionGate, DecisionRuleEvaluator, BudgetLedger)
│   ├── Ports/                (JobSourcePort, SearchPort, PageFetcherPort, SignalExtractorPort, DraftWriterPort, AiModelCatalogPort, Company/JobPosting/OutreachRepositoryPort)
│   └── Exceptions/           (BudgetExceededException, SourceTermsNotReviewedException, InvalidStageTransitionException)
├── Application/
│   ├── DTOs/                 (Spatie Data: filtros, requests, respuestas, RawPosting, SearchResult, CandidateSignal, AiSettingsData, OpportunityData)
│   ├── Commands/             (handlers de §3.1, planos)
│   └── Queries/              (handlers de §3.1, planos)
├── Infrastructure/
│   ├── JobSources/           (ItJobsApiSource, LandingJobsApiSource, RssFeedSource, ArbeitnowApiSource)
│   ├── Cvs/                  (EloquentCvSource — lectura de la tabla cvs)
│   ├── Search/               (TavilySearchAdapter)
│   ├── Fetching/             (DirectHttpPageFetcher, FirecrawlPageFetcher, RobotsTxtPolicy, OutboundUrlGuard)
│   ├── Ai/                   (ExtractCompanySignalsAgent, WriteOutreachOpenerAgent, LaravelAiSignalExtractor, LaravelAiDraftWriter, ConfigAiModelCatalog)
│   ├── Queue/                (IngestSourceJob, DiscoverAgenciesJob, EnrichCompanyJob, ExtractSignalsJob, ScoreCompanyJob)
│   ├── Console/Commands/     (Ingest, Discover, ImportLeads, Score, Expire, Prune, ImportDgc, Privacy, Backup) — planificados en routes/console.php como los demás módulos
│   ├── Http/Controllers/     (§5) · Http/Requests/ · Http/Export/
│   ├── Persistence/Eloquent/Models/ (Scout*EloquentModel) · Persistence/Repositories/ · Persistence/SupabaseRls.php
│   └── Routes/web.php
└── Tests/
    ├── Feature/              (ingesta, descubrimiento, importación, resolución, enriquecimiento, extracción, scoring end-to-end, bandeja, borradores, métricas, presupuesto, supresión, privacidad, permisos, esquema + RLS)
    ├── Unit/                 (ScoringEngine, TierClassifier, FetchLadder, ChannelAdvisor, DecisionRuleEvaluator, BudgetLedger, ProofPointMatcher, VOs)
    └── Fixtures/             (JSON ITJobs/Landing.jobs, XML RSS, HTML sintético de agencias, respuestas Tavily/Firecrawl, CSV ICP)

database/migrations/2026_09_*_create_scout_*_table.php
resources/js/modules/lead-scout/…  (según ARCHITECTURE-VUE; definir en /frontend-new)
```

**Reutilizado sin cambios:** `TavilyClientInterface`, `FirecrawlClientInterface`,
`CircuitBreakerInterface`, `UsesPromptCache`, `AIClientInterface`, activity log. **Cambio mínimo
propuesto** (D5): que Tavily acepte `search_depth` por llamada, para usar `basic` en LeadScout sin
alterar CourseScripts.

## 7. Estrategia de testing

- **Dónde corren:** hoy `phpunit.xml` de argenis-hub usa **sqlite `:memory:`** (verificado), así
  que la suite no toca Supabase. Se mantiene para no alterar el resto de módulos. Los tests de
  LeadScout que dependen de PostgreSQL (CHECK, índices parciales, `jsonb`, RLS) van en el grupo
  Pest **`pgsql`**, contra un PostgreSQL **local** con la misma versión mayor que Supabase. Las
  migraciones son compatibles con ambos motores (el helper de RLS no hace nada en sqlite). El guard
  de `tests/Pest.php` aborta si `DB_HOST` apunta a Supabase (T003).
- **Unit (Pest):** `ScoringEngine` con golden tests (mismas señales → mismas razones y totales;
  inferencia × 0,6; topes; vacante activa; ejemplos «87» y «75»), `TierClassifier` (bordes de cada umbral,
  `needs_research`), `FetchLadder` (caché fresca, robots disallow, presupuesto agotado → sin
  Firecrawl, **401/403/429 o página de desafío → `blocked` y ninguna llamada a Firecrawl**),
  `ProofPointMatcher` (señal «reservas» → Servispin; empate → la más reciente), `BudgetLedger` (suma por categoría y mes), `ChannelAdvisor` (ES → sin email en frío;
  PT buzón genérico `allow` pendiente → no recomendado; tras verificarse → email con opt-out), `DecisionRuleEvaluator` (incluido «no concluyente»), `CanonicalDomain`
  (www, IDN, subdominios careers.), `PostingFingerprint`.
- **Feature (Pest):** `Http::fake` para todas las fuentes, Tavily y Firecrawl; `AgentFake`
  para los agentes; `Queue::fake` / cadenas síncronas. Casos clave:
  - **esquema (grupo `pgsql`):** 22 tablas `scout_*` con RLS activado (`pg_class.relrowsecurity`);
  - **perfil desde `cvs`:** CV markdown principal del operador (factory del módulo Cvs con el
    fixture `Argenis_Gonzalez_CV_2026.md` como `raw_text`) → Laravel, Vue e Inertia confirmados,
    AWS potencial, `proof_points` de Servispin/AquaShield/Vidula; CV de otro usuario → 404; CV
    borrado → 404; PDF sin `raw_text` → 422; `raw_text` cambiado → perfil `stale`; la respuesta y
    los logs **no** contienen `raw_text`; ningún prompt de IA contiene el CV completo;
  - **sin evasión:** `Http::fake` con 403 + página de desafío (p. ej. marcadores de CAPTCHA o
    «checking your browser») → intento `blocked` y **ninguna** petición a Firecrawl
    (`Http::assertNotSent`); `robots.txt` con `Disallow` → `skipped_robots`; URL o resultado de
    búsqueda de `linkedin.com` → descartado sin petición; toda llamada a Firecrawl lleva
    `proxy: "basic"` y `storeInCache: false`; respuesta con `metadata.proxyUsed = "enhanced"` →
    contenido descartado, intento `proxy_mismatch` y Firecrawl desactivado; toda petición a Tavily
    lleva la denylist en `exclude_domains` y ninguna consulta contiene un nombre de persona;
  - **datos públicos de empresa (A16, fixtures HTML sintéticos PT/ES/EN):** JSON-LD con
    `legalName`, `taxID` y `foundingDate` + aviso legal con «S.L.» o «Lda.» → campos rellenados con
    evidencia; teléfono y dirección completa en el pie → **no** guardados; aviso legal de un
    autónomo (nombre y apellidos + NIF de persona física) o «empresário em nome individual» →
    ningún dato de identificación guardado y `solo_freelancer`; clave fuera de la allowlist →
    ignorada; NIPC en la lista DGC → suprimida;
  - **limpieza antes de la IA:** página con testimonio firmado, autor de blog, email y teléfono →
    el cliente espía no recibe ninguno;
  - **envío humano y registro (A18):** test de arquitectura sin `Mail`/`Notification`/mailer en
    `Modules\LeadScout`; borrador para Tier C → 409; `sent` sin `send_medium`, canal u operador →
    422; `operator_id` enviado por el cliente → ignorado y sustituido por el usuario autenticado;
    `sent` guarda `template_key`, `template_version` y `sender_kind` (sin él → 422); métricas segmentables por `sender_kind` (A19);
  - **respuestas y bajas:** `interested` → `positive`; `not_interested` → `lost`; `unsubscribe` →
    `do_not_contact` + supresión; después, la misma empresa en descubrimiento, CSV, alta manual u
    oferta nueva → no se crea ni enriquece, borrador y `sent` → 409, 0 llamadas a la IA; ninguna
    ruta web levanta la supresión;
  - **reglas pendientes:** regla PT buzón genérico `allow` + `pending_verification` → canal con
    aviso y no recomendado; `sent` sin `acknowledge_pending_legal` → 422, con él → `legal_ack_at`
    guardado; regla `block` pendiente (ES) → se aplica igual;
  - misma oferta en 2 fuentes → 1 posting con 2 pivots;
  - **descubrimiento:** resultado de portal de empleo o directorio → descartado; dominio ya
    existente → no se duplica; consulta repetida en la ventana → 0 llamadas; empresa suprimida →
    no se crea; presupuesto de búsqueda agotado → sin llamadas;
  - **importación ICP:** CSV con filas válidas, duplicadas e inválidas → solo las válidas, con
    informe;
  - Tavily devuelve 432 → breaker abierto, `quota_exhausted` y el resto sigue;
  - el LLM devuelve un fragmento inexistente en la página → señal descartada;
  - `needs_research` → una sola ronda extra y re-score (sin bucle);
  - **calidad de agencia (A14, fixtures HTML sintéticos PT/ES/EN):** «Soy Juan, desarrollador
    Laravel freelance» → Descartar `solo_freelancer`; último caso de 2021 + sitemap de 2022 + ©
    2022 → Descartar `inactive`; dominio que redirige (301) a otra empresa → `dead_or_acquired`;
    sin ninguna fecha → `activity_status=unknown`, no se descarta y queda `needs_research`; equipo
    de 3 personas sin vacante → como máximo Tier B; equipo de 3 con vacante activa `fact` → puede
    ser A; «daily client calls, native English» → Comunicación 25 sin descartar; agencia
    remote-first en NL → golden «75» (Tier B); empresa de la ola 2 sin regla de email configurada →
    `ChannelAdvisor` sin email en frío;
  - generar un borrador para una empresa española → `channel=job_application_reply|contact_form`
    + warning; empresa de descubrimiento sin oferta → nunca `job_posting_apply`;
  - un borrador con «AWS» sin confirmar → 422 al marcarlo `ready`;
  - regla de decisión bloqueada → 409 al editar; con 30 contactos → «no concluyente»;
  - empresa suprimida → no aparece en la bandeja y el borrador devuelve 409;
  - presupuesto IA agotado → 402 y no se llama al agente;
  - permisos: 403 para cada ruta sin permiso;
  - `PrunePersonalDataHandler` anonimiza contactos vencidos y vacía el markdown de > 30 días;
  - decisores (fixtures HTML sintéticos PT/ES/EN): «Fundador & CEO», «CTO» y «Sócio-gerente» se
    guardan; «Talent Acquisition», «Recruiter», «RRHH», «Desarrollador Senior» y «Tech Lead»
    **no**; una página de 12 personas → `team_size_observed=12` y solo los decisores
    persistidos; `info@` y `joao@gmail.com` no se asignan; `joao@agencia.pt` junto al nombre sí;
    `cold_email_allowed`: PT buzón genérico → `false` + «verificación legal pendiente» mientras la regla no esté `verified` (true después) · PT nominativo → false + aviso «zona gris» ·
    ES → false; persona en `scout_contact_objections` → no se re-extrae; lead Tier C → decisores no
    persistidos; contacto sin enviar a los 30 días → anonimizado; todo borrador contiene aviso del
    art. 14 + línea de baja y `notified_at` se fija al marcarlo `sent`; captura > 90 días →
    re-verificación antes del borrador;
  - **privacidad por comando:** `lead-scout:privacy export` devuelve solo los datos de esa
    persona; `erase` anonimiza; el registro en `scout_privacy_requests` no contiene nombre ni email;
    **cliente de IA espía: ningún prompt contiene nombres extraídos ni contenido de páginas
    `team`**; lead sin decisor → sigue en la bandeja con `has_decision_maker=false`;
  - canales (fixtures HTML sintéticos PT/ES/EN): formulario con email + mensaje en `/contacto` →
    `contact_form`; formulario en `/trabaja-con-nosotros` o `/carreiras` → `careers_form` con
    `audience=hr_recruiting` **extraído** y rank bajo; «trabajamos con freelancers» →
    `freelance_call` + señal comercial `fact`; `info@agencia.es` → `generic_email`, pero
    `joao@agencia.pt` no (va a contactos); ningún teléfono guardado; **no hay petición HTTP a la
    URL `action` del formulario** (`Http::assertNotSent`); `forms_summary` no contiene HTML;
    `ChannelAdvisor` en ES descarta `generic_email` con motivo LSSI y en PT lo bloquea si la
    empresa está en la lista DGC; empresa sin canales permitidos → «sin canal permitido» y sin
    borrador de email; `PATCH outreach` a `sent` sin `contact_channel_id` → 422;
  - selector de IA: proveedor/modelo fuera del catálogo → 422; proveedor sin key →
    `available=false` y 422; el principal lanza una excepción → se usa el respaldo y `ai_model`
    registra el respaldo; el cliente espía verifica que `AIClientInterface` recibe el
    provider/model elegido.
- **Eval manual de extracción (fuera de CI, diferida por A12):** 30 páginas etiquetadas a mano
  (de ofertas y de descubrimiento) → precisión ≥ 0,85 en señales comerciales y elección definitiva
  del modelo. Hasta entonces: verificación literal + allowlist + revisión humana del 100 % de los
  borradores.
- **Cierre del módulo:** `php artisan test --compact --filter=LeadScout` → suite completa →
  pipeline de finalización del router (ide-helper, `typescript:transform`, Pint, Scramble,
  `index_repository` full).

## 8. Seguridad y cumplimiento

| Riesgo | Mitigación | Referencia |
|---|---|---|
| **Datos expuestos por la Data API de Supabase** | RLS activado sin políticas + `REVOKE` a `anon`/`authenticated` en todas las `scout_*` (helper de migración + test de esquema); Data API desactivada si no se usa; clave `service_role` nunca en el frontend ni en logs | research R12 · OWASP §1/§5 · T010, T014, T078 |
| **Tests borrando la base en la nube** | Guard de `DB_HOST` + base local de tests | T003 |
| Conexión a la base | `sslmode=require`; credenciales solo en `.env`; la cadena de conexión nunca en logs ni props de Inertia | OWASP §4/§5 · T076 |
| SSRF (URLs de fuentes externas y resultados de búsqueda) | `OutboundUrlGuard`: solo http/https, resolución DNS y bloqueo de rangos privados/metadata; sin redirecciones a IPs privadas; **denylist de redes profesionales y portales con ToS restrictivos** (linkedin.com, indeed.com, glassdoor.*) | OWASP §15 · FR-13 · FR-25 |
| **LinkedIn y redes profesionales** | Ninguna petición a linkedin.com (guard + denylist de resultados de búsqueda); sin cuentas, cookies ni extensiones; solo se guarda la URL de una página de empresa o perfil **si la web de la agencia la enlaza**, y nunca se visita | User Agreement de LinkedIn · LinkedIn v. Proxycurl/ProAPIs · CNIL KASPR (240.000 €) · research R13 |
| **Caché del extractor externo** | Firecrawl guarda por defecto cada página en su índice compartido → `storeInCache: false` en todas las peticiones de LeadScout (test) | FR-13 · research R15 · A16 |
| **Consultas de búsqueda** | La política de Tavily permite usar las consultas para mejorar su servicio → nunca nombres de personas; solo servicio × tecnología × país o nombre de empresa. **Guard previo (A17):** se rechaza sin llamar a Tavily cualquier consulta con `linkedin`, `site:linkedin.com`, `xing` o intermediarios de datos de contacto (Apollo, ZoomInfo, RocketReach, Lusha, Kaspr, Hunter…); esos dominios también en `exclude_domains` y en `OutboundUrlGuard`; nunca `tavily extract` | FR-13, FR-25 · research R15 · A16, A17 |
| **Envío manual desde el buzón** | Sin envío desde el sistema (FR-16). El operador envía uno por uno (≤ 15/día) y marca «enviado» con el canal y `sender_kind`. **Por fases (A19):** desarrollo y pruebas → cuenta personal (Gmail), idealmente hacia direcciones propias; outreach real → buzón del **dominio propio** con cualquier proveedor de correo empresarial con términos de encargo (Workspace es una opción, no un requisito), antes de bloquear la regla de decisión. Enviar a mano no cambia la ley: LSSI art. 21 aplica a un solo email (AEPD A/00008/2019) | FR-16 · A17 · OP-27 |
| **Datos públicos de empresa** | Allowlist cerrada (`NORMATIVA-RGPD.md` §11); sin teléfonos, dirección completa ni imágenes; autónomo/ENI = persona física → no se guarda | Considerando 14 RGPD · LSSI art. 10 · DL 7/2004 art. 10 · FR-38, FR-39 |
| **CAPTCHAs, anti-bot y muros de login** | `FetchLadder`: 401/403/429, desafío o login → `blocked` sin reintento por otra vía; Firecrawl v2 con `proxy: "basic"` (nunca `auto`/`enhanced`) **verificado en `metadata.proxyUsed`**; sin navegador headless propio, sin rotación de IPs, sin falsear User-Agent (se identifica como `LeadScoutBot/1.0 (+URL de contacto)`), sin resolvedores de CAPTCHA | CP español art. 197 bis.1 · Lei 109/2009 art. 6 (PT) · Directiva 2013/40/UE · research R13 · A11 |
| **Texto del CV** (`cvs.raw_text`) | Solo lectura a través de `CvSourcePort`; no se copia a LeadScout; nunca a la IA, logs, exports ni props; a la IA solo llega el resumen público de un `proof_point` | Comentario de seguridad del módulo Cvs (OWASP §12) · A9, A10 |
| Prompt injection en páginas y ofertas | Contenido en bloque delimitado como **datos**; agentes **sin tools**; salida con esquema; allowlist de `signal_key`; verificación literal del fragmento | OWASP §16 LLM01/LLM05/LLM06 |
| Salida del LLM renderizada | Vue con `{{ }}`, nunca `v-html`; borrador tratado como texto no confiable | LLM05 |
| Consumo sin límite | `RateLimiter::for('lead-scout-llm')`, `MaxTokens` y `Timeout` por agente, presupuesto mensual por categoría (búsqueda, extracción, IA) acumulado en `scout_budgets` y aplicado con **cualquier** modelo del selector, circuit breaker | LLM10, OWASP §14 |
| Selección de modelo arbitraria | Catálogo cerrado con IDs **fijados** en config; `Rule::in` en el Data; el frontend nunca envía texto libre; proveedor sin credenciales → rechazado | LLM03, OWASP §3 |
| Datos personales | `PersonalDataScrubber` antes de cada prompt (emails, teléfonos, testimonios, autores, firmas) · **Solo decisores** (lista de cargos permitidos); del resto del equipo solo el recuento. Nombre, cargo, email **publicado por la empresa** en su dominio (Q16) y URL de perfil enlazada; nunca emails adivinados ni servicios de verificación; sin acceso a redes profesionales; solo leads A/B; anonimizar a los 30 días sin contacto o a los 12 meses tras la última interacción (FR-27); oposición → anonimización inmediata + `person_hash` en `scout_contact_objections`; **extracción sin IA** y nombres ocultados antes de cualquier prompt (Q17) | RGPD (minimización, oposición), LLM02, Q11, Q16, Q17 |
| **Capacidad de envío** | Ninguna en el módulo (FR-40): test de arquitectura que prohíbe `Mail`, `Notification`, `Illuminate\Mail`, `Symfony\Component\Mailer` y peticiones POST a URLs `action` de formularios; el operador envía fuera del sistema y registra `sent` con `operator_id` del usuario autenticado (nunca del cliente) | A18 · T085 |
| **Bajas** | `SuppressionGate` único; `unsubscribe` → supresión de empresa + oposición de persona si procede; 409 en borrador y `sent`; sin enriquecimiento ni IA; no se levanta desde la web (solo `lead-scout:privacy lift-suppression --evidence`) | FR-43 · A18 · T084 |
| **Reglas por país** | En config con `decision`, `legal_status` (`pending_verification` \| `verified`), `source_url`, `verified_at`, `verified_by`; todas empiezan pendientes; bloquear se aplica, permitir solo verificado; `sent` por canal pendiente exige `acknowledge_pending_legal` y guarda `legal_ack_at` | FR-44 · A18 · OP-15, OP-26 |
| Email comercial | Sin envío automático (FR-16); `ChannelAdvisor` por país **y tipo de buzón** (**notas de investigación pendientes de verificación**, FR-44): ES → sin email en frío (LSSI art. 21); PT nominativo → **zona gris, no recomendado** (Lei 41/2004 art. 13.º-A + CNPD Diretriz/2022/1); PT genérico de empresa → opt-out con baja **y solo si la empresa no está en la lista DGC importada del trimestre en curso** (art. 13.º-B; si la lista tiene > 3 meses → aviso y email bloqueado); todo mensaje incluye un email válido de oposición (LSSI 21.2) · **olas 2 y 3 (resto de la UE, UK/IE, EE. UU., LatAm…): país sin regla verificada en config → sin email en frío** (solo oferta publicada, formulario o red profesional a mano) hasta completar OP-26 | research R8.1 · `NORMATIVA-RGPD.md` §3-4 · OP-26 |
| **RGPD — obligaciones del responsable** | Art. 14: aviso + baja en cada borrador y `notified_at` (FR-26) · art. 5: solo leads A/B, 30 d / 12 m, re-verificación a los 90 d (FR-27/28) · arts. 15-17/21: búsqueda, exportación, supresión y registro por comando (FR-29) · art. 9: sin categorías especiales ni fotos (FR-30) · art. 28/30: Supabase, Tavily, Firecrawl, Google y Anthropic como encargados en el RoPA con DPA/DPF; copias de T077 con retención de 4 semanas (OP-9 a OP-13, OP-19) | `NORMATIVA-RGPD.md` §2, §6 |
| Formularios de terceros | Solo detección: se guarda un resumen (campos, CAPTCHA, host del `action`), nunca se envía ni se invoca el `action`, no se resuelven CAPTCHAs, no se guardan teléfonos; el envío por formulario lo hace el operador a mano, con el aviso del art. 14 y la baja | FR-32 · OWASP §15 |
| Bases de datos de las fuentes y directorios | Solo APIs/RSS oficiales, enlace a la fuente, sin republicar (FR-30); directorios (Sortlist, Clutch, partners.laravel.com) **no se rastrean**: consulta manual e importación | TJUE C-762/19 · `NORMATIVA-RGPD.md` §5 |
| Términos de las fuentes | Una fuente no se activa sin `terms_reviewed_at` (CHECK + 422); robots.txt respetado; sin proxies ni evasión | FR-13 |
| Autorización | `auth` + `permission:*` en cada ruta; `whereUuid`; comandos de privacidad y DGC solo por consola | Router Security |
| Auditoría | `LogsActivity` v5 con `logOnly` explícito en Company, Outreach y Suppression; nunca `draft_body` ni datos de contacto | Router |

## 9. Riesgos y decisiones abiertas

- **Riesgo:** poco volumen de ofertas en PT/ES (research R6) → **Mitigación (A1):** el
  descubrimiento de agencias y la importación ICP entran en el MVP; términos ampliados (FR-5);
  métrica semanal por fuente y **por origen** (FR-18).
- **Riesgo:** el descubrimiento trae falsos positivos (freelancers individuales, SEO/branding sin
  ingeniería, grandes consultoras) → **Mitigación:** denylist + clasificación del tipo de
  empresa + tiers; revisar a mano los primeros 50 leads y ajustar familias y denylist.
- **Riesgo:** construir retrasa la prospección → **Mitigación:** Fase 0 manual desde el día 1 con
  `ICP-AGENCIAS-LISTA.md`; checkpoint de la semana 1 (T035) ya exporta leads puntuados.
- **Riesgo (Firecrawl):** el parámetro `proxy` figura como *deprecated*, su valor por defecto en v2
  (`auto`) escala a proxies para anti-bots avanzados y `enhanced` ya cuesta lo mismo que `basic`
  (research R15) → **Mitigación:** cliente propio v2 con `proxy: "basic"` + comprobación de
  `metadata.proxyUsed` en **cada** respuesta (un test con `Http::fake` no prueba lo que hace
  Firecrawl en su servidor; esta comprobación sí) + nunca pasar a Firecrawl una URL bloqueada. Si
  `proxyUsed` no es `basic` o Firecrawl elimina el parámetro, **se desactiva Firecrawl en
  LeadScout** y queda solo HTTP directo (se pierden las SPA; el pipeline sigue).
- **Riesgo (Firecrawl, privacidad):** por defecto guarda las páginas en su índice compartido →
  **Mitigación:** `storeInCache: false` (A16).
- **Riesgo (Tavily):** cambio de propietario (Nebius, 2026) y uso de las consultas para mejorar el
  servicio → **Mitigación:** DPA y subencargados revisados (OP-12); sin nombres de personas en las
  consultas; `SearchPort` permite cambiar de proveedor.
- **Riesgo:** cuota o presupuesto agotado (Tavily respondió el 16-09-2026 tras días con 432) →
  **Mitigación:** la resolución
  de empresa usa primero el dominio del payload; búsqueda basic y cacheada; presupuesto;
  OP-17. **Sin Tavily, el descubrimiento se detiene** (no hay respaldo; las ofertas y la
  importación siguen).
- **Decisión abierta — ¿desplegar en la web?** Legalmente, alojar el módulo en tu servidor tras
  login no cambia su licitud (la determina **qué hace**, no dónde corre; `NORMATIVA-RGPD.md` §10).
  Cambia la seguridad (art. 32: HTTPS, 2FA, rate limiting, servidor en la UE) y resuelve el riesgo
  del PC apagado (D2). Abrirlo a otros usuarios o venderlo es otro escenario legal (Q5, fuera de
  alcance). Ver OP-21.
- **Riesgo:** alucinaciones del LLM → **Mitigación:** verificación literal + hechos frente a
  inferencias + eval manual previa.
- **Riesgo:** `laravel/ai` 0.x con breaking changes → **Mitigación:** fijar minor, `AgentFake` en
  todos los tests, Context7 en la implementación.
- **Riesgo:** el scheduler solo corre con el PC encendido (Herd local) → ver D2. En Supabase Free,
  además, **una semana sin actividad pausa el proyecto** (se reanuda desde el panel).
- **Riesgo (Supabase):** exposición por la Data API, tamaño de 500 MB compartido con todo el
  proyecto, sin backups en Free, latencia de red → §4.2 (RLS, poda, `pg_dump`, consultas
  acotadas).
- **Riesgo:** Gemini 3.7 Flash dobla su precio el 01-01-2027 (research R9) → **Mitigación:** el
  coste estimado del selector se lee de config con `price_valid_until`; al vencer, aviso.
- **Riesgo técnico:** la documentación de la API de Landing.jobs tiene ejemplos de 2015 (research
  R11) → **Mitigación:** T021 comprueba primero que el endpoint responde.
- **Riesgo contractual:** términos de las APIs de empleo (Remotive: atribución, ≤ 4 peticiones/día,
  no redistribuir) → **Mitigación:** frecuencia mínima por fuente, enlace y nombre de la fuente
  visibles en la bandeja, sin republicación.
- **Riesgo legal:** email nominativo en Portugal (zona gris CNPD) → **Mitigación:** marcado «no
  recomendado en frío» por defecto; validar con un abogado (`NORMATIVA-RGPD.md` §7).
- **Riesgo legal:** el TJUE puede anular el EU-US DPF (recurso C-703/25 P) → **Mitigación:** los
  nombres nunca salen hacia la IA; base de datos en región UE; DPA con cláusulas tipo (OP-12).
  **Actualización 17-09-2026:** el 31-07-2026 la presidenta del EDPB pidió a la Comisión reevaluar
  el DPF tras *Trump v. Slaughter* → cláusulas tipo **además** del DPF en cada DPA, no como
  alternativa (research R15).
- **Riesgo normativo:** las EDPB Guidelines 03/2026 pueden endurecerse (consulta hasta el
  30-10-2026) → **Mitigación:** OP-14.
- **Riesgo (resuelto como decisión A12):** **colchón de 2 meses** (hasta ~16-11-2026) frente a un
  primer cliente recurrente que el escenario base sitúa en el mes 3 → **Mitigación:** tres vías en
  paralelo (agencias, empleo/freelance directo, consultoras nearshore PT), sistema operable al
  final de la semana 2, tareas diferidas y punto de control de caja en la semana 4 de operación
  (OP-23 a OP-25).

**Decisiones confirmadas (2026-09-16, `clarify.md`):**

- **D1 ✅** Parser de `robots.txt` **propio** (sin dependencias nuevas).
- **D2 ✅** Web, worker y scheduler en **Laravel Herd local**; **base de datos en Supabase** (A5).
  Herd no arranca el worker ni el scheduler: `composer run dev` ya incluye `queue:listen`, pero
  **no** `schedule:work` → añadirlo (T008).
- **D3 ✅** **Sonnet 5 o Gemini 3.7 Flash**. Defaults en `.env`: extracción = Gemini 3.7 Flash
  (≈ $0,015/empresa) con respaldo Sonnet 5; borradores = Sonnet 5 (≈ $0,0055/borrador) con
  respaldo Gemini; selector en el editor de borrador (A2). La eval de 30 páginas (T058) decide si
  se invierte el default de extracción. Con ~150 empresas y ~300 borradores al mes: ≈ $4 o ≈ $7,5
  (todo Sonnet 5), dentro de los €7 de IA.
- **D4 ⏳** API key de ITJobs.pt: se pide más adelante → fuente `paused`; no bloquea el MVP.
- **D5 ✅** `TavilyClientInterface::search()` acepta `searchDepth` opcional; LeadScout usa
  **`advanced` para el descubrimiento y `basic` para resolver empresas** (A13). Re-ejecutar los
  tests de CourseScripts.
- **A1-A15 ✅** Ajustes de la 6ª pasada (`clarify.md`): descubrimiento en el MVP, selector reducido,
  privacidad y DGC por comando, Supabase Free, 22 tablas (A15), aportes de MUSE-SPARK, CV, sin RAG, sin
  evasión, plan de 2 meses (A12) y **sin FastAPI/Crawl4AI/proxies residenciales** (A13, §3.5).

## 10. Entregas incrementales (colchón de 2 meses, A12)

| Semana | Fechas aprox. | Entrega usable | Historias |
|---|---|---|---|
| **0 (ya)** | 16-09 | Fase 0 manual: 10-15 contactos/día desde `ICP-AGENCIAS-LISTA.md` + LaraJobs/Tecnoempleo/ITJobs, **registrados en CSV** · vías 2 y 3 (OP-23, OP-24) · `/agencies` · regla de decisión · datos de Supabase (OP-19) | Prerrequisito |
| **1** | 16-09 → 22-09 | Tests seguros + 22 tablas con RLS + perfil desde `Argenis_Gonzalez_CV_2026.md` + fuentes RSS/Arbeitnow + **importación ICP con el registro manual** + reglas + score + export CSV → **checkpoint: prospectar con datos del sistema** | US-1, US-2, parte de US-4, FR-20, FR-21 |
| **2** | 23-09 → 29-09 | Búsqueda + resolución + **descubrimiento en 3 olas** + reglas de vitalidad, tamaño y B1 + obtención sin evasión + presupuesto + **backup (T077)** + decisores + canales + IA verificada + tiers → **sistema operable** (con exportación) | US-3, US-4, **US-7**, US-8, US-11, US-12 |
| **3** | 30-09 → 06-10 | Bandeja web + borradores con canal legal y selector de IA + etapas + métricas (endpoint + export) con lectura de muestra + regla bloqueable + comandos de privacidad y DGC | US-5, US-6, US-10, FR-17, FR-19, FR-29, FR-33 |
| **Operación** | 07-10 → ~16-11 | 10-15 contactos/día · **punto de control de caja ~21-10 (OP-25)** · la regla de 150 contactos termina ~mediados de noviembre | — |
| **Diferido hasta el primer ingreso** | — | T021 Landing.jobs · T023 ITJobs · T058 eval de IA · T068 frontend de métricas | — |
| **Fase 2** (si la regla da «tibia» o «funciona») | — | ATS boards por slug · fuentes de empleo nuevas por país (las olas de búsqueda ya están en el MVP) · pantalla de privacidad si hay volumen | US-9 |
| **Fase 3** (criterios de §3.5 cumplidos) | — | Reevaluar Crawl4AI self-hosted, embeddings y adaptación del CV por oferta | — |

## 11. Trazabilidad

| Requisito (spec.md) | Cubierto por |
|---|---|
| US-1 / FR-1 | §3.1 `CvSourcePort`/`EloquentCvSource`, `ImportCvProfileHandler`, `SkillTaxonomy`, `ProofPointMatcher` · §3.2 «antes de todo» · §3.4 (sin RAG) · §4 `scout_profiles` (`source_cv_uuid`, `cv_hash`, `proof_points`) · §5 profile/cvs e import-cv |
| FR-13 ampliado (sin evasión, sin LinkedIn) | §2 extracción · §3.1 `FetchLadder`, `OutboundUrlGuard` · §3.2 paso 4 · §7 «sin evasión» · §8 |
| US-2 / FR-2, FR-3, FR-4, FR-5 | §3.1 JobSources · §3.2 pasos 1-2 · §4 `scout_sources`, `scout_job_postings`, pivot |
| US-3 / FR-6 | §3.2 pasos 3-5 · §4 `scout_companies`, `scout_signals` |
| US-4 / FR-7, FR-8, FR-9 | §3.3 (incluida `needs_research`) · §4 `scout_score_results` · §7 golden tests |
| US-5 / FR-15, FR-16 | §3.2 paso 7 · `ChannelAdvisor` · §5 drafts/outreaches · §8 |
| US-6 / FR-18, FR-19, FR-33 | §4 `scout_outreach_stage_events`, `scout_opportunities`, `scout_decision_rules` · §5 metrics, opportunities |
| FR-40 a FR-44 (envío humano, registro, respuestas, bajas, reglas pendientes; A18) | §0 principio · §3.1 `ChannelAdvisor`, `SuppressionGate` · §3.2.1 diagrama · §4 `scout_outreaches` (`send_medium`, `operator_id`, `template_key`/`template_version`, `legal_rule_status`, `reply_outcome`) y `scout_outreach_stage_events` · §5 `PATCH outreaches`, `POST outreaches/{uuid}/reply`, `privacy lift-suppression` · §7 · §8 · §12 gate 3 · §13 |
| **US-7** | §3.2 paso 0 · `SearchPort`, `DiscoverAgenciesHandler` · §4 `origin` · §5 comandos |
| US-8 / FR-11, FR-12, FR-14 | `FetchLadder`, `BudgetLedger` · §4 `scout_budgets`, `scout_fetch_attempts`, `scout_search_queries` · §5 budgets |
| US-9 / FR-37 (olas geográficas + B1) | §3.2 paso 0 (olas) · §3.3 Comunicación (B1) y Geo (solape horario) · §4 `discovery_wave`, `timezone_overlap_hours` · §8 email por país |
| FR-38, FR-39 (datos públicos de empresa, A16) | §3.1 `PublicCompanyDataExtractor` · §3.2 paso 4d · §4 columnas de datos públicos en `scout_companies`, `page_type` legal/privacy · §7 · §8 · `NORMATIVA-RGPD.md` §11 |
| FR-13 y FR-25 ampliados (A16) | §2 extracción y búsqueda · §3.1 `PersonalDataScrubber` · §3.2 paso 5b · §7 · §8 · §9 |
| FR-36 (vitalidad y tamaño) | §3.2 paso 5a · §3.3 dimensión Vitalidad y tamaño + reglas de Descartar y de Tier A · §4 `activity_status`, `last_activity_at`, `employee_range`, `discard_reason` · §7 «calidad de agencia» |
| US-10 / FR-22 | §2 modelos de IA · §3.1 `AiModelCatalogPort`/`ConfigAiModelCatalog` · §4 `ai_provider`/`ai_model` · §4 `scout_ai_settings` · §5 ai-settings + selector en drafts · §7 · §8 |
| US-11 / FR-23, FR-24, FR-25 | §3.1 `DecisionMakerExtractor`, `RoleTaxonomy`, handlers · §3.2 paso 4b y redacción en 5b · §4 `scout_contacts`, `scout_contact_objections`, columnas de `scout_companies` · §5 contacts/objection · §7 · §8 |
| US-12 / FR-31, FR-32 | §3.1 `ContactChannelDetector`, `ChannelAdvisor` · §3.2 paso 4c · §4 `scout_contact_channels`, `forms_summary`, `contact_channel_id` · §5 channels y outreach · §7 · §8 |
| FR-10 | §3.2 paso 5 (reglas primero, LLM acotado) |
| FR-13 | §4 CHECK `terms_reviewed_at` · `RobotsTxtPolicy` · §8 |
| FR-17 | §4 `scout_suppressions` · §5 suppressions · comando `import-dgc` |
| FR-20 | §5 `POST leads` · comando `import-leads` |
| FR-21 | §5 export |
| FR-26 (art. 14) · FR-27 (conservación) · FR-28 (exactitud) | §3.2 pasos 4b, 6b y 7 · §4 `notified_at`, `contact_deadline_at`, `last_verified_at` · §8 |
| FR-29 (derechos) · FR-30 (art. 9, sin republicar) | §5 comando `privacy` · §4 `scout_privacy_requests` · §8 |
| FR-34 (alojamiento y exposición de datos) | §2 base de datos · §4.2 · §8 · T003, T010, T014, T077, T078 |
| NFR Coste | §0 · §2 · §3.2 · `BudgetLedger` (Q3 = €30) |
| NFR Seguridad / Cumplimiento | §4.2 · §8 |
| NFR Auditabilidad | §3.3 `rules_version` · §4 `scout_score_reasons` |
| NFR Tolerancia a fallos | §3.2 paso 1 · §9 |

## 12. Gates antes del primer contacto real (no negociables)

Aporte de MUSE-SPARK (plan §8), fusionado con las tareas OP. **No bloquean la implementación;
bloquean el primer envío medido.**

1. `/agencies` publicada con portfolio (Servispin, AquaShield, Vidula), no captación visible y un
   formulario con «¿cómo me conociste?» (OP-1).
2. Regla de decisión registrada y **bloqueada** (OP-6, T067).
3. Reglas de contacto por país **verificadas** (`legal_status: verified`, con fuente y
   responsable) para cada país y medio que se vaya a usar; lo pendiente solo se usa con
   confirmación explícita registrada (FR-44).
3b. Validación legal de los puntos de `NORMATIVA-RGPD.md` §7 y del uso del formulario de contacto
   (OP-15).
4. Sección «Prospección B2B» en la política de privacidad de argenis.dev (OP-11).
5. Lista DGC importada del trimestre en curso **si** se va a escribir a empresas portuguesas por
   email (OP-16, T074).
6. One-pager de no captación listo (OP-18) y frase de recibos verdes confirmada con el
   contabilista e idéntica en web, mensaje y acuerdo (OP-7).
7. RoPA y DPA de los encargados, **incluido Supabase** en región UE (OP-10, OP-12, OP-19).
8. **Si el módulo se despliega en un servidor en la web** (OP-21): HTTPS, login con 2FA, servidor y
   base en la UE, `APP_DEBUG=false`, rate limiting activo y revisión T078 superada.

## 13. Qué NO hacer (acuerdo explícito)

Aporte de MUSE-SPARK (plan §12), ajustado a esta spec:

- **Descubrimiento automático ≠ contacto automático:** sin ninguna capacidad de envío en el módulo
  (email, formularios, LinkedIn, mensajería); sin borradores para leads C o descartados.
- Sin levantar una baja desde la web; sin convertir notas de investigación legal en reglas que
  **permitan** contactar sin verificación (A18).
- Sin envío automático, secuencias, seguimiento de aperturas ni adjuntar el CV.
- Sin tarifa en el primer mensaje; sin escribir a `info@` de agencias españolas en frío; sin email
  nominativo en frío en Portugal.
- Sin scraping de LinkedIn, Indeed, Glassdoor ni de directorios; sin evadir robots, CAPTCHAs,
  anti-bot, muros de login ni términos de uso; sin proxies propios; sin el modo `auto`/`enhanced`
  de Firecrawl; sin reintentar por otra vía una URL que nos bloqueó; sin reutilizar tal cual el
  `FirecrawlScrapeAdapter` compartido (no fija `proxy`, usa `/v1` y deja `storeInCache: true`);
  sin aceptar una respuesta de Firecrawl cuyo `proxyUsed` no sea `basic`.
- Sin usar Claude WebSearch/WebFetch (ni otras *provider tools* de búsqueda) dentro del pipeline.
- Sin consultas a buscadores dirigidas a LinkedIn u otras redes profesionales ni a intermediarios de
  datos de contacto; sin «saltar» LinkedIn a través de fragmentos de resultados de búsqueda (A17).
- Sin mezclar en la misma muestra de la regla de decisión envíos desde la cuenta personal y desde el
  dominio sin segmentar por `sender_kind`; sin asumir que enviar a mano o desde Gmail personal hace
  legal un canal (A19); sin descubrir secuencialmente una ola tras otra.
- Sin guardar teléfonos, direcciones completas, imágenes ni datos de identificación de autónomos;
  sin nombres de personas en consultas de búsqueda.
- Sin RAG ni embeddings del CV en el MVP; sin enviar el CV completo a la IA.
- Sin agentes autónomos, sin MCP en el backend, sin Crawl4AI/FastAPI en el MVP.
- Sin presentar inferencias como hechos; sin inventar agencias, capacidades ni datos de contacto;
  sin adivinar emails.
- Sin enviar nombres de personas a la IA; sin guardar trabajadores que no son decisores.
- Sin ejecutar tests ni `migrate:fresh` contra Supabase; sin tablas `scout_*` sin RLS.
- Sin concluir nada antes de completar la muestra de la regla de decisión.
