# argenis.dev vs. Pipeline B2B — Análisis de brecha y rediseño de posicionamiento

> Fecha: 2026-09-10
> Entradas: `specs/ANALISIS-ARGENIS-DEV.md`, `specs/LeadScout-MODULE/*` (5 docs), código de `src/`, research web (Tavily) sobre sitios de consultores/freelancers que sí venden.

---

## 1. Qué dicen tus specs (y dónde se contradicen)

Los cinco documentos de `LeadScout-MODULE` convergen en lo mismo. Eso es señal fuerte: cuatro modelos distintos, misma conclusión.

**Consenso unánime (4/4 docs):**

| Punto | CHATGPT | CLAUDE | GEMINI | GROK |
|---|---|---|---|---|
| Canal #1 = agencias 5–50 empleados | ✅ | ✅ | ✅ 9/10 | ✅ |
| Vender "capacidad", no "soy developer" | ✅ | ✅ | ✅ | ✅ |
| Canal infravalorado = otros freelancers / fractional CTOs | ✅ | ✅ | ✅ 9.5/10 | ✅ |
| Upwork = mal canal para tu perfil | ✅ | ✅ | ✅ | ✅ |
| Buying signals > volumen de contactos | ✅ | ✅ | ✅ | ✅ |
| Stack del módulo: Laravel, no FastAPI | ✅ | — | ✅ | — |

**Divergencia real en las tasas esperadas** — importante porque de aquí sale tu ROI:

- CHATGPT-MAIN: 5–20% de respuesta sobre 100 agencias.
- CLAUDE: 5–10% de respuesta, 300 mensajes → 2–4 clientes recurrentes.
- GEMINI: corrige a la baja explícitamente — **4–8% real en frío sin señal previa**.
- GROK: 5–15% "en el mejor de los casos".

Toma **GEMINI como base de planificación** (es el único que corrige el optimismo de los otros) y CLAUDE como techo. Planifica con 5%.

**Contradicción de perfil que tienes que resolver antes de escribir una sola línea de copy:**

- `PIPELINE-FREELANCER-CLAUDE.md` y `GROK` te describen como: 4 años Laravel, inglés B1, Vue/TS 3 meses, **desempleado**, mid-level, "no te vendas como Full-Stack todavía".
- `ANALISIS-ARGENIS-DEV.md` te puntúa **9/10 en nivel técnico**, con testimonios, clientes internacionales, formación corporativa de IA a empresas españolas, y relaciones de 4 y 8 años con clientes.

Son dos personas distintas. Y tu web ya arrastra esa inconsistencia: dice `12+ projects shipped` y "4+ years", pero tus testimonios describen relaciones plurianuales. El doc de CHATGPT ya te lo señaló y sigue sin resolverse.

**Decisión requerida:** ¿cuántos años de experiencia profesional real tienes en total? Todo el posicionamiento cuelga de eso. Si Servispin es una relación de 8 años, tu web se está vendiendo por debajo de lo que eres.

---

## 2. Diagnóstico de argenis.dev — con evidencia del código

Tu intuición es correcta. Lo verifico contra `src/`:

### 2.1 La navegación es de portfolio, no de venta

`src/components/layout/Header.astro:7-13`

```js
{ href: '/',                 label: 'Home' },
{ href: '/projects/',        label: 'Projects' },
{ href: '/services/',        label: 'Services' },
{ href: '/blog/',            label: 'Blog' },
{ href: '/about/',           label: 'About' },
{ href: '/contact-support/', label: 'Support' },
{ href: '/#contact',         label: 'Contact' },
```

Tres problemas concretos:

1. **`Projects`** — nomenclatura de portfolio. Tighten lo llama `/results`. La diferencia no es cosmética: "projects" invita a mirar; "results" invita a comprar.
2. **`Support` en la navegación principal** — es lenguaje *post-venta*. Un CTO que llega en frío ve un menú donde el soporte compite con la conversión. Eso pertenece al footer.
3. **Siete ítems de nav, dos de ellos apuntando a contacto** (`/contact-support/` y `/#contact`). La investigación de CTA es consistente en esto: páginas con un solo foco de conversión superan a las multi-oferta, en algunos casos por >200%.

### 2.2 Los servicios están nombrados por tecnología, no por problema

`src/content/services/`:

```
laravel.md          → "Laravel Backend Development"
nextjs.md           → "Next.js Frontend Development"
astro-development.md→ "Astro Website Development"
devops.md           → "DevOps & Cloud Infrastructure"
ai-automation.md    → "AI Automation & Integration"
web-development.md  → "Custom Web Development"
```

Esto es un **inventario de capacidades**, no un catálogo de ofertas. Ningún fundador de agencia busca "Astro Website Development". Busca "no puedo entregar este proyecto a tiempo".

Justo es decir que el frontmatter **ya tiene el campo `problem:`** y está bien escrito. La materia prima existe; el problema es que el *título* — que es lo único que se ve en el menú y en las tarjetas — sigue siendo el nombre del framework.

### 2.3 El hero habla a developers, no a compradores

`src/components/home/Hero.astro` renderiza snippets de `routes/web.php` e `@inertiajs/vue3` como elemento visual, junto a `12+ projects shipped`.

Eso es señalización *developer-a-developer*. Impresiona a otro programador. A un Delivery Director de una agencia de 18 personas no le dice nada sobre si le resuelves su problema de capacidad este trimestre.

Y el título del sitio (`index.astro`):

```
Argenis — Laravel Developer & AI Automation Specialist
```

Describe **lo que eres**. Tighten y Spatie describen **qué te pasa a ti si les contratas**.

### 2.4 No existe la página que tu estrategia entera necesita

Tu plan de negocio es *aliarme con agencias B2B en España y LatAm*. Tu sitio no tiene:

- ❌ `/agencies` o `/partners` — la landing donde aterriza cada mensaje de outbound
- ❌ `/es` — target España + LatAm, sitio 100% en inglés
- ❌ Case studies con estructura problema → plan → resultado
- ❌ Una oferta productizada de precio y alcance cerrados

Estás a punto de construir un motor de leads (LeadScout) que dirigirá tráfico a un sitio que **no tiene página de destino para ese tráfico**.

### 2.5 Hallazgos secundarios

- `src/content/projects/` contiene tres placeholders (`aurora-saas`, `helix-commerce`, `lattice-design-system`) con `liveUrl: 'https://example.com'` y un `demoVideo` que apunta a un rickroll. No se renderizan — `projects/index.astro` y `FeaturedWork.astro` consumen la API del CRM — pero conviene borrarlos antes de que alguien los encuentre en el repo o de que un cambio de fuente los saque a producción.
- Tus clientes reales (Servispin, AquaShield, Vidula) sí están en `Testimonials.astro` y `Experience.astro`. Están infrautilizados: son tu mejor activo comercial y viven en secciones secundarias.

---

## 3. Research: cómo se estructuran los sitios que sí venden

Mapeé y extraje la arquitectura de referencias reales del mismo nicho.

### 3.1 Tighten (tighten.co) — el molde exacto para tu caso

Arquitectura de información completa:

```
/
/services
  /services/discovery              ← oferta de entrada, ticket bajo
  /services/application-development
  /services/embedded-teams         ← "capacidad" productizada
  /services/codebase-upgrades      ← el nicho legacy
  /services/engineering-leadership ← expansión de cuenta
/results
  /results/sweetwater
  /results/genentech
  /results/chicago-botanic-garden
  /results/the-boutique-hub
  /results/adopt-a-storm-drain
/insights   (blog)
/team  /about  /manifesto  /careers  /contact
```

**Lo que hay que copiar, punto por punto:**

**a) `/services/embedded-teams` — es literalmente el "overflow developer" de tus specs, productizado.** Su H1 no es un stack, es un resultado:

> # Add capacity to your team

Y el cuerpo cierra el alcance con números, no con adjetivos:

> "Each embedded team consists of a Lead Programmer and a Staff Programmer under the guidance of a Project Manager. We book embedded teams in **8-week cycles**, during which the assigned team(s) are fully dedicated to your project **four days per week**."

Ciclos de 8 semanas. 4 días/semana. Roles nombrados. **El precio por hora desaparece de la conversación** — exactamente lo que te recomienda `PIPELINE-FREELANCER-CLAUDE.md`. Vendes un bloque, no horas.

**b) `/services/codebase-upgrades` segmenta la misma oferta por tipo de comprador**, con un párrafo para cada uno:

> "If you're a **startup company**, we can prep your MVP for scale…
> If you're a **SME** undergoing digital transformation, we can convert your legacy PHP app to modern Laravel + Vue…
> If you're a **solo SaaS founder**, we can update your dependencies…
> **If you're not the one in charge**, but you know the codebase could use attention from an expert third-party, we can help you **pitch our service to your boss**."

Ese último párrafo es el más inteligente de toda la página: le da munición al campeón interno que no tiene poder de firma. Nadie más hace eso.

**c) Cada página de servicio termina en: bloque `Disciplines` + testimonio con foto/cargo/empresa + case study enlazado + formulario.** Siempre el mismo orden. El formulario incluye `How did you hear about us? (Required)` — atribución de canal obligatoria. Si construyes LeadScout, ese campo es lo que cierra el bucle de medición.

**d) `/results/sweetwater` — anatomía del case study.** Encabezado tripartito, literal:

```
The Challenge  → As a distributed team, figure out how best to deliver technical
                 leadership and programming capacity to an entirely in-person team…
The Plan       → Pay a visit to Sweetwater's headquarters in Fort Wayne, IN…
The Outcome    → We've firmly established a long-standing partnership…
```

Y el testimonio no dice "gran trabajo". Dice la objeción vencida:

> "We have prided ourselves on doing everything **100% in-house**… but after meeting the folks at Tighten… we **stepped out on a ledge** and decided to make them part of the Sweetwater team."

Compáralo con el tuyo, que ya es igual de bueno y está enterrado:

> "Our old site was just a phone number and a logo. What we actually needed was organization — Argenis built us a real booking system."

Ese testimonio de Servispin **es un case study completo comprimido en una frase**. Tiene el antes, el dolor y el después.

### 3.2 Spatie (spatie.be) — el modelo de autoridad

IA mucho más plana: `/web-development` (**una sola** página de servicio), `/open-source`, `/products`, `/courses`, `/blog`, `/guidelines`, `/about-us`.

Spatie **no hace outbound**. Su canal es open source + contenido. No es tu modelo hoy — requiere años de acumulación — pero hay dos cosas robables ya:

**a) La sección "A good match?" — cualificación explícita en la propia página de venta:**

```
What we do best              Not our cup of tea
- All things Laravel         - WordPress themes
- Custom frontend components - Cutting corners
- Building APIs              - Free mockups to win a job
- AI-powered features        - "Just execute the briefing"
```

Decir a quién **no** sirves sube el ticket y filtra basura antes de la llamada. Es la aplicación más barata del principio de especialización.

**b) Cómo posicionan la IA** — relevante porque tu diferenciador declarado es "Laravel + AI":

> "None of it ships without a human in front of it. Every result goes through a senior Laravel developer, who reviews it as carefully as they'd review a colleague's pull request."

No venden "usamos IA". Venden **IA + criterio senior**. Encaja con el dato que tus propios specs citan tres veces: 84% de developers usa IA pero **46% desconfía de sus outputs**. El valor está en quien la revisa.

### 3.3 Consenso de la escuela de posicionamiento (Jonathan Stark / Philip Morgan)

La fórmula que Stark repite en su coaching:

> `Soy [DISCIPLINA] que ayuda a [MERCADO OBJETIVO] con [PROBLEMA CARO], a diferencia de mis competidores [DIFERENCIA ÚNICA].`

Y el testimonio más útil de su web es de otro developer que hizo exactamente lo que tú estás a punto de hacer:

> "He construido mi web personal donde hablo de mi cliente ideal, sus dolores y objetivos… me ha permitido **posicionarme por encima de otros developers**, ganar más respeto de los clientes y ganar proyectos **solo por el posicionamiento y la web**."

De su programa "Cold Start", lo aplicable a ti: microsite de una página con headline fuerte + lead magnet visible + prueba social. En tu caso, el "microsite" es `/agencies`.

### 3.4 El patrón de las páginas white-label para agencias

Todos los proveedores white-label que revisé (DoodleWeb, Black Kite, Scepter, KrishaWeb) usan **el mismo esqueleto**. Es un género con convenciones fijas:

1. Promesa de invisibilidad — "your client never sees us"
2. Proceso numerado de 4 pasos (brief → build en staging neutral → tú revisas → tú presentas)
3. Garantía de no-solicitación — *"a signed non-solicit keeps the account yours"*
4. Argumento de capacidad — "your in-house team has other work; a partner's job is your overflow"
5. CTA específico de partnership, distinto del de cliente final

El punto 3 es el que más fricción elimina y el que casi ningún freelancer individual menciona. **El miedo #1 de una agencia al subcontratar a un freelance es que le robes el cliente.** Ponerlo por escrito en la página lo desactiva antes de la primera llamada.

---

## 4. La brecha, en una tabla

| Elemento | argenis.dev hoy | Referencias que venden | Impacto |
|---|---|---|---|
| H1 | "Laravel Developer & AI Automation Specialist" | "Add capacity to your team" | 🔴 Alto |
| Prueba visual del hero | Snippets de `routes/web.php` | Resultado de negocio + prueba social | 🔴 Alto |
| Nombres de servicio | Por tecnología (Laravel, Next.js, Astro) | Por problema (upgrades, embedded, discovery) | 🔴 Alto |
| Sección de trabajo | `/projects` — showcase | `/results` — challenge/plan/outcome | 🔴 Alto |
| Página para agencias | ❌ no existe | Género completo y estandarizado | 🔴 Crítico |
| Cualificación (a quién NO sirves) | ❌ | "Not our cup of tea" | 🟡 Medio |
| Oferta de precio/alcance cerrado | ❌ | Ciclos de 8 semanas, discovery de entrada | 🟡 Medio |
| Idioma para España/LatAm | Solo inglés | — | 🟡 Medio |
| Foco de CTA | 2 rutas de contacto + "Support" en nav | Un solo formulario repetido | 🟡 Medio |
| Atribución de canal en el form | ❌ | "How did you hear about us? (Required)" | 🟢 Bajo pero barato |
| Case studies | Datos de CRM: stack + demo | Narrativa con objeción vencida | 🔴 Alto |

---

## 5. Qué haría, en orden

### Fase 0 — Resolver la contradicción de perfil (antes de tocar código)

Fija tu número real de años de experiencia y tu nivel real de Vue/TS. `CLAUDE` y `GROK` te dicen que no te vendas como Full-Stack; `ANALISIS-ARGENIS-DEV` dice que ya tienes una base de 8/10 en portfolio y prueba social. No puedes escribir copy honesto hasta que esto esté decidido.

### Fase 1 — `/agencies` (la única página que bloquea el pipeline)

Es la que convierte cada mensaje de outbound. Estructura, siguiendo el género:

```
H1     Capacidad senior Laravel para cuando tu equipo está al límite
Sub    Trabajo en marca blanca para agencias en España y LatAm.
       No aparezco delante de tu cliente si no quieres.

[Bloque] El problema        → ganas un proyecto, tu equipo está lleno,
                              contratar fijo no compensa
[Bloque] Cómo trabajo       → 4 pasos numerados, staging neutral, tú presentas
[Bloque] No-solicitación    → por escrito, explícito
[Bloque] Modalidades        → bloque de 4 semanas / retainer 20h / retainer 40h
[Bloque] A quién sirvo y a quién no  ← "A good match?" de Spatie
[Bloque] Prueba             → Servispin, AquaShield, Vidula
[CTA]    Un solo formulario, con "¿Cómo me has conocido?"
```

### Fase 2 — Renombrar servicios de tecnología a problema

El `problem:` de tu frontmatter ya está escrito. Solo hay que **promoverlo a título**:

| Ahora | Propuesta |
|---|---|
| Laravel Backend Development | Rescate y modernización de Laravel legacy |
| Custom Web Development | Software interno a medida para empresas con procesos manuales |
| AI Automation & Integration | Automatización con IA para procesos que hoy son manuales |
| Next.js / Astro / DevOps | → colapsar en las tres anteriores; el stack pasa a nota al pie |

El nicho de legacy Laravel merece página propia. `PIPELINE-FREELANCER-CLAUDE.md` lo llama "el nicho que casi nadie quiere y paga bien", y Tighten tiene una página entera (`/services/codebase-upgrades`) dedicada precisamente a él. Coincidencia que vale la pena escuchar.

### Fase 3 — `/projects` → `/results` con estructura de tres actos

Servispin como primer case study, en el molde de Tighten:

- **El reto** — "una web que era un teléfono y un logo; la disponibilidad se gestionaba a mano"
- **El plan** — reservas + calendario + pagos + soporte remoto + API
- **El resultado** — el testimonio del fundador, tal cual, sin editar

Si tu CRM ya sirve los proyectos por API, esto es añadir tres campos (`challenge`, `plan`, `outcome`) al esquema y a la plantilla — no un rediseño.

### Fase 4 — `/es`

España + LatAm es tu mercado natural por idioma nativo, y es el que tienes que atacar primero mientras el inglés sube. Prioriza `/es/agencies` sobre la home en español.

---

## 6. Cómo encaja LeadScout

Sobre el stack, tus specs ya convergen y **el veredicto es correcto**: Laravel + AI SDK + Tavily + Firecrawl + PostgreSQL + Redis, con MCP como capa de interoperabilidad, no como columna vertebral. GEMINI lo argumenta mejor que nadie — scraping es I/O, no CPU, y Horizon te da rate limiting, backoff y reintentos que en FastAPI tendrías que montar a mano con Celery. No hay debate que abrir ahí.

Lo que sí quiero marcar es una **dependencia de orden** que ninguno de los cinco docs menciona:

> **El pipeline no falla por el código. Falla por la landing.**

Si LeadScout genera 200 leads cualificados y cada mensaje enlaza a un sitio cuyo H1 dice "Laravel Developer & AI Automation Specialist" y cuyo menú principal ofrece "Support", conviertes a la tasa baja del rango de GEMINI (4%) en vez de la alta (8%). Con 200 contactos eso es la diferencia entre **8 conversaciones y 16**. Mismo esfuerzo de prospección, mismo coste de API, la mitad de resultado.

`/agencies` cuesta un día. El pipeline cuesta semanas. **Haz la página primero.**

Un detalle que sí conviene copiar de Tighten hacia el módulo: su formulario hace obligatorio `How did you hear about us?`. Ese campo es el que te permite atribuir qué agencia vino de LeadScout y cuál de un referral — sin él, el dashboard de KPIs que diseña `PIPELINE-MAIN-FREELANCER-CHATGPT.md` no puede cerrar el bucle.

Y una nota sobre el ROI que el propio doc principal ya intuye pero no remata: **el sistema es tu mejor case study**. "Construí un sistema de research automatizado que descubre agencias, analiza su stack e identifica buying signals con Laravel, IA y crawling" vende infinitamente mejor que "soy developer Laravel". Que ese case study exista en `/results` es parte del retorno del módulo, no un extra.

---

## 7. Lo que no compraría de los specs

Tres cosas que someto a tu criterio:

1. **El volumen.** CHATGPT-MAIN propone 100 empresas y 150–250 contactos *por semana*. GROK lo corrige y tiene razón: 40–60 leads muy bien investigados baten a 200 genéricos. Con 5% de respuesta real, 200 contactos genéricos son 10 respuestas tibias; 50 hiperrelevantes pueden ser las mismas 10, pero calientes.

2. **"500 agencias españolas" segmentadas en marketing/SEO/branding.** Una agencia de SEO no tiene dolor de capacidad de ingeniería Laravel. Tu ICP verdadero está en el Tier A que el propio doc identificó — agencias de software a medida con stack Laravel/Vue confirmado. Son ~10–30 en España, no 500. Ahí es donde `Evolved Ideas / Secret Source` destaca: ya vende *staff augmentation*, o sea que **ya entiende y ya compra el modelo que le vas a proponer**. No hay que educarles.

3. **El dato de tamaño de LinkedIn como filtro duro.** El propio doc principal se autocorrige: no pudo verificar 30–50 agencias que cumplan "Laravel + Inertia/Vue + 5–50 empleados" con fuentes públicas. Si LeadScout depende de ese filtro para puntuar, va a descartar buenos leads (Bloonde, 9 empleados, stack idéntico al tuyo) y a colar malos. Puntúa por **señal de stack + señal de compra**, y deja el tamaño como desempate, no como criterio eliminatorio.

---

## Fuentes del research

- `tighten.co` — mapa de IA completo + extracción de `/services/embedded-teams`, `/services/codebase-upgrades`, `/results/sweetwater`
- `spatie.be` — mapa de IA + extracción de `/web-development`
- `jonathanstark.com` — fórmula de posicionamiento, programa "Cold Start", testimonios
- `philipmorgan.net` — *The Positioning Manual for Indie Consultants*
- Proveedores white-label (DoodleWeb, Black Kite Technologies, Scepter, KrishaWeb) — convenciones del género "for agencies"
- SalesHive / CXL — datos sobre foco único de CTA en landings B2B
