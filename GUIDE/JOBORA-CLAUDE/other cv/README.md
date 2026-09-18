# other cv/

Carpeta de trabajo para el flujo **Other Niche Studio** (niche `other`): cualquier
CV que **no** sea tu track fullstack (Laravel/PHP/Inertia/Vue), por ejemplo un CV
de marketing, datos, soporte, etc.

## Contenido

- Coloca aquí el/los CV(s) en Markdown que quieras optimizar.
- `job-search-cache.json` — historial/caché de URLs de vacantes ya vistas para
  este CV (deduplicación, independiente del caché de `fullstack cv/`).
- `output/` — se genera automáticamente: CV ATS reescrito, reporte de
  auditoría, traducciones.

## Cómo usarla

Ejecuta `/cv-job-search-other` en Cursor. A diferencia del flujo fullstack,
**no** usa GitHub — en su lugar te pedirá un *targeting prompt* (rol, industria,
ubicación, tono) para orientar la búsqueda y la reescritura ATS.
