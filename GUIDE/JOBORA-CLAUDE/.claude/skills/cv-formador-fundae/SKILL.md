---
name: cv-formador-fundae
description: >-
  Pipeline de investigación de academias/consultoras españolas de formación
  bonificada FUNDAE (cursos para empresas) + reescritura de CV para el nicho
  "formador de IA / formador tecnológico" + mensaje de solicitud por empresa +
  auditoría estilo cv-judge. Usa Tavily (búsqueda) + cascada de extract
  Firecrawl/Tavily en páginas "sé formador / colabora / trabaja con nosotros"
  y sequentialthinking. Invocado por /cv-job-search-formador.
disable-model-invocation: true
---

# CV Formador FUNDAE Studio (local)

Variante del pipeline `cv-job-studio` (`fullstack cv/` / `other cv/`) para un nicho concreto:
**formador/instructor freelance de IA y tecnología en academias españolas de formación bonificada
FUNDAE**. A diferencia de `/cv-job-search` y `/cv-job-search-other`, este flujo no busca ofertas de
empleo puntuales — investiga **empresas/academias** (sus páginas de "sé formador", "colabora",
"trabaja con nosotros") y genera un **CV + mensaje de solicitud por empresa**.

Comparte las reglas no-negociables de `.claude/rules/cv-job-studio.md` (veracidad, no auto-envío,
`ats_score` heurístico 75–85, frases prohibidas, formato ATS de una columna) — este archivo es el
flujo paso a paso específico de este nicho.

## Workflow

```
Progreso:
- [ ] 1. Resolver CV base + caché de empresas
- [ ] 2. Compilar/confirmar la lista de empresas objetivo (semillas del usuario + ampliación)
- [ ] 3. Investigar cada empresa (Tavily + cascada extract Firecrawl/Tavily) → perfil real de lo que buscan
- [ ] 4. Judge pass (sequentialthinking): 10s scan + xyz + keyword gaps para el nicho formador
- [ ] 5. Grounding rápido (Tavily) de buenas prácticas ATS 2026 para CVs de formación
- [ ] 6. Reescribir CV maestro → formador cv/output/CV_Formador_IA_ATS_<fecha>.md
- [ ] 7. Redactar 1 mensaje de solicitud por empresa (150–220 palabras, canal real encontrado)
- [ ] 8. Aplicar auditoría estilo /cv-judge al CV generado → output/ats-informe.md
- [ ] 9. Actualizar companies-cache.json + output/resumen.md
- [ ] 10. Ofrecer siguientes pasos (variante de CV por empresa, traducción, marcar status)
```

### 1. Resolver CV base + caché

- El CV **fuente** siempre es `fullstack cv/Argenis_Gonzalez_CV_2026.md` (o el que el usuario indique) —
  **nunca se edita**. Si no existe, pedir al usuario que lo indique y detener.
- Leer `formador cv/companies-cache.json` (crear uno vacío según
  `references/companies-cache-schema.md` si no existe).

### 2. Lista de empresas objetivo

- Partir de las empresas que el usuario mencione (semillas). Si no menciona ninguna, preguntar o usar
  como semillas: Imagina Formación, Formadores IT, OpenWebinars, KeepCoding, Deusto Formación Empresas,
  Femxa, Mainfor, Grupo Hedima, IMF Smart Education, Euroinnova Empresas.
- **No limitarse a las semillas**: usar Tavily para ampliar con más academias/consultoras españolas de
  formación tecnológica bonificada FUNDAE que busquen formadores freelance (ver
  `references/companies-playbook.md`).
- Mostrar la lista final al usuario antes de invertir tiempo en Firecrawl si la lista creció mucho
  (más de ~6 empresas) — confirmar cuáles priorizar.

### 3. Investigación por empresa

Seguir `references/companies-playbook.md`. Para cada empresa: `tavily_search` para localizar su página
de "sé formador / colabora / trabaja con nosotros / bolsa de empleo", luego la **cascada de extract**
(Firecrawl scrape → `tavily_extract` advanced → raw content → pivot a canal público). Extraer:
tecnologías/áreas que imparten, modalidad, idioma, requisitos, canal real de aplicación (formulario,
email, URL). No quedarse en el snippet si scrape falla.

### 4. Judge pass (recruiter 10-second scan)

Usar `sequentialthinking` (mínimo una vez aquí) para auditar el CV base contra el target
`Formador de IA / Formador Tecnológico freelance (España, FUNDAE)`, siguiendo
`.claude/skills/cv-job-studio/references/scoring-rubric.md` §1–2. El CV base de Argenis ya lidera con
"Full Stack Developer" — el gap típico es de **orden y título**, no de contenido: la experiencia real de
AI Trainer / Imagina Formación ya existe y ya tiene métricas (horas, vídeos, alumnos, sesiones). No
inventar certificaciones FUNDAE a título personal — quien se acredita como entidad organizadora FUNDAE
es la empresa, no el formador freelance.

### 5. Grounding

Una búsqueda Tavily corta (`search_depth: advanced`, `time_range: month`) sobre buenas prácticas ATS
2026 para CVs de formación/e-learning — solo para sanity-check de formato, nunca como fuente de hechos
sobre el candidato.

### 6. Reescritura del CV

Reordenar el CV para el nicho: título + resumen lideran con la experiencia de formador/AI Trainer, la
experiencia de desarrollo se mantiene pero como "base técnica que da credibilidad para enseñar
desarrollo con IA", condensada. Mantener el bloque de contacto/redes igual que el CV base (mismo
espíritu que el "Always-preserve block" de `cv-job-studio.mdc`, aunque este nicho no está en su glob).
Añadir vocabulario ATS del sector (formación bonificada, FUNDAE, aula virtual, e-learning, in-company,
docente) **solo si ya está sustentado por hechos reales del CV**. Guardar como
`formador cv/output/CV_Formador_IA_ATS_<YYYY-MM-DD>.md` — nunca sobrescribir el CV fuente.

### 7. Mensaje de solicitud por empresa

Un archivo `output/<empresa-slug>/mensaje-solicitud.md` por empresa investigada, 150–220 palabras, tono
humano, sin frases-IA prohibidas (ver `scoring-rubric.md` §5), usando el vocabulario/canal real
encontrado en su web (p. ej. responder implícitamente a las preguntas de un formulario real si el
scrape las reveló). Si la empresa es una colaboración ya existente (p. ej. Imagina Formación), el tono
debe ser de continuidad/ampliación, no de solicitud en frío. Incluir siempre una nota de veracidad al
final indicando que es un borrador no enviado.

### 8. Auditoría ATS (estilo `/cv-judge`)

Aplicar el mismo criterio que `/cv-judge` (`scoring-rubric.md` §1–3) al CV generado en el paso 6:
veredicto 10 segundos, `strengths`/`improvements`/`keyword_gaps`/`xyz_gaps`, `ats_score` heurístico.
**Banda objetivo 75–85** — nunca forzar a >90 con keyword stuffing, aunque una nota anterior del usuario
pida ">95"; explicar la corrección si aplica. Guardar en `output/ats-informe.md`.

### 9. Caché + resumen

Actualizar `formador cv/companies-cache.json` (`references/companies-cache-schema.md`) con cada empresa
investigada + un registro en `runs`. Escribir/actualizar `output/resumen.md` con la tabla comparativa
(empresa, canal real, qué buscan, archivo de mensaje).

### 10. Siguientes pasos

Ofrecer: variante de CV específica por empresa (si el perfil buscado difiere mucho entre empresas),
traducción del CV (solo si alguna empresa lo pide explícitamente), o marcar el `status` de una empresa
en la caché (`researched` → `applied`/`dismissed`) cuando el usuario confirme que envió la solicitud él
mismo.

## Reglas heredadas (no repetir, ya están en el rule del proyecto)

- Veracidad: solo hechos del CV base / respuestas del usuario. Nunca inventar empleadores, horas,
  certificaciones FUNDAE personales, o testimonios de alumnos.
- No auto-envío: todo son borradores para revisión humana.
- `ats_score` heurístico, banda 75–85, nunca >90 forzado.
- Frases prohibidas / formato ATS de una columna / idioma: igual que
  `.claude/skills/cv-job-studio/references/scoring-rubric.md` §5–6.

## Recursos adicionales

- [references/companies-playbook.md](references/companies-playbook.md) — cómo buscar/ampliar la lista
  de empresas y qué extraer de cada página con Tavily/Firecrawl/sequentialthinking.
- [references/companies-cache-schema.md](references/companies-cache-schema.md) — esquema de
  `companies-cache.json`.
- `.claude/skills/cv-job-studio/references/scoring-rubric.md` — checklist de judge/ATS/frases
  prohibidas/idioma (compartido con este nicho).
