# fullstack cv/

Carpeta de trabajo para el flujo **Career Studio** (niche `fullstack`), espejo local
del módulo Laravel `AiResumeStudio` / spec `003-cv-ats-job-studio`.

## Contenido

- `Argenis_Gonzalez_CV_2026.md` — CV base (fuente de verdad). No se sobrescribe:
  cada mejora se guarda en `output/`.
- `job-search-cache.json` — historial/caché de URLs de vacantes ya vistas
  (deduplicación), para no repetir links entre corridas. Ver
  `.claude/skills/cv-job-studio/references/cache-schema.md`.
- `output/` — se genera automáticamente: CV ATS reescrito, reporte de
  auditoría del reclutador, traducciones (`CV_en.md`, `CV_pt.md`, `CV_es.md`).

## Cómo usarla

Ejecuta `/cv-job-search` en Cursor. El agente lee el CV de aquí, te pide
seleccionar repos de GitHub, busca vacantes remotas recientes y te entrega
tabla + CV corregido + veredicto de reclutador. Ver skill `cv-job-studio`.
