## Contexto

Quiero analizar y evolucionar mi módulo/proyecto actual llamado **Lead Scout**.

El objetivo del módulo es construir un pipeline automatizado para **descubrir, investigar, calificar y extraer agencias de software/web que puedan contratarme como desarrollador externo, white-label o subcontractor**, especialmente para proyectos recurrentes con **Laravel + Vue.js**.

Quiero trabajar **100% remoto desde Portugal**, buscando oportunidades globales. No quiero limitar el sistema a España o Reino Unido.

Antes de proponer cambios, **analiza primero el código existente del Lead Scout Module y mi CV ATS en Markdown**. Usa ambos como fuente principal para determinar:

1. Mi perfil técnico real.
2. Mi stack y seniority.
3. Qué tipo de proyectos puedo asumir.
4. Qué tipo de agencias tienen mejor encaje.
5. Qué señales debería buscar el crawler/research pipeline.
6. Qué criterios deberían entrar en el scoring.
7. Qué partes de mi arquitectura actual deberían mantenerse, modificarse o reemplazarse.

---

# Objetivo comercial

Quiero encontrar agencias que puedan utilizarme como:

- external developer
- subcontractor
- white-label developer
- development partner
- overflow developer
- staff augmentation
- project-based developer
- recurring remote developer

El objetivo NO es competir principalmente por marketplaces de freelance ni buscar clientes finales.

El target principal son **agencias de software, web agencies, digital agencies, development studios y consultoras pequeñas/medianas** que puedan necesitar capacidad adicional de desarrollo.

Especialmente interesantes:

- Laravel agencies
- PHP agencies
- Vue.js agencies
- Laravel + Vue agencies
- agencies que utilizan Inertia.js
- agencies que trabajan con SaaS
- agencies con proyectos recurrentes
- agencies que contratan freelancers/subcontractors
- agencies que muestran señales de crecimiento
- agencies que están buscando developers
- agencies que aceptan partners externos
- agencies con exceso de capacidad o necesidad temporal de developers

---

# Perfil de agencia objetivo

Prioridad inicial:

**5–50 empleados.**

Quiero evitar en principio:

- freelancers individuales
- agencias de 1–4 personas salvo que exista una señal comercial muy fuerte
- grandes consultoras corporativas
- empresas de producto que sólo buscan contratación interna
- empresas que exigen presencialidad
- empresas que no aceptan contractors externos

Sin embargo, no descartes automáticamente una agencia fuera de estos parámetros si existe una señal excepcional de fit. En ese caso, explica por qué.

---

# Geografía

Quiero trabajar desde Portugal y buscar globalmente.

No quiero una estrategia limitada a:

- Spain only
- UK only

Quiero investigar progresivamente:

### Prioridad europea

- Portugal
- Spain
- France
- Germany
- Netherlands
- Belgium
- Luxembourg
- Ireland
- Austria
- Italy
- Greece
- Poland
- Czech Republic
- Slovakia
- Hungary
- Romania
- Bulgaria
- Lithuania
- Latvia
- Estonia
- Slovenia
- Croatia
- Ukraine
- otros países europeos relevantes

Incluye también países del **Schengen Area** cuando tenga sentido, pero no asumas que Schengen implica automáticamente permiso laboral, contratación o equivalencia contractual.

### Otras regiones

Investiga también:

- United Kingdom
- United States
- Canada

Para USA/Canada quiero identificar específicamente agencias que:

- trabajen remotamente
- acepten contractors internacionales
- acepten colaboración en inglés
- no requieran presencia física
- puedan trabajar con un developer ubicado en Portugal

Mi inglés hablado es aproximadamente **B1**, pero tengo capacidad para **lectura y escritura técnica en inglés**.

Por tanto, el sistema debe valorar especialmente oportunidades donde:

- la comunicación sea principalmente escrita
- Slack/email/GitHub/Jira/Linear/etc. sean importantes
- no sea imprescindible un nivel C1/C2 de inglés hablado

No quiero excluir automáticamente una agencia por idioma. Quiero que el sistema estime el riesgo de comunicación y lo incluya en el scoring.

---

# Matching

Quiero implementar un sistema de matching con un **score de 0–100**.

Objetivo:

**Lead score >= 85 = high-priority lead.**

Pero no quiero un scoring arbitrario.

Quiero que diseñes el scoring basándote en señales observables.

Por ejemplo:

### Technical fit

- Laravel
- PHP
- Vue.js
- Inertia.js
- Laravel AI SDK
- APIs
- SaaS
- PostgreSQL/MySQL
- REST
- integrations
- testing
- Docker
- CI/CD
- cloud
- existing Laravel applications

### Commercial fit

- acepta subcontractors
- acepta freelancers
- external developers
- white-label development
- staff augmentation
- recurring work
- long-term collaboration
- project overflow
- hiring developers
- growing team
- multiple active projects

### Company fit

- 5–50 employees
- agency/studio/consultancy
- remote-first
- distributed team
- Europe-friendly
- contractor-friendly

### Communication fit

- English required
- written communication
- asynchronous work
- English-speaking team
- no mandatory native-level English

### Geographic/contract fit

- remote
- international contractors
- EU contractors
- Portugal-compatible
- CET/WET-friendly
- no relocation required

Diseña una fórmula de scoring explicable y auditable.

Cada score debe poder responder:

> "¿Por qué este lead obtuvo 91 y este otro 67?"

No quiero un simple LLM score sin explicación.

---

# Recurring work

Quiero dar especial importancia a la posibilidad de **trabajo recurrente**.

Diferencia entre:

1. One-off project
2. Short project
3. Project overflow
4. Monthly retainer
5. Long-term subcontracting
6. Staff augmentation
7. Ongoing development partnership

Quiero que el sistema detecte estas señales y las incorpore al score.

Idealmente quiero encontrar agencias donde pueda convertirse en:

> "the developer they call when they need additional Laravel/Vue capacity."

---

# Pipeline técnico

Estoy considerando una arquitectura basada en:

### Backend

- Laravel
- Laravel AI SDK
- FastAPI cuando tenga sentido

### AI / orchestration

Quiero evaluar **Laravel AI SDK** como capa principal de orquestación de agentes/workflows.

Analiza si en septiembre de 2026 sigue siendo una buena decisión o si existe una alternativa mejor.

No quiero añadir frameworks innecesarios.

---

# Search

Estoy considerando:

**Tavily**

para:

- web search
- discovery
- finding agencies
- finding relevant pages
- search queries específicas

Analiza si Tavily es adecuado para este caso.

También quiero evaluar:

- search APIs alternativas
- Google/Bing/Bright Data u otras opciones si aportan ventajas
- coste
- rate limits
- calidad
- cobertura europea
- facilidad de integración

---

# Crawling / extraction

Quiero utilizar:

**Firecrawl**

para:

- scraping
- crawling
- extracting structured content
- websites de agencias
- páginas About
- Careers
- Services
- Contact
- Team
- Case Studies

Pero quiero un fallback cuando se agoten las cuotas.

Estoy considerando:

**FastAPI + Crawl4AI**

como segunda capa/fallback.

Analiza si esta arquitectura tiene sentido:

Tavily → discovery

↓

Firecrawl → primary extraction

↓

Crawl4AI → fallback extraction

↓

LLM → classification / enrichment

↓

Scoring engine

↓

Lead database

También analiza si conviene introducir:

- Playwright
- browser automation
- direct HTTP fetching
- trafilatura
- BeautifulSoup
- sitemap.xml
- robots.txt
- structured data
- DNS/domain checks
- LinkedIn only when legally/technically appropriate
- job boards
- GitHub
- company directories

No quiero scraping innecesario. Prioriza fuentes públicas, estables y relevantes.

---

# Arquitectura que quiero que evalúes

Propón una arquitectura concreta para septiembre de **2026**, teniendo en cuenta:

- coste
- simplicidad
- mantenibilidad
- escalabilidad
- rate limits
- observabilidad
- retries
- caching
- deduplication
- enrichment
- LLM costs
- crawling costs
- search costs
- fallback strategies

Quiero evitar overengineering.

Diseña el pipeline desde:

**Discovery → Crawl → Extract → Normalize → Enrich → Classify → Score → Deduplicate → Store → Review → Outreach**

---

# Data model

Propón las entidades/tablas necesarias.

Como mínimo considera:

- agencies
- domains
- contacts
- sources
- crawls
- extracted_pages
- technologies
- agency_signals
- jobs
- scoring_results
- scoring_reasons
- outreach
- opportunities

Quiero poder almacenar evidencia.

Ejemplo:

```text
score = 91

reasons:
+20 Laravel detected
+15 Vue detected
+10 agency
+10 11–50 employees
+15 accepts subcontractors
+10 recurring work signal
+6 remote
+5 Europe compatible
```

Pero **no uses esos pesos como definitivos**. Diseña los pesos después de analizar mi CV y el mercado.

Cada señal importante debe tener:

- source URL
- extracted text/evidence
- timestamp
- confidence
- extraction method

---

# AI architecture

Quiero saber qué debería hacer el LLM y qué debería hacer código determinista.

Por ejemplo:

### Deterministic

- domain normalization
- deduplication
- employee range parsing
- country detection
- URL canonicalization
- scoring arithmetic
- cache
- retries
- rate limiting

### LLM

- agency classification
- technology inference
- commercial signal extraction
- subcontracting detection
- recurring-work detection
- communication-fit analysis
- summarization
- lead reasoning

Quiero minimizar llamadas LLM innecesarias.

---

# Discovery strategy

Diseña las queries de búsqueda.

No quiero solamente:

> Laravel agencies

Quiero query families como:

- Laravel agency Portugal
- Laravel development studio Germany
- Laravel Vue agency France
- Laravel subcontractor Europe
- Laravel white label development
- Laravel development partner
- PHP agency outsourcing
- Laravel freelance subcontractor agency
- Vue Laravel development studio
- Laravel agency hiring developer
- Laravel agency remote developer
- Laravel agency external developer
- Laravel staff augmentation
- etc.

Pero quiero que generes una estrategia sistemática para producir cientos/miles de queries sin duplicación excesiva.

---

# Lead qualification

Diseña un sistema para clasificar:

### Tier A
Muy buen matching técnico + comercial.

### Tier B
Buen matching pero falta alguna señal.

### Tier C
Matching parcial.

### Reject
No es realmente una agencia objetivo.

IMPORTANTE:

No quiero que Tier A/B/C sea simplemente otro score arbitrario.

Explica las reglas.

---

# Output esperado

Después de analizar mi código y mi CV, quiero que produzcas:

## 1. Perfil comercial

Describe exactamente qué tipo de agencia debería buscar.

## 2. Ideal Customer Profile

Define el ICP.

## 3. Matching criteria

Lista las señales.

## 4. Scoring model

Diseña un score 0–100 explicable.

## 5. Pipeline architecture

Dame la arquitectura recomendada.

## 6. Tech stack

Dime qué stack utilizarías en septiembre de 2026 y por qué.

## 7. Tavily vs alternativas

Compara opciones.

## 8. Firecrawl vs Crawl4AI

Explica cuándo utilizar cada uno.

## 9. AI orchestration

Evalúa Laravel AI SDK frente a alternativas.

## 10. Database schema

Propón tablas, relaciones e índices.

## 11. Crawling strategy

Diseña discovery + crawl + extraction + fallback.

## 12. Deduplication

Explica cómo evitar duplicados por:

- domain
- company
- agency
- subsidiaries
- multiple sources

## 13. Cost control

Diseña una estrategia para minimizar:

- search API costs
- crawling costs
- LLM costs

## 14. MVP

Define el MVP más pequeño que permita validar el modelo comercial.

## 15. Phase 2

Qué añadir después de validar.

## 16. Phase 3

Qué automatizar cuando existan suficientes leads.

## 17. Example

Dame 5 ejemplos hipotéticos de agencias y muestra cómo el sistema calcularía su score y qué evidencia necesitaría.

---

# Restricciones importantes

1. No hagas overengineering.
2. No asumas que más agentes = mejor arquitectura.
3. Prioriza código determinista cuando sea suficiente.
4. El scoring debe ser explicable.
5. La evidencia debe almacenarse.
6. El sistema debe tolerar fallos de APIs.
7. Debe existir fallback de crawling.
8. Debe existir caching.
9. Debe existir deduplicación.
10. Debe poder ejecutarse inicialmente con un presupuesto pequeño.
11. Debe poder escalar posteriormente.
12. No quiero depender exclusivamente de una API de búsqueda.
13. No quiero depender exclusivamente de un crawler SaaS.
14. Respeta robots.txt, términos de servicio y restricciones legales aplicables.
15. No diseñes mecanismos para evadir anti-bot, CAPTCHAs o controles de acceso.
16. No inventes datos sobre agencias.
17. Toda señal comercial importante debe tener evidencia verificable.
18. Separa claramente hechos extraídos de inferencias del modelo.

---

# Importante sobre mi CV

Mi CV ATS Markdown es la fuente principal para determinar mi perfil.

**No inventes skills que no aparezcan en el CV.**

Si encuentras tecnologías que deberían ser relevantes pero no aparecen en el CV, márcalas como:

> "potential skill / requires verification"

No las utilices como capacidades confirmadas.

---

# Resultado final

Quiero que actúes como:

- software architect
- AI systems architect
- lead-generation systems designer
- B2B sales automation analyst

pero manteniendo una filosofía de:

**simple → measurable → evidence-based → scalable**

Primero analiza lo que ya existe.

Después identifica gaps.

Después propone arquitectura.

Después propone implementación.

**No quiero que empieces escribiendo código.**

Quiero primero un análisis técnico/comercial completo y una arquitectura recomendada para septiembre de 2026.

Dos cambios que considero especialmente importantes

1. No fijaría 85+ como verdad absoluta.
Lo dejaría como threshold configurable. El sistema debería guardar score=87 y además confidence=0.72, porque no es lo mismo una agencia con 87 puntos respaldados por cinco fuentes que una con 87 inferidos por el LLM a partir de una sola página.

2. Separaría fit score de buying signal.

Por ejemplo:

Technical Fit:       94
Commercial Fit:      82
Remote Fit:          96
Communication Fit:   71
Recurring Potential: 88
Evidence Confidence: 91

Overall Lead Score: 87

Eso te va a resultar muchísimo más útil que un único número mágico.

Y conceptualmente, tu arquitectura inicial Tavily → Firecrawl → Crawl4AI fallback → LLM → scoring determinista → DB me parece una dirección razonable. Pero antes de decidir definitivamente entre Laravel AI SDK, otros orquestadores y la combinación exacta de Tavily/Firecrawl/Crawl4AI, sí haría una revisión actualizada de septiembre de 2026, porque son componentes cuyo pricing, APIs, capacidades y límites pueden cambiar bastante.
