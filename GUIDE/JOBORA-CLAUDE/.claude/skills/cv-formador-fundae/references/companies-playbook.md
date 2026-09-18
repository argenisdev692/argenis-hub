# Companies playbook — Tavily + Firecrawl + sequential-thinking

## Semillas por defecto (ampliables, no exhaustivas)

Imagina Formación, Formadores IT, OpenWebinars, KeepCoding, Deusto Formación Empresas, Femxa, Mainfor,
Grupo Hedima, Core Networks, IMF Smart Education, Adecco Learning, Euroinnova Empresas.

No limitarse a esta lista: buscar también "empresas de formación tecnológica", "formación para
empresas bonificada FUNDAE", "formador freelance IA", "instructor Claude/Copilot/GitHub Copilot España".

## Búsqueda (Tavily)

Queries recomendadas (`search_depth: "advanced"`, `max_results: 8–10`):

1. `empresas España formación bonificada FUNDAE "formador freelance" OR "ser formador" tecnología IA`
2. `"colabora con nosotros" OR "bolsa de formadores" formador Copilot Claude IA cursos bonificados España`
3. `site:<dominio-empresa> formador OR colabora OR trabaja OR bolsa OR docente` (por cada semilla que no
   dé resultado directo con las queries generales)
4. Variantes con nombres concretos de empresas nuevas encontradas (ronda 2 de expansión)

Para cada resultado: identificar la URL más específica posible de "sé formador / colabora / trabaja con
nosotros / bolsa de empleo / docente" — evitar quedarse con la home si hay una página más concreta.

## Scrape / extract cascade (Tavily + Firecrawl)

Misma lógica host-agnóstica que
`.claude/skills/cv-job-studio/references/search-playbook.md` (Deep extract
cascade), adaptada a páginas de empresa (colabora / formador / bolsa), no solo
a JDs de job boards.

Solo sobre URLs concretas de canal (no home genérica salvo que no exista nada
más específico). Parar en el primer paso con contenido usable. Anotar en
`notes` del cache el paso que funcionó (ej. `extract: tavily_advanced`).

### Step 1 — Firecrawl scrape

`firecrawl_scrape` con `formats: ["markdown", "links"]`, `onlyMainContent: true`.
Opcional `proxy: "auto"` si vuelve vacío / challenge / login.

**Skip** `linkedin.com/in/*`. En hosts que bloquean a menudo (LinkedIn jobs,
InfoJobs, cookie walls agresivas): **un intento**; si falla → step 2. No
forzar `actions` para aceptar cookies.

### Step 2 — Tavily extract

`tavily_extract` con `extract_depth: "advanced"`, `format: "markdown"`,
opcional `query` con keywords del nicho (formador, FUNDAE, colabora…).

### Step 3 — Tavily raw content

Si extract falla o es pobre: `tavily_search` con la URL exacta o
`"<empresa>" formador OR colabora` + `include_raw_content: true`,
`search_depth: "advanced"`.

### Step 4 — Pivot a canal público scrapable

Si el host original sigue sin dar texto útil:

1. Buscar otra URL en el mismo dominio (`/colabora`, `/formadores`, `/empleo`).
2. `tavily_search` excluyendo el host bloqueado si hace falta.
3. `firecrawl_extract` con `allowExternalLinks` + `enableWebSearch` pidiendo
   formulario/email/careers públicos.
4. Scrape la URL alternativa encontrada.

Nunca inventar canal. Si solo queda snippet: usarlo y marcar
`extract: snippet_only` + canal `"no se encontró canal público — usar contacto
general"` cuando aplique.

Extraer de cada página, cuando esté disponible:

- Tecnologías / áreas de formación que imparten (ej. IA, Python, Laravel, Power BI, ciberseguridad...).
- Modalidad (online, aula virtual, in-company, presencial + ciudad).
- Idioma de la formación.
- Requisitos explícitos del formulario/página (ej. "¿has sido formador?", "experiencia en cámara",
  años de experiencia, certificaciones).
- Canal real de aplicación: URL del formulario, email de contacto, o "no se encontró canal público —
  usar contacto general".

## Sequential-thinking

Llamar `sequentialthinking` (servidor `user-sequential-thinking`) al menos en estos puntos:

1. Antes de cerrar la lista final de empresas a investigar (semillas + ampliación).
2. Judge pass del CV contra el nicho formador (10s scan + xyz + keyword gaps) — ver `SKILL.md` paso 4.
3. Verificación de veracidad antes de escribir el CV/mensajes: ¿algo de lo que se va a escribir no está
   respaldado por el CV base, por la investigación real, o por una respuesta explícita del usuario? Si
   sí, quitarlo o preguntar.

## Grounding ATS

Una búsqueda Tavily corta (`time_range: "month"`) tipo `CV ATS formador instructor cursos empresas
España 2026 palabras clave buenas prácticas` antes de reescribir el CV — solo para formato, nunca como
fuente de hechos sobre el candidato.
