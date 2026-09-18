# formador cv/

Carpeta de trabajo para el flujo **Formador FUNDAE Studio** (nicho `formador-fundae`): CV y
candidaturas para postularte como **formador/instructor de IA y tecnología** en academias y consultoras
españolas de **formación bonificada FUNDAE** (cursos para empresas).

Es un espejo local del mismo pipeline que `fullstack cv/` y `other cv/`
(`.cursor/skills/cv-job-studio/`), pero con su propio skill (`cv-formador-fundae`) porque el flujo es
distinto: no busca ofertas de empleo puntuales, sino que investiga **empresas/academias** y genera un
**CV + mensaje de solicitud por empresa**.

## Contenido

- No hay un CV base propio aquí — la fuente de verdad sigue siendo
  `fullstack cv/Argenis_Gonzalez_CV_2026.md` (nunca se edita). Este CV se reescribe para el nicho de
  formador y se guarda en `output/`.
- `companies-cache.json` — caché de empresas ya investigadas (para no repetir el scraping en cada
  corrida). Ver `.cursor/skills/cv-formador-fundae/references/companies-cache-schema.md`.
- `output/` — se genera automáticamente:
  - `CV_Formador_IA_ATS_<fecha>.md` — CV maestro reescrito para el nicho formador (español, ATS).
  - `<empresa>/mensaje-solicitud.md` — mensaje/carta de solicitud adaptado a cada empresa, con el canal
    real de aplicación encontrado en su web.
  - `ats-informe.md` — auditoría estilo `/cv-judge` del CV generado (veredicto 10 segundos, gaps,
    `ats_score` heurístico).
  - `resumen.md` — tabla comparativa de empresas investigadas + estado.

## Cómo usarla

Ejecuta `/cv-job-search-formador` en Cursor (ver `.cursor/commands/cv-job-search-formador.md`). El
agente investiga con Tavily + Firecrawl + `sequentialthinking` las páginas de "sé formador / colabora /
trabaja con nosotros" de academias españolas de formación bonificada, reescribe el CV para ese nicho,
redacta un mensaje de solicitud por empresa y aplica la auditoría `/cv-judge` al resultado — todo en
español y sin inventar experiencia ni certificaciones FUNDAE que no existan.

**Importante:** todo lo que se genera aquí son **borradores para revisión humana**. El agente nunca
envía ninguna solicitud ni rellena formularios reales por ti.
