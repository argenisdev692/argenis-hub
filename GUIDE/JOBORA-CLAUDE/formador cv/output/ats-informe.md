# Informe ATS / Judge — CV_Formador_IA_ATS_2026-08-04.md

Generado siguiendo `.cursor/skills/cv-job-studio/references/scoring-rubric.md` §1–3
(mismo criterio que usa `/cv-judge`). **Heurístico, no un score de un ATS/Jobscan real.**

## 1. Veredicto de reclutador — escaneo de 10 segundos

**BORDERLINE → PASA con la reescritura.** El CV base ("fullstack cv/Argenis_Gonzalez_CV_2026.md")
lidera con "Full Stack Developer", lo que en un escaneo de 10 segundos hecho por alguien de RRHH /
gestión de formación puede leerse como "programador, no formador" y quedar descartado antes de llegar
a la experiencia de Imagina Formación. La reescritura (`CV_Formador_IA_ATS_2026-08-04.md`) corrige esto
poniendo el título y el resumen de formador primero — con eso pasaría el primer filtro.

- **Lo que destaca:** métricas reales y verificables de docencia (7h/48 vídeos, 6h/10 módulos, 25h/8
  sesiones/30 alumnos), producción de vídeo 1080p/30fps con pipeline propio, y ser desarrollador activo
  real (no solo "usuario de IA") — esto último es un diferenciador fuerte frente a formadores que solo
  saben prompting.
- **Lo olvidable:** los proyectos de desarrollo (Vidula, Servispin, AquaShield) no aportan directamente
  al nicho de formador salvo como prueba de competencia técnica — están bien condensados en la
  reescritura para no robar espacio al perfil docente.

## 2. Fortalezas / mejoras / gaps

- **Fortalezas:** experiencia real y ya remunerada como formador de IA para empresas españolas;
  producción audiovisual propia (relevante porque OpenWebinars y KeepCoding valoran explícitamente
  "experiencia delante de cámara"); base técnica real en desarrollo (Laravel/PHP) que da credibilidad
  frente a consultoras como Formadores IT que atienden clientes corporativos serios.
- **Mejoras aplicadas:** título y resumen reordenados al nicho; vocabulario ATS del sector añadido
  (formación bonificada, FUNDAE, aula virtual, in-company, e-learning) — todo honesto, ya descrito en el
  CV base con otras palabras.
- **`keyword_gaps` (reales, no inventados):**
  - Sin certificación formal de diseño instruccional / andragogía (nice-to-have en algunas consultoras).
  - Sin testimonios o valoraciones de alumnos documentadas por escrito.
  - Inglés B1 — puede limitar si alguna de estas empresas forma en inglés a clientes multinacionales
    (ninguna de las 4 investigadas lo exige explícitamente en lo scrapeado, pero es un gap real a vigilar).
- **`xyz_gaps`:** ninguno crítico — las 3 experiencias de formador ya tienen X (qué hizo), Y (métrica) y
  Z (cómo) con números reales; no fue necesario inventar ni estimar nada.
- **`metric_questions` (opcionales, solo si quieres reforzar aún más el CV):**
  1. ¿Tienes valoración media de los alumnos de los cursos de Imagina Formación (ej. encuesta de
     satisfacción)? Si es un número real, se puede añadir.
  2. ¿El curso de "Claude AI para Usuarios" tuvo un número de empresas/alumnos matriculados además de
     las horas/vídeos? Ayudaría a reforzar el bullet.
  3. ¿Sigues colaborando activamente con Imagina Formación a fecha de hoy (para confirmar "Presente")?

## 3. `ats_score` heurístico

**81 / 100** (banda "buena — vale la pena aplicar", 75–85 según `job-match-scoring.md`).

Comparado contra un "job brief" compuesto = intersección de lo que piden las 4 páginas investigadas
(formador/docente + IA aplicada + experiencia en cámara/vídeo + formación bonificada/in-company +
español). **No se ha forzado a >90** — el proyecto define ese rango como señal de keyword stuffing, no
de mejor CV real (`.cursor/skills/cv-job-studio/references/scoring-rubric.md` §3 y
`job-match-scoring.md`).

| Criterio | Nota |
|---|---|
| Claridad / escaneabilidad | Alta — una columna, sin tablas ni iconos, títulos estándar |
| Alineación de keywords (sin relleno) | Alta — vocabulario del sector, todo verificable en el CV base |
| Impacto cuantificado | Alta — horas, vídeos, alumnos, sesiones, todo real |
| Formato ATS-safe | Alta |
| Gaps residuales | Medios (ver `keyword_gaps` arriba) |

## 3b. Addendum — ronda 2 de investigación (más empresas/keywords)

La ronda 2 amplió la búsqueda con keywords nuevos y confirmó canal real para 2 empresas más
(Grupo Hedima Corporate L&D, Adecco Learning) y una vacante concreta de Formadores IT ("Formador de
IA"). Ninguna de estas reveló un requisito que obligue a reescribir el CV maestro — el `ats_score` se
mantiene en **81/100**. Sí surgió un `keyword_gap` nuevo, específico de una sola oferta (Formadores IT):
esa vacante concreta pide perfil de ML/redes neuronales/PLN/visión por computadora, que el CV **no**
respalda a ese nivel — se documentó en `formadores-it/mensaje-solicitud.md` en vez de forzar el CV para
"encajar" con una vacante que no es un buen fit real. Ver `resumen.md` para el detalle de las 6 empresas.

## 3c. Addendum — ronda 3 (Nanfor, Core Networks, Formagesting)

Tavily + Firecrawl + sequentialthinking. **3 empresas nuevas con canal real.** Mejor fit: **Nanfor**
(catálogo Claude AI / Microsoft 365 Copilot / GitHub Copilot). Core Networks tiene empleo de formadores
pero la vacante PHP es presencial Sevilla (gap geográfico declarado en el mensaje). Formagesting es
colaboración FUNDAE para freelance, no empleo clásico.

**Judge 10s (CV formador):** PASS para el nicho. No se reescribió el CV — `ats_score` sigue en **81/100**.
Gaps residuales sin cambio: inglés B1, sin ML puro, sin certificación formal de diseño instruccional.

## 3d. Addendum — ronda 4 + variante Nanfor

- Variante por empresa: `nanfor/CV_Nanfor_Formador_IA_ATS.md` (título Copilot/Claude/GitHub primero; vocabulario Learning Partner / teleformación; **sin inventar** MCT ni Azure).
- Nueva empresa: **Be-skiller** — vacante real Copilot/Gemini/ChatGPT; canal `hola@be-skiller.com`; requisito duro de vídeo 2–3 min. Gap declarado: Gemini no impartido como curso.
- Explored: DIGNITAE, Technova, AIbility (cerrada).

## 4. Correlación con `cv-training/analize.md`

El borrador `cv-training/analize.md` (de una sesión anterior) pedía iterar "hasta obtener ATS > 95".
Esa meta se **corrige intencionalmente** aquí: la regla del proyecto
(`.cursor/rules/cv-job-studio.mdc` → "Scoring honesty") fija la banda objetivo en **~75–85** y advierte
explícitamente contra perseguir >90 porque normalmente significa saturar de palabras clave, no mejorar
el CV real. Este informe sigue esa regla en vez del borrador antiguo.
