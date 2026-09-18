# Pricing, MCPs, proxies y control de costes

Quiero que el análisis incluya una investigación actualizada a **septiembre de 2026** sobre el pricing, límites y condiciones de uso de todas las herramientas y servicios propuestos.

No quiero asumir que una herramienta sigue teniendo el mismo pricing o límites que tenía anteriormente.

Para cada servicio analiza:

- pricing actual
- free tier
- créditos incluidos
- coste por búsqueda
- coste por crawl
- coste por página
- coste por millón de páginas cuando sea aplicable
- rate limits
- concurrency limits
- API limits
- costes de MCP si existe MCP oficial
- costes de almacenamiento
- costes de browser execution
- costes de proxy
- costes adicionales ocultos
- condiciones de uso relevantes
- posibilidad de self-hosting
- facilidad de migración a otra alternativa

Investiga específicamente, cuando sean relevantes:

- Tavily
- Firecrawl
- Crawl4AI
- Laravel AI SDK
- MCP servers / MCP integrations
- proveedores de búsqueda
- proveedores de proxies
- proveedores de residential proxies
- proveedores de datacenter proxies
- browser automation
- cualquier servicio adicional que propongas

No quiero asumir que usar MCP significa que el servicio sea gratuito.

Distingue claramente entre:

**MCP como protocolo/integración**

y

**el coste del proveedor/API que está detrás del MCP.**

---

# Cost model

Construye un modelo aproximado de coste para:

### Scenario A — MVP

100 agencias/mes.

### Scenario B — Small production

500 agencias/mes.

### Scenario C — Production

1.000 agencias/mes.

### Scenario D — Scale

5.000 agencias/mes.

Para cada escenario calcula aproximadamente:

```text
Search API
Crawler
LLM
Proxy
Database
Storage
Browser execution
Other infrastructure
--------------------
Estimated monthly cost
Cost per qualified lead
Cost per high-priority lead
```

No necesito una precisión matemática falsa.

Quiero rangos y supuestos explícitos.

---

# Crawling fallback architecture

Quiero evaluar una arquitectura de fallback similar a:

```text
                ┌──────────────┐
                │ Tavily Search│
                └──────┬───────┘
                       │
                       ▼
                ┌──────────────┐
                │ Lead Discovery│
                └──────┬───────┘
                       │
                       ▼
                ┌──────────────┐
                │  Firecrawl   │
                │   Primary    │
                └──────┬───────┘
                       │
                failure/quota/
                difficult page
                       │
                       ▼
                ┌──────────────┐
                │   Crawl4AI   │
                │  + FastAPI   │
                └──────┬───────┘
                       │
                 if necessary
                       │
                       ▼
                ┌──────────────┐
                │ Proxy layer  │
                └──────────────┘
```

Analiza si realmente necesito proxies para este use case.

No quiero añadir residential proxies simplemente porque "scraping = proxy".

Determina cuándo sería suficiente:

- direct HTTP
- normal requests
- Crawl4AI
- Playwright/browser
- datacenter proxy

y cuándo, si acaso, tendría sentido:

- residential proxy
- rotating proxy
- proxy pool

---

# Proxy analysis

Si propones residential proxies, compara proveedores actuales de septiembre de 2026 y analiza:

- precio por GB
- precio por IP cuando aplique
- geographic coverage
- EU coverage
- Portugal
- Spain
- Germany
- France
- UK
- USA
- Canada
- Lithuania
- Poland
- Romania
- Ukraine
- estabilidad
- latency
- session persistence
- rotation
- API integration
- compatibility with Crawl4AI
- compatibility with Playwright
- legal/compliance considerations

Pero **no añadas residential proxies como requisito por defecto**.

Quiero una arquitectura:

**proxy only when necessary.**

---

# Cost-aware crawler

Diseña el crawler para que primero utilice el método más barato y simple.

Por ejemplo:

```text
1. cached result
       ↓
2. existing database evidence
       ↓
3. sitemap.xml
       ↓
4. direct HTTP
       ↓
5. Firecrawl
       ↓
6. Crawl4AI
       ↓
7. browser rendering
       ↓
8. proxy only if justified
```

Evalúa si este orden es realmente óptimo y modifícalo cuando los datos indiquen otra estrategia.

No quiero gastar créditos de Firecrawl para páginas que puedo obtener fácilmente mediante HTTP.

---

# Adaptive crawling

Quiero que el sistema pueda decidir dinámicamente qué método utilizar.

Ejemplo:

```text
if cached:
    use cache

elif static HTML:
    direct HTTP

elif sitemap available:
    crawl sitemap/pages

elif JS-heavy:
    Firecrawl or Crawl4AI

elif browser required:
    browser

elif blocked:
    evaluate proxy requirement

else:
    mark extraction_failed
```

El sistema debe registrar:

- extraction_method
- extraction_attempts
- failure_reason
- HTTP status
- crawl duration
- API cost estimate
- proxy_used
- proxy_type
- timestamp

---

# MCP architecture

Analiza también si realmente merece la pena utilizar MCP en producción.

Quiero diferenciar:

### Development / AI-assisted workflow

MCP puede facilitar:

- exploration
- debugging
- development
- agent tooling

### Production backend

Evaluar si es mejor:

- llamar directamente a APIs
- usar SDKs
- utilizar queues/jobs
- implementar adapters propios

No quiero introducir MCP en el backend simplemente porque esté disponible.

Explica dónde MCP aporta valor real y dónde añade complejidad innecesaria.

---

# Provider abstraction

Diseña interfaces/adapters para evitar lock-in.

Por ejemplo:

```text
SearchProvider
    ├── TavilyProvider
    ├── AlternativeSearchProvider
    └── ...

CrawlerProvider
    ├── FirecrawlProvider
    ├── Crawl4AIProvider
    └── BrowserProvider

ProxyProvider
    ├── NoProxy
    ├── DatacenterProxy
    └── ResidentialProxy

LLMProvider
    ├── Provider A
    ├── Provider B
    └── ...
```

El Lead Scout no debería depender directamente de Tavily o Firecrawl en toda la aplicación.

Debe depender de interfaces/adapters.

Esto permitirá cambiar proveedores si:

- suben precios
- cambian pricing
- reducen cuotas
- cambian API
- aparecen mejores alternativas
- necesito self-hosting

---

# Budget-aware execution

Quiero poder definir un presupuesto mensual:

```text
MONTHLY_SEARCH_BUDGET
MONTHLY_CRAWL_BUDGET
MONTHLY_LLM_BUDGET
MONTHLY_PROXY_BUDGET
```

Y que el sistema pueda priorizar automáticamente.

Por ejemplo:

```text
High probability lead
    → more expensive enrichment

Low probability lead
    → cheap discovery only

Already high-confidence lead
    → don't crawl unnecessary pages
```

Quiero una estrategia de **progressive enrichment**:

```text
Cheap discovery
      ↓
Basic qualification
      ↓
Technical enrichment
      ↓
Commercial enrichment
      ↓
Contact discovery
      ↓
Deep analysis
```

El sistema no debe gastar dinero en leads que probablemente serán descartados.

---

# Evidence vs inference

Para cada dato distinguir:

```text
source = website
evidence = "Laravel development services"
confidence = 0.98
```

frente a:

```text
inference = "Likely accepts external developers"
confidence = 0.64
```

Nunca presentar una inferencia como hecho.

El scoring debe poder utilizar ambas cosas, pero con pesos diferentes.

---

# Lead Score + Confidence

No quiero un único número que mezcle todo.

Utiliza al menos:

```text
Technical Fit
Commercial Fit
Remote Fit
Communication Fit
Recurring Potential
Geographic/Contract Fit
Evidence Confidence

Overall Lead Score: 87/100
```

Ejemplo:

```text
Technical Fit:        94
Commercial Fit:       82
Remote Fit:           96
Communication Fit:    71
Recurring Potential:  88
Geographic Fit:       92

Overall Lead Score:   87

Evidence Confidence:  91
```

**87/100 es el Lead Score.**

**91/100 es la confianza de la evidencia.**

No deben confundirse.

Una agencia puede tener:

```text
Lead Score: 91
Evidence Confidence: 58
```

y debe requerir investigación adicional antes de considerarse un lead de alta prioridad.

Otra puede tener:

```text
Lead Score: 87
Evidence Confidence: 94
```

y tener evidencia mucho más sólida.

Quiero que el sistema sea capaz de explicar:

> Why 87?

y también:

> Why are we only 58% confident about this 91?