# Tareas: LeadScout

> Fase 5 · BREAK DOWN TASKS — Pasos pequeños, verificables y ordenados por dependencia.
> Cada tarea deja el sistema compilando y con tests en verde. `[P]` = paralelizable (no comparte
> archivos ni depende de otra tarea pendiente).

**Feature ID:** 003-lead-scout
**Basado en:** `plan.md` (6ª pasada, 16-09-2026: Supabase, 22 tablas, descubrimiento de agencias en
el MVP, alcance recortado y aportes de la variante MUSE-SPARK — ver `clarify.md` A1-A15) y
7ª pasada (17-09-2026, A16: datos públicos básicos de empresa, Firecrawl v2 con `storeInCache: false`
y `proxyUsed` verificado, Tavily con `exclude_domains` y sin nombres, limpieza antes de la IA →
**T082 y T083 nuevas**; A18: envío humano, registro de envío, bajas y reglas por país pendientes → **T084 y T085 nuevas**; se numeran al final para no romper las referencias existentes)
**Numeración:** renumerada de forma secuencial en la 6ª pasada (la equivalencia con la
numeración anterior está en `analyze.md` §7).
**Colchón de 2 meses (A12):** el sistema debe ser **operable al final de la semana 2** (~30-09-2026)
con la exportación. **Diferidas hasta el primer ingreso:** T021, T023, T058 y T068 (77 tareas en el
MVP). T077 (backup) se adelanta a la semana 2.
**Reglas que aplican a todas las tareas:** leer la skill correspondiente antes de escribir
(`BACKEND-PHP`, `ARCHITECTURE-PHP`, `OWASP`; `FRONTEND` + `ARCHITECTURE-VUE` en las tareas de
UI) · Context7 antes de usar APIs de `laravel/ai`, Spatie Data o Inertia v3 · `php artisan
make:*` con `--no-interaction` · tests Pest 5 · `vendor/bin/pint --dirty --format agent` tras
cada tarea PHP · **los tests nunca se ejecutan contra Supabase** (T003) · commit
`feat(lead-scout): T0XX descripción`.

---

## Fase 0 — Operador (en paralelo desde el día 1, sin código)

- [ ] OP-1 Publicar `/agencies` (o `/es/agencias`) en argenis.dev: es el prerrequisito del test.
- [ ] OP-2 CV: añadir «3 años facturando con recibos verdes», unificar el nº de proyectos (web
      12+ vs CV ~8) y quitar o confirmar «AWS» en las plantillas.
- [ ] OP-3 Adaptar `EMAIL-OUTREACH-AGENCIAS.md` a la LSSI: en España nada de email en frío;
      usar la respuesta a la oferta, el formulario o una red profesional.
- [ ] OP-4 Prospección manual de 10-15 contactos/día desde `ICP-AGENCIAS-LISTA.md` + LaraJobs,
      Tecnoempleo e ITJobs (no esperar al software). Pasar la lista ICP a CSV
      (`nombre,url,nota`) para importarla con T029.
- [ ] OP-5 Revisar los términos de uso de cada fuente antes de activarla; pedir la API key de
      ITJobs.pt (D4).
- [ ] OP-6 Registrar y bloquear la regla de decisión antes del primer contacto medido.
- [ ] OP-7 Confirmar con el contabilista el coeficiente del régimen simplificado, el IVA en
      servicios B2B intracomunitarios y **la frase exacta sobre recibos verdes** que irá igual en
      la web, en los mensajes y en el acuerdo (la retención del 23 % ya está verificada, R11).
- [ ] OP-8 Aprobar el gasto de la eval T058 (≈ $1-2).
- [ ] OP-9 **LIA** (evaluación de interés legítimo, 1-2 páginas): interés (conseguir clientes
      B2B), necesidad (solo decisores y datos profesionales publicados) y ponderación
      (expectativas razonables, baja inmediata, 30 días sin contacto). EDPB Guidelines 1/2024.
- [ ] OP-10 **Registro de actividades de tratamiento** (art. 30, 1 página): finalidad, categorías,
      fuentes, destinatarios/encargados (**incluido Supabase y su región**), transferencias,
      plazos (incluidas las copias de T077) y medidas.
- [ ] OP-11 **Política de privacidad** en argenis.dev: añadir la sección «Prospección B2B» en
      EN/ES/PT (`/privacy#prospeccion`) con el checklist de `NORMATIVA-RGPD.md` §8.2, y
      corregir §8.3: quitar el aviso de «template», aclarar o quitar la casilla de marketing
      (`smsConsent`), teléfono opcional y declarar dónde se guardan los datos de
      `/api/appointments`.
- [ ] OP-12 Verificar el **DPA** y la certificación **EU-US DPF** **y además** las cláusulas tipo
      (carta del EDPB del 31-07-2026, research R15) de Tavily, Firecrawl, Google (Gemini),
      Anthropic **y Supabase**; anotarlo en el RoPA. **Tavily:** registrar el grupo Nebius, pedir el
      DPA, el informe SOC 2 Type II 2026 y la lista de subencargados en trust.tavily.com, y oponerse
      al uso de las consultas para mejorar el servicio (support@tavily.com) si el DPA no lo excluye.
      **Firecrawl:** confirmar en el DPA el efecto de `storeInCache: false`.
- [ ] OP-13 **Cribado de DPIA** (art. 35): dejar escrito por qué no es obligatoria.
- [ ] OP-14 Revisar la versión final de las EDPB Guidelines 03/2026 (tras el 30-10-2026) y ajustar
      la spec si cambia algo.
- [ ] OP-15 Validar con un abogado los puntos de `NORMATIVA-RGPD.md` §7 (email nominativo en PT,
      plazo del art. 14, textos de privacidad) **y el uso comercial del formulario de contacto de
      las agencias en ES/PT** **antes del primer contacto real**.
- [ ] OP-16 Pedir a la **DGC** la lista de personas colectivas que se oponen
      (consumidor.gov.pt) antes del primer email a una empresa portuguesa, y renovarla **cada
      trimestre** (T074).
- [ ] OP-17 Revisar tu límite de uso en Tavily (app.tavily.com → Billing/Usage): bloqueó con 432
      durante la investigación y volvió a responder el 16-09-2026. **El descubrimiento de agencias
      (T044) depende de esto** (no hay búsqueda de respaldo).
- [ ] OP-18 **One-pager de no captación** (marca blanca, bajo el nombre de la agencia, sin
      contacto directo con su cliente) listo **antes de la primera respuesta positiva**; la misma
      frase en la web, en los mensajes y en el acuerdo (aporte de MUSE-SPARK, DEC-4).
- [ ] OP-19 **Supabase (plan Free, confirmado):** anotar la **región** (debe ser UE), la versión de
      PostgreSQL (`select version();`), el **tamaño actual de la base** (panel → Database; el
      límite de 500 MB es para todo argenis-hub) y si algún cliente usa la **Data API**
      (supabase-js / PostgREST). Si nadie la usa, desactivarla en el panel. Dato necesario para
      T002, T003 y T077.
- [x] OP-20 ~~Confirmar el colchón real~~ → **2 meses** (hasta ~16-11-2026). Plan A12 en
      `clarify.md`: tres vías en paralelo, sistema operable al final de la semana 2, tareas
      diferidas y punto de control de caja en la semana 4 de operación.
- [ ] OP-21 **¿Dónde corre?** Decidir si LeadScout se queda en Herd local durante el test (D2) o se
      despliega con argenis-hub en un servidor en la web. Si se despliega: servidor en la UE,
      HTTPS, login con 2FA, `APP_DEBUG=false`, scheduler y worker en el servidor (gate 8 del plan
      §12). **Solo para uso propio**: abrirlo a otros usuarios es otro escenario legal (Q5).
- [ ] OP-22 CV de origen = **`Argenis_Gonzalez_CV_2026.md`** (confirmado). Queda: comprobar en el
      módulo Cvs que ese registro está marcado como **principal** (`is_primary`, `file_type = md`)
      y, si se quiere citar en los mensajes, añadir «3 años facturando con recibos verdes» (OP-2;
      hoy no aparece en el CV). Las URLs públicas de Vidula, AquaShield y Servispin ya están.
- [ ] OP-23 **Vía 2 — empleo y freelance directo (A12):** desde la semana 1, responder cada semana
      a las ofertas Laravel/PHP compatibles (remoto o Covilhã) y registrarlas como
      `employment_application` para medirlas aparte.
- [ ] OP-24 **Vía 3 — consultoras nearshore PT (A12):** listar y contactar consultoras que
      contraten contractors PHP/Laravel en remoto; registrar el resultado. Que cierren en 2-6
      semanas y acepten B1 es una hipótesis de MUSE-SPARK `[UNVERIFIED]`.
- [ ] OP-25 **Punto de control de caja** (~21-10-2026, semana 4 de operación): si hay 0
      conversaciones en el pipeline y ninguna entrevista en OP-23/OP-24 → repartir ~70 % del tiempo
      a empleo/nearshore y ~30 % a agencias, sin dejar de registrar ni tocar la regla bloqueada.
- [ ] OP-26 **Email en frío fuera de ES/PT (olas 2 y 3):** antes de activar el email en frío para
      un país (p. ej. UK, IE, NL, DE, US), verificar su regla B2B y añadirla a config. Hasta
      entonces esos leads se contactan solo respondiendo a ofertas, por formulario o por red
      profesional a mano. Incluirlo en la consulta al abogado (OP-15).
- [ ] OP-27 **Buzón de envío manual (A17, ajustado por A19):** **desarrollo y pruebas** → Gmail
      personal (preferiblemente hacia direcciones propias o de prueba), `sender_kind =
      personal_mailbox`. **Antes de bloquear la regla de decisión (OP-6) y del primer contacto
      real medido** → dirección del dominio propio (p. ej. `hola@argenis.dev`) con **cualquier
      proveedor de correo empresarial** con términos de encargo (Workspace u otro),
      `sender_kind = business_domain`; añadir ese proveedor al RoPA (OP-10) y a la política
      (OP-11). En ambos casos: nombre completo como remitente, aviso art. 14 + baja con email válido
      y enlace a `/privacy#prospeccion`. Registrar cada envío en LeadScout y cada «BAJA» el mismo
      día.

---

## Fase A — Fundamentos (semana 1)

- [ ] T001 Context7: resolver la documentación de `laravel/ai` 0.11 (agents,
      `HasStructuredOutput`, `AgentFake`), `spatie/laravel-data` 4, Inertia v3 y Laravel 13
      (scheduler, middleware `RateLimited` de jobs, conexión `pgsql` con `sslmode`). Anotar
      firmas en `research.md` (addendum de R3). Sin código.
- [ ] T002 Cerrar los `[UNVERIFIED]` del plan §2: conexión a Supabase (host del **pooler en modo
      sesión**, puerto 5432, usuario `postgres.[PROJECT-REF]`, `sslmode=require`), versión de
      PostgreSQL, `QUEUE_CONNECTION`, `CACHE_STORE`, presencia (no el valor) de
      `GEMINI_API_KEY`, `ANTHROPIC_API_KEY`, `TAVILY_*` y `FIRECRAWL_*`, versión de la API de
      Firecrawl frente al `/v1` del adaptador, **si el parámetro `proxy: "basic"` sigue aceptado**
      (figura como *deprecated*) y precio oficial de Gemini 3.7 Flash. Actualizar el plan. Nunca
      copiar credenciales en documentos. *(Ya verificado el 16-09-2026: `FirecrawlClientInterface`
      solo expone `scrape` → sin búsqueda de respaldo; `phpunit.xml` usa sqlite `:memory:`.)*
- [ ] T003 **Tests sin riesgo para Supabase.** La suite sigue en sqlite `:memory:` (como hoy). Se
      añade el grupo Pest **`pgsql`** para los tests de LeadScout que necesitan PostgreSQL
      (CHECK, índices parciales, `jsonb`, RLS), con conexión `pgsql_testing` a un PostgreSQL local
      de la misma versión mayor que Supabase (OP-19), y un guard en `tests/Pest.php` que **aborta
      si `DB_HOST` contiene `supabase.co` o `supabase.com`** + test del guard. **Confirmar con el
      operador antes de editar `phpunit.xml` o `tests/Pest.php`.**
- [ ] T004 Esqueleto `src/Modules/LeadScout` + `LeadScoutServiceProvider` registrado según el
      patrón de CourseScripts (`registerWebRoutes`, bindings) +
      `Infrastructure/Routes/web.php` con el grupo `auth`. Test: el provider arranca y las rutas
      existen.
- [ ] T005 [P] Permisos `VIEW_ANY_`, `VIEW_`, `CREATE_`, `UPDATE_`, `DELETE_` y
      `EXPORT_LEAD_SCOUT` en el seeder de permisos existente, asignados al rol admin + test.
- [ ] T006 [P] `config/lead-scout.php`: `rules_version`, pesos, umbrales de tier y de
      `needs_research`, antigüedad máxima de ofertas (30 d), `max_age` de páginas (14 d),
      retención de markdown (30 d), términos ampliados, **olas de descubrimiento** (1: PT/ES ~60 %,
      2: resto de la UE + UK/IE ~30 %, 3: resto del mundo ~10 %) con sus familias de consultas y el
      **catálogo de países por ola** del plan §3.2.1 (ISO, zona horaria IANA, idioma de consulta;
      A17), **denylist de consultas** (redes profesionales e intermediarios de datos de contacto),
      **umbrales de vitalidad** (6/12/24 meses), rangos de tamaño, **reglas de contacto por país**
      (país × medio × tipo de buzón → `decision` allow/block, `legal_status`
      `pending_verification`|`verified`, `source_url`, `verified_at`, `verified_by`; **todas
      empiezan pendientes**, A18/FR-44), **`sender_kind` por defecto** (`personal_mailbox` en desarrollo, A19), **plantillas de borrador versionadas**
      (`template_key` + `template_version`), denylist de dominios de resultados, TTL de caché
      de búsquedas (30 d),
      presupuestos (búsqueda €8 / extracción €15 / IA €7, leídos de `.env`), **catálogo de IA**
      (Gemini 3.7 Flash y Claude Sonnet 5 con IDs fijados, precios y `price_valid_until`),
      proveedor/modelo por defecto y de respaldo **por propósito desde `.env`** (D3) y regla de
      decisión por defecto (150 / 56 días / umbrales Q9). Sin secretos.
- [ ] T007 D5: `TavilyClientInterface::search()` acepta `?string $searchDepth` (default = config
      actual) + adaptador + test; `php artisan test --compact --filter=CourseScripts` en verde.
- [ ] T008 D2: añadir `php artisan schedule:work` al script `dev` de `composer.json` (proceso
      `scheduler`). **Confirmar con el operador antes de editar `composer.json`.**

## Fase B — Modelo de datos: 22 tablas (semana 1)

- [ ] T009 [P] Enums de dominio (claves TitleCase): CompanyType, CompanyOrigin (JobPosting,
      Discovery, Manual, Import), EmployeeRange (Solo, From2To4, From5To10, From11To50,
      From51To200, Over200, Unknown), ActivityStatus (Active, Stale, Inactive, Unknown),
      DiscardReason (Suppressed, LargeOutsourcer, LowTechnical, OnsiteAbroad, SoloFreelancer,
      Inactive, DeadOrAcquired), RemoteMode, ContractType, PostingStatus,
      SignalDimension (incluye `Vitality`), SignalNature, ExtractionMethod, Tier, OutreachStage, OutreachChannel
      (incluye `EmploymentApplication`), MessageVariant, RoleCategory (Founder, Executive,
      TechnicalLead), ContactSource, ChannelType, ChannelAudience, ChannelStatus, PageType,
      SourceType, SourceStatus, FetchMethod, FetchStatus, SearchPurpose, SearchStatus,
      BudgetCategory, AiPurpose, SuppressionSource (Manual, Objection, DgcList),
      PrivacyRequestType, PrivacyRequestOutcome, OpportunityType, OpportunityStatus,
      DecisionOutcome.
- [ ] T010 Helper de migración `SupabaseRls::protect(string $table)`: `ALTER TABLE … ENABLE ROW
      LEVEL SECURITY` + `REVOKE ALL … FROM anon, authenticated` **solo si esos roles existen**
      (en PostgreSQL local no existen; en sqlite no hace nada) · **6 migraciones de
      configuración**: `scout_profiles`, `scout_sources` (CHECK de `terms_reviewed_at`),
      `scout_suppressions` (empresas; UNIQUE parcial de `canonical_domain`),
      `scout_decision_rules`, `scout_ai_settings` (`purpose` UNIQUE) y `scout_budgets`
      (UNIQUE `period, category`). Cada migración llama al helper.
- [ ] T011 **9 migraciones de captación y evidencia** (con el helper): `scout_companies`
      (`origin`, `origin_ref`, `discovery_wave`, `team_size_observed`, `has_decision_maker`,
      `needs_research`, `activity_status`, `last_activity_at`, `timezone_overlap_hours`,
      `employee_range` con `solo`; **A16:** `legal_name`, `legal_form`, `tax_id` indexado,
      `registry_info`, `founded_year`, `services`, `sectors`, `site_languages`, `client_companies`,
      `public_urls`, `public_data_evidence`; `page_type` de `scout_fetched_pages` con `blog`,
      `partners`, `legal`, `privacy`; estado `proxy_mismatch` en `scout_fetch_attempts`), `scout_job_postings`, `scout_job_posting_sources`,
      `scout_search_queries` (`query_hash` UNIQUE, `search_depth`, `purpose`, `discovery_wave`,
      `family`, `results`, `new_companies_count`, `status`, coste), `scout_fetched_pages`
      (`content_markdown` nullable + `content_pruned_at`, `forms_summary`),
      `scout_fetch_attempts` (con `company_id` y estado `blocked`), `scout_signals` (con
      `ai_provider`/`ai_model`), `scout_score_results` (subscore `vitality`, `discard_reason`,
      UNIQUE parcial `is_current`) y `scout_score_reasons`.
- [ ] T012 **7 migraciones de personas, canales y funnel** (con el helper): `scout_contacts` (solo
      decisores: `role_category`, `is_primary` con UNIQUE parcial, `published_email`,
      `email_kind`, `public_profile_url`, `source`, evidencia, `anonymized_at`,
      `contact_deadline_at`, `notified_at`, `last_verified_at`), `scout_contact_channels`,
      `scout_contact_objections` (`person_hash` UNIQUE), `scout_privacy_requests` (sin PII),
      `scout_outreaches` (`contact_channel_id`, `ai_provider`/`ai_model`, `sent_at`,
      `stage_changed_at`; **A18:** `send_medium` enum(email, contact_form, job_posting,
      linkedin_manual), `outreach_kind`, `template_key`, `template_version`, `operator_id` FK a
      `users`, `legal_rule_status`, `legal_ack_at`, `reply_outcome`, `replied_at`; **A19:**
      `sender_kind` enum(personal_mailbox, business_domain); CHECK
      `stage=sent ⇒ send_medium, sent_at, operator_id, sender_kind NOT NULL`), `scout_outreach_stage_events`
      (+ `reply_outcome`, `operator_id`) y `scout_opportunities`.
- [ ] T013 Modelos Eloquent (`HasUuids` v7, `$fillable`, casts a enums y a `array` para jsonb,
      relaciones bidireccionales con PHPDoc genérico, `LogsActivity` v5 con `logOnly` en Company,
      Outreach — `stage`, `channel`, `sent_at` —, Opportunity — `status`, `hourly_rate_cents`,
      `hours_per_month` — y Suppression) + factories con estados (`tierA`, `needsResearch`,
      `suppressed`, `spanishAgency`, `portugueseAgency`, `discovered`).
- [ ] T014 Test de esquema (patrón `CourseScriptsSchemaTest`, grupo `pgsql`): **22 tablas
      `scout_*`**, uniques, CHECK, índices parciales y **RLS activado en todas**
      (`pg_class.relrowsecurity = true`); en sqlite, que las migraciones corren sin error.
- [ ] T015 [P] VOs `CanonicalDomain` y `PostingFingerprint` + Unit tests (www, mayúsculas, IDN,
      subdominio `careers.`, path/query, hash estable por semana ISO).

## Fase C — US-1: Perfil desde el CV (semana 1)

- [ ] T016 VO `SkillTaxonomy` (términos + sinónimos PT/ES/EN; confirmadas vs potenciales) +
      servicio de dominio `ProofPointMatcher` (puro: señales de la empresa → `proof_point` más afín
      por solapamiento de tecnologías y sector, desempate por recencia) + Unit tests.
- [ ] T017 **Perfil desde la tabla `cvs`:** `CvSourcePort` + `EloquentCvSource` (solo lectura del
      módulo Cvs: `user_id` del operador, `deleted_at IS NULL`, por defecto `is_primary` y
      `file_type = md`; devuelve uuid, tipo, `raw_text` y hash) + `ImportCvProfileHandler`
      **determinista, sin LLM ni embeddings** (FR-10, A10): parsea las secciones del CV markdown
      ATS → confirmadas; potenciales según una lista configurable (AWS, Forge, Vapor, Pest,
      Filament, Symfony, NestJS, Next.js); extrae `proof_points` (proyecto, resumen público,
      tecnologías, sector, resultado, URL); guarda `source_cv_uuid` + `cv_hash`, **no** el texto;
      nueva versión del perfil. El CV por defecto es `Argenis_Gonzalez_CV_2026.md` (markdown ATS
      en inglés, estructura en research R14): secciones `## **…**` (TECHNICAL SKILLS, WORK
      EXPERIENCE, PROJECTS, LANGUAGES), habilidades en líneas `**Etiqueta:** a · b · c`, puestos en
      `### **Puesto - Empresa**` y proyectos con URL; el parser **ignora la línea de contacto**
      (teléfono, email, redes). Tests con un registro de `cvs` creado desde un **fixture
      anonimizado** de ese CV (sin teléfono, email ni redes): Laravel, Vue, Inertia, Livewire,
      PostgreSQL, Supabase y PHPUnit confirmados; AWS, Pest y Forge **no**; `proof_points` de
      Vidula, AquaShield, Servispin, Tutorial Cleanup API y **Restoration Control** (CRM legacy
      en PHP sin framework → prueba para la variante `legacy_maintenance`); idiomas ES nativo,
      PT residente, EN B1; PDF sin `raw_text` → excepción de dominio; `raw_text` nunca en logs.
- [ ] T018 `ProfileController` GET/PUT + `GET profile/cvs` (lista de CVs del operador **sin**
      `raw_text`) + `POST profile/import-cv {cv_uuid}` + detección de perfil `stale` (hash distinto
      al del CV actual) + Data/Requests + feature tests (nueva versión; CV de otro usuario o
      borrado → 404; PDF sin texto → 422; `stale` tras editar el CV; 403).
- [ ] T019 Verificar los criterios de aceptación de US-1 en `spec.md`.

## Fase D — US-2: ingesta de ofertas + alta manual e importación ICP (semana 1)

- [ ] T020 `JobSourcePort` + `RawPostingData` + `RssFeedSource` (LaraJobs, Remotive, WWR) +
      fixtures XML + tests (parseo, cursor por fecha, feed caído). **Remotive:**
      `frequency_minutes ≥ 360` (máx. 4/día según sus términos), atribución «vía Remotive» +
      enlace original visibles, sin republicación.
- [ ] T021 [P] **[DIFERIDA — A12]** `LandingJobsApiSource` + fixture JSON + tests. **Primero** comprobar que
      `landing.jobs/api/v1/jobs` sigue activo (su documentación es de 2015); si no responde,
      marcar la tarea como descartada y retirar la fuente del seeder.
- [ ] T022 [P] `ArbeitnowApiSource` + fixture JSON + tests (paginación de 250).
- [ ] T023 [P] **[DIFERIDA — A12, hasta tener la key de D4]** `ItJobsApiSource` + fixture + tests
      (queda `paused` hasta D4/OP-5). Mientras tanto, ITJobs se revisa a mano en OP-4/OP-23.
- [ ] T024 Seeder de fuentes: todas `paused`, `terms_reviewed_at = null`, prioridad API oficial >
      RSS (FR-3).
- [ ] T025 `IngestSourceHandler`: normalizar (modalidad, contrato, idioma, país), filtro de
      relevancia con `SkillTaxonomy` + términos ampliados (FR-5), fingerprint, upsert + pivot,
      empresa con `origin=job_posting`, circuit breaker por fuente, estados
      `quota_exhausted`/`failing`, `last_cursor`, cada llamada registrada en
      `scout_fetch_attempts` (método `api`/`rss`) + feature tests (misma oferta en 2 fuentes → 1 posting; una fuente
      falla y el resto sigue; oferta irrelevante descartada).
- [ ] T026 `IngestSourceJob` + `IngestLeadSourcesCommand` (`lead-scout:ingest {--source=}`) +
      planificación en `routes/console.php` con `withoutOverlapping` + test.
- [ ] T027 `ExpirePostingsHandler` + `ExpireJobPostingsCommand` diario + test (caducada → no
      genera lead ni suma la señal de vacante activa).
- [ ] T028 `SourceController` list/patch/run + tests (422 al activar sin términos, 409 si ya está
      en ejecución, 403).
- [ ] T029 Alta manual e importación: `CreateManualLeadHandler` + `POST leads` (FR-20) ·
      `lead-scout:import-leads {file}` (CSV `nombre,url,nota`, p. ej. la lista ICP de OP-4;
      `origin=import`; deduplicación por dominio canónico; filas inválidas reportadas sin abortar;
      **columnas opcionales de contacto ya enviado** `sent_at,channel,variant,stage` para cargar
      el registro manual de la Fase 0 y que cuente en la muestra de 150, A12)
      · `SuppressCompanyHandler` + `POST suppressions` (FR-17) + tests (empresa suprimida → 409 en
      el alta e ignorada en la importación; fila con `sent_at` → outreach con su `sent_at` y su evento de etapa).
- [ ] T030 Verificar los criterios de aceptación de US-2, FR-17 (supresión manual) y FR-20.

## Fase E — US-4 (parte 1): scoring determinista + export (cierre de la semana 1)

- [ ] T031 Servicio de dominio `RuleBasedSignalExtractor` (puro): tecnologías, remoto, contrato,
      idioma de trabajo, nº de vacantes, **vacante dev activa**, precios públicos bajos,
      mantenimiento/soporte/SLA y clientes de varios años; **vitalidad** (fechas de contenido en
      formatos PT/ES/EN, `lastmod` del sitemap —ignorado si todas las URLs comparten la misma
      fecha—, año del copyright → `activity_status` (active ≤ 12 m, stale 12-24 m, inactive > 24 m,
      unknown) y
      `last_activity_at`); **tamaño** (recuento de equipo, «somos N», «team of N», primera persona
      del singular → `solo`); **web muerta o absorbida** (dominio aparcado/en venta, redirección
      permanente a otro dominio); **B1** (remote-first, equipo distribuido, procesos asíncronos,
      «fluent/native English», «daily client calls») y **solape horario** con Portugal según el país;
      evidencia con fragmento literal; PT/ES/EN + Unit tests con fixtures sintéticos.
- [ ] T032 `ScoringEngine` + golden Unit tests (ejemplos «87» y «75» del plan §3.3, pesos
      20/20/20/15/10/10/5, inferencia × 0,6, topes, tecnología no confirmada −10, vacante activa en
      Recurrente y en Vitalidad, penalización de «native English» sin descartar).
- [ ] T033 `TierClassifier` + Unit tests de bordes (79/80, confianza 69/70, sin señal comercial
      `fact` → B) + **reglas de Descartar con `discard_reason`** (freelancer individual, agencia
      parada, web muerta o absorbida, outsourcer grande) + **Tier A exige `activity_status = active` (≤ 12 meses) y equipo
      ≥ 5** (o 2-4 con vacante activa o llamada a freelancers `fact`) + **`needs_research`** (Lead
      ≥ 80 y Confianza < 70, o `activity_status=unknown` con Lead ≥ 65) + sin dato de tamaño no se
      descarta.
- [ ] T034 `ScoreCompanyHandler` + `ScoreCompanyJob` + `ScoreLeadsCommand` +
      `POST leads/{uuid}/rescore` + feature tests (mismo input → mismo resultado; un solo
      `is_current`; filas en `scout_score_reasons` con `signal_id`, puntos y explicación).
- [ ] T035 Export CSV/XLSX con `dataset=leads|funnel` según `BACKEND-PHP` §8 (transformer,
      request, controller, `/export` antes de `/{uuid}`) + tests.
      **Checkpoint semana 1:** con la lista ICP importada y las ofertas ingeridas ya se puede
      prospectar con la exportación.

## Fase F — Empresa, descubrimiento, obtención, presupuesto, decisores y canales (semana 2)

- [ ] T036 [P] `OutboundUrlGuard`: solo http/https; resolución DNS que bloquea rangos privados y
      metadata (OWASP §15); denylist de redes profesionales, portales restrictivos **e
      intermediarios de datos de contacto** (linkedin.com, xing.com, indeed.com, glassdoor.*,
      apollo.io, zoominfo.com, rocketreach.co, lusha.com, kaspr.io, hunter.io; A17) + Unit tests.
- [ ] T037 [P] `RobotsTxtPolicy` **propia** (D1): user-agent `*` y propio, Allow/Disallow,
      comodines `*` y `$`, caché de 24 h, fallo de red = permitido con log + Unit tests.
- [ ] T038 `PageFetcherPort` + `DirectHttpPageFetcher` (timeout de 5 s, 2 reintentos solo ante
      errores de red/5xx, límite de 2 MB, solo `text/html`, heurística de SPA vacía, User-Agent
      honesto `LeadScoutBot/1.0 (+URL de contacto)`, **detección de bloqueo**: 401/403/429, muro de
      login y marcadores de desafío CAPTCHA/anti-bot → resultado `blocked` sin reintento) + tests
      con `Http::fake` (403 con desafío → `blocked` y 1 sola petición).
- [ ] T039 `FirecrawlPageFetcher` **con cliente propio de LeadScout sobre la API v2**
      (`POST /v2/scrape`; no el `FirecrawlScrapeAdapter` compartido, que usa `/v1`, no fija `proxy`
      y deja `storeInCache: true`): `proxy: "basic"` y **`storeInCache: false`** siempre, formatos
      markdown (+ `html` para el resumen de formularios de T050, mismo crédito; el HTML no se
      guarda), **validación de `metadata.proxyUsed == "basic"` en cada respuesta** (si no →
      contenido descartado, intento `proxy_mismatch` y Firecrawl desactivado en LeadScout hasta
      revisión manual), circuit breaker existente + tests (`Http::assertSent` comprueba
      `proxy=basic` y `storeInCache=false` en cada petición; respuesta fake con
      `proxyUsed: "enhanced"` → sin contenido guardado, `proxy_mismatch` y la siguiente URL no llama
      a Firecrawl; respuesta de Firecrawl indicando bloqueo → `blocked`) (A16, research R15).
- [ ] T040 `BudgetLedger` sobre `scout_budgets` (fila por mes y categoría creada al vuelo con el
      límite de config; `spent_micros` incrementado de forma **atómica** en cada llamada de pago de
      búsqueda, extracción e IA; comprobación previa del límite) + `GetBudgetStatusHandler` +
      `UpdateBudgetsHandler` + `GET/PUT budgets` (límites editables) + tests (límite alcanzado → la
      categoría se detiene con motivo; cambio de mes → fila nueva con gasto 0; dos llamadas
      concurrentes no pierden incrementos; PUT con valor negativo → 422).
- [ ] T041 `FetchLadder`: caché fresca → robots → HTTP directo → Firecrawl si hay presupuesto →
      fallo, registrando cada intento en `scout_fetch_attempts` (con `company_id`) + tests (presupuesto agotado → sin
      Firecrawl; robots disallow → `skipped_robots`; **HTTP directo `blocked` → nunca Firecrawl**;
      Firecrawl solo tras SPA vacía o timeout; URL de linkedin.com → rechazada por el guard sin
      petición).
- [ ] T042 `SearchPort` + `TavilySearchAdapter` (reutiliza `TavilyClientInterface`; profundidad
      **por uso**: `advanced` para el descubrimiento — más relevancia, ~60 consultas × 2 créditos =
      ~120/mes dentro del free tier — y `basic` para resolver el dominio de una empresa conocida) +
      caché de 30 días en **`scout_search_queries`** (`query_hash` de la consulta normalizada;
      guarda resultados, `purpose`, ola, familia, estado y coste; incrementa `scout_budgets`) +
      **denylist enviada en `exclude_domains`** (≤ 150) y filtro local como segunda barrera
      (linkedin.com y demás) + `country` según la ola + `auto_parameters` desactivado + **guard que
      rechaza consultas con nombres de personas** (solo plantillas servicio × tecnología × país o
      nombre de empresa) **y consultas dirigidas a redes profesionales o intermediarios de datos**
      (`linkedin`, `site:linkedin.com`, `xing`, `apollo`, `zoominfo`… sin distinguir mayúsculas; A17),
      nunca `extract` + ampliar `TavilyClientInterface` con `excludeDomains` y `country`
      opcionales sin romper CourseScripts + tests (Tavily 432 → breaker abierto y parada con
      motivo, **sin respaldo**; consulta repetida dentro de la ventana → 0 llamadas; resultado de
      linkedin.com → descartado; `Http::assertSent` con `exclude_domains`; consulta con un nombre
      de decisor → rechazada sin petición; `CTO agencia site:linkedin.com` → rechazada sin
      petición y sin coste) (A16, A17).
- [ ] T043 `ResolveCompanyHandler`: dominio desde el payload → `CanonicalDomain`; si falta,
      `SearchPort`; estado `unresolved`; supresión; consolidación por alias + tests (sin
      reintento en bucle).
- [ ] T044 **US-7 en el MVP:** `DiscoverAgenciesHandler` + `DiscoverAgenciesJob` +
      `lead-scout:discover {--wave=} {--country=} {--family=}` (semanal en `routes/console.php`):
      **tres olas** de config con su reparto **en paralelo por peso** (~60/30/10 % de las consultas
      de cada ejecución, no secuencial; A17), países del catálogo con `country` de Tavily y familias por ola
      (servicio × tecnología × ciudad/país × modelo comercial, siempre con variante «recurrente»:
      mantenimiento, soporte, partner, marca blanca), `discovery_wave` guardado, sin repetir
      combinaciones dentro de la ventana de caché, resultados filtrados por la denylist (portales
      de empleo, directorios, marketplaces freelance, redes sociales), `CanonicalDomain`,
      deduplicación, supresión respetada, empresas con `origin=discovery` y
      `origin_ref=<consulta>`, presupuesto de búsqueda aplicado → encadena `EnrichCompanyJob` +
      tests (dominio de portal descartado; dominio ya existente **o suprimido → no se duplica ni se
      encadena `EnrichCompanyJob`** (0 peticiones a la web ni a Firecrawl); presupuesto agotado →
      sin llamadas; el reparto por ola se respeta en una semana; solape horario calculado con la
      zona horaria IANA: `Europe/Athens` en septiembre → 7 h, `Australia/Sydney` → 0 h).
- [ ] T045 `EnrichCompanyHandler` + `EnrichCompanyJob`: sitemap/enlaces de la home → ≤ 4 páginas
      por palabras clave PT/ES/EN → `FetchLadder` → `scout_fetched_pages`; se detiene con
      evidencia suficiente; si la empresa tiene `needs_research`, **una sola** ronda extra con
      páginas no obtenidas (casos, blog) y re-score + tests (sin bucles).
- [ ] T046 [P] VO `RoleTaxonomy`: cargos permitidos por categoría (Founder/Fundador/Cofundador/
      Co-founder; CEO/Managing Director/Director General/Diretor Geral/Sócio-gerente/Partner/Socio/
      Sócio; CTO/Director técnico/Diretor técnico/Head of Engineering/Head of Development/
      Head of Delivery/VP Engineering/Engineering Manager), excluidos (Recruiter, Talent
      Acquisition, RRHH/Recursos Humanos/People, Developer/Programador/Desenvolvedor, Designer,
      Marketing, Sales/Comercial, Intern/Becario/Estagiário, Assistant) y ambiguos → no guardar;
      prioridad por tamaño + Unit tests.
- [ ] T047 Servicio de dominio `DecisionMakerExtractor` (puro, **sin IA**): JSON-LD
      (`founder`, `Person.jobTitle`), bloques de equipo nombre+cargo, frases de «nosotros»,
      contacto en la oferta; email solo del mismo bloque y del dominio de la empresa;
      `team_size_observed`; evidencia por persona; clasifica `email_kind` (nominativo/genérico);
      **ignora imágenes y nunca extrae categorías especiales** (FR-30) + Unit tests con HTML
      sintético PT/ES/EN (recruiter y developer no extraídos; `info@` y gmail no asignados a
      personas).
- [ ] T048 `ExtractDecisionMakersHandler` después de `EnrichCompanyHandler`: produce candidatos
      **en memoria** (para ocultar nombres), y tras el scoring **persiste solo si el lead es A/B**
      (`contact_deadline_at = +30 d`, `email_kind`); si el lead baja a C/Descartar, anonimiza;
      elige el principal por tamaño, marca `has_decision_maker`, omite las personas con
      oposición en `scout_contact_objections` + feature tests (lead sin decisor sigue en la bandeja; lead C →
      0 contactos guardados).
- [ ] T049 Endpoints de contactos: `POST leads/{uuid}/contacts`, `PATCH contacts/{uuid}`
      (`UpsertContactHandler`: 422 con cargo excluido o email fuera del dominio; cambio de
      principal) y `POST contacts/{uuid}/objection` (`ObjectContactHandler`: anonimiza + fila en
      `person_hash` en `scout_contact_objections` + fila `objection` en `scout_privacy_requests`; 409 al volver a
      añadirla) + tests de permisos.
- [ ] T050 Resumen de formularios en los fetchers: `DirectHttpPageFetcher` y
      `FirecrawlPageFetcher` (formatos `markdown` + `html`, 1 crédito) generan `forms_summary`
      (campos, `textarea`, CAPTCHA, host del `action`) y **descartan el HTML** + tests (ningún
      HTML persistido).
- [ ] T051 Servicio de dominio `ContactChannelDetector` (puro, **sin IA**): `contact_form`,
      `careers_form` (**siempre extraído**, `audience=hr_recruiting`), `freelance_call`,
      `partner_page` (+ señal comercial `fact`), `job_posting_apply`, `generic_email` (solo buzones
      genéricos del dominio), `company_network_page`; patrones PT/ES/EN; **no extrae teléfonos** +
      Unit tests con HTML sintético.
- [ ] T052 `DetectContactChannelsHandler` en la cadena (después de los decisores) +
      `PATCH channels/{uuid}` (activo/roto; re-verificación marca `broken`) + feature tests:
      **ninguna petición a la URL `action` de un formulario** (`Http::assertNotSent`) y ningún
      teléfono en la base de datos.
- [ ] T082 Servicio de dominio `PublicCompanyDataExtractor` (puro, **sin IA**, A16, FR-38, FR-39) +
      paso 4d en la cadena (después de T052): allowlist de `NORMATIVA-RGPD.md` §11 desde JSON-LD
      `Organization` (`legalName`, `taxID`, `vatID`, `foundingDate`, `address.addressLocality`,
      `addressCountry`) → aviso legal / *termos* / privacidad → pie de página; `public_urls` por
      `page_type`; `public_data_evidence` por campo; formas jurídicas PT/ES (S.L., S.L.U., S.A.,
      Lda., Unipessoal Lda., S.A.) frente a persona física (autónomo, *empresário em nome
      individual*, NIF de persona física, primera persona) → sin datos de identificación y
      `solo_freelancer`; cruce del NIPC con `scout_suppressions` (lista DGC) + Unit tests con HTML
      sintético PT/ES/EN (teléfono y dirección completa **no** guardados; clave fuera de la
      allowlist ignorada; autónomo → nada guardado) + feature test del paso 4d (0 peticiones HTTP
      nuevas).
- [ ] T083 Servicio de dominio `PersonalDataScrubber` (puro, A16, FR-25): elimina emails, teléfonos
      y bloques de persona (testimonios firmados, autor/firma de posts, tarjetas de equipo) y
      sustituye los nombres de T048 por `[PERSONA]`; se usa en T057 y en el borrador (T062) + Unit
      tests PT/ES/EN (texto con testimonio, autor, email y teléfono → ninguno sale; texto de
      servicios intacto).
- [ ] T084 `SuppressionGate` (dominio, A18, FR-43) + `RecordReplyHandler` + `POST
      outreaches/{uuid}/reply` (`interested` → `positive`, `not_interested` → `lost`, `unsubscribe`
      → `do_not_contact` + supresión de la empresa en todos los canales + oposición de la persona si
      el buzón era nominativo; evento de etapa con `operator_id`) + integración del gate en
      descubrimiento (T044), importación CSV, alta manual, ingesta de ofertas, enriquecimiento,
      score, borrador (T062) y `sent` (T063) + subcomando `lead-scout:privacy lift-suppression
      --domain= --evidence=` (única vía; registrado en activity log y `scout_privacy_requests`) +
      tests (tras `unsubscribe`: la empresa reaparece en Tavily, en un CSV, en alta manual y con una
      oferta nueva → no se crea ni enriquece, 0 llamadas a la IA, borrador y `sent` → 409; ninguna
      ruta web levanta la supresión; `lift-suppression` sin `--evidence` → falla).
- [ ] T085 Test de arquitectura Pest (A18, FR-40): `Modules\LeadScout` no usa `Illuminate\Mail`,
      `Illuminate\Support\Facades\Mail`, `Illuminate\Notifications`, `Symfony\Component\Mailer`
      ni SDKs de mensajería; ningún `Http::post` hacia el `action` de un formulario detectado;
      ninguna referencia a automatización de LinkedIn. **Context7 antes de escribirlo** (sintaxis de
      `arch()` en Pest 5).
- [ ] T053 Verificar los criterios de aceptación de US-3, US-7, US-8, US-11 y US-12 (detección)
      **y FR-38/FR-39** (datos públicos de empresa).

## Fase G — Extracción con IA verificada (semana 2)

- [ ] T054 `AiModelCatalogPort` + `ConfigAiModelCatalog`: opciones del catálogo, disponibilidad
      según credenciales, coste estimado por 100 usos, resolución propósito (default guardado en
      **`scout_ai_settings`**, sembrado desde `.env`) + override opcional → principal/respaldo +
      `GetAiSettingsHandler` + `UpdateAiSettingsHandler` + `AiSettingsController` `GET/PUT
      ai-settings` + seeder con los defaults de D3 + tests (fuera del catálogo → 422; proveedor sin
      key → `available=false` y 422 al guardarlo; cambiar el default no altera borradores ya
      generados).
- [ ] T055 `ExtractCompanySignalsAgent`: salida estructurada (`signal_key` de un enum cerrado,
      nature, excerpt, confidence, source_url; company_type; texto de tamaño), sin tools, datos
      en bloque delimitado, firmas según T001.
- [ ] T056 `LaravelAiSignalExtractor` vía `AIClientInterface`: provider/model de `extraction`,
      respaldo si falla, **verificación literal del fragmento**, allowlist y registro de
      `ai_provider`/`ai_model` + coste real (`usage`) sumado a `scout_budgets` (categoría IA)
      + tests con cliente espía (fragmento inventado → descartado; falla el principal → respaldo).
- [ ] T057 `ExtractSignalsHandler` + `ExtractSignalsJob`: reglas primero, LLM solo para las
      dimensiones que faltan, **sin páginas `team` y con el contenido pasado por
      `PersonalDataScrubber` (T083)**, presupuesto de IA, `RateLimiter` `lead-scout-llm` + tests
      (presupuesto de IA agotado → no hay llamada; el cliente espía no recibe ningún nombre
      extraído, email, teléfono, testimonio ni autor).
- [ ] T058 **[DIFERIDA — A12, hasta el primer ingreso]** Mientras no se haga, se usan los modelos
      por defecto de D3: la extracción sigue protegida por la verificación literal y la allowlist
      de T056, y el operador revisa el 100 % de los borradores antes de enviarlos (no hay envío
      automático).
      Eval manual de extracción (grupo Pest `llm-eval`, excluido por defecto): 30 páginas
      etiquetadas (mezcla de empresas de ofertas y de descubrimiento), **Gemini 3.7 Flash vs
      Sonnet 5**, precisión de señales comerciales ≥ 0,85 y coste real → confirmar el default de
      D3 y anotarlo en `research.md`. Requiere claves reales y OP-8.
- [ ] T059 Cadena end-to-end `Bus::chain` ((Ingest | Discover | Import) → Resolve → Enrich →
      Decisores → Canales → Extract → Score), idempotente + feature test con fakes → verificar los
      criterios completos de US-3/US-4/US-7.

## Fase H — US-5 y US-10: bandeja y borradores (semana 3)

- [ ] T060 `ListLeadsHandler` (filtros tier/país/**ola**/tipo/señal/etapa/**origen**/
      `needs_research`/**actividad**/**tamaño**/búsqueda; los descartados se ven aparte con su
      `discard_reason`, eager loading con columnas explícitas, `withCount`, paginación ≤ 100) +
      `GetLeadHandler` (razones y evidencia) + `LeadController` index/show + tests: sin N+1 con
      `shouldBeStrict`; con 5.000 leads de factory el nº de consultas queda acotado (≤ 5 por
      página: la base está en la nube y cada consulta cuesta latencia).
- [ ] T061 [P] `ChannelAdvisor` con **orden legal × comercial** configurable por país (US-12):
      oferta publicada / llamada a freelancers → página de partners → formulario de contacto →
      red profesional (a mano) → buzón genérico (solo donde sea legal) → «Trabaja con nosotros»
      (aviso: «suele llegar a RRHH/recruiters; preséntalo como colaboración freelance») → email
      nominativo (no en frío); cada canal descartado lleva su motivo; sin canales permitidos →
      «sin canal permitido» y sin borrador de email; **reglas por país leídas de config con estado
      legal (A18, FR-44): `block` se aplica aunque esté pendiente; `allow` solo si `verified`, si no
      → «⚠️ verificación legal pendiente» y no recomendado**; **países sin regla en config (olas 2 y
      3 hasta OP-26) → nunca email en frío** + Unit tests (`allow` pendiente → no recomendado con
      aviso; mismo `allow` en `verified` → recomendado; `block` pendiente → bloquea; NL o US sin regla → solo
      oferta/formulario/red; ES → respuesta a la
      oferta/formulario/red + warning y `cold_email_allowed=false` aunque haya email publicado;
      **PT nominativo → `false` + aviso «zona gris»**; PT buzón genérico de empresa → email con
      baja **solo si su regla está `verified`** y la empresa no está en la lista DGC vigente (lista
      con > 3 meses → bloqueado con aviso); oferta de empleo fijo → `employment_application`; empresa de descubrimiento sin
      oferta → nunca `job_posting_apply`; sin decisor → formulario).
- [ ] T062 `WriteOutreachOpenerAgent` + `LaravelAiDraftWriter` (provider/model de `drafting` o el
      **elegido en el selector** del borrador) + `GenerateDraftHandler` (plantilla por variante +
      saludo con el nombre del decisor principal, insertado por la plantilla y **nunca enviado a
      la IA** + `ProofPointMatcher` elige la prueba del CV + primera frase (la IA recibe solo la
      evidencia de la empresa y el resumen público de esa prueba) + canal + validación de claims
      contra el perfil) + `POST leads/{uuid}/drafts` (opciones del selector desde `GET ai-settings`) +
      tests: AWS sin confirmar → aviso y 422 al marcar `ready`; suprimida → 409; presupuesto → 402;
      proveedor fuera del catálogo o sin key → 422; el borrador registra el proveedor/modelo usado;
      perfil `stale` → aviso; **el cliente espía no recibe el `raw_text` del CV**; **lead Tier C o
      descartado → 409 (solo A/B, FR-40)**; el borrador guarda `template_key` y `template_version`.
- [ ] T063 `UpdateOutreachStageHandler` (transiciones válidas, **fila en
      `scout_outreach_stage_events`** por cada cambio, `stage_changed_at`, `sent_at`,
      **`contact_channel_id` obligatorio al pasar a `sent`** y canal marcado `used`; **A18/FR-41:**
      `send_medium` obligatorio, `operator_id` = usuario autenticado (lo que envíe el cliente se
      ignora), **`sender_kind` obligatorio con valor por defecto de config (A19)**, `legal_rule_status` según `ChannelAdvisor`, regla pendiente sin
      `acknowledge_pending_legal` → 422 y con él → `legal_ack_at`; `SuppressionGate` → 409) +
      `PATCH outreaches/{uuid}` + **botón «Copiar» del borrador (asunto + cuerpo con aviso art. 14 y
      baja) para el envío manual desde el buzón del operador; el sistema nunca envía** (FR-16, A17) +
      aviso en la bandeja al superar 15 contactos `sent` en el día + tests (transición inválida →
      422; `sent` sin canal → 422; ir y volver entre etapas deja todo el historial; ninguna clase de
      Mail/SMTP en el módulo).
- [ ] T064 Frontend de la bandeja: **leer `FRONTEND/SKILL.md` y `ARCHITECTURE-VUE/SKILL.md` y
      seguir `/frontend-new`**. `typescript:transform` · página bandeja (DataTable `:lazy`,
      **sin selección de filas** o, si se añade, con BulkDelete/BulkRestore; columnas y filtros
      «decisor sí/no», origen e «investigar») · panel de detalle con razones y evidencia ·
      **bloque de decisores** (nombre, cargo, categoría, principal, evidencia, email publicado con
      la insignia «usable en frío / no usar en ES», alta manual y botón de oposición) · **bloque
      de canales** ordenados (enlace directo al formulario o página para enviar **a mano**,
      insignia de permitido / bloqueado con motivo, aviso RRHH en «Trabaja con nosotros», botón
      «roto», selector del canal usado al marcar enviado) · editor de borrador con **Select de
      proveedor y modelo de IA** (opciones de `ai-settings`, deshabilitadas sin key, coste estimado
      visible) · acciones de etapa · **pantalla de ajustes de IA** (default y respaldo por
      propósito) · **presupuesto editable** (límite, gasto del mes y estado) · bloque de
      **oportunidades** del contacto (alta y edición) · aviso del art. 14
      visible en el borrador y no eliminable. Tokens `var(--token)`; la salida de IA se muestra con
      `{{ }}`, nunca con `v-html`.

## Fase I — US-6: funnel, métricas y regla de decisión (semana 3)

- [ ] T065 `UpsertOpportunityHandler` + `OpportunityController`: `POST
      outreaches/{uuid}/opportunities` y `PATCH opportunities/{uuid}` (tipo, horas/mes, tarifa,
      importe, estado, fechas; **varias por contacto**) + tests (404, 422, dos oportunidades en el
      mismo contacto).
- [ ] T066 `GetFunnelMetricsHandler` (tasas por etapa/canal/variante/país/tipo de señal/**origen**
      a partir de `scout_outreach_stage_events`; volumen semanal por fuente; horas y € facturados
      desde `scout_opportunities`; coste por lead cualificado desde `scout_budgets`;
      efectividad desde `scout_search_queries` y `scout_fetch_attempts`; **% de leads A/B con decisor** y
      **respuesta por categoría de cargo**; **tasa de respuesta por tipo de canal**; **% de leads
      A/B con canal permitido**; **lectura de muestra (FR-33):** positivas esperadas con el rango
      2-5 % y aviso «no concluyente» mientras no se alcance la muestra de la regla, p. ej. «0 de
      30: esperado 0,6-1,5 → no concluyente»; **calidad de lo descubierto por ola:** % descartadas
      por motivo (freelancer, parada, absorbida…), % A/B y tasa de respuesta por ola (sirve para
      reajustar el reparto 60/30/10); **efectividad de búsqueda y extracción:** por familia
      de consulta, resultados → empresas nuevas válidas → leads A/B; por empresa, % con ≥ 1 página
      leída, % de URLs `blocked`, % de SPA vacías rescatadas por Firecrawl y coste por empresa
      enriquecida) + `MetricsController` + tests.
- [ ] T067 `DecisionRuleEvaluator` + `LockDecisionRuleHandler` + controller + tests (409 al editar
      una regla bloqueada; rama correcta con 2, 5 y 8 positivos sobre 150; antes de 150 →
      «no concluyente»).
- [ ] T068 **[DIFERIDA — A12, hasta el primer ingreso]** Frontend de métricas y regla de decisión
      (mismo proceso que T064). Mientras tanto, las métricas se consultan con `GET metrics` (T066)
      y la exportación `dataset=funnel` (T035).
- [ ] T069 Verificar los criterios de aceptación de US-5, US-6, US-10, US-11 y US-12 (0 personas con
      cargos excluidos en la base de datos) y la matriz de `NORMATIVA-RGPD.md` §6 contra FR-26 a
      FR-30 (tras T070-T074).

## Fase J — Transversal

- [x] T070 `PruneLeadContactsCommand` diario: anonimiza decisores **no contactados con
      `contact_deadline_at` vencido (30 d)** y contactados con `retention_until` vencido (12 m tras
      la última interacción) — nombre, email y URL a null; se conserva el cargo para las métricas —
      y **vacía `content_markdown` de páginas con más de 30 días** (conserva `content_hash` y
      `forms_summary`; la evidencia vive en `scout_signals`) para no llenar la base + tests.
- [x] T071 Aviso del art. 14 en los borradores (FR-26): bloque de plantilla por idioma (ES/PT/EN)
      con responsable, fuente pública (URL de la web de la empresa), finalidad, base legal, plazo,
      derecho de oposición, **línea de baja con una dirección de email válida** (LSSI 21.2) y
      enlace a la política de OP-11; `notified_at` se fija al marcar el outreach como `sent` +
      tests (el borrador sin aviso no puede pasar a `ready`).
- [x] T072 Exactitud (FR-28): antes de `GenerateDraftHandler`, si `last_verified_at` tiene > 90 días,
      re-obtener la página de evidencia con `FetchLadder`; si la persona ya no aparece →
      anonimizar y avisar al operador + tests.
- [x] T073 Derechos de las personas (FR-29) **por comando**: `lead-scout:privacy {search|export|
      erase|object} {--name=} {--email=}` (búsqueda mín. 3 caracteres; export → JSON a un archivo
      local fuera del repo; erase → anonimiza; object → `person_hash` en
      `scout_contact_objections`) + registro de cada solicitud en **`scout_privacy_requests`** con
      `subject_ref` (hash), tipo, fechas y resultado, **sin PII** +
      tests (el registro no contiene nombre ni email).
- [x] T074 Importación de la **lista DGC** (Lei 41/2004 art. 13.º-B) **por comando**:
      `lead-scout:import-dgc {file} {--period=}` (CSV/XLSX ≤ 10 MB, extensión y contenido
      validados), cruce por NIPC, dominio o nombre normalizado, `source=dgc_list` y
      `list_period`; `ChannelAdvisor` bloquea el email a empresas PT en la lista y cuando la última
      importación tiene > 3 meses + tests.
- [x] T075 Rate limiters (`lead-scout-llm`; export 10/min) en el provider + tests 429.
- [x] T076 Logging estructurado del pipeline (`ApplicationLogger`): sin secretos, sin PII de
      contactos, sin `draft_body` y **sin la cadena de conexión a Supabase**; `LogsActivity` solo
      con `logOnly`.
- [x] T077 **Copias de seguridad (obligatoria: Supabase Free, sin backups automáticos).**
      `lead-scout:backup` semanal → `pg_dump` solo de las tablas `scout_*` con el `pg_dump` del
      PostgreSQL local de T003 (misma versión mayor), a una carpeta fuera del repo indicada por el
      operador, retención de 4 copias (queda anotado en el RoPA, OP-10) + test del comando con
      `Process::fake`. **Confirmar la ruta con el operador.** Se adelanta a la semana 2: debe
      existir antes de guardar el primer lead real.
- [x] T078 Revisión OWASP: baseline de 15 ítems + §16 LLM (prompt injection, output handling,
      excessive agency, unbounded consumption) + **Supabase**: RLS activo en todas las `scout_*`,
      roles `anon`/`authenticated` sin permisos, Data API desactivada si no se usa (OP-19), SSL en
      la conexión, credenciales solo en `.env` + **sin evasión**: ninguna referencia a
      `proxy: auto|enhanced`, navegadores headless, rotación de IPs, User-Agent falso o resolvedores
      de CAPTCHA en el módulo; toda petición a Firecrawl con `storeInCache: false` y validación de
      `proxyUsed`; ninguna *provider tool* de búsqueda (WebSearch/WebFetch) en los agentes; ningún
      teléfono, dirección completa ni dato de autónomo en `scout_companies`; linkedin.com en la denylist; `raw_text` del CV fuera de logs,
      exports, props y prompts; con evidencia archivo:línea. Si OP-21 = servidor en la web: HTTPS,
      2FA, `APP_DEBUG=false` y rate limiting verificados.

## Fase K — Cierre

- [ ] T079 `vendor/bin/pint --dirty --format agent` → `php artisan test --compact
      --filter=LeadScout` → `php artisan test --compact --group=pgsql` (PostgreSQL local) →
      `php artisan test --compact` (suite completa en verde, **nunca contra Supabase**, con los
      conteos reales). *(18-09-2026: pint OK · LeadScout 137/140, 3 skip pgsql · suite completa
      omitida a petición del operador.)*
- [x] T080 Pipeline de finalización del router: `optimize:clear`, `ide-helper:generate`,
      `ide-helper:models --write`, `typescript:transform`, `pint`, `pint --test`,
      `scramble:clear`/`export`/`cache`; re-ejecutar el filtro si ide-helper tocó modelos →
      `index_repository(mode: "full")` + `index_status` (`indexed_at` posterior a la última
      edición).
- [x] T081 Pase de trazabilidad con el plan §11 (cada FR/US tiene código + test) → regenerar
      `SSD-SUMMARY.md` (Fase 8). Las migraciones se aplican a Supabase **solo después** de este
      pase y con confirmación del operador (`php artisan migrate` contra la nube).

## Fuera del MVP (documentado, sin tareas)

- **US-9** Fases geográficas 2-3 → activar fuentes, familias de consultas y países cuando llegue
  la Fase 2; la penalización de comunicación ya entra en T031/T032.
- Microservicio FastAPI + Crawl4AI, proxies, embeddings/pgvector y adaptación del CV por oferta →
  Fase 3 (> 1.000 empresas/mes medidas).

---
**Convención de commits:** `feat(lead-scout): T0XX descripción corta`
