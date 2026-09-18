# Playbook de búsqueda — modo gap

Tavily para descubrir, cascada Firecrawl/Tavily para extraer, igual que
`cv-job-studio`. Lo que cambia: frescura de 72h, semillas en español, y el
rechazo duro de todo lo que no sea contrato (G2b).

## Parámetros Tavily

| Uso | Parámetros |
|---|---|
| Barrido de 24h | `search_depth: "advanced"`, `time_range: "day"`, `max_results: 10` |
| Alcance de 72h | `time_range: "week"` + filtrar por fecha visible (descartar >72h) |

Tavily no ofrece un rango de 72 horas. La combinación de arriba es la forma
honesta de aproximarlo: lo que no traiga fecha visible ni señal de "nuevo /
hoy" no entra a los bloques A y B.

## Semillas — español (principal)

1. `empleo remoto "atención al cliente" español sin experiencia teletrabajo`
2. `"soporte al cliente" remoto español contrato internacional teletrabajo`
3. `"asistente virtual" remoto español teletrabajo Europa Portugal España`
4. `"entrada de datos" OR "data entry" remoto español sin experiencia`
5. `"moderador de contenido" remoto español teletrabajo`
6. `"transcriptor" OR "transcripción" remoto español teletrabajo pago`
7. `"evaluador de IA" OR "entrenador de IA" remoto español`
8. `trabajo remoto administrativo español "desde casa" contrato UE`
9. `empleo teletrabajo español "chat" OR "email support" sin experiencia`

## Semillas — inglés (cobertura EU-remote)

10. `remote "customer support" Spanish speaker Europe entry level hiring`
11. `remote "chat support" OR "email support" Spanish bilingual EU remote`
12. `"AI data trainer" OR "AI rater" Spanish remote Europe apply`
13. `remote "virtual assistant" Spanish speaking Europe no experience`
14. `remote "content moderator" Spanish Europe hiring EU residents`
15. `remote "data annotation" Spanish Europe freelance contract`

## Cuadrante 4 — español para empresas internacionales (inglés técnico B1)

Añadido el 2026-08-25 por decisión del usuario, tras dos rondas vacías. Es el
cuadrante **prioritario** a partir de ahora: es el único donde el español
vuelve a ser un idioma escaso, porque el empleador no está en un país
hispanohablante.

**La distinción que hay que sembrar, y que decide el cap G4b:**

| Uso del inglés en el puesto | Efecto |
|---|---|
| **Inglés interno** — leer documentación, herramientas, base de conocimiento, hablar con el equipo; el cliente se atiende en español | **B1 basta**. Sin cap. Es el objetivo de este cuadrante |
| **Inglés de cara al cliente** — atender tickets o llamadas en inglés | Cap 55/65 según G4b. Fuera |

El candidato tiene **inglés técnico B1**: lee documentación, herramientas y
código sin problema. Eso es exactamente lo que estos puestos necesitan, y es
distinto de "inglés conversacional B2". **Nunca escribirlo como B2 ni como
"fluent"** — se siembra hacia puestos donde B1 basta, no se infla el nivel.

### Semillas del cuadrante

16. `remote "Spanish speaking" customer support hiring Europe EU permanent contract full-time`
17. `remote "Spanish market" support specialist SaaS "hire in Portugal" OR "employer of record" EU`
18. `"bilingual Spanish" support remote EMEA "English B1" OR "conversational English" OR "internal documentation"`
19. `remote support "Spanish" "LATAM market" OR "Iberia" hiring from Europe contract salary`
20. `"atención al cliente" español remoto empresa internacional contrato "desde cualquier país de la UE"`

Empresas y bolsas que sí hay que revisar en este cuadrante: NoDesk (etiqueta
`spanish`) · euremotejobs · Remotive · Jobgether · Himalayas · y las
`careers` de SaaS con mercado hispano (HubSpot, Squarespace, Dropbox, Doist,
Metricool, ActiveCampaign, Deel, Remote.com).

**Aviso de rendimiento medido el 2026-08-25:** el board `spanish` de NoDesk
tenía 24 vacantes y **ninguna** publicada en los últimos 7 días; la más
reciente tenía 20 semanas. Sembrar aquí **exige** filtrar por fecha antes de
gastar extract, o se pierde el presupuesto en anuncios de hace un año.

### Trampa de residencia propia de este cuadrante

El español remoto internacional se contrata sobre todo desde **Irlanda**
(HubSpot, Dropbox), **Estados Unidos** (Squarespace) o **solo España**
(Alphanumeric, Babel Profiles). Un puesto "full remote anywhere in **Spain**"
exige contrato y residencia españoles: para un residente en Portugal es tan
inalcanzable como uno de Grecia. **Tratarlo como `remote-country-locked`, no
como `remote-pt-es`** — la etiqueta compartida agrupa PT y ES y aquí engaña.

Lo que sí abre este cuadrante: empresas que contratan por **EOR** (Deel,
Remote.com, Oyster) o que aceptan **freelance con contrato**, porque el
candidato tiene recibos verdes en Portugal. Sembrar con `employer of record`,
`EOR`, `hire anywhere in the EU`, `long-term freelance contract`.

## Semillas por portal (`site:`)

Generales remoto: `site:weworkremotely.com` (categoría Customer Support) ·
`site:remoteok.com` · `site:remotive.com` · `site:remote.co` ·
`site:workingnomads.com` · `site:jobspresso.co` · `site:nodesk.co` ·
`site:europeremotely.com` · `site:jobgether.com` · `site:justremote.co`

España/Portugal: `site:infojobs.net` (filtro teletrabajo) ·
`site:es.indeed.com` · `site:pt.indeed.com` · `site:talent.com` ·
`site:jobatus.es` · `site:net-empregos.com`

BPO que contratan hispanohablantes en remoto UE: Teleperformance ·
Concentrix · Foundever (antes Sitel/Majorel) · TaskUs · TELUS Digital.
Buscar sus páginas de carreras directamente — sus vacantes suelen no estar
bien indexadas en agregadores.

**Nunca** sembrar locales de Asia (misma política de `geography_policy` que
career mode).

## Palabras que orientan la búsqueda al contrato

G2b rechaza colas de tareas, encuestas y clics. Conviene además **sembrar
hacia el contrato**, no solo filtrarlo después — se gasta menos presupuesto:

- Añadir a las semillas: `contrato` · `jornada completa` · `media jornada` ·
  `indefinido` · `nómina` · `full-time` · `permanent` · `employment
  contract` · `salary`.
- Evitar en las semillas: `microtareas` · `tareas` · `gana dinero` ·
  `encuestas` · `regístrate` · `freelance marketplace` · `por proyecto` ·
  `gig`. Traen sobre todo lo que G2b va a descartar.
- `freelance` **sí** se siembra, pero emparejado: `freelance contrato`,
  `freelance jornada`, `long-term freelance` — no `freelance` a secas.

**No sembrar** plataformas de crowdsourcing ni de micro-tareas (Appen,
Outlier, Clickworker, Toloka, Remotasks, paneles de encuestas). Están fuera
de alcance por G2b desde el 2026-08-25; buscarlas es gastar consultas en
resultados que se van a descartar.

## Cascada de extract

Idéntica a `.claude/skills/cv-job-studio/references/search-playbook.md`:
Firecrawl scrape → `tavily_extract` con `extract_depth: "advanced"` → boost
de raw content → pivote a la página de carreras de la empresa. Detenerse en
el primer paso que dé texto usable.

**Orden obligatorio, distinto al de career mode:** correr **G4 anti-fraude
sobre el título y el snippet ANTES de cualquier extract**. Una oferta con
señal clara de fraude no merece presupuesto de Firecrawl — y abrir su página
tampoco aporta nada bueno. Anotar `scam_gate: <motivo>` y seguir.

Después: G1 → G2 → **G2b (modelo de contratación)** → G3 → G5 → cascada de
extract solo sobre las que sobreviven →
H/S/D.

## sequentialthinking

Al menos tres veces por corrida:

1. Antes de fijar las semillas — qué roles priorizar según lo que ya haya en
   el caché y qué falló la corrida anterior.
2. Después del descubrimiento en crudo — repasar el gate anti-fraude oferta
   por oferta antes de gastar extract. **Este es el checkpoint que no se
   salta**; es el que evita recomendarle una estafa a alguien desempleado.
3. Antes de la tabla final — verificar cada `match_score` contra H/S/D y que
   la normalización salarial sea coherente (una tarifa horaria convertida
   nunca se presenta como sueldo garantizado).

## Rendimiento bajo

Si tras gates quedan menos de 3 candidatos: ampliar a 7 días (avisando que
se relajó el filtro de 72h), añadir 4–6 semillas de portales BPO y de
carreras de empresa, y rehacer el descubrimiento **una sola vez**. Anotar
`freshness: relaxed_7d` en las notas de la corrida.
