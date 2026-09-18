# SSD-SUMMARY — 003-lead-scout · LeadScout

> Fase 8 · CONSOLIDATE — Documento autónomo con todo el recorrido SDD del módulo.
> Fecha: 2026-09-16 (6ª pasada) · actualizado 2026-09-17 (7ª pasada, A16) · Operador: Argenis Carrillo Gonzalez (Covilhã, Portugal)
>
> **Versión PRE-IMPLEMENTACIÓN.** Por petición del operador («sin codificar aún») se consolida
> antes de la Fase 7. **Las 85 tareas técnicas (4 diferidas; T082-T083 en A16 y T084-T085 en A18) y 26 de las 27 del operador están
> pendientes** (OP-20 resuelta: colchón de 2 meses). Este documento debe regenerarse al terminar la
> implementación (T081).

| Fase | Estado | Archivo |
|---|---|---|
| 0 · Análisis previo (CV + 28 docs + mercado + ROI) | ✅ | [`ANALISIS-LEADSCOUT.md`](ANALISIS-LEADSCOUT.md) |
| 1 · Specify | ✅ 12 historias, 44 FR (FR-38 a FR-44 en la 7ª pasada) | [`spec.md`](spec.md) |
| 2 · Clarify | ✅ Q1-Q25 + D1-D5 + **A1-A19** (colchón 2 meses, Supabase Free, CV confirmado, agencia buena, 22 tablas, datos públicos de empresa, países por ola, envío humano, reglas por país pendientes de verificación y buzón por fases) | [`clarify.md`](clarify.md) |
| 3 · Research | ✅ R1-R11 + **R12 Supabase (Context7) · R13 legal (Tavily) · R14 código · R15 verificación 17-09 (Tavily + Firecrawl)** | [`research.md`](research.md) |
| 3b · Normativa RGPD / ES / PT + legalidad del scraping | ✅ (validación legal pendiente: OP-15) | [`NORMATIVA-RGPD.md`](NORMATIVA-RGPD.md) |
| 4 · Plan | ✅ | [`plan.md`](plan.md) |
| 5 · Tasks | ✅ 85 técnicas + 27 del operador (OP-27 en A17) (renumeradas) | [`tasks.md`](tasks.md) |
| 6 · Analyze | ✅ 35 huecos encontrados y corregidos, 0 abiertos | [`analyze.md`](analyze.md) |
| 7 · Implement | ⏳ **No iniciada** | — |
| 8 · Consolidate | ✅ Este documento (pre-implementación) | `SSD-SUMMARY.md` |

---

## 0. Qué cambió en la 6ª pasada (en una lista)

1. **Se trajo lo mejor de MUSE-SPARK:** resumen ejecutivo, descubrimiento de agencias en el MVP,
   gates antes del primer contacto, «qué no hacer», lectura de muestra «no concluyente», marca
   `needs_research`, vacante activa como señal de urgencia, tamaño como criterio (luego endurecido por A14), one-pager
   de no captación. **No** se trajo su FastAPI/Crawl4AI/Railway ni sus proxies.
2. **Base de datos Supabase** (región UE): pooler en modo sesión, RLS en todas las tablas, tests
   nunca contra la nube, poda de contenido y backup si el plan es Free.
3. **Tablas:** se fusionaron 22 → 14 y, a petición del operador, se **restauraron las 22** (A15).
4. **Alcance:** privacidad y lista DGC por comando; ajustes de IA editables desde la web + selector por borrador (A15).
5. **Perfil desde la tabla `cvs`** (CV principal en markdown ATS), sin copiar su texto; **sin
   RAG** (un documento corto no lo necesita y el perfil debe ser determinista).
6. **Legalidad verificada con Tavily:** solo URLs públicas y APIs/RSS; **sin LinkedIn, sin
   CAPTCHAs, sin anti-bots**. Se encontró y cerró un hueco: Firecrawl, por defecto, escalaba a
   proxies para anti-bots.
7. **Respuestas del operador:** colchón de **2 meses** (A12: tres vías de ingresos en paralelo,
   sistema operable en la semana 2, 4 tareas diferidas, punto de control de caja ~21-10-2026) ·
   Supabase **Free** (backup obligatorio) · CV **`Argenis_Gonzalez_CV_2026.md`** · las **22 tablas
   eran solo del módulo** (argenis-hub ya tiene 51).
8. **Sin FastAPI, Crawl4AI ni proxies residenciales** (A13): no mejoran la extracción de páginas
   públicas y los proxies sirven para esquivar bloqueos. La efectividad se gana en la búsqueda
   (Tavily `advanced`) y se mide (plan §3.5).
9. **Agencia buena (A14):** solo pasan agencias **vivas** (actividad ≤ 12 meses para Tier A), con
   **equipo real** (freelancers de una persona descartados; 2-4 personas con techo en B), con
   **trabajo recurrente** (Recurrente sube a peso 20) y compatibles con **B1**. El descubrimiento
   cubre **PT/ES, resto de la UE + UK/IE y resto del mundo** en el MVP (~60/30/10 %).

---

## 1. Contexto y veredicto de negocio

**Situación:** desarrollador full-stack Laravel/PHP con 4+ años, desempleado, factura como
independiente con recibos verdes desde hace 3 años, español nativo, portugués de residente,
inglés B1. Objetivo: trabajo recurrente como **contractor en marca blanca** para agencias,
empezando por Portugal y España.

**Perfil confirmado en el CV:** PHP 8.x, Laravel 10-13, Vue 3, Inertia v3, Livewire 3,
TypeScript, REST, PostgreSQL 17, MySQL 8, Redis, Docker, GitHub Actions, Horizon, Reverb,
Sanctum, Spatie, OpenAPI, laravel/ai, Python/FastAPI. Pruebas: Servispin (~25 clientes/mes),
AquaShield (Lighthouse 98), Vidula (24 módulos, 96 archivos de test), formador de IA para Imagina.
**No confirmado** (no se usa en mensajes): AWS, Forge/Vapor, Pest, Filament, Symfony, NestJS,
«12+ projects». **Falta en el CV:** «3 años facturando con recibos verdes».

**Realidad del mercado (16-09-2026):** ITJobs.pt 4 ofertas «laravel»; Tecnoempleo ~7 vigentes;
LaraJobs ~10, casi todas de EE. UU. → las ofertas solas no llenan el funnel: por eso el MVP suma
**descubrimiento de agencias** e **importación de la lista ICP**.

**ROI (supuestos explícitos, `ANALISIS-LEADSCOUT.md` §4):**

| | Pesimista | Base | Optimista |
|---|---|---|---|
| Respuestas positivas por cada 100 contactos | 2 | 5 | 10 |
| Contactos para 1 cliente recurrente | ~500 | **~150-200** | ~80 |
| Ingresos en 6 meses | €800 | €6.600 | €11.200 |
| €/h sobre las ~260 h (construir + prospectar) | ~€3/h → parar | ~€25/h | ~€43/h |

- Herramientas **€0-30/mes**; el coste real es el tiempo. **Basta un «sí» recurrente.**
- El riesgo no es el dinero: es **quemar la lista corta** con mensajes genéricos o ilegales.
- **Veredicto:** efectivo **si** `/agencies` está publicada, cada contacto lleva una señal
  verificable, se hacen ≥ 150 contactos en 8 semanas con la regla fijada antes y se mantiene el
  funnel de empleo en paralelo. Un 0 de 30 **no concluye nada**.

---

## 2. Specify — qué se construye y por qué

**Resumen:** sistema de **uso personal** que descubre ofertas Laravel/PHP y agencias en PT/ES
(después UE y remoto). La oferta es una señal de compra que sube la prioridad; las agencias sin
vacante entran por descubrimiento e importación. Califica cada empresa contra el perfil derivado
del **CV guardado en la plataforma**, con **score explicable, evidencia y confianza**; identifica
**decisores** (no reclutadores) y **canales de contacto**; prepara borradores que **el operador
envía a mano**; mide el funnel. **Solo fuentes públicas u oficiales, sin evasión.**

### 2.1 Historias de usuario

| ID | Historia | Prioridad | Alcance |
|---|---|---|---|
| US-1 | Perfil desde **el CV guardado** (confirmadas vs potenciales, pruebas citables, aviso si el CV cambia) | Alta | MVP |
| US-2 | Ingesta de ofertas desde un registro de fuentes | Alta | MVP |
| US-3 | Resolución y clasificación de la empresa | Alta | MVP |
| US-4 | Score por dimensiones + confianza + tier por reglas + `needs_research` | Alta | MVP |
| US-5 | Bandeja + borrador con canal legal por país, sin envío automático | Alta | MVP |
| US-6 | Funnel, métricas con **lectura de muestra** y regla de decisión bloqueable | Alta | MVP |
| US-7 | **Descubrimiento de agencias PT/ES** + importación de la lista ICP | Alta | **MVP** (antes Fase 2) |
| US-8 | Presupuesto mensual y degradación controlada | Media | MVP |
| US-9 | **Tres olas geográficas** (PT/ES, resto de la UE + UK/IE, resto del mundo) y encaje con **inglés B1** | Alta | **MVP** |
| US-10 | Ajustes de IA por propósito **editables desde la web** + selector por borrador | Media | MVP |
| US-11 | Decisores (fundador, CEO, CTO…), nunca reclutadores ni trabajadores | Alta | MVP |
| US-12 | Canales de contacto de la empresa y recomendación legal × comercial | Alta | MVP |

### 2.2 Requisitos funcionales (resumen)

| Grupo | FR | Esencia |
|---|---|---|
| Perfil y fuentes | FR-1 a FR-5 | Perfil versionado **desde `cvs`**, determinista, con pruebas; registro de fuentes con términos revisados; APIs/RSS primero; deduplicación; términos ampliados |
| Señales y score | FR-6 a FR-10 | Señales con evidencia (hecho/inferencia); score determinista y versionado; tiers por reglas; IA solo donde las reglas no llegan |
| Obtención y coste | FR-11 a FR-14 | Escalera de coste; caché; **FR-13: sin evasión — bloqueo = parada, User-Agent honesto, sin LinkedIn ni cuentas**; presupuestos |
| Contacto y funnel | FR-15 a FR-21 | Borradores por variante; sin envío automático; supresión (incl. lista DGC); métricas; regla inmutable; alta manual; exportación |
| IA | FR-22 | Defaults por propósito en configuración + selector en el borrador + registro del modelo usado |
| Decisores | FR-23 a FR-25 | Sin IA; solo emails publicados; oposición; nunca nombres a la IA ni acceso a redes profesionales |
| RGPD | FR-26 a FR-30 | Art. 14 + baja con email válido; solo A/B; 30 d / 12 m; re-verificación a 90 d; derechos **por herramienta de administración**; sin categorías especiales |
| Canales | FR-31, FR-32 | Detección sin IA; nunca rellenar formularios, CAPTCHAs ni teléfonos |
| **Nuevos** | FR-33 · FR-34 · FR-35 | Lectura de muestra «no concluyente» · datos en la UE, sin exposición por APIs automáticas, backups, tests fuera de la base real · texto del CV nunca a la IA, logs ni exports |
| **Calidad de agencia (A14)** | FR-36 · FR-37 | Vitalidad y tamaño deterministas con descarte motivado (freelancer individual, agencia parada, web muerta/absorbida) y Tier A solo con actividad ≤ 12 meses y equipo real · tres olas geográficas, comunicación B1 por forma de trabajo, solape horario y sin email en frío en países sin regla |

### 2.3 No funcionales
Coste ≤ €30/mes · 1.000 empresas/mes sin intervención · bandeja < 2 s con 5.000 leads **con la
base en la nube** · capacidad sin acercarse al límite del plan · ≤ 5 min por lead Tier A ·
secretos fuera de logs · entrada externa no confiable · tolerancia a cuotas · scores
reproducibles · RGPD + LSSI + Lei 41/2004 + términos de fuentes + **sin sortear protecciones**.

### 2.4 Fuera de alcance
SaaS/multi-tenant · envío automático · LinkedIn y redes profesionales · **navegadores
automatizados, rotación de IPs, proxies y resolución de CAPTCHAs** · rastreo de directorios
(Sortlist, Clutch, partners.laravel.com: consulta manual + importación) · adivinación de emails ·
datos de no decisores · **RAG/embeddings del CV** · Crawl4AI/FastAPI · pantallas de ajustes de IA,
privacidad y DGC · la landing `/agencies` (prerrequisito operativo).

### 2.5 Criterios de éxito
Semana 2: ≥ 30 leads A/B con evidencia (ofertas + descubrimiento + importación) y primeros
contactos · 8 semanas: ≥ 150 contactos cualificados con tasas por etapa, canal y **origen** ·
coste por lead cualificado ≤ €0,50 · 100 % de Tier A con señal comercial verificada · 0 personas
con cargos excluidos · **0 peticiones a redes profesionales y 0 reintentos tras un bloqueo** ·
regla de decisión con resultado inequívoco · objetivo de negocio: 1 cliente recurrente en ≤ 12
semanas.

---

## 3. Clarify — decisiones tomadas

| # | Pregunta | Resolución |
|---|---|---|
| Q1 → **A1** | Canal del MVP | Ofertas como señal **+ descubrimiento de agencias PT/ES + importación ICP** |
| Q2 → **Q24/A12** | Colchón | **2 meses** (hasta ~16-11-2026): tres vías en paralelo (agencias, empleo/freelance directo, nearshore PT), operable en la semana 2, bandeja en la semana 3, contactos manuales importados, punto de control de caja en la semana 4 de operación |
| Q3 | Presupuesto | ≤ €30/mes (búsqueda €8 · extracción €15 · IA €7) |
| Q4 | Contacto en España | Sin email en frío (LSSI): oferta, formulario o red profesional a mano |
| Q5 | ¿SaaS? | No, uso personal |
| Q6 | ¿Empleo fijo o contrato? | Ambos; candidatura clásica medida aparte |
| Q7-Q8 | Geografía e idioma | Fase 1 PT+ES; ES con «ustedes», PT en portugués, resto en inglés escrito |
| Q9 | Regla de decisión | 150 contactos / 8 semanas: ≤ 2 positivas = sin señal · 3-7 = tibia · ≥ 8 = funciona |
| Q10 | Tarifa | Suelo €30/h; objetivo €35-45/h; nunca en el primer mensaje |
| Q11/Q18 | Conservación | Solo decisores de A/B; 30 días sin contacto; 12 meses tras la última interacción |
| Q12 | Claims no verificados | AWS, Forge/Vapor, «12+» bloqueados hasta confirmarlos |
| Q13 | Interfaz | Bandeja web en el MVP |
| Q14 → **A2** | Selector de IA | Select en el editor de borrador; defaults en `.env` |
| Q15-Q17, Q20 | Decisores | Sí, sin IA, sin recruiters; LinkedIn solo si la web lo enlaza |
| Q16/Q16-bis | Emails de decisores | Solo publicados; ES no en frío; PT nominativo = zona gris |
| Q19 | Art. 14 | Aviso + baja en cada borrador + política en argenis.dev |
| Q21 | Canales | Sí; «Trabaja con nosotros» se extrae con prioridad baja |
| D1-D5 | Técnicas | Robots propio · Herd local · Gemini 3.7 Flash / Sonnet 5 · ITJobs en pausa · Tavily `basic` |
| **A3-A4** | Privacidad y DGC | Por comando, con registro sin PII |
| **A5** | Base de datos | **Supabase** en la UE, RLS, pooler en modo sesión, backup si es Free |
| **A6** | Tablas | 22 → **14** |
| **A7-A8** | MUSE-SPARK | Se traen gates, «qué no hacer», lectura de muestra, `needs_research`…; no su FastAPI/proxies/100-4 |
| **A9** | CV | Perfil desde la tabla **`cvs`** (markdown ATS principal), solo lectura, sin copiar el texto |
| **A10** | RAG | **No** en el MVP; recuperación determinista de la prueba más afín |
| **A11** | Obtención | **Sin evasión**: bloqueo = parada; Firecrawl `proxy: "basic"`; sin LinkedIn |
| **Q22** | ¿Legal subirlo a la web? | Sí para uso propio tras login (con seguridad del art. 32); no como SaaS con este diseño; código publicable sin secretos ni evasión (NORMATIVA §10) |
| **Q23** | ¿LinkedIn, CAPTCHAs, anti-bots? | **No.** Solo URLs públicas y APIs/RSS oficiales |
| Q25 | Plan de Supabase | **Free** → backup semanal obligatorio y poda (región, versión, Data API y tamaño actual: OP-19) |
| OP-22 | CV de origen | **`Argenis_Gonzalez_CV_2026.md`** (inglés, secciones ATS; fixture anonimizado) |
| **A13** | ¿FastAPI + Crawl4AI + proxies residenciales? | **No.** HTTP directo + Firecrawl `basic`; Tavily `advanced` en descubrimiento; métricas de efectividad y umbrales para reconsiderar (plan §3.5) |

---

## 4. Research — hallazgos clave

| Tema | Hallazgo | Verificación |
|---|---|---|
| Tavily | Free 1.000 créditos/mes; basic 1 · advanced 2; PAYG $0,008. Bloqueó con 432 y **volvió a responder el 16-09-2026** | ✅ Tavily |
| Firecrawl | Free 1.000/mes; 1 crédito/página; sin pago por uso. **`proxy` por defecto = `auto` → escala a proxies *enhanced* para anti-bot avanzado**; parámetro marcado *deprecated* | ✅ Tavily (docs.firecrawl.dev) |
| laravel/ai | Salida estructurada, failover, `queue()`, `AgentFake`, embeddings; `^0.11` en argenis-hub | WebFetch oficial + código |
| Crawl4AI | 3 parches de seguridad en 3 meses; exige Python + Docker | WebFetch (CHANGELOG) |
| Modelos | Gemini 3.7 Flash $0,75/$3,75 (dobla el 01-01-2027); Sonnet 5 $2/$10 | ✅ ai.google.dev |
| Fuentes de empleo | Arbeitnow gratis; Remotive con atribución y ≤ 4/día; Landing.jobs dudosa; ITJobs con key | Firecrawl/WebFetch |
| Cold email 2026 | Reply medio 3,43 %; positivo 1,5-5 % | ✅ woodpecker.co |
| **Supabase** | Directa solo IPv6; pooler IPv4 (5432 sesión / 6543 transacción); tablas en `public` sin RLS = legibles y escribibles con la clave pública; Free 500 MB, pausa a 7 días, sin backups; Pro 8 GB y 7 días de backup | ✅ Context7 (repo oficial) |
| **Legal — LinkedIn** | User Agreement prohíbe scraping; *hiQ* perdió por contrato; Proxycurl cerró (jul. 2025); ProAPIs acuerdo de principio (feb. 2026); **CNIL multó a KASPR con 240.000 €** | ✅ Tavily |
| **Legal — protecciones** | **CP 197 bis.1** (ES): acceso vulnerando medidas de seguridad → 6 meses-2 años. **Lei 109/2009 art. 6** (PT): hasta 1 año; hasta 3 si se violan reglas de seguridad; castiga también distribuir programas destinados a ello | ✅ Tavily (Iberley, pgdlisboa, DRE) |
| **Legal — EDPB 03/2026** | Scraping para IA generativa (aplicable por analogía): público ≠ consentimiento; sin `robots.txt` ≠ consentimiento; consulta hasta el 30-10-2026 | ✅ Tavily (EDPB, bufetes) |
| **Código argenis-hub** | Tabla `cvs` con `raw_text` (excluido a propósito de `CvData`); `FirecrawlScrapeAdapter` sin `proxy`; `FirecrawlClientInterface` solo `scrape`; `phpunit.xml` en sqlite `:memory:`; `laravel/ai ^0.11`, Laravel `^13.17` | ✅ Lectura del repo |

**Contradicciones resueltas:** email en frío a España (→ no) · Crawl4AI desde el día 1 (→ no) ·
proxies (→ no) · Tavily `advanced` (→ `basic`) · tasas del 5-20 % (→ 2-5 %) · **Firecrawl «neutro»
(→ fija `basic`, A11)** · **MVP solo con ofertas (→ A1)** · **tests en PostgreSQL (→ sqlite +
grupo `pgsql`)** · **RAG sobre el CV (→ no, A10)**.

---

## 5. Normativa y legalidad

> **No es asesoría legal.** Validación con abogado antes del primer contacto real (OP-15).

1. **Público ≠ libre de usar.** Guardar a un CTO para contactarle es tratamiento → interés
   legítimo con LIA (EDPB 1/2024).
2. **Art. 14 RGPD:** informar como máximo en la primera comunicación → aviso + baja en cada
   borrador.
3. **España:** LSSI art. 21 prohíbe el email comercial no solicitado (también B2B) y el 21.2 exige
   un email válido de oposición. LOPDGDD art. 19 no ampara la prospección.
4. **Portugal:** consentimiento para personas singulares; **lista DGC** trimestral para personas
   colectivas; email nominativo = zona gris.
5. **Responsable:** minimización, 30 d / 12 m, exactitud, derechos con registro, RoPA, cribado de
   DPIA, DPA/DPF con Tavily, Firecrawl, Google, Anthropic **y Supabase**.
6. **Bases de datos de empleo:** TJUE C-762/19 → APIs/RSS oficiales, sin republicar.
7. **Sin evasión (NORMATIVA §10):** no LinkedIn, no CAPTCHAs, no anti-bots, no logins, no proxies;
   un bloqueo detiene la obtención. Sortear protecciones puede ser **delito** (CP 197 bis, Lei
   109/2009 art. 6), no solo un incumplimiento de términos.
8. **Subirlo a la web:**
   - **A. Tu servidor, uso propio, tras login → sí**, con HTTPS, 2FA, `APP_DEBUG=false`, rate
     limiting y servidor/base en la UE (OP-21).
   - **B. Abrirlo a terceros o venderlo → no con este diseño** (Q5).
   - **C. Publicar el código → sí**, sin secretos, sin datos reales y sin funciones de evasión.
9. **argenis.dev:** falta la sección «Prospección B2B» en la política de privacidad (OP-11).

---

## 6. Plan — cómo se construye

### 6.1 Arquitectura
Módulo **`src/Modules/LeadScout`** en argenis-hub (Laravel 13), **Hexagonal Lean**. Orquestación
**determinista**; la IA solo extrae señales ambiguas y redacta la primera frase.

```
CV principal (tabla cvs, solo lectura) ─► perfil determinista + proof_points
Fuentes API/RSS ─┐
Descubrimiento   ├─► empresa (dominio canónico, supresión)
Importación ICP ─┘        ─► caché → robots → HTTP directo ─(no legible)→ Firecrawl basic
                                             └─(bloqueo 401/403/429/CAPTCHA/login)→ PARADA
                          ─► decisores (sin IA) · canales (sin IA) ─► señales (reglas → IA verificada)
                          ─► score + confianza + tier (+ needs_research) ─► bandeja
                          ─► canal legal + prueba del CV más afín + aviso art. 14 ─► ENVÍO MANUAL
                          ─► funnel con historial de etapas ─► métricas con lectura de muestra
```

### 6.2 Stack

| Componente | Elección |
|---|---|
| Framework | Laravel `^13.17`, PHP 8.5, Inertia v3 + Vue 3.5 + PrimeVue DataTable `:lazy` |
| **Base de datos** | **Supabase** (PostgreSQL, UE), pooler en modo sesión, RLS en `scout_*` |
| Tests | Pest 5; suite en sqlite `:memory:` + grupo **`pgsql`** en PostgreSQL local; guard anti-Supabase |
| Perfil | Tabla **`cvs`** vía `CvSourcePort`; **sin RAG** |
| IA | `laravel/ai ^0.11` vía `AIClientInterface`; Gemini 3.7 Flash / Sonnet 5 por defecto; selector en el borrador |
| Búsqueda | Tavily `advanced` (descubrimiento) y `basic` (resolución), caché 30 d, sin respaldo |
| Extracción | HTTP directo → Firecrawl markdown con **`proxy: "basic"`** (cliente propio) |
| Fuentes | ITJobs (en pausa) · Landing.jobs (por comprobar) · Arbeitnow · RSS LaraJobs/Remotive/WWR |
| **Descartado** | Crawl4AI/FastAPI, proxies, navegadores automatizados, MCP en backend, embeddings, multi-agentes |

### 6.3 Scoring (`rules_version 2026.09.3`)
**Agencia buena (A14)** = viva + equipo real + compra capacidad recurrente + B1 no es un freno.

- **Pesos:** Técnico 20 · Comercial 20 · **Recurrente 20** · **Vitalidad y tamaño 15** ·
  Comunicación (B1) 10 · Geo/contrato 10 · Remoto 5. Inferencias × 0,6.
- **Vitalidad y tamaño:** contenido fechado reciente, `lastmod` del sitemap (ignorado si es
  autogenerado), vacante activa, copyright; 5-50 personas suman más.
- **Comunicación (B1):** ES/PT = 100 · inglés escrito/asíncrono = 80 · inglés sin datos = 55 ·
  «native English» o llamadas diarias con cliente = 25 (no descarta). Geo incluye el solape
  horario con Portugal.
- **Descartar (con motivo):** suprimida · outsourcer grande · Técnico < 30 · presencial fuera de PT
  · **freelancer individual** · **agencia parada** (> 24 meses sin actividad) · **web muerta o
  absorbida**.
- **Tiers:** A = Lead ≥ 80, Confianza ≥ 70, señal comercial de hecho, Técnico ≥ 60, **actividad ≤ 12
  meses** y **equipo ≥ 5** (o 2-4 con señal de compra fuerte) · B = Lead ≥ 65 y Confianza ≥ 50 ·
  C = resto.
- **`needs_research`:** Lead ≥ 80 con Confianza < 70, o sin ninguna fecha → una ronda extra.
- **Ejemplos:** agencia en Sevilla → **87**, Tier A · agencia remote-first en Países Bajos → **75**,
  Tier B.
- **Descubrimiento en tres olas:** PT/ES ~60 % · resto de la UE + UK/IE ~30 % · resto del mundo
  ~10 % (+ ofertas remotas globales); sin email en frío en países sin regla verificada (OP-26).

### 6.4 Modelo de datos (22 tablas `scout_*`, A15)
- **Configuración (6):** `profiles` (+ `source_cv_uuid`, `cv_hash`, `proof_points`), `sources`,
  `suppressions` (empresas; manual/oposición/DGC), `decision_rules`, `ai_settings` (editables),
  `budgets` (límite editable + gasto acumulado).
- **Captación (4):** `companies` (+ `origin`, `discovery_wave`, `needs_research`,
  `activity_status`, `last_activity_at`, `employee_range` con `solo`, `timezone_overlap_hours`,
  `team_size_observed`, `has_decision_maker`), `job_postings`, `job_posting_sources`,
  `search_queries` (caché 30 d + familia, ola, estado, coste y empresas nuevas).
- **Evidencia y score (5):** `fetched_pages` (markdown podado a 30 d), `fetch_attempts` (incl.
  `blocked`), `signals`, `score_results` (+ `vitality`, `discard_reason`), `score_reasons`.
- **Personas y canales (4):** `contacts`, `contact_channels`, `contact_objections` (hash),
  `privacy_requests` (sin PII).
- **Funnel (3):** `outreaches`, `outreach_stage_events` (historial completo),
  `opportunities` (varias por contacto).
- **Historia:** la 6ª pasada las fusionó en 14 (A6); el operador pidió restaurarlas (A15) y se
  retiró `provider_calls`, que solo existía para sustituir a tres de ellas.

### 6.5 Contratos (web + sesión; `auth` + `permission:*_LEAD_SCOUT` + `throttle`)
- **Bandeja y leads:** `GET /lead-scout` · `GET/POST leads` · `GET leads/export` ·
  `GET leads/{uuid}` · `POST leads/{uuid}/rescore` · `POST leads/{uuid}/drafts` (provider/model
  opcionales) · `GET/PUT ai-settings`
- **Contacto y funnel:** `PATCH outreaches/{uuid}` · `POST outreaches/{uuid}/opportunities` ·
  `PATCH opportunities/{uuid}` · `PATCH channels/{uuid}`
- **Decisores:** `POST leads/{uuid}/contacts` · `PATCH contacts/{uuid}` ·
  `POST contacts/{uuid}/objection`
- **Perfil:** `GET/PUT profile` · `GET profile/cvs` · `POST profile/import-cv {cv_uuid}`
- **Resto:** `POST suppressions` · `GET/PATCH sources` + `POST sources/{uuid}/run` ·
  `GET metrics` · `POST decision-rules` + `/lock` · `GET/PUT budgets`
- **Comandos:** `lead-scout:ingest` · `:discover` · `:import-leads` · `:score` · `:expire` ·
  `:prune` · `:import-dgc` · `:privacy` · `:backup`

### 6.6 Seguridad
- **Supabase:** RLS + `REVOKE` a `anon`/`authenticated`; Data API desactivada si no se usa; SSL;
  tests nunca contra la nube.
- **Sin evasión:** bloqueo = parada; Firecrawl `basic`; User-Agent honesto; denylist de LinkedIn,
  Indeed y Glassdoor también en resultados de búsqueda.
- **SSRF:** guard de URLs salientes.
- **Prompt injection:** agentes sin tools, datos delimitados, allowlist y fragmento literal
  verificado.
- **PII:** sin nombres ni CV completo hacia la IA; retención automática; derechos por comando.
- **Envíos:** formularios nunca enviados, sin CAPTCHAs, sin teléfonos.

### 6.7 Entregas
Plan para un colchón de **2 meses** (A12):
- **Semana 0 (ya, 16-09):** prospección manual registrada en CSV, empleo/freelance directo y
  nearshore PT (OP-23, OP-24), `/agencies`, regla de decisión, datos de Supabase.
- **Semana 1 (16-09 → 22-09):** tests seguros + 22 tablas con RLS + perfil desde
  `Argenis_Gonzalez_CV_2026.md` + ingesta + importación ICP con contactos manuales + score + export
  → **ya se puede prospectar con datos del sistema**.
- **Semana 2 (23-09 → 29-09):** búsqueda + resolución + **descubrimiento** + obtención sin evasión +
  presupuesto + **backup** + decisores + canales + IA verificada + tiers → **operable**.
- **Semana 3 (30-09 → 06-10):** bandeja, borradores con selector, etapas, métricas (endpoint +
  export), regla bloqueable, comandos de privacidad y DGC.
- **Operación (07-10 → ~16-11):** 10-15 contactos/día; **punto de control de caja ~21-10** (OP-25).
- **Diferido hasta el primer ingreso:** T021 Landing.jobs, T023 ITJobs, T058 eval de IA, T068
  frontend de métricas.
- **Fase 2:** ATS por slug, LatAm/UE. **Fase 3:** Crawl4AI y embeddings/RAG solo si se cumplen los
  umbrales medidos del plan §3.5; proxies residenciales, nunca.

### 6.8 Gates antes del primer contacto real
1. `/agencies` publicada.
2. Regla de decisión bloqueada.
3. Abogado (OP-15).
4. Política de privacidad (OP-11).
5. Lista DGC si es PT (OP-16).
6. One-pager de no captación y frase de recibos verdes (OP-18, OP-7).
7. RoPA/DPA incluido Supabase (OP-10, OP-12, OP-19).
8. Si se despliega en la web: HTTPS, 2FA, UE, `APP_DEBUG=false` (OP-21).

---

## 7. Tasks — estado

**Total: 85 técnicas (4 diferidas: T021, T023, T058, T068) + 27 del operador · Completadas: 1
(OP-20).** Detalle en [`tasks.md`](tasks.md);
equivalencia con la numeración anterior en `analyze.md` §7.

**Fase 0 — Operador**
- [ ] OP-1 `/agencies` · [ ] OP-2 CV · [ ] OP-3 Email adaptado a la LSSI · [ ] OP-4 Prospección
  manual + CSV ICP · [ ] OP-5 Términos + key ITJobs · [ ] OP-6 Regla de decisión · [ ] OP-7
  Contabilista + frase de recibos verdes · [ ] OP-8 Eval de IA
- [ ] OP-9 LIA · [ ] OP-10 RoPA · [ ] OP-11 Política de privacidad · [ ] OP-12 DPA/DPF ·
  [ ] OP-13 DPIA · [ ] OP-14 EDPB 03/2026 final · [ ] OP-15 **Abogado** · [ ] OP-16 Lista DGC ·
  [ ] OP-17 Límite de Tavily
- [ ] OP-18 One-pager de no captación · [ ] OP-19 **Supabase Free** (región, versión, Data API,
  tamaño actual) · [x] OP-20 Colchón = 2 meses · [ ] OP-21 ¿Local o servidor? · [ ] OP-22 Marcar
  `Argenis_Gonzalez_CV_2026.md` como principal en `cvs`
- [ ] OP-23 Empleo/freelance directo · [ ] OP-24 Consultoras nearshore PT · [ ] OP-26 Email fuera de ES/PT · [ ] OP-25 Punto de
  control de caja (~21-10-2026)

**A · Fundamentos** — T001 Context7 · T002 UNVERIFIED (Supabase, Firecrawl `basic`) · T003 Tests
sin riesgo para Supabase · T004 Esqueleto · T005 Permisos · T006 Config · T007 Tavily
`searchDepth` · T008 `schedule:work`

**B · Datos** — T009 Enums · T010 Helper RLS + configuración · T011 Captación y evidencia · T012
Contacto y funnel · T013 Modelos · T014 Test de esquema + RLS · T015 VOs

**C · Perfil** — T016 SkillTaxonomy + ProofPointMatcher · T017 CvSourcePort + import sin IA ·
T018 ProfileController (cvs, import, stale) · T019 Verificar US-1

**D · Ingesta e importación** — T020 Puerto + RSS · T021 Landing.jobs · T022 Arbeitnow · T023
ITJobs · T024 Seeder · T025 Ingesta · T026 Job + comando · T027 Caducidad · T028 SourceController ·
T029 Alta manual + import CSV + supresión · T030 Verificar US-2

**E · Score + export** — T031 Extractor por reglas · T032 ScoringEngine · T033 TierClassifier +
`needs_research` · T034 Score handler · T035 Export (**checkpoint semana 1**)

**F · Empresa, descubrimiento, obtención, decisores y canales** — T036 Guard · T037 Robots ·
T038 HTTP con detección de bloqueo · T039 Firecrawl `basic` · T040 ProviderCalls + BudgetLedger ·
T041 FetchLadder sin escalar bloqueos · T042 SearchPort · T043 ResolveCompany · T044
**Descubrimiento** · T045 Enrich · T046 RoleTaxonomy · T047 DecisionMakerExtractor · T048
Decisores A/B · T049 Endpoints de contactos · T050 Resumen de formularios · T051
ContactChannelDetector · T052 Canales en la cadena · T053 Verificar US-3/7/8/11/12

**G · IA verificada** — T054 Catálogo · T055 Agente · T056 Extractor · T057 ExtractSignals ·
T058 Eval · T059 Cadena end-to-end

**H · Bandeja y borradores** — T060 List/Get · T061 ChannelAdvisor · T062 Borradores + prueba del
CV + selector · T063 Etapas · T064 Frontend

**I · Funnel** — T065 Deal · T066 Métricas + lectura de muestra · T067 Regla · T068 Frontend ·
T069 Verificar US-5/6/10/11/12

**J · Transversal (hecho, 18-09-2026)** — T070 `PruneLeadContactsHandler` + `lead-scout:prune`
diario (30 d sin contacto / 12 m tras interacción; markdown > 30 d) · T071 `Article14Notice`
ES/PT/EN + `ready` exige aviso · T072 re-verificación > 90 d en `GenerateDraftHandler`
(`RetentionAndAccuracyTest`, 3/3) · T073 `HandlePrivacyRequestHandler` + `lead-scout:privacy
{search|export|erase|object}` con ledger hash sin PII (`PrivacyCommandTest`) · T074
`ImportDgcListHandler` + `lead-scout:import-dgc` (CSV/XLSX ≤ 10 MB, NIPC/dominio/nombre;
`ChannelAdvisor` bloquea PT listado o lista > 3 m; `DgcImportTest`) · T075 limiters
`lead-scout-llm`/`lead-scout-export` en provider + rutas con test 429 (`RateLimitTest`) · T076
`ApplicationLogger` con redacción (secretos, PII, `draft_body`, connection strings) usado en
score/draft (`ApplicationLoggerTest`) · T077 `lead-scout:backup` semanal (`pg_dump
--table=scout_*`, retención 4, fuera del repo; `BackupCommandTest`) · T078 OWASP 15 + §16 LLM
verificado con evidencia (sin evasión, RLS, raw_text confinado; §6.6)

**K · Cierre (parcial, 18-09-2026)** — T079 `LeadScout`: 137/140 (3 skip `pgsql`, sin PG local);
`pint --test` OK; suite completa omitida a petición del operador · T080 pipeline del router
ejecutado + `index_repository(mode: "full")` (20715 nodos / 76691 aristas) · T081 este pase →
resumen regenerado

---

## 8. Analyze — consistencia

**Resultado:** 35 huecos encontrados → 35 corregidos · **0 abiertos** · 16 riesgos aceptados ·
dato pendiente del operador: OP-19 · bloqueo antes del primer contacto real: OP-15.

| # | Hueco corregido |
|---|---|
| G1-G6 | Endpoint de acuerdos · export del funnel · canal de empleo · extractor por reglas · capas del selector · convención de comandos |
| G7-G8 | Extracción de decisores · nombre por plantilla frente a IA |
| G9-G15 | Art. 14 · email nominativo PT · conservación · derechos y exactitud · responsabilidad proactiva · lista DGC · email válido de oposición |
| G16-G17 | Términos de Remotive/Landing.jobs · canales de contacto |
| G18 | MVP dependía del canal con menos volumen → descubrimiento en el MVP |
| G19 | Sobredimensionamiento → comandos para privacidad y DGC (la fusión de tablas se revirtió en A15) |
| G20-G22 | Supabase: exposición por la Data API · tests contra la nube / sqlite · 500 MB y backups |
| G23-G24 | Lectura de muestra · `needs_research` |
| G25 | CV duplicado → lectura de `cvs` |
| **G26-G27** | **Firecrawl escalaba a proxies anti-bot · la escalera no paraba ante un bloqueo** |
| G28-G30 | Supabase fuera del RoPA/DPA · referencia errónea en NORMATIVA · respaldo de búsqueda inexistente |
| G31 | Plan pensado para 3+ meses con un colchón de 2 → A12 |
| G32 | Sin métricas para decidir si hacía falta Crawl4AI/proxies → A13 |
| G33-G35 | Freelancers de 1 persona podían llegar a A/B · sin señal de actividad (agencias paradas) · descubrimiento y B1 solo para PT/ES → A14 |

**Riesgos aceptados:** R1 PC encendido · R2 tope de IA · R3 precio de Gemini · R4 sqlite vs
PostgreSQL · R5 decisores perdidos · R6 email ambiguo · R7 interpretación legal · R8 cambios
regulatorios · R9 cruce DGC · R10 detección de canales · **R11 Supabase Free · R12 latencia ·
R13 falsos positivos del descubrimiento · R14 deprecación de `proxy` en Firecrawl · R15 colchón
de 2 meses · R16 sin jurisprudencia sobre CAPTCHA como medida de seguridad**.

---

## 9. Implement — informe

**Estado (18-09-2026): fases J1+J2 hechas; K parcial.** Trazabilidad del pase (cada FR tiene
código + test): FR-27 → `PruneLeadContactsHandler` + `:prune` + `RetentionAndAccuracyTest` ·
FR-26 → `Article14Notice` + gate `ready` + `RetentionAndAccuracyTest` · FR-28 →
`GenerateDraftHandler::reverifyDecisor` + `RetentionAndAccuracyTest` · FR-29 →
`HandlePrivacyRequestHandler` + `:privacy` + `PrivacyCommandTest` · FR-17/DGC →
`ImportDgcListHandler` + `:import-dgc` + `ChannelAdvisor` + `DgcImportTest` · rate limit →
limiters + throttle en rutas + `RateLimitTest` (429) · logging → `ApplicationLogger` +
`ApplicationLoggerTest` · backup → `:backup` + `BackupCommandTest` · FR-40 → `LeadScoutArchTest`.

**Verde:** `LeadScout` 137/140 (3 skip `pgsql`: sin PostgreSQL local; **nunca contra
Supabase**, guard T003) · `pint --test` OK · pipeline del router ejecutado + reindex
(`indexed_at` posterior a la última edición).

**Pendiente del operador:** suite completa (omitida a petición); `migrate` en Supabase con
confirmación; ruta de `LEAD_SCOUT_BACKUP_DIR`; OP-10 (RoPA: copias T077), OP-16 (DGC
trimestral), OP-19 (Data API), OP-21 (despliegue).

---

## 10. Próximos pasos recomendados

1. **Hoy, sin código:**
   - OP-1 `/agencies`, OP-4 prospección manual y OP-6 regla de decisión.
   - **OP-23** y **OP-24**: abrir hoy las vías de empleo/freelance directo y nearshore PT (con
     2 meses de colchón no pueden esperar al sistema).
   - **OP-19** región, versión, Data API y tamaño actual de Supabase; **OP-22** marcar
     `Argenis_Gonzalez_CV_2026.md` como principal en `cvs`.
2. **Antes del primer contacto real:** OP-11 (política), OP-15 (abogado, con NORMATIVA §10.4),
   OP-16 (DGC si es PT), OP-18 (one-pager).
3. **Cuando lo pidas:** Fase 7 desde la Fase A (T001-T008); el checkpoint de la semana 1 (T035)
   ya permite prospectar con datos del sistema.
