# Clarificación — 003-lead-scout

> Fase 2 · CLARIFY — Registro de ambigüedades de `spec.md`, su impacto y cómo se resolvió cada una.
> Fecha: 2026-09-16

## Preguntas de alto impacto (preguntadas al operador)

### Q1 — ¿Por qué canal arranca el MVP?
Opciones: (a) señales de ofertas publicadas → empresa → contacto; (b) discovery de agencias sin
vacante; (c) ambas desde el día 1.
**Impacto si queda sin resolver:** cambia el orden de las historias (US-2/US-3 frente a US-7),
las fuentes del registro, el gasto de búsqueda (las ofertas llegan por API/RSS gratis; el
discovery de agencias consume Tavily/Firecrawl) y el canal legal en España (responder a una
oferta publicada frente a un contacto en frío). Plan §3, §4 y §9.
**Estado:** pendiente → ver respuesta abajo.

### Q2 — Colchón económico y horas diarias disponibles
**Impacto:** define el tamaño del MVP. Con menos de 6 semanas de colchón → Fase 0 manual
inmediata + comando mínimo, sin panel web. Con 3 meses o más → MVP con bandeja web. También
fija el reparto de tiempo entre construir, prospectar y el funnel de empleo en paralelo.
Plan §1, §6 y tasks.
**Estado:** pendiente → ver respuesta abajo.

### Q3 — Presupuesto mensual de herramientas
**Impacto:** hoy la cuota de Tavily está agotada (research R1). Con €0 → solo fuentes
API/RSS + free tiers que se reinician cada mes, y el discovery de agencias queda muy limitado.
Con hasta €30 → Firecrawl Hobby o Tavily PAYG. Con hasta €100 → holgura para 1.000
empresas/mes. Plan §2 y §9, US-8.
**Estado:** pendiente → ver respuesta abajo.

## Preguntas de menor impacto (resueltas por defecto)

### Q4 — Canal de contacto en España ante la LSSI art. 21
**Resolved by default:** en España el borrador recomienda (1) responder a la oferta publicada
por el canal que indique, (2) el formulario de contacto de la empresa o (3) un mensaje en una
red profesional enviado a mano. Email directo en frío solo a personas colectivas de Portugal
(opt-out) y de países con un régimen equivalente registrado en la configuración, siempre con
una línea de baja. `EMAIL-OUTREACH-AGENCIAS.md` se adapta en consecuencia. *No es asesoría
legal (research R8.1).*
**Impacto:** US-5 (recomendación de canal), plan §8.

### Q5 — ¿Producto SaaS / white-label para terceros?
**Resolved by default:** **no**. Uso personal y un solo operador. La visión SaaS de
`PIPELINE-A-B-COMPARATION.md` se aparca hasta tener clientes recurrentes propios.
**Impacto:** sin multi-tenant, billing ni API pública (spec §8).

### Q6 — ¿Ofertas de empleo fijo o solo freelance/contrato?
**Resolved by default:** **ambas**. Una oferta de contrato o freelance suma más Ajuste
comercial. Una de empleo fijo se usa como **señal de capacidad** (variante «mientras cubren la
vacante…») y, si el operador quiere, como candidatura clásica registrada en un canal aparte
(«empleo») para no mezclar métricas.
**Impacto:** US-4 (pesos), US-6 (canales).

### Q7 — Geografía de la fase 1
**Resolved by default:** fase 1 = **Portugal + España**. Fase 2 = LatAm hispanohablante +
remoto UE por APIs públicas. Fase 3 = UK/IE/DE/NL y después US/CA con Ajuste de comunicación.
**Impacto:** registro de fuentes y familias de consultas.

### Q8 — Idioma de los borradores
**Resolved by default:** España y LatAm → español con «ustedes» (`EMAIL-OUTREACH-AGENCIAS.md`
§0). Portugal → portugués (el operador revisa antes de enviar) con alternativa en español.
Resto → inglés escrito simple, siempre en canales async.
**Impacto:** US-5.

### Q9 — Umbrales de la regla de decisión
**Resolved by default** (editables **antes** del inicio y bloqueados después, FR-19). Muestra =
**150 contactos cualificados** en **8 semanas**:

| Respuestas positivas | Lectura | Acción |
|---|---|---|
| ≤ 2 (< 1,5 %) | Sin señal | Cambiar segmento u oferta; **no** construir más tecnología |
| 3-7 (2-5 %) | Señal tibia | Iterar la primera frase, la variante y el ICP |
| ≥ 8 (> 5 %) | Funciona | Escalar el volumen y activar la fase 2 geográfica |

Base: benchmarks de 2026 (research R7) en lugar del 5-20 % de los documentos.
**Impacto:** US-6, criterios de éxito.

### Q10 — Tarifa mínima y modalidades
**Resolved by default:** suelo de **€30/h** (entrada), objetivo de €35-45/h, urgente/legacy
€45+/h (CHATGPT-8). Formatos: bloque cerrado de N semanas o retainer mensual de horas; la
tarifa nunca aparece en el primer contacto (`EMAIL-OUTREACH-AGENCIAS.md` §8-9).
**Impacto:** Ajuste comercial (señales de precio bajo público → penalización), registro de
oportunidades.

### Q11 — Retención de datos personales de contacto
**Resolved by default:** solo nombre, cargo y URL de un perfil profesional público o una web de
empresa. Sin emails adivinados. Anonimización a los **12 meses** sin interacción. Supresión
permanente si piden no ser contactados.
**Impacto:** NFR de cumplimiento, plan §8.

### Q12 — Claims del CV no verificados en los documentos
**Resolved by default:** «despliegue en AWS», Forge/Vapor y «12+ projects» **no** se usan en
los borradores hasta que el operador los confirme en el perfil (US-1, US-5). Se añade «3 años
facturando con recibos verdes» como capacidad confirmada por el operador.
**Impacto:** US-1, US-5.

### Q13 — ¿Interfaz web o solo comando?
**Resolved by default, condicionado a Q2:** si el colchón es corto, primero comando + export
tabular (FR-21) y después la bandeja web mínima dentro del panel existente. Si hay margen, la
bandeja web entra en el MVP.
**Impacto:** tamaño del MVP (plan §6).

## Respuestas del operador (2026-09-16)

| Pregunta | Respuesta | Consecuencia |
|---|---|---|
| **Q1** Canal del MVP | **Ofertas como señal** | El MVP = US-1 a US-6 + US-8. US-7 (discovery de agencias sin vacante) pasa a la **fase 2**. En la fase 1 el gasto de búsqueda se limita a resolver empresas y careers pages. **→ Revisado por A1 (6ª pasada): US-7 entra en el MVP** |
| **Q2** Colchón | **3+ meses** | MVP completo en ~2-3 semanas **con bandeja web** (Q13 → la bandeja web entra en el MVP). Prospección manual (Fase 0) desde la semana 1 en paralelo. **→ Sustituido por Q24 (2 meses) y A12** |
| **Q3** Presupuesto | **Hasta €30/mes** | Tope configurable de €30: búsqueda ≤ €8, extracción ≤ €15 (Firecrawl Hobby, $16), IA ≤ €7. Degradación a free tiers + API/RSS al agotarse |

No quedan preguntas de alto impacto abiertas → se puede pasar a Fase 3/4.

## Decisiones del plan confirmadas por el operador (2026-09-16)

| Decisión | Respuesta | Aplicación |
|---|---|---|
| **D1** robots.txt | Parser propio (recomendado) | Sin dependencias nuevas |
| **D2** Dónde corre | **Laravel Herd local** durante el test | El scheduler y el worker corren mientras el PC está encendido (`schedule:work` + `queue:listen`) |
| **D3** Modelo IA | **Sonnet 5 o Gemini 3.7 Flash** | Ambos seleccionables; por defecto Gemini 3.7 Flash para extracción (más barato) y Sonnet 5 para borradores, cada uno como respaldo del otro; la eval de 30 páginas confirma cuál queda de principal |
| **D4** API key de ITJobs.pt | Se pide más adelante | La fuente ITJobs queda `paused` hasta tener la key y los términos revisados; no bloquea el MVP |
| **D5** `search_depth` por llamada en Tavily | Sí | Cambio en el adaptador compartido + re-ejecutar los tests de CourseScripts |
| **Q14 (nueva)** Selector de proveedor IA | **Sí, «un select para elegir provider AI»** | Nueva US-10 / FR-22: selector por propósito (extracción, borradores), opciones cerradas, respaldo, override por borrador y coste estimado visible. **→ Reducido por A2: el select queda en el editor de borrador; los defaults, en `.env`** |
| **Q15 (nueva)** ¿El pipeline extrae decisores (CTO, fundador, cofundador…) y no recruiters ni trabajadores? | **Sí** — era un hueco del plan (la tabla existía, pero no había un paso de extracción ni un filtro por cargo) | Nueva US-11 / FR-23-25 |
| **Q16** Emails de decisores publicados en la web de la agencia | **Solo si son públicos** (recomendado) | Se guardan si la empresa los publica en su dominio junto al nombre; nunca se adivinan; en Portugal se pueden usar con opción de baja; en España se marcan «no usar para email en frío» (LSSI). **Amplía Q11** |
| **Q16-bis** (revisión normativa, 16-09) Email nominativo en Portugal | **Resolved by default (conservador):** zona gris → **no recomendado para contacto en frío** | La CNPD Diretriz/2022/1 deja fuera el marketing a personas colectivas y exige consentimiento para personas singulares sin relación previa; un email `nombre@empresa.pt` identifica a una persona. Se guarda, pero el borrador propone la respuesta a la oferta, el formulario, el buzón genérico de la empresa (con baja) o la red profesional. **Validar con un abogado** (`NORMATIVA-RGPD.md` §4, §7) |
| **Q18** Conservación de decisores | **Resolved by default:** solo leads A/B; anonimizar a los 30 días si no se contactó; 12 meses tras la última interacción si hubo contacto | FR-27 (sustituye los 12 meses generales de Q11) |
| **Q19** Deber de informar (art. 14 RGPD) | **Resolved by default:** aviso breve + línea de baja en todo borrador a una persona, más una política de privacidad en argenis.dev | FR-26 · OP-11 |
| **Q20** Límites de decisores (LinkedIn solo si la web lo enlaza; alta manual; cobertura parcial; oposición) | **Aceptados por el operador (16-09)** | Sin cambios de diseño: US-11 y FR-23 a FR-25 quedan confirmados |
| **Q21** Canales de contacto de la empresa (formulario, llamada a freelancers, partners, «Trabaja con nosotros», buzón genérico) | **Sí, añadir** (US-12). Sobre «Trabaja con nosotros» el operador confirma que suele llegar a RRHH/recruiters y se pierde el contacto, **pero si existe, se extrae** | FR-31/FR-32: detección sin IA; «Trabaja con nosotros» con prioridad baja y aviso; nunca se rellenan formularios; la tasa de respuesta por canal lo validará con datos |
| **Q17** ¿IA para extraer personas? | **Sin IA, solo reglas** (recomendado) | Datos estructurados + patrones nombre/cargo; los nombres nunca salen hacia proveedores de IA y se ocultan en el texto enviado para extraer señales; la persona que falte se añade a mano |

## Ajustes de la 6ª pasada (2026-09-16) — comparación con MUSE-SPARK, Supabase, CV y límites legales

Origen: el operador pidió ajustar esta spec con los puntos fuertes de la variante
`003-leadscout-pipeline-MUSE-SPARK`, indicó que la base de datos es **Supabase en la nube**, que el
CV markdown ATS ya está en la tabla `cvs` (migración `2026_07_25_000100_create_cvs_table.php`), y
preguntó por RAG, por la legalidad de subir el módulo a la web y por si hará scraping de LinkedIn o
saltará CAPTCHAs/anti-bots. **A1 revisa Q1; A2 reduce Q14; el resto son decisiones nuevas.**

| # | Decisión | Motivo | Aplicación |
|---|---|---|---|
| **A1** | **Descubrimiento de agencias PT/ES en el MVP** (US-7) + importación CSV de la lista ICP. La vacante pasa a ser una **señal que sube la prioridad**, no la única entrada | Research R6: 4 ofertas «laravel» en ITJobs y ~7 vigentes en Tecnoempleo → un MVP solo de ofertas no llega a 150 contactos en 8 semanas. MUSE-SPARK acertaba en ir a por agencias directamente | spec US-7 · plan §3.2 paso 0 · T029, T042, T044 |
| **A2** | Selector de IA **solo en el editor de borrador**; defaults por propósito en `.env`; sin tabla ni pantalla de ajustes | Un solo usuario y 2 modelos; se mantiene el «select para elegir provider AI» pedido en Q14 donde aporta | spec US-10, FR-22 · T054, T062 |
| **A3** | Derechos de las personas (FR-29) por **comando** con registro en el activity log sin PII | Pocas solicitudes esperadas; la obligación legal se cumple igual | T073 |
| **A4** | Lista DGC por **comando** de importación | Se usa una vez por trimestre | T074 |
| **A5** | **Base de datos Supabase** (PostgreSQL, región UE): pooler en modo sesión, RLS + `REVOKE` en todas las `scout_*`, tests nunca contra Supabase, poda de contenido, backup si el plan es Free | Research R12: la Data API expone `public`; Free = 500 MB para todo el proyecto, sin backups y pausa tras 7 días | plan §4.2 · T002, T003, T010, T014, T070, T077, T078 · OP-19 |
| **A6** | ~~**22 → 14 tablas**~~ **(revertido por A15: vuelven a ser 22)** | El número de tablas no cuesta en PostgreSQL; cuesta construirlas y mantenerlas. Se fusionan las que no aportaban (plan §4.1) | plan §4 · T010-T013 |
| **A7** | Aportes de MUSE-SPARK: resumen ejecutivo, gates antes del primer contacto, «qué no hacer», lectura de muestra «no concluyente», marca `needs_research`, vacante activa +15 en Recurrente, tamaño solo desempata (**sustituido por A14**: 1 persona descarta, 2-4 con techo en B), one-pager de no captación | Eran lo mejor de esa variante y no chocan con esta spec | plan §0, §3.3, §12, §13 · spec US-4, US-6, FR-33 · T033, T045, T066 · OP-18 |
| **A8** | Lo que **no** se trae de MUSE-SPARK: FastAPI + Crawl4AI + Railway, proxies, su regla de 100/4 semanas | Contradecía su propio research y su «sin overengineering»; la regla 150/8 semanas se basa en benchmarks 2026 (Q9) | spec §8 · plan §13 |
| **A9** | **Perfil desde la tabla `cvs`** (CV principal en markdown ATS del operador), leído en solo lectura con `CvSourcePort`; se guardan `source_cv_uuid`, `cv_hash` y `proof_points`, **no** el texto; aviso de perfil desactualizado si el CV cambia | Evita duplicar el dato más sensible del módulo Cvs (su `CvData` excluye `raw_text` a propósito) y una segunda subida | spec US-1, FR-1, FR-35 · plan §3.2, §4, §5 · T017, T018 · OP-22 |
| **A10** | **Sin RAG ni embeddings** en el MVP; recuperación **determinista** de la prueba del CV más afín (`ProofPointMatcher`) | Un único documento de ~1-2k tokens no necesita recuperación; FR-10 exige determinismo; embeber el CV lo envía a otro proveedor. Reconsiderar en Fase 3 (varias versiones del CV, > 1.000 ofertas/mes) | plan §3.4 · T016, T062 |
| **A11** | **Obtención sin evasión**: solo APIs/RSS oficiales y páginas públicas; ningún acceso a LinkedIn; un bloqueo (401/403/429, CAPTCHA, anti-bot, login) se registra y **no se reintenta por otra vía**; Firecrawl con `proxy: "basic"` en un cliente propio (el adaptador compartido no fija `proxy` y el valor por defecto `auto` escala a proxies para anti-bots) | Research R13: CP español art. 197 bis.1, Lei 109/2009 art. 6 (PT), Directiva 2013/40/UE; LinkedIn v. Proxycurl/ProAPIs; CNIL KASPR 240.000 €. Research R14: adaptador actual sin `proxy` | spec FR-13 · plan §2, §3.1, §8, §13 · T038, T039, T041, T042, T078 |

### Preguntas nuevas

| # | Pregunta | Estado |
|---|---|---|
| **Q22** | ¿Es legal subir el módulo a la web? | **Resuelto (análisis, no asesoría legal):** alojarlo en tu servidor tras login para uso propio no cambia su licitud; la determina **qué hace** (fuentes públicas, sin evasión, sin LinkedIn, sin email en frío en ES, RGPD cumplido). Añade obligaciones de seguridad (art. 32). Abrirlo a terceros o venderlo es otro escenario (Q5). Publicar el código es posible sin secretos y sin funciones de evasión. Detalle en `NORMATIVA-RGPD.md` §10 · OP-21 · OP-15 sigue siendo el gate legal |
| **Q23** | ¿Hará scraping de LinkedIn, saltará CAPTCHAs o anti-bots? | **No.** Solo URLs públicas y APIs/RSS oficiales. Se detectó y corrigió un hueco: Firecrawl por defecto escalaba a proxies anti-bot (A11) |
| **Q24** | ¿Colchón real: 3+ meses (esta spec) o escenario corto (MUSE-SPARK)? | **Resuelto: 2 meses** → A12 |
| **Q25** | ¿Supabase Free o Pro, qué región y se usa la Data API? | **Plan Free** (resuelto). Región, versión, Data API y tamaño actual siguen en OP-19 |

### Respuestas del operador a la 6ª pasada (2026-09-16)

| # | Respuesta | Consecuencia |
|---|---|---|
| **Q24** Colchón real | **2 meses** (hasta ~16-11-2026). Sustituye a los «3+ meses» de Q2 y al «escenario corto» de MUSE-SPARK | **A12** (abajo) |
| **Q25** Plan de Supabase | **Free** (región, versión de PostgreSQL, uso de la Data API y tamaño actual de la base: pendientes en OP-19) | T077 (backup semanal) **deja de ser condicional**; la poda de T070 es obligatoria; si el PC está apagado ≥ 7 días el proyecto se pausa (R11) |
| **OP-22** CV de origen | **`Argenis_Gonzalez_CV_2026.md`** (markdown ATS, en inglés; misma huella que la copia de `GUIDE/LeadScout-MODULE/` en argenis-hub) | T017 lo usa como CV por defecto y como fixture **anonimizado** (sin teléfono, email ni redes). Estructura verificada en research R14 |
| Aclaración | Las **22 tablas eran solo de LeadScout** (`scout_*`), no de todo el proyecto. argenis-hub ya tiene **51 tablas** creadas en sus migraciones (más las de permisos) | Con 14, LeadScout suma ~22 % a las tablas del proyecto |

### A12 — Plan para un colchón de 2 meses (resuelto por defecto, editable por el operador)

**Problema:** el escenario base del análisis (`ANALISIS-LEADSCOUT.md` §4) pone el primer cliente
recurrente **en el mes 3**, y el ciclo con agencias suele tener 2-4 meses de silencio. Con 2 meses,
el pipeline de agencias **no puede ser la única fuente de ingresos**.

| Decisión | Detalle |
|---|---|
| **Tres vías en paralelo desde la semana 1** | (1) Pipeline de agencias (este módulo) · (2) **ofertas de empleo o freelance** a las que se responde directamente (canal `employment_application`, ya medido aparte, Q6) · (3) **consultoras nearshore portuguesas** como vía de caja rápida (sugerencia de MUSE-SPARK; que cierren en 2-6 semanas y acepten B1 es `[UNVERIFIED]`) |
| **Construir menos antes de usar** | El sistema debe ser **operable al final de la semana 2** (~30-09-2026) con exportación, no con bandeja web. La bandeja y los borradores con IA llegan en la semana 3. Se **difieren** hasta el primer ingreso: T021 Landing.jobs, T023 ITJobs (ya en pausa), T058 eval de IA (se usan los modelos por defecto; la verificación literal protege la extracción y el operador revisa el 100 % de los borradores) y T068 frontend de métricas (se usa la exportación del funnel) |
| **Los contactos manuales cuentan** | Lo enviado a mano desde el día 1 se registra en un CSV y se importa con T029 (columnas de outreach opcionales), así la muestra de 150 empieza a contar ya |
| **Regla de decisión sin cambios** | 150 contactos / 8 semanas y umbrales Q9, bloqueada antes del primer contacto. Termina ~mediados de noviembre, justo con el colchón |
| **Punto de control de caja (no altera la regla)** | Semana 4 de operación (~21-10-2026): si hay 0 conversaciones en el pipeline **y** ninguna entrevista en las vías 2-3, se pasa a ~70 % del tiempo en empleo/nearshore y ~30 % en agencias (sin dejar de registrar). Es una decisión de reparto de horas, no una conclusión sobre el método |
| **Presupuesto** | Tope de €30/mes sin cambios; lo esperado durante el test es €0-10 |

Aplicación: spec §9 · plan §0, §9, §10 · tasks OP-20, OP-23, OP-24, T029, T058, T062, T068,
T021, T023, T077 · analyze R11, R15.

### A13 — ¿FastAPI + Crawl4AI + proxies residenciales o basta con Firecrawl? (pregunta del operador, 16-09-2026)

**Resuelto: basta con HTTP directo + Firecrawl `basic`; sin FastAPI, Crawl4AI ni proxies
residenciales en el MVP.** Detalle y criterios medidos para reconsiderar en `plan.md` §3.5.

| Pregunta | Respuesta |
|---|---|
| ¿Crawl4AI extrae más que Firecrawl en webs de agencias? | No en páginas públicas: ambos renderizan JS con un navegador. Crawl4AI solo ahorra dinero a gran volumen (> 20.000 páginas/mes o Firecrawl > €80/mes), a cambio de operar Python, Playwright, Docker y parches de seguridad (R4) |
| ¿Los proxies residenciales mejoran la extracción? | Solo en webs que **bloquean bots**, y ahí su función es esquivar el bloqueo → contrario a FR-13 y con riesgo penal (NORMATIVA §10). **No** |
| ¿Qué mejora de verdad la efectividad? | La **búsqueda**: Tavily `advanced` en el descubrimiento (~80 créditos/mes, dentro del free tier), familias de consulta medidas por «consulta → lead A/B», importación ICP y revisión manual de directorios; y medir la extracción (% empresas sin páginas leídas, % `blocked`, SPA rescatadas) |
| ¿Y con 2 meses de colchón? | Cada semana en infraestructura es una semana sin contactos (A12). MUSE-SPARK necesitaba ~7 tareas más solo para ese microservicio |

Aplicación: plan §2, §3.5, §9 (D5), §10 · tasks T042 (`advanced` en descubrimiento), T066
(métricas de efectividad).

### A14 — Qué es una «agencia buena» (petición del operador, 16-09-2026)

**Petición:** «que el algoritmo encuentre agencias buenas: ibéricas (ES y PT) y resto del mundo con
inglés B1, con trabajos recurrentes; no agencias paralizadas ni de 1 empleado».

**Huecos que tenía la spec:** (1) el tamaño «solo desempataba» y un freelancer de una persona podía
llegar a A/B; (2) no había ninguna señal de actividad, así que una agencia parada puntuaba igual
que una viva; (3) el descubrimiento solo buscaba en PT/ES y el resto del mundo quedaba para las
fases 2-3.

| Decisión | Detalle |
|---|---|
| **Definición** | Agencia buena = **viva** + **equipo real** + **compra capacidad recurrente** + **B1 no es un freno** |
| **Nueva dimensión «Vitalidad y tamaño» (peso 15)** | Fecha del contenido más reciente, `lastmod` del sitemap (ignorado si es autogenerado), vacante activa, año del copyright y tamaño observado. Todo determinista y a partir de páginas ya descargadas: **coste 0** |
| **Pesos nuevos** | Técnico 20 · Comercial 20 · **Recurrente 20** (antes 15) · **Vitalidad y tamaño 15** (nueva) · Comunicación B1 10 · Geo 10 (antes 15) · Remoto 5 (antes 10; lo presencial fuera de PT ya se descarta por regla) |
| **Descartar con motivo** | Freelancer individual (web en primera persona o equipo de 1) · agencia parada (> 24 meses sin actividad y copyright antiguo) · web muerta o absorbida · outsourcer grande (> 200) |
| **Tier A exige** | Actividad en los últimos 12 meses y equipo ≥ 5 (o 2-4 con vacante activa o llamada a freelancers verificada). Sin fechas → `needs_research`, no descarte |
| **Tres olas en el MVP** | 1: PT/ES ~60 % · 2: resto de la UE + UK/IE en inglés ~30 % · 3: resto del mundo ~10 % (+ ofertas remotas globales ya ingeridas). El reparto se reajusta con las métricas por ola |
| **B1** | ES/PT = 100 · inglés escrito/asíncrono demostrado = 80 · inglés sin datos = 55 · «native English» o llamadas diarias con el cliente = 25 (penaliza, no descarta) · solape horario con Portugal en Geo |
| **Email fuera de ES/PT** | Sin email en frío en países sin regla verificada (OP-26); oferta, formulario o red profesional a mano |
| **Coste** | ~60 consultas/mes en `advanced` ≈ 120 créditos de Tavily (free tier) |

Aplicación: spec US-4, US-9 (Alta, MVP), FR-36, FR-37, §10 · plan §0, §3.2 (pasos 0 y 5a), §3.3
(`rules_version 2026.09.3`, ejemplos «87» y «75»), §4 (`activity_status`, `last_activity_at`,
`discovery_wave`, `timezone_overlap_hours`, `employee_range` con `solo`, `discard_reason`,
subscore `vitality`), §7, §8, §11 · tasks T006, T009, T011, T031-T033, T042, T044, T060, T061, T066,
OP-26 · sin tablas nuevas, solo columnas (el modelo completo de 22 tablas se restauró después en A15).

### A15 — Se restauran las 22 tablas (petición del operador, 16-09-2026)

**Petición:** tras ver qué 9 tablas se habían fusionado en A6, el operador pide «añádelas de nuevo».

| Decisión | Detalle |
|---|---|
| **Tablas restauradas (9)** | `scout_ai_settings`, `scout_budgets`, `scout_search_queries`, `scout_fetch_attempts`, `scout_score_reasons`, `scout_contact_objections`, `scout_privacy_requests`, `scout_outreach_stage_events`, `scout_opportunities` |
| **Tabla retirada (1)** | `scout_provider_calls`: solo existía para sustituir a `fetch_attempts`, `search_queries` y `budgets`; mantenerla duplicaría datos |
| **Total** | **22 tablas** en el módulo (argenis-hub pasa de 51 a ~73) |
| **Se conservan** | Todas las columnas añadidas después de A6: vitalidad y tamaño, olas, solape horario, `discard_reason`, CV (`source_cv_uuid`, `cv_hash`, `proof_points`), `content_pruned_at`, RLS en todas las tablas |
| **Cambios de funcionalidad** | Ajustes de IA **editables desde la web** (`GET/PUT ai-settings`, además del selector por borrador) · presupuestos **editables** (`GET/PUT budgets`) · caché y efectividad de búsquedas en tabla propia (con familia, ola, estado y coste) · **varias oportunidades por contacto** (`POST/PATCH`) · historial completo de etapas · objeciones de personas separadas de las supresiones de empresas · solicitudes de privacidad en tabla propia (siguen gestionándose por comando) |
| **Lo que no cambia** | Privacidad y lista DGC siguen siendo comandos, sin pantalla (A3, A4) |
| **Coste** | ~8 migraciones/modelos/factories más, pantalla de ajustes de IA y edición de presupuestos: ~1-2 días. Entra en las semanas 1 y 3 sin mover el checkpoint de la semana 2 (A12) |

**Sustituye a:** A6 (22 → 14). **Amplía:** A2 (ajustes de IA de nuevo editables desde la web) y A3
(el registro de privacidad vuelve a una tabla propia).

Aplicación: spec US-10, FR-22, §7, §8, §9 · plan §0, §2, §3.1, §3.2, §3.5, §4 (modelo completo),
§4.1, §5, §6, §7, §8, §10, §11 · tasks T009-T014, T025, T029, T034, T040-T042, T048, T049, T054,
T056, T062-T066, T073.

### A16 — Datos públicos básicos de la empresa + correcciones de la verificación del 17-09-2026 (petición del operador)

**Petición:** «aplica los fixes; me parece bien extraer la URL y demás datos básicos públicos
disponibles sin que rompa el RGPD».

| Decisión | Detalle |
|---|---|
| **Datos de empresa (persona jurídica) — se extraen siempre** | Allowlist cerrada de `NORMATIVA-RGPD.md` §11: URLs públicas por tipo de página, denominación social, NIF/NIPC, forma jurídica, registro mercantil, ciudad/país de la sede, año de fundación, servicios, sectores, tecnologías, idiomas de la web, clientes/casos **como empresas**, programas de partners, buzones genéricos, URL del formulario de contacto, páginas de empresa en redes enlazadas desde su web. Base: considerando 14 RGPD (no cubre datos de personas jurídicas) + la empresa está **obligada a publicarlos** (LSSI art. 10.1 · DL 7/2004 art. 10) |
| **Límite** | Si la «empresa» es una **persona física** (autónomo, ENI, freelancer: nombre propio como denominación, NIF de persona física, web en primera persona) sus datos **son personales** → no se guardan los datos de identificación y el lead se descarta (`solo_freelancer`, FR-36) |
| **Datos de personas** | Sin cambios: solo decisores de leads A/B (FR-23 a FR-29) |
| **Nunca** | Teléfonos (FR-32), direcciones postales completas, fotos/logos, nombres de firmantes de testimonios, autores de blog o trabajadores, perfiles personales en redes, categorías especiales |
| **Firecrawl** | Cliente propio sobre **API v2** con `proxy: "basic"` + `storeInCache: false` (no dejar páginas con nombres en el índice compartido de Firecrawl) + verificar `metadata.proxyUsed == "basic"` en cada respuesta; si no coincide → se descarta el contenido, se registra y se desactiva Firecrawl en LeadScout |
| **Tavily** | `exclude_domains` con la denylist en la propia petición + `country` por ola; **nunca nombres de personas en las consultas** (la política de Tavily permite usar las consultas para mejorar el servicio) |
| **IA** | Antes de cualquier prompt: quitar emails, teléfonos y bloques de persona (testimonios, autores, firmas), además de los nombres de decisores ya ocultados |
| **RGPD — encargados** | Tavily es de **Nebius** (NL) desde feb. 2026, responsable AlphaAI Technologies Inc. (EE. UU.), SOC 2 Type II 2026 + ISO 27001 → pedir DPA. Transferencias a EE. UU.: DPF **más** cláusulas tipo (carta del EDPB del 31-07-2026 tras *Trump v. Slaughter*) |
| **EDPB 03/2026** | Es un **borrador** en consulta hasta el 30-10-2026, no la versión final |

**Coste:** 0 € (sin llamadas nuevas: todo sale de páginas ya descargadas). Unas 2 tareas nuevas
(T082, T083) y ajustes en T011, T039, T042, T057, T078 y OP-12.

Aplicación: spec §1, FR-13, FR-25, **FR-38**, **FR-39**, §7, §9 · plan §2, §3.1, §3.2 (pasos 4d y
5b), §4 (`scout_companies`, `page_type`), §7, §8, §9, §13 · research **R15** · `NORMATIVA-RGPD.md`
§1, N11, §6, **§11**, §9 · tasks T011, T039, T042, T057, T078, **T082**, **T083**, OP-12.

### A17 — Flujo de descubrimiento, países por ola, guard de LinkedIn y envío manual desde el buzón (petición del operador, 17-09-2026)

**Petición:** el operador valida el flujo «discovery → PT/ES ~60 % / resto del mundo ~40 % →
dominio canónico → ¿ya existe? → enriquecimiento → web → contacto/equipo → limpieza de datos
personales → IA → score → cumplimiento por país → canal permitido», pide la lista explícita de
países por ola y el guard de LinkedIn en las consultas, y decide **enviar los emails él mismo, uno
por uno, 10-15 al día, desde su buzón**, sin que el sistema envíe nada.

| Decisión | Detalle |
|---|---|
| **Reparto** | En **paralelo por peso**, no secuencial: ola 1 PT/ES ~60 % · ola 2 ~30 % · ola 3 ~10 % de las consultas de cada ejecución semanal; se reajusta con la métrica «consulta → lead A/B» por ola (T066) |
| **¿Ya existe?** | Dominio canónico ya en `scout_companies` **o** suprimido → STOP (sin enriquecimiento, sin IA). La búsqueda de Tavily ya se ha cobrado; lo que se ahorra es Firecrawl, IA y revisión. Excepción: una **oferta nueva** de una empresa existente no para: se enlaza y se re-puntúa (vacante = señal de compra) |
| **Países por ola** | Catálogo explícito en config (plan §3.2.1) con zona horaria IANA; el solape con Portugal se **calcula** con la zona horaria y la fecha (cambios de hora), no se fija a mano. Ucrania en la ola 3 (fuera de la UE) |
| **Guard de consultas** | Antes de llamar a Tavily se rechaza toda consulta que mencione redes profesionales (`linkedin`, `site:linkedin.com`, `xing`) o intermediarios de datos de contacto (Apollo, ZoomInfo, RocketReach, Lusha, Kaspr, Hunter, etc.); esos dominios van también en `exclude_domains` y en el `OutboundUrlGuard`. Nunca `tavily extract` |
| **Envío** | **Manual, fuera del sistema**, 10-15/día como máximo. El sistema prepara el borrador (con aviso art. 14 + baja) y el operador lo copia, lo envía y marca «enviado» con el canal usado (obligatorio para métricas, regla de decisión, retención de 12 meses y oposición) |
| **Buzón** | Dirección del **dominio propio** (p. ej. `@argenis.dev`) en un servicio con **DPA** (Google Workspace u otro), no una cuenta Gmail personal: la cuenta personal no tiene acuerdo de encargo y la identidad del remitente debe coincidir con la del aviso art. 14 y la política de privacidad. Gmail como cliente vale si envía como el dominio con Workspace |
| **Legalidad** | Enviar a mano **no cambia la ley**: LSSI art. 21 aplica a un solo email (AEPD A/00008/2019, apercibimiento por un único correo sin baja). `ChannelAdvisor` sigue mandando: ES sin email en frío; PT solo buzón genérico fuera de la lista DGC; olas 2-3 sin email hasta OP-26 |
| **Respuesta «BAJA»** | Se registra en el sistema el mismo día (oposición de persona o supresión de empresa) |

**Coste:** 0 €. Sin tablas nuevas.

Aplicación: spec FR-13, FR-16, FR-37 · plan §3.2 paso 0 + **§3.2.1** (diagrama y países), §8, §13 ·
tasks T006, T036, T042, T044, T062 · OP-27 (buzón con DPA) · `NORMATIVA-RGPD.md` N11.

### A18 — «Descubrimiento automático ≠ contacto automático», registro de envío, respuestas y reglas por país pendientes de verificación (petición del operador, 17-09-2026)

**Petición:** añadir la regla explícita *AUTOMATED DISCOVERY ≠ AUTOMATED OUTREACH* (descubrimiento,
enriquecimiento, scoring y borrador automáticos; **envío manual**), guardar un registro de cada
envío (lead, canal, estado, fecha, operador, plantilla), registrar la respuesta (interesado / no
interesado / baja), dar **precedencia absoluta** a la baja y **no convertir en lógica definitiva**
las reglas de contacto por país sin validar cada jurisdicción.

| Decisión | Detalle |
|---|---|
| **Principio (FR-40)** | Automático: descubrimiento → enriquecimiento → decisores/canales → score → borrador. **Humano:** revisar, enviar y registrar. El módulo **no tiene capacidad de envío**: sin Mail/Notification/SMTP, sin enviar formularios, sin automatizar LinkedIn. Lo comprueba un test de arquitectura |
| **Solo A/B** | El borrador solo se genera para leads Tier A/B (C y Descartar → 409) |
| **Score** | Sigue siendo **por reglas** (FR-8); la IA solo extrae señales que faltan. El diagrama del operador dice «AI score»: se interpreta como «score con señales extraídas con IA» |
| **Registro de envío (FR-41)** | `scout_outreaches`: `company_id` (lead) · `send_medium` enum(`email`, `contact_form`, `job_posting`, `linkedin_manual`) · `contact_channel_id` · `stage=sent` · `sent_at` · **`operator_id`** (usuario autenticado) · **`template_key` + `template_version`** (plantillas versionadas en config, sin tabla nueva) · `message_variant` · `outreach_kind` enum(`contractor_offer`, `employment_application`) · estado legal del canal en el momento del envío. Sustituye al antiguo enum `channel` |
| **Respuesta (FR-42)** | `reply_outcome` enum(`interested`, `not_interested`, `unsubscribe`) + `replied_at`. `interested` → etapa `positive` · `not_interested` → `lost` · `unsubscribe` → `do_not_contact` + supresión. Cada cambio con `operator_id` en `scout_outreach_stage_events` |
| **Baja con precedencia absoluta (FR-43)** | Una baja crea **supresión de la empresa en todos los canales** (y oposición de la persona si el buzón es nominativo). Prevalece sobre score, tier, ola, descubrimiento, importación CSV, alta manual, oferta nueva, re-score y cualquier nueva ronda de contactos: 409 en borrador y en `sent`, 0 enriquecimiento, 0 IA. **No se levanta desde la web**; solo por `lead-scout:privacy lift-suppression` si la propia empresa vuelve a contactar, con evidencia registrada |
| **Reglas por país (FR-44)** | Pasan a **config con estado legal**: cada regla (país × medio × tipo de buzón) tiene `decision` (allow/block), `legal_status` (`pending_verification` \| `verified`), `source_url`, `verified_at`, `verified_by`. **Todas empiezan en `pending_verification`**. Asimetría conservadora: las reglas que **bloquean** se aplican aunque estén pendientes; las que **permiten** no se aplican hasta estar verificadas (OP-15/OP-26). Con regla pendiente el canal se muestra «⚠️ verificación legal pendiente» y marcarlo como enviado exige una confirmación explícita del operador que queda registrada (`legal_ack_at`) |
| **Qué cambia en la práctica** | «PT buzón genérico → email con baja» y «ES → sin email» dejan de ser lógica definitiva: la primera no se recomienda hasta verificarla; la segunda se sigue aplicando por ser restrictiva. `NORMATIVA-RGPD.md` §3-§4 pasan a ser **notas de investigación**, no reglas |

**Coste:** 0 €. Sin tablas nuevas (columnas en `scout_outreaches` y `scout_outreach_stage_events`).
2 tareas nuevas (T084, T085).

Aplicación: spec §1, US-5, US-6, FR-15, FR-40 a FR-44 · plan §0, §1, §3.2.1, §4, §5, §7, §8, §12, §13 ·
tasks T006, T012, T061, T062, T063, **T084**, **T085** · `NORMATIVA-RGPD.md` §3, §4, §6.

### A19 — Buzón de envío por fases: Gmail personal en desarrollo, dominio propio en el outreach real (petición del operador, 17-09-2026)

**Petición:** matizar A17. Con Gmail personal se puede enviar a mano; Workspace + `hola@dominio`
separa identidad personal y negocio con documentación contractual; **cualquier proveedor de correo
del dominio propio** vale, no hace falta Workspace. En el MVP, Gmail personal para probar el flujo
y cambiar la dirección después. Enviar a mano o desde una cuenta personal **no elimina las
obligaciones legales**: el canal y la automatización son cuestiones distintas.

| Decisión | Detalle |
|---|---|
| **Sustituye en A17** | La fila «Buzón» («no una cuenta Gmail personal»). El resto de A17 sigue igual |
| **Fase de desarrollo y pruebas** | Gmail personal permitido para probar LeadScout → descubrimiento → score → borrador → copiar → enviar → marcar `sent`. Recomendado: pruebas con **direcciones propias o de prueba**, no con agencias reales |
| **Outreach real** | Buzón del **dominio propio** (p. ej. `hola@argenis.dev`) con **cualquier proveedor de correo empresarial** que ofrezca términos de encargo (Workspace es una opción, no un requisito). Se usa desde el **primer contacto que cuente para la regla de decisión** |
| **Si se envía a agencias reales desde Gmail personal** | Permitido, pero **no cambia la ley** (LSSI art. 21, lista DGC, art. 14, oposición) y queda registrado; riesgo adicional: sin acuerdo de encargo (art. 28) para los datos que quedan en el buzón y remitente distinto del de la política de privacidad → pregunta añadida al abogado (`NORMATIVA-RGPD.md` §7) |
| **Nuevo campo** | `scout_outreaches.sender_kind` enum(`personal_mailbox`, `business_domain`), obligatorio al marcar `sent` (valor por defecto en config). Sirve para segmentar métricas: el remitente cambia la entregabilidad y la tasa de respuesta, y mezclar ambos en la misma muestra contamina la regla de decisión |
| **Recomendación de corte** | Cambiar a `business_domain` **antes de bloquear la regla de decisión** (OP-6); si no, las métricas se leen por `sender_kind` |
| **Identidad** | En ambos casos: nombre completo como remitente, aviso art. 14 y baja con una dirección válida (LSSI 21.2) y enlace a `/privacy#prospeccion` |

**Coste:** 0 € en desarrollo; el buzón del dominio, cuando empiece el outreach real.

Aplicación: spec FR-16, FR-41, §7 · plan §4 (`sender_kind`), §8, §13 · tasks T006, T012, T063,
OP-27 · `NORMATIVA-RGPD.md` §6, §7.
