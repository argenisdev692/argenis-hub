# Análisis previo — LeadScout (CV + 28 documentos + mercado a 16-09-2026)

> Fecha: 2026-09-16
> Entradas: `GUIDE/LeadScout-MODULE/Argenis_Gonzalez_CV_2026.md` (fuente principal),
> `Prompt maestro — Lead Scout White-Label Agency Pipeline.md`,
> `Sección adicional — Pricing, MCPs, proxies y costes.md`, los 25 archivos de `PIPELINE/`,
> el código de `src/` y la investigación en vivo de `research.md`.
> Sin código. Este documento alimenta `spec.md` → `clarify.md` → `plan.md`.

---

## 0. Resumen ejecutivo

1. **Tu método puede funcionar, pero el scraping no va a ser lo que lo haga funcionar.** Los 28
   documentos coinciden y los datos de hoy lo confirman: el cuello de botella es comercial
   (señal + mensaje + constancia + landing), no técnico.
2. **Hoy se publican muy pocas ofertas Laravel en la península ibérica.** ITJobs.pt: **4**
   resultados. Tecnoempleo: **10**, de los que 3 tienen fecha de 2023-2024. LaraJobs: ~10
   recientes, casi todas de EE. UU. y a tiempo completo, con **1** contrato de contractor.
   Un «scraper de empleos Laravel PT/ES» te daría ~5-15 ofertas nuevas por semana, y con eso
   no llenas un funnel de 100 contactos al mes.
   → Usa cada oferta como **señal de compra sobre la empresa que la publica**, amplía a
   PHP/Vue/full-stack PHP y suma agencias que no tienen vacante abierta.
3. **Hay una restricción legal que ninguno de los 28 documentos menciona.** En España, la LSSI
   (art. 21) exige consentimiento previo para enviar email comercial, **incluido el B2B** y a
   personas jurídicas. En Portugal (DL 7/2004, art. 22) las personas colectivas están en
   régimen de **opt-out**: se puede escribir si se ofrece la baja. Por eso la plantilla de
   `EMAIL-OUTREACH-AGENCIAS.md` (email en frío a agencias españolas) es un riesgo, y
   «responder a una oferta publicada» sale reforzado como canal. *(No es asesoría legal:
   verificar.)*
   > **Actualización (16-09):** la revisión normativa matiza Portugal. La Lei 41/2004 + la CNPD
   > Diretriz/2022/1 exigen consentimiento para personas singulares sin relación previa, y el
   > email **nominativo** de un decisor es zona gris. Además aplica el **art. 14 del RGPD**
   > (informar en el primer contacto). Ver `NORMATIVA-RGPD.md`.
4. **Stack.** Laravel AI SDK **sí**, pero como extractor y clasificador con salida
   estructurada, no como orquestador agéntico. Tavily y Firecrawl **sí**, con presupuesto
   (ya tienes los adaptadores en `src/Shared/Infrastructure/Research/`). Crawl4AI **no** en el
   MVP. Proxies residenciales **no**.
5. **ROI.** En dinero el coste es trivial (**€0-40/mes**); el coste real es tu tiempo. Un solo
   cliente recurrente de 40 h/mes a €35/h (€1.400 brutos) paga ~35 veces el mes de
   herramientas. La pregunta útil no es «¿el ROI es positivo?», sino **«¿llego a 1-2 clientes
   recurrentes antes de que se acabe mi colchón?»**. Ver §4.

> Dato operativo: al investigar hoy, **Tavily devolvió `432 — exceeds your plan's set usage
> limit`**. La cuota de tu plan está agotada. Un diseño que dependa de free tiers se rompe en
> producción el mismo mes (ver R1 en `research.md`).

---

## 1. Tu perfil real según el CV

### 1.1 Confirmado (aparece en el CV)

| Área | Evidencia en el CV |
|---|---|
| Backend núcleo | PHP 8.x · Laravel 10-13 · Eloquent · REST APIs · Sanctum · Jetstream · Fortify · Horizon · Reverb · Spatie Permission / Activity Log · OpenAPI |
| Frontend | Vue 3 (Composition API, script setup) · Inertia.js v3 · Livewire 3 · TypeScript · Pinia · React 19 · Astro 5 · Tailwind v4 · Zod |
| Datos | PostgreSQL 17 · MySQL 8 · Redis · prevención de N+1 |
| DevOps | Docker Compose / Sail · GitHub Actions · Cloudflare Workers / R2 · Railway · VPS · Pint · PHPUnit · OWASP |
| Integraciones / IA | Google Calendar / OAuth / Meet · Resend · SumUp · DocuSign · Supabase · laravel/ai · Python/FastAPI (microservicio faster-whisper) |
| Seniority | **4+ años** (2022-2026): 2 años manteniendo un CRM legacy en PHP vanilla, y proyectos Laravel en producción entre 2024 y 2026 |
| Idiomas | Español nativo · portugués de nivel residente · **inglés B1** |
| Prueba comercial | Servispin (~25 clientes/mes y ~42 clientes nuevos en 2 meses con Meta Ads) · AquaShield (Lighthouse 98, −97 % spam) · Vidula (24 módulos, 96 archivos de test) · formador de IA para Imagina (España) |

### 1.2 Potencial / requiere verificación (NO usar como capacidad confirmada)

| Tecnología o claim | Dónde aparece | Problema |
|---|---|---|
| **AWS / «despliegue en AWS»** | `EMAIL-OUTREACH-AGENCIAS.md` §3 y `ANALISIS-ARGENIS-DEV.md` | **No está en el CV** (el CV dice Cloudflare, Railway, VPS). Si una agencia lo pregunta en la llamada, se cae la credibilidad. Quitarlo o verificarlo |
| Forge / Vapor | `PIPELINE-FREELANCER-GEMINI-2.md`, CHATGPT pitch | No está en el CV |
| Pest | Este repo usa Pest 5 | El CV dice PHPUnit. Si lo usas, añádelo |
| Filament, Symfony, WordPress, NestJS, Octane, Next.js | Varios docs | No están en el CV |
| «12+ projects shipped» | Web argenis.dev | El CV dice «~8 client landings and CRMs». **Inconsistencia**: unifica la cifra |
| **3 años facturando con recibos verdes** | Tu mensaje de hoy | **No está en el CV y es un activo comercial clave** (quita a la empresa la fricción de contratar a alguien de otro país, sin EOR). Añadirlo |

### 1.3 Objeciones previsibles del comprador (y qué dice el CV)

- **Trabajo dentro de un equipo ajeno:** todas tus experiencias son en solitario o con el
  cliente directo. Una agencia que compra *staff augmentation* preguntará por PRs, code
  review, Jira o Linear. → Conviene tener evidencia (GitHub público con flujo de PR) o
  mencionar la colaboración con Restoration Control durante 2 años.
- **Inglés B1 hablado:** neutralizable en canales async y en PT/ES. Es una penalización
  que va en el score, no un filtro excluyente.

### 1.4 Qué tipo de trabajo puedes asumir con evidencia

Mantenimiento y evolución de apps Laravel existentes · CRMs y paneles internos · reservas,
calendario y pagos · APIs e integraciones de terceros · colas y tiempo real · hardening OWASP
y tests · features de IA (laravel/ai) · landings de alto rendimiento. **Lo que no:**
«lead técnico cara al cliente en inglés».

---

## 2. Qué dicen los 28 documentos

### 2.1 Consenso (mantener)

| Punto | Docs que lo sostienen |
|---|---|
| Canal nº 1 = agencias de software de 5-50 personas que compran capacidad | CLAUDE, GEMINI, GROK, CHATGPT-MAIN/-3/-8, ANALISIS-WEB, DECISION |
| Vender capacidad y bloques/retainers, no horas sueltas | CLAUDE, ANALISIS-WEB (Tighten), EMAIL-OUTREACH |
| Señal (vacante, crecimiento, stack confirmado) > volumen | ICP-AGENCIAS, CHATGPT-8 («capacity pressure»), ANALISIS-WEB §7 |
| No migrar de stack desde el desempleo | DECISION, CLAUDE, MUSE-PARK, CHATGPT-8 |
| Publicar `/agencies` antes del pipeline (si no, mides la landing y no el canal) | ANALISIS-WEB, DECISION |
| Score explicable por dimensiones + confianza separada | Prompt maestro, Sección adicional, CHATGPT-8 |
| Regla de decisión fijada **antes** de empezar | DECISION §5.3, CHATGPT-MAIN KPIs |
| Fase 0 manual → un solo comando → dashboard solo si hay facturación | KIMI-K2 (el más alineado con «no overengineering») |

### 2.2 Contradicciones y lo que descarto

| Tema | Conflicto | Decisión propuesta |
|---|---|---|
| Arquitectura | GEMINI: todo Laravel · CHATGPT-7 / A-B: Laravel + FastAPI/Crawl4AI · GROK: FastAPI | Todo Laravel en el MVP (§5). FastAPI + Crawl4AI solo si un volumen medido lo justifica |
| Proxies | `PROXIES.md` recomienda residenciales para LinkedIn/Indeed/Glassdoor/InfoJobs | **Descartado**: viola ToS y las restricciones 14-15 de tu propio prompt maestro (no evadir anti-bot ni controles de acceso) |
| Alcance | `PIPELINE-A-B-COMPARATION.md` lo convierte en un SaaS multi-tenant (10k-500k agencias, 5M jobs, «CV Intelligence») | **Fuera de alcance.** Es un producto distinto; alguien desempleado necesita caja, no un SaaS |
| Cifras | «B1 elimina 60-70 %», «50-60 % fracaso», «600 vs 1.868 NestJS» | Son hipótesis de modelos, no datos (CHATGPT-4 y -8 ya lo señalan). No se usan en el ROI |
| Tasas de respuesta | 4-8 % (GEMINI) · 5-10 % (CLAUDE) · 5-20 % (MAIN) | Sustituidas por los benchmarks de 2026 (§3) |
| Email a agencias españolas | `EMAIL-OUTREACH-AGENCIAS.md` | Choca con la LSSI art. 21 (§0.3). Ver `clarify.md` Q4 |
| ICP «agencias que no hacen Laravel» | CHATGPT-8 §11 lo propone | Válido como Tier C / fase 2; no en el test inicial (diluye la señal) |

### 2.3 Hallazgo sobre el código

**No existe ningún módulo LeadScout en `src/Modules/`.** El prompt maestro pide «analizar el
código existente del Lead Scout Module», pero ese código no existe. Lo que sí existe y se
reutiliza:

- `src/Shared/Infrastructure/Research/TavilyResearchAdapter.php` (máx. 4 queries, timeout de
  15 s, `time_range`, circuit breaker).
- `src/Shared/Infrastructure/Research/FirecrawlScrapeAdapter.php` (`scrape(url): ?string`,
  circuit breaker, `max_age` configurable; `base_url` apunta a `/v1`).
- `src/Shared/Infrastructure/AI/AIClientInterface.php` (`generateStructured`) + `UsesPromptCache`.
- `laravel/ai ^0.11.0`, que ya usan Post, Campaigns, SocialMedia y VideoEdits (agentes con
  salida estructurada).
- `src/Shared/Infrastructure/Resilience/CircuitBreaker/`.

---

## 3. Realidad del mercado hoy (16-09-2026)

| Fuente | Resultado «laravel» | Lectura |
|---|---|---|
| ITJobs.pt | **4** (Porto híbrido, Braga híbrido junior, **1 remoto**, 1 prácticas presencial) | Portugal casi no publica Laravel con ese nombre |
| Tecnoempleo (ES) | **10**, de las que ~7 son de sept. 2026; salarios de €30-42k (empleado); **«PHP Freelance Developer (remoto Spain)» vía Michael Page** | Hay demanda de freelance, pero la canalizan las reclutadoras |
| LaraJobs (global) | ~10 recientes, casi todas EE. UU./full-time; **1 contractor**; CRAE Group Chipre remoto €100k | El remoto global exige inglés alto y compite a nivel global |
| Tarifas contractor PT (Lemon.io 2026) | Mid **$29-35/h** · Senior **$40-45/h** | Coincide con el rango de €30-45/h de los docs |
| Cold email B2B 2026 | Reply medio **3,43 %** (5,1 % en 2024) · positivo 1,5-3 % «fuerte» · 4-5 % top · **10-18 %** con listas de ~100 prospectos bien investigados | Planificar con **positivo 2-5 %**, no con 5-20 % |

Fuentes y método en `research.md` R5-R7. Los conteos son una foto de un día y **no** una
serie; el MVP debe medir el volumen real semana a semana (FR-18).

**Lectura:** buscar solo «Laravel» en PT/ES produce pocas señales. El sistema tiene que:
(a) ampliar términos (PHP, Symfony + Vue, «full stack PHP», «programador PHP»);
(b) mirar **quién publica** (una agencia con vacante vale más que la vacante en sí);
(c) sumar agencias sin vacante; (d) incluir remoto UE a través de APIs públicas.

---

## 4. ¿Será efectivo tu método? — Modelo de ROI

> Todos los números son **supuestos explícitos**. El test de 8 semanas existe para sustituirlos
> por tus datos reales.

### 4.1 Coste monetario mensual (fuentes: `research.md` R1-R4)

Supuesto por empresa analizada: ~3 créditos de Tavily (discovery), ~4 páginas (60 % por HTTP
directo gratis, 40 % por Firecrawl a 1 crédito/página en markdown) y ~12k tokens de entrada +
1,5k de salida en Haiku 4.5.

| Escenario (empresas/mes) | Búsqueda | Extracción | LLM | Proxy | Infra | **Total aprox.** | €/lead cualificado* |
|---|---|---|---|---|---|---|---|
| A — 100 | 300 cr → free | 160 cr → free | ~$2 | $0 | $0 (Herd local) | **$0-5** | ~$0,1-0,2 |
| B — 500 | 1.500 cr → ~$4 PAYG | 800 cr → free | ~$10 | $0 | $0-10 | **$15-25** | ~$0,1-0,2 |
| C — 1.000 | 3.000 cr → ~$16 PAYG | 1.600 cr → Hobby $16 | ~$20 | $0 | $0-10 | **$50-60** | ~$0,2-0,3 |
| D — 5.000 | 15.000 cr → plan ~$75-110 | 8.000 cr → Standard $83 | ~$100 (~$50 batch) | $0 | $10-20 | **$220-310** | ~$0,2-0,3 |

\* Suponiendo que el 20-30 % de las empresas descubiertas pasa la cualificación. Coste por
lead de alta prioridad (~5-10 %): ~$0,5-1.

**Conclusión:** en los escenarios A-C el dinero es irrelevante. El único que pide optimizar
(Crawl4AI self-hosted, batch) es el D, y no lo necesitas para validar.

### 4.2 Coste de tiempo (el coste real) `[estimación, sin fuente]`

| Concepto | Horas |
|---|---|
| Construir el MVP reutilizando los adaptadores existentes | 40-60 h (una sola vez) |
| Operación: revisar, verificar la señal y personalizar la primera frase (10-15 contactos/día × ~8 min) | 30-40 h/mes |
| Coste de oportunidad a €30/h | Construir ≈ €1.200-1.800 · operar ≈ €900-1.200/mes |

### 4.3 Funnel por cada 100 contactos cualificados

| Etapa | Pesimista | Base | Optimista | Base del supuesto |
|---|---|---|---|---|
| Respuesta positiva | 2 | 5 | 10 | Benchmarks de 2026 (R7): positivo 1,5-5 %; top 10-18 % con ~100 prospectos investigados |
| Llamada | 1 | 3 | 6 | 50-60 % de los positivos |
| Proyecto de prueba | 0,4 | 1,2 | 2,4 | 40 % de las llamadas `[hipótesis]` |
| Cliente recurrente | 0,2 | 0,6 | 1,2 | 50 % de las pruebas `[hipótesis]` |

→ **Base: hacen falta ~150-200 contactos cualificados para 1 cliente recurrente.**
Coincide con CHATGPT-MAIN («200-300 contactos») y con DECISION («con 30 contactos un cero no
significa nada»).

**Canal de ofertas publicadas** (la empresa ya pidió candidatos): se espera una respuesta
mayor, pero con poco volumen (~20-60 señales/mes en PT+ES). `[HIPÓTESIS sin fuente: la tasa
de respuesta de este canal es la primera métrica que medirá el MVP]`.

### 4.4 Ingresos y neto con recibos verdes

| Escenario | Clientes × horas × tarifa | Bruto/mes | Neto aprox.** |
|---|---|---|---|
| Malo | 1 × 20 h × €30 | €600 | €390-450 |
| Razonable | 2 × 40 h × €35 | €2.800 | €1.800-2.100 |
| Bueno | 3 × 50 h × €40 | €6.000 | €3.900-4.500 |

\*\* Seguridad Social 21,4 % sobre el 70 % del rendimiento (≈ 15 % del bruto) + IRS efectivo
estimado del 10-20 % (régimen simplificado). **Retención verificada: 23 %** (Lei 45-A/2024, se
mantiene en 2026), solo si el pagador tiene contabilidad organizada en PT; es un adelanto, no el
impuesto final (research R11). **Confirmar con tu contabilista**: el coeficiente y el IVA
intracomunitario siguen sin verificar.

### 4.5 Cálculo de ROI a 6 meses

Inversión: herramientas ~€35 × 6 = **€210** + tiempo: 50 h de construcción + 35 h × 6 meses de
operación = **260 h**.

| Escenario | Ingresos brutos en 6 meses | €/h sobre las 260 h | ROI en dinero | Lectura |
|---|---|---|---|---|
| Pesimista | 1 proyecto pequeño de €800 | ~€3/h | ~4× | **Parar en la semana 8** y cambiar segmento o mensaje, no el stack |
| Base | 1 recurrente de 40 h × €35 desde el mes 3 (4 × €1.400) + 1 prueba de €1.000 = **€6.600** | ~€25/h | ~30× | Funciona; los meses siguientes el coste de adquisición cae |
| Optimista | 2 recurrentes desde el mes 3 (4 × €2.800) = **€11.200** | ~€43/h | ~50× | Escalar el volumen |

**Break-even de tiempo:** para que las 260 h rindan al menos como €30/h facturados necesitas
~€7.800 en 6 meses ≈ **1 cliente recurrente de 40 h desde el mes 2, o 2 clientes desde el
mes 4**.

**Las variables que mueven el resultado, por orden:** (1) tasa de respuesta positiva →
(2) semanas hasta el primer «sí» (el ciclo con agencias es de 2-4 meses de silencio, GROK) →
(3) **utilización** (50 h/mes a €40 = €2.000 = €12,5/h sobre 160 h disponibles, CHATGPT-4) →
(4) tarifa.

### 4.6 Veredicto

**Es efectivo SI** se cumplen estas 5 condiciones. Si falla alguna, el experimento mide ruido:

1. `/agencies` (o `/es/agencias`) publicada antes del primer contacto.
2. Cada contacto con una **señal verificable** (vacante, stack confirmado, crecimiento).
3. **≥150-200 contactos cualificados en 8 semanas** (10-15 al día).
4. Regla de decisión escrita antes de empezar (FR-19).
5. Funnel de empleo en paralelo (consultoras PT/ES, ofertas freelance) mientras llega el
   primer «sí».

**No es efectivo si** el plan consiste en construir durante 6 semanas un sistema grande antes
de contactar a nadie. Con tu situación, el MVP tiene que estar operativo en **≤2 semanas**, y
en la semana 1 ya deberías enviar contactos preparados a mano (Fase 0 de KIMI-K2).

---

## 5. Veredicto sobre la arquitectura propuesta

| Componente | MVP | Por qué | Reconsiderar cuando |
|---|---|---|---|
| **Laravel AI SDK** (`laravel/ai ^0.11` ya instalado) | ✅ Extractor/clasificador | Salida estructurada con esquema, failover entre proveedores, ejecución en cola, *fakes* para tests (R3). Ya lo usan 4 módulos | Es 0.x: fijar la versión menor |
| Laravel AI SDK como **orquestador multi-agente** (Planner/Researcher/Validator) | ❌ | El flujo es determinista y conocido; los agentes solo añaden coste y no-determinismo. Tu prompt maestro: «más agentes ≠ mejor» | Nunca en el MVP |
| **Orquestación** | ✅ Jobs/colas de Laravel + comando programado | Reintentos, backoff y rate limiting deterministas | — |
| **APIs/RSS oficiales + HTTP directo** | ✅ **Primero** | Coste 0, estable y legal (R5) | — |
| **Tavily** | ✅ Discovery (agencias, careers pages) | 1 crédito/búsqueda basic, adaptador existente. **Cuota agotada hoy** → exige presupuesto y degradación | Si el discovery supera 3.000 créditos/mes → plan mensual |
| **Firecrawl** | ✅ Extractor de páginas no triviales | 1 crédito/página en markdown; **evitar el formato JSON (+4 créditos/página)** y usar tu LLM (R2) | Si supera €80/mes o 20k páginas/mes |
| **Crawl4AI + FastAPI** | ❌ | Añade Python + Docker + VPS a un entorno Herd **sin Docker** (regla del proyecto). La 0.9.0 (jun-2026) trajo breaking changes (auth obligatoria) y la 0.9.3 (ago-2026) 5 fixes de seguridad: carga de mantenimiento (R4) | >5.000 páginas/mes medidas o Firecrawl caro |
| **Proxies** (residenciales o de datacenter) | ❌ | No hacen falta para APIs ni para webs públicas de agencias; usarlos contra LinkedIn/Indeed = evasión y ToS | Nunca para eludir bloqueos |
| **MCP en el backend** | ❌ | Solo para desarrollo; en producción, adaptadores directos | — |
| **Embeddings / pgvector** | ❌ | El matching determinista con la taxonomía del CV es suficiente y auditable | >1.000 ofertas/mes o sinónimos sin resolver |
| **Scraping de LinkedIn / Indeed / InfoJobs web** | ❌ | ToS + anti-bot. InfoJobs/ITJobs/Landing.jobs tienen API: usarla | — |

**Resumen:** tu arquitectura «Tavily → Firecrawl → Crawl4AI → LLM → score → DB» es correcta
en dirección, pero está sobredimensionada para validar. La versión para septiembre de 2026 es:

```
Fuentes con API/RSS (0 €) ─┐
Tavily (discovery, budget) ├─► Normalizar + deduplicar ─► HTTP directo ─► Firecrawl (si hace falta)
                           ┘            │
                                        ▼
                   LLM (laravel/ai, salida estructurada) → hechos + inferencias con evidencia
                                        ▼
                   Score determinista por dimensiones + confianza + tier por reglas
                                        ▼
                   Bandeja de revisión → borrador (tú envías) → funnel y métricas
```
