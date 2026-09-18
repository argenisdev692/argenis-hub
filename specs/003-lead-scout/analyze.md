# Análisis de consistencia — 003-lead-scout

> Fase 6 · ANALYZE — Verificación cruzada spec ↔ clarify ↔ plan ↔ tasks antes de implementar.
> Fecha: 2026-09-16 · 6ª pasada: comparación con la variante MUSE-SPARK, Supabase, CV desde la
> tabla `cvs`, RAG y límites legales del scraping (LinkedIn, CAPTCHAs, anti-bots).

## 1. Resultado

**35 huecos encontrados en total → 35 corregidos. 0 huecos abiertos en spec/plan/tasks. 16 riesgos
aceptados. 1 dato pendiente del operador que no bloquea la implementación (OP-19: región,
versión, Data API y tamaño actual de Supabase) y 1 bloqueo operativo antes del primer contacto
real (OP-15, abogado).**

> **Respuestas del operador (16-09-2026, tras la 6ª pasada):** colchón = **2 meses**, Supabase =
> **Free**, CV = **`Argenis_Gonzalez_CV_2026.md`**, y la pregunta de si hacen falta FastAPI +
> Crawl4AI + proxies residenciales. Resultado: A12 (plan de 2 meses: tres vías, sistema operable
> en la semana 2, T021/T023/T058/T068 diferidas, T077 obligatoria), A13 (sin esa infraestructura;
> Tavily `advanced` en el descubrimiento y métricas de efectividad) y G31-G32.

> **Calidad de agencia (16-09-2026):** el operador define qué es una agencia buena (viva, con
> equipo real, trabajo recurrente, compatible con B1, ibérica o del resto del mundo). La spec no lo
> garantizaba → G33-G35 y A14: dimensión «Vitalidad y tamaño», reglas de descarte con motivo,
> condiciones extra para Tier A, tres olas geográficas en el MVP y puntuación B1 a nivel de agencia.

> **Modelo de datos (16-09-2026, A15):** el operador pide restaurar las 9 tablas fusionadas en A6.
> Vuelven `scout_ai_settings`, `scout_budgets`, `scout_search_queries`, `scout_fetch_attempts`,
> `scout_score_reasons`, `scout_contact_objections`, `scout_privacy_requests`,
> `scout_outreach_stage_events` y `scout_opportunities`; se retira `scout_provider_calls` para no
> duplicar datos → **22 tablas**. No es un hueco sino una decisión: se revisó la propagación en
> spec, plan y tareas (endpoints `ai-settings`, `budgets` editables y `opportunities`; historial de
> etapas; objeciones y privacidad en tablas propias) sin referencias pendientes a la versión de 14.

> **6ª pasada (2026-09-16):** el operador pide (1) traer lo mejor de MUSE-SPARK, (2) usar Supabase
> y valorar si 22 tablas son muchas, (3) leer el CV markdown ATS de la tabla `cvs` y valorar RAG,
> y (4) saber si el módulo es legal para subirlo a la web y si hará scraping de LinkedIn o saltará
> CAPTCHAs/anti-bots. Se revisó el código real de argenis-hub (research R14) y se investigó con
> Tavily (R13) y Context7 (R12). Aparecen G18-G30; **el más grave es G26**: por defecto Firecrawl
> escala a proxies anti-bot y el adaptador compartido no lo evita, lo que contradecía FR-13.
> Propagado a spec (US-1, US-4, US-6, US-7, US-10, FR-1, FR-13, FR-22, FR-29, FR-33 a FR-35),
> clarify (A1-A11, Q22-Q25), research (R12-R14), NORMATIVA (§6, §10), plan (casi todas las
> secciones) y tasks (renumeradas T001-T081 + OP-1 a OP-22).

> **5ª pasada:** US-12 (canales de contacto; «Trabaja con nosotros» se extrae con prioridad baja).
> **4ª pasada:** re-verificación con Tavily/Firecrawl (retención IRS 23 %, Bisnode, Landing.jobs)
> → G14-G16. **3ª pasada:** revisión normativa → G9-G13 (art. 14, email nominativo en PT).
> **2ª pasada:** faltaba la extracción de decisores (G7), señalado por el operador.

## 2. Huecos y contradicciones (todos corregidos)

### 2.1 6ª pasada

| # | Tipo | Hallazgo | Corrección aplicada |
|---|---|---|---|
| **G26** | **Contradicción con FR-13 (legal)** | Firecrawl usa `proxy: auto` por defecto, que «reintenta con proxies *enhanced*… para sitios con anti-bot avanzado» (docs.firecrawl.dev). El `FirecrawlScrapeAdapter` compartido no fija `proxy` (verificado en el código). Reutilizarlo tal cual = **evasión anti-bot sin saberlo** | A11 · cliente propio con `proxy: "basic"` + test `Http::assertSent` · plan §2, §8, §13 · T039, T078 |
| **G27** | Requisito sin operacionalizar | La escalera HTTP → Firecrawl no distinguía «bloqueado» de «no legible»: un 403 o un CAPTCHA acababa en Firecrawl | FR-13 ampliado · `FetchLadder`: bloqueo = parada · T038, T041 |
| G18 | Riesgo de negocio sin mitigar en el MVP | MVP solo con ofertas como señal frente a 4-11 ofertas «laravel» visibles en PT/ES (R6) | A1 · US-7 al MVP + importación ICP · T029, T042, T044 |
| G19 | Sobredimensionamiento | 22 tablas, pantallas de ajustes de IA, privacidad y DGC para un único usuario | A2-A4 · comandos para privacidad y DGC · **la reducción de tablas (A6) se revirtió por decisión del operador (A15): 22 tablas** |
| G20 | Seguridad (Supabase) | Las tablas del esquema `public` se exponen por la Data API; sin RLS son legibles y escribibles con la clave pública; `scout_contacts` tiene datos personales | A5 · helper `SupabaseRls` · T010, T014, T078 · OP-19 |
| G21 | Riesgo operativo | Tests y base en la nube: un `.env.testing` mal copiado con `RefreshDatabase` borraría la base; además `phpunit.xml` usa sqlite `:memory:` (R14), así que CHECK, índices parciales, `jsonb` y RLS no se probarían | Guard de `DB_HOST` + grupo Pest `pgsql` en PostgreSQL local · T003, T014, T079 |
| G22 | Capacidad (Supabase Free) | 500 MB para todo argenis-hub, sin backups automáticos, pausa tras 7 días | Poda de markdown a 30 días (T070) · `lead-scout:backup` obligatorio en plan Free (T077) · plan §4.2 |
| G23 | Métrica engañosa | Con muestras pequeñas, un 0 % se leería como fracaso (aporte MUSE-SPARK) | FR-33 · US-6 · T066, T067 |
| G24 | Hueco de flujo | Lead con score alto y confianza baja acababa en C sin más evidencia | Marca `needs_research` + 1 ronda extra (aporte MUSE-SPARK) · US-4 · T033, T045 |
| G25 | Duplicación de datos sensibles | El plan pedía subir el CV en markdown, pero ya está en `cvs.raw_text`, que el módulo Cvs protege a propósito | A9 · `CvSourcePort` en solo lectura · FR-1, FR-35 · T017, T018 · OP-22 |
| G28 | Obligación legal incompleta | Supabase (encargado que aloja datos personales) no figuraba en RoPA/DPA | NORMATIVA N11 y §6 · OP-10, OP-12, OP-19 |
| G29 | Referencia errónea | `NORMATIVA-RGPD.md` §6 N4 (conservación) apuntaba a la tarea de re-verificación en vez de a la de poda | Corregido a T070 |
| G30 | Dependencia inexistente | El plan preveía búsqueda de respaldo con Firecrawl, pero `FirecrawlClientInterface` solo expone `scrape` (R14) | Sin respaldo; parada con motivo · T042 · plan §9 |
| G31 | Plan incompatible con el colchón | Entregas pensadas para 3+ meses; con 2 meses, el primer cliente del escenario base (mes 3) cae fuera del colchón | A12 · tres vías en paralelo · operable en la semana 2 · 4 tareas diferidas · punto de control de caja · import de contactos manuales (T029) · OP-23 a OP-25 |
| G32 | Decisión técnica sin datos | No había forma de saber si la extracción fallaba lo bastante como para justificar Crawl4AI o proxies, ni qué consultas de búsqueda funcionaban | A13 · métricas de efectividad en T066 · umbrales en plan §3.5 · Tavily `advanced` en descubrimiento (T042) |
| G33 | Contradicción con el objetivo del operador | «El tamaño solo desempata, nunca descarta»: un freelancer de una persona (competidor, no comprador) podía llegar a Tier A/B | FR-36 · Descartar `solo_freelancer`; 2-4 personas con techo en B salvo señal fuerte · T031, T033 |
| G34 | Señal inexistente | Ninguna medida de actividad: una agencia parada puntuaba igual que una viva | FR-36 · dimensión «Vitalidad y tamaño» (peso 15), `activity_status`, Descartar `inactive` y `dead_or_acquired`; Tier A exige actividad ≤ 12 meses; `lastmod` autogenerado ignorado · T011, T031-T033 |
| G35 | Alcance geográfico insuficiente | Descubrimiento solo PT/ES; resto del mundo en Fase 2/3; B1 solo se evaluaba en ofertas, no en agencias; email en países sin regla sin contemplar | FR-37 · US-9 al MVP · tres olas 60/30/10 · Comunicación B1 por forma de trabajo + solape horario · sin email en frío sin regla (OP-26) · T006, T042, T044, T061, T066 |

### 2.2 Pasadas 1-5

| # | Tipo | Hallazgo | Corrección aplicada |
|---|---|---|---|
| G1 | Requisito sin endpoint | US-6 pide «horas y euros facturados» sin endpoint | `POST outreaches/{uuid}/opportunities` + `PATCH opportunities/{uuid}` · T065 |
| G2 | Requisito cubierto a medias | FR-21 exige exportar bandeja **y** funnel | `dataset=leads\|funnel` · T035 |
| G3 | Contradicción con clarify | Q6 mide aparte las candidaturas a empleo fijo; faltaba el valor en `channel` | `employment_application` · T009, T061 |
| G4 | Tarea sin componente | Extractor por reglas sin componente en el plan | `RuleBasedSignalExtractor` · T031 |
| G5 | Violación de capas | Resolución de modelos de IA leyendo config desde Application | `AiModelCatalogPort` + `ConfigAiModelCatalog` · T054 |
| G6 | Convención del proyecto | Comandos en `Infrastructure/Console/Commands/` + `routes/console.php` | plan §6 · T026, T027, T070 |
| G7 | Requisito implícito sin cobertura | No existía la extracción de decisores ni el filtro por cargo | US-11, FR-23-25 · T046-T049 |
| G8 | Contradicción interna | Saludo con nombre frente a «sin nombres a la IA» | Nombre por plantilla; `[PERSONA]` en prompts · T057, T062 |
| G9 | Obligación legal | Art. 14 RGPD (informar) | FR-26 · T071 · OP-11 |
| G10 | Contradicción con la normativa | Email nominativo en PT como «usable en frío» | Q16-bis → zona gris · T061 · OP-15 |
| G11 | Conservación y minimización | Decisores de todos los leads durante 12 meses | FR-27: solo A/B, 30 d / 12 m · T048, T070 |
| G12 | Derechos y exactitud | Sin acceso/supresión/registro ni re-verificación | FR-28, FR-29 · T072, T073 |
| G13 | Responsabilidad proactiva | Sin LIA, RoPA, DPIA ni DPA/DPF | OP-9 a OP-14 |
| G14 | Obligación legal | Lista DGC (Lei 41/2004 art. 13.º-B) | FR-17 · T074 · OP-16 |
| G15 | Requisito legal incompleto | LSSI 21.2: email válido de oposición | FR-26 · T071 |
| G16 | Términos de fuentes | Remotive (atribución, ≤ 4/día) y Landing.jobs obsoleta | T020, T021 |
| G17 | Hueco de diseño | Recomendador de canal sin conocer formularios/partners | US-12 · FR-31/32 · T050-T052, T061 |

## 3. Cobertura requisito → plan → tareas

| Requisito | Plan | Tareas | Estado |
|---|---|---|---|
| US-1 / FR-1 (perfil desde `cvs`, sin RAG) | §3.1, §3.2, §3.4, §4, §5 | T016-T019 · OP-22 | ✅ (G25) |
| US-2 / FR-2, FR-3, FR-4, FR-5 | §3.1, §3.2 (1-2), §4 | T009, T011, T015, T020-T028, T030 | ✅ |
| US-3 / FR-6 | §3.2 (3-5), §4 | T031, T043, T045, T055-T057, T059 | ✅ |
| US-4 / FR-7, FR-8, FR-9 (+ `needs_research`) | §3.3, §4 | T011, T031-T034, T045, T059 | ✅ (G24) |
| **FR-36** (vitalidad y tamaño; descartes con motivo) | §3.2 (5a), §3.3, §4, §7 | T009, T011, T031-T033, T060, T066 | ✅ (G33, G34) |
| US-5 / FR-15, FR-16 | §3.2 (7), §5, §8 | T060-T062, T064 | ✅ |
| US-6 / FR-18, FR-19, FR-33 | §4, §5 | T065-T069 | ✅ (G1, G23) |
| **US-7** (descubrimiento + importación ICP) | §3.2 (0), §4, §5 | T029, T042, T044, T053, T059 | ✅ **MVP** (G18) |
| US-8 / FR-11, FR-12, FR-14 | §3.1 `FetchLadder`, `BudgetLedger`, §4 | T038-T042, T057 | ✅ |
| **US-9 / FR-37** (tres olas + B1 + email por país) | §3.2 (0), §3.3, §4, §8 | T006, T031, T032, T042, T044, T061, T066 · OP-26 | ✅ **MVP** (G35); fuentes de empleo nuevas por país en Fase 2 |
| US-10 / FR-22 (selector en el borrador) | §2, §3.1, §4, §5, §7, §8 | T006, T054, T056, T062, T064 | ✅ (A2) |
| US-11 / FR-23 | §3.1, §3.2 (4b), §4 | T046-T048, T059, T069 | ✅ (G7) |
| FR-24 | §3.2 (4b), §4, §5 | T047, T061, T064 | ✅ |
| FR-25 | §3.2 (5b), §4 `scout_contact_objections`, §8 | T036, T049, T057, T062, T070 | ✅ (G8) |
| FR-26 (art. 14) | §3.2 (7), §4, §8 | T071, T064 · OP-11 | ✅ (G9) |
| FR-27 (solo A/B; 30 d / 12 m) | §3.2 (4b, 6b), §4 | T048, T070 | ✅ (G11) |
| FR-28 (re-verificación a 90 d) | §3.2 (7), §4 | T072 | ✅ (G12) |
| FR-29 (derechos, por comando) | §5 comandos, §8 | T073 | ✅ (G12, A3) |
| FR-30 | §3.2 (4b), §8 | T047, T035 | ✅ |
| US-12 / FR-31 | §3.1, §3.2 (4c), §4 | T050-T052, T061, T064 | ✅ (G17) |
| FR-32 | §3.2 (4c), §8 | T050, T052 | ✅ |
| Métrica por canal y por origen | §4 | T063, T066 | ✅ |
| FR-10 | §3.2 (5a-b) | T017, T031, T057 | ✅ |
| **FR-13 ampliado** (sin evasión, bloqueo = parada, sin LinkedIn) | §2, §3.1, §3.2 (4), §7, §8, §13 | T024, T028, T036-T039, T041, T042, T078 · OP-5 | ✅ (G26, G27) |
| FR-17 · FR-20 | §4, §5 | T029, T074 | ✅ |
| FR-21 | §5 | T035 | ✅ (G2) |
| **FR-34** (alojamiento UE, sin exposición, backups, tests fuera) | §2, §4.2, §8 | T003, T010, T014, T070, T077, T078 · OP-19 | ✅ (G20-G22) |
| **FR-35** (texto del CV fuera de IA/logs/exports) | §3.2, §3.4, §8 | T017, T062, T076, T078 | ✅ (G25) |
| NFR Coste | §0, §2, §3.2 | T006, T040, T057 | ✅ |
| NFR Rendimiento (BD en la nube) · Capacidad | §4.1, §4.2 | T060, T070 | ✅ |
| NFR Seguridad | §4.2, §8 | T005, T036, T075-T078 | ✅ |
| NFR Tolerancia a fallos | §3.2 (1), §9 | T025, T042, T056 | ✅ |
| NFR Auditabilidad | §3.3 `rules_version`, §4 `scout_score_reasons` | T032, T034, T056 | ✅ |
| NFR Cumplimiento | §8, §12 | T061, T070, T073, T074 · OP-3, OP-5, OP-9 a OP-16 | ✅ |

**Tareas sin justificación en la spec (huérfanas):** ninguna. T001, T002, T004 y T008 trazan a
los `[UNVERIFIED]` del plan §2 y a D2/D5; T079-T081 son el cierre obligatorio del router.

## 4. Plan frente a decisiones de clarify

| Decisión | ¿El plan la respeta? |
|---|---|
| Q1 ofertas como señal → **revisada por A1** | ✅ §3.2 paso 0 · US-7 en el MVP |
| Q2 bandeja web en el MVP | ✅ §10 semana 3 · T064 · ⚠️ colchón por confirmar (OP-20) |
| Q3 ≤ €30/mes (€8 / €15 / €7) | ✅ T006, T040 · ver riesgo R2 |
| Q4 canal legal en España | ✅ `ChannelAdvisor` · T061 |
| Q5 sin SaaS | ✅ · NORMATIVA §10.3 escenario B |
| Q6 empleo + contrato | ✅ tras G3 |
| Q8 idioma de los borradores | ✅ T062 |
| Q9 umbrales 150 / 8 semanas | ✅ T006, T067 (no se adopta el 100/4 de MUSE-SPARK, A8) |
| Q10 tarifa y precios bajos | ✅ `min_hourly_rate_cents` · T031 |
| Q11 PII mínima; sin PII al LLM | ✅ §8 · T070, T076 |
| Q12 claims no verificados | ✅ T017, T062 |
| Q13 interfaz web | ✅ |
| Q14 selector de IA → **reducido por A2** | ✅ selector en el borrador · T062, T064 |
| Q15-Q21 decisores, emails, conservación, art. 14, canales | ✅ (sin cambios en la 6ª pasada salvo numeración) |
| D1 parser de robots propio | ✅ T037 |
| D2 Herd local | ✅ T008 · alternativa de servidor en OP-21 |
| D3 Sonnet 5 / Gemini 3.7 Flash | ✅ defaults en `.env` + T058 |
| D4 ITJobs más adelante | ✅ fuente `paused` · T023, OP-5 |
| D5 `search_depth` por llamada | ✅ T007 |
| A1 descubrimiento en el MVP | ✅ §3.2 (0) · T042, T044 |
| A2-A4 selector, privacidad y DGC reducidos | ✅ §5 comandos · T062, T073, T074 |
| A5 Supabase | ✅ §4.2 · T002, T003, T010, T014, T077, T078 |
| A6 14 tablas → **revertido por A15** | ✅ §4 (22 tablas), §4.1 · T009-T014 |
| A7 aportes MUSE-SPARK | ✅ §0, §3.3, §12, §13 · T033, T045, T066 · OP-18 |
| A8 lo descartado de MUSE-SPARK | ✅ spec §8 · plan §13 |
| A9 perfil desde `cvs` | ✅ §3.2, §4, §5 · T017, T018 |
| A10 sin RAG | ✅ §3.4 · T016, T062 |
| A11 sin evasión | ✅ §2, §3.1, §8, §13 · T038, T039, T041, T042, T078 |
| Q22 legalidad de subirlo a la web | ✅ NORMATIVA §10 · plan §9, §12 gate 8 · OP-21 |
| Q23 LinkedIn / CAPTCHAs / anti-bots | ✅ No; FR-13 · A11 |
| Q24 → A12 colchón de 2 meses | ✅ §0, §9, §10 · T029, T077 · diferidas T021, T023, T058, T068 · OP-23 a OP-25 |
| Q25 Supabase Free | ✅ §4.2 · T070, T077 obligatoria |
| OP-22 CV `Argenis_Gonzalez_CV_2026.md` | ✅ T017 (parser por secciones, fixture anonimizado) · research R14 |
| A13 sin FastAPI/Crawl4AI/proxies residenciales | ✅ §2, §3.5, §9 D5, §10 · T042, T066 |
| A14 agencia buena (viva, equipo real, recurrente, B1; tres olas) | ✅ §0, §3.2, §3.3, §4, §7, §8 · T006, T009, T011, T031-T033, T044, T060, T061, T066 · OP-26 |
| A15 restaurar las 22 tablas | ✅ plan §4, §4.1, §5 (`ai-settings`, `budgets` editables, `opportunities`) · spec US-10, FR-22, §7 · T009-T014, T040-T042, T054, T063-T066, T073 |

## 5. Riesgos aceptados (no bloquean)

| # | Riesgo | Por qué se acepta | Vigilancia |
|---|---|---|---|
| R1 | «1.000 empresas/mes sin intervención» solo con el PC encendido (D2) | €0 durante el test; OP-21 permite desplegar en servidor | Volumen semanal por fuente |
| R2 | Todo en Sonnet 5 roza el tope de IA de €7 | `BudgetLedger` detiene la IA y degrada a reglas; el default mixto cuesta ≈ $4 | Coste en T066; aviso en el selector |
| R3 | Gemini 3.7 Flash dobla su precio el 01-01-2027 | `price_valid_until` en el catálogo + T002 | Aviso automático |
| R4 | La suite general corre en sqlite y producción en PostgreSQL | Grupo `pgsql` cubre lo específico de LeadScout; no se cambia la suite del resto de módulos | T014 y T079 con `--group=pgsql` |
| R5 | Decisores perdidos al extraer solo con reglas | Decisión Q17 a favor de la privacidad; alta manual | «% de leads A/B con decisor»; < 30 % → revisar `RoleTaxonomy` |
| R6 | Email publicado atribuido a otra persona | Mismo contenedor + dominio; revisión manual | Evidencia visible en la bandeja |
| R7 | Interpretación legal pendiente (email nominativo PT, plazo art. 14) | Opción conservadora; sin envío automático | OP-15 |
| R8 | Cambio regulatorio (EDPB 03/2026 final, DPF) | Diseño minimizado | OP-12, OP-14 |
| R9 | Cruce impreciso con la lista DGC | Duda → bloquear email | T074 |
| R10 | Detección de formularios/llamadas a freelancers por patrones; uso comercial del formulario | Detección conservadora; envío manual con aviso | «% de leads A/B con canal permitido»; OP-15 |
| R11 | Supabase **Free (confirmado)**: pausa tras 7 días, 500 MB compartidos con todo argenis-hub (tamaño actual desconocido), sin backups | Poda + backup semanal obligatorio; pasar a Pro solo si se acerca al límite | OP-19 (tamaño actual), T070, T077 |
| R12 | Latencia de la base en la nube en bandeja y colas | ≤ 5 consultas por página; volumen bajo | T060; Redis local para colas si molesta |
| R13 | El descubrimiento trae falsos positivos (freelancers, SEO, grandes consultoras) | Denylist + clasificación + tiers | Revisión manual de los primeros 50 leads |
| R14 | Firecrawl marca `proxy` como *deprecated*: podría dejar de respetar `basic` | Test que comprueba el parámetro; plan B = desactivar Firecrawl en LeadScout | T039; revisión trimestral de la documentación |
| R15 | **Colchón de 2 meses**: la regla de 150 contactos termina justo cuando se acaba el colchón, y el escenario base pone el primer cliente en el mes 3 | Tres vías en paralelo; los contactos manuales cuentan desde el día 1; la regla no se altera | Punto de control de caja ~21-10-2026 (OP-25) |
| R16 | No hay jurisprudencia localizada sobre si un CAPTCHA/anti-bot es «medida de seguridad» (CP 197 bis, Lei 109/2009) | Se adopta la lectura conservadora: nunca se sortean | OP-15 · NORMATIVA §10.4 |

## 6. Restricciones que la implementación debe recordar

- Si la bandeja añade **selección de filas**, la regla del router obliga a BulkDelete **y**
  BulkRestore (T064).
- Toda firma de `laravel/ai`, Spatie Data o Inertia v3 se confirma con **Context7** (T001).
- `composer.json`, `phpunit.xml` y `tests/Pest.php` solo se tocan con confirmación (T003, T008);
  sin dependencias nuevas (D1).
- **Nunca** reutilizar el `FirecrawlScrapeAdapter` compartido tal cual en LeadScout (G26).
- **Nunca** ejecutar tests ni `migrate:fresh` contra Supabase; `migrate` en la nube solo tras T081
  y con confirmación.
- El `raw_text` de `cvs` no sale de `EloquentCvSource` salvo hacia el parser del perfil.
- Tareas **diferidas hasta el primer ingreso** (A12): T021, T023, T058, T068. No bloquean el MVP.
- **Sin FastAPI, Crawl4AI ni proxies residenciales** (A13); reconsiderar solo con los umbrales
  medidos del plan §3.5, y nunca proxies residenciales.
- No se implementa nada hasta que el operador lo pida explícitamente («sin codificar aún»).

## 7. Equivalencia de numeración (antes de la 6ª pasada → ahora)

| Antes | Ahora | Antes | Ahora | Antes | Ahora |
|---|---|---|---|---|---|
| T001, T002 | T001, T002 | T031-T041 | T031-T041 | T055 | T063 |
| — (nueva) | **T003** tests sin riesgo para Supabase | — (nueva) | **T042** SearchPort | T056 | T029 (alta manual + supresión) |
| T003-T007 | T004-T008 | T042 | T043 | T057 | T064 |
| T008 | T009 | — (nueva) | **T044** descubrimiento | T058-T062 | T065-T069 |
| T009 | T010 | T043 | T045 | T063 | T070 |
| T010 + T011 | T011 | T071-T074 | T046-T049 | T075-T078 | T071-T074 |
| T012-T019 | T012-T019 (T017/T018 rehechas: CV desde `cvs`) | T079-T081 | T050-T052 | T064, T065 | T075, T076 |
| T020 + T021 | T020 | T044 | T053 | — (nueva) | **T077** backup |
| T022-T025 | T021-T024 | T045-T050 | T054-T059 | T066 | T078 |
| T026-T029 | T025-T028 | T051-T053 | T060-T062 | T067 | T079 |
| T030 | T030 | T054 (ajustes de IA) | integrada en T054 (`GET/PUT ai-settings`) | T068 + T069 | T080 |
| | | | | T070 | T081 |
