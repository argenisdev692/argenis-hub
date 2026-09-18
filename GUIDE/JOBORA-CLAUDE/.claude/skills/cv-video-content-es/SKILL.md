---
name: cv-video-content-es
description: >-
  Pipeline de búsqueda de trabajo de Course Creator / creador de píldoras y
  videotutoriales e-learning en español (grabar cursos, escribir cursos,
  laboratorios, ejercicios, revisar contenido) en España, la UE y clientes
  globales que contratan como contractor — con gate de
  residencia (contratable residiendo en Portugal), gate anti-marketing, suelo
  de tarifa y scoring 0.30·H + 0.25·S + 0.45·D. Incluye reescritura de CV,
  mensaje por empresa y auditoría estilo cv-judge. Usa Tavily + cascada de
  extract Firecrawl/Tavily y sequentialthinking. Idioma de salida y del
  contenido: español. Invocado por /cv-job-search-video-content.
disable-model-invocation: true
---

# CV Video Content ES Studio (local)

Variante del pipeline `cv-job-studio` para un nicho concreto: **Course Creator /
creador de píldoras y videotutoriales e-learning en español** (grabación,
guion/escritura de cursos, labs, ejercicios, revisión de contenido).

A diferencia de `/cv-job-search-formador` (academias FUNDAE que reclutan
formadores para aula), este flujo busca empresas/edtech/estudios/L&D que
necesitan **producir o encargar contenido en vídeo y materiales de curso** — no
necesariamente impartir clase en vivo.

Comparte las reglas no-negociables de `.claude/rules/cv-job-studio.md`
(veracidad, no auto-envío, `ats_score` heurístico 75–85, frases prohibidas, ATS
una columna).

## Qué cambió el 2026-08-26

Antes este modo **no tenía scoring ni gates de residencia** — solo un gate de
"idioma español". Cinco cambios, todos deliberados. Los puntos 4 y 5 son
decisiones del usuario tomadas por la tarde, tras medir las dos primeras
corridas v2:

1. **Fórmula propia** — `0.30·H + 0.25·S + 0.45·D`, con D repartido en
   `0.35·residencia + 0.25·pago + 0.20·modelo_pago + 0.20·idioma`. Verificada
   numéricamente sobre las 6 empresas reales del caché + 6 casos adversarios.
2. **G1 residencia exigida** — el gate que faltaba. Se envió candidatura a ADR
   Formación y contestaron **remoto solo para residentes en España**. El caché
   guardaba la modalidad pero nunca la residencia. Idioma del contenido y país
   de residencia son **dos ejes distintos**.
3. ~~**Ámbito ampliado** a LatAm~~ → **revertido la misma tarde**. El ámbito
   quedó en **España + UE + `contractor-global`**. Se probó y se midió: de 27 JD
   leídos, 22 (81%) con muro de residencia, 0 con vehículo válido desde
   Portugal, y las dos únicas tarifas publicadas fueron 4,12 y 5,29 EUR/h contra
   un suelo de 15. Ver punto 5.
4. **G4 frescura reescrita** (decisión del usuario, tarde del 2026-08-26, tras
   la primera corrida v2): una **fecha de cierre futura publicada manda sobre la
   ventana de días** — responde directamente a "¿sigue abierta?", que es lo que
   el límite de días sólo aproximaba. Y cuando **no** hay fecha de cierre, la
   ventana se estrecha de 7 a **3 días**. Detalle y motivo en
   [references/video-scoring.md](references/video-scoring.md) § G4.
5. **LatAm fuera y discovery por canal directo** (decisión del usuario,
   2026-08-26 tarde). Ámbito: **España + UE + `contractor-global`**. Y el
   **hallazgo espejo** que justifica mirar a España: en la UE el vehículo
   contractual es correcto pero el entregable se vuelve **inglés** (Lemon
   Learning: `remote-eu` + contractor, *native English* → G3 FAIL); en España el
   entregable es español pero el vehículo es **nómina** (ThePowerMBA, The Globe,
   Codeway). El objetivo es la intersección estrecha, y vive en los canales de
   colaborador de las edtech españolas.

## Workflow

```
Progreso:
- [ ] 1. Resolver CV base + caché + parámetros (suelo de tarifa, R, tipo de cambio)
- [ ] 2. Compilar lista objetivo: canales directos de empresa (semillas + Tavily)
- [ ] 3. Discovery por rondas: CANAL DIRECTO → ES mercantil → UE → contractor-global
- [ ] 4. Triaje en snippet: G2 negativa → G1 residencia → G3 idioma → G4 frescura
- [ ] 5. Cascada de extract sobre las supervivientes + G5 tarifa + G6 modelo pago
- [ ] 6. Score 0.30·H + 0.25·S + 0.45·D + caps (sequentialthinking antes del ranking)
- [ ] 7. Judge pass del CV vs nicho (sequentialthinking)
- [ ] 8. Reescribir CV → video content cv/output/CV_Video_Pildoras_ATS_<fecha>.md
- [ ] 9. Bloques A/B/C ordenados por ROI_app + mensaje por empresa
- [ ] 10. Auditoría ATS + caché (apply_links triado) + informe de mercado a fichero
```

### 1. CV base, caché y parámetros

- Fuente: `fullstack cv/Argenis_Gonzalez_CV_2026.md` (o la que indique el
  usuario) — **nunca editar**.
- Leer `video content cv/companies-cache.json` (crear vacío v2 según
  `references/companies-cache-schema.md` si falta; migrar v1 según la sección
  de migración).
- **Parámetros ya confirmados (2026-08-26), en `params` del caché** — no volver
  a preguntarlos, solo aplicarlos:
  - `pay_floor_eur_hour` = **19,5 EUR/h efectivos** (G5) — subido desde 15 el
    2026-08-26 por la tarde ("tarifa española + 4,5")
  - `hours_per_finished_minute` (`R`) = **4 a 6 h por minuto acabado**, banda.
    Umbrales derivados: **< 78 EUR/min → FAIL** · **78–116 → verificar** ·
    **≥ 117 → paso limpio**
- **Sí hay que resolver cada corrida:** el **tipo de cambio** USD→EUR (y el de
  cualquier otra divisa que aparezca) del día, declarado junto a cada conversión
  con su fecha.

### 2–3. Lista objetivo y discovery

Tiers, canales, vocabulario y las 10 queries en
[references/companies-playbook.md](references/companies-playbook.md).

**El descubrimiento empieza por el canal directo de la empresa, no por los
portales** (decisión del usuario, 2026-08-26). Los portales están sesgados hacia
la contratación por país; los tres canales vivos del caché (OpenWebinars, Nanfor,
Imagina) salieron todos del canal directo.

Partir de lo que nombre el usuario + semillas del playbook, **sin limitarse a
ellas**. Si la lista pasa de ~6 empresas con canal real, mostrarla antes de
gastar Firecrawl en todas.

**Sembrar sólo con vocabulario de España y EN.** Las palabras LatAm
(*capacitación*, *cápsulas*, *curso virtual*) quedan como referencia de lectura
para reconocer un anuncio LatAm y descartarlo por G1 — no para buscarlo.

### 4. Triaje en el snippet — antes de gastar extract

Orden obligatorio (el descarte más barato primero):

```
G2 negativa (¿marketing en redes?) → G1 residencia → G3 idioma → G4 frescura
```

**G2 negativa.** "Creador de contenido" es un homónimo: fuera de España significa
influencer por defecto. Excluir `UGC` · `redes sociales` · `TikTok` · `Reels` ·
`community manager` · `influencer` · `social media` · `brand content` ·
`marketing de contenidos`, salvo que haya señal formativa explícita (`curso`,
`formación`, `capacitación`, `alumnos`, `LMS`).

**G1 residencia.** Señales que ya deciden en el snippet y ahorran el extract:
`W2` · `anywhere in the U.S.` · `residencia en España` · `alta en Seguridad
Social` · `RETA` · `from anywhere in Latin America`. Anotar
`extract: skipped_g1_snippet`.

LinkedIn y hosts bloqueados: **no scrapear perfiles** `linkedin.com/in/*`.
Discovery con Tavily; si el scrape falla, seguir la cascada del playbook. No
parar en el snippet cuando la decisión no está clara.

### 5–6. Extract, gates económicos y scoring

Cascada de extract en el playbook. Gates, sub-scores, caps, normalización de
divisa y minuto acabado, y `ROI_app` en
[references/video-scoring.md](references/video-scoring.md).

```
cascada → G5 suelo de tarifa · G6 modelo de pago
        → H / S / D → match_score = round(min(0.30H + 0.25S + 0.45D, caps))
        → tabla si ≥ 70
```

`sequentialthinking` **antes** del ranking final, no un cálculo de una pasada.

### 7. Judge pass

`sequentialthinking` + `scoring-rubric.md` §1–2 contra el target:
`Course Creator / Creador de píldoras y videotutoriales e-learning (español)`.

Destacar hechos reales del CV: cursos Imagina (Claude AI, M365 Copilot, GitHub
Copilot), producción 1080p/30fps, pipeline FFmpeg, 7 h / 48 vídeos, 25 h / 30
alumnos. **Nunca** inventar clientes de vídeo ni métricas de visualizaciones.

**Precisión obligatoria:** Imagina fue **contrato de impartición y grabación**,
no una certificación. Nunca escribir "certificado" ni listarlo bajo
Certificaciones. OWASP no aplica a contenido de vídeo.

### 8. Reescritura del CV

Grounding corto (una Tavily, `time_range: month`) sobre CV ATS para
instructional designers / e-learning content creators — **solo formato**.

Liderar con **producción de contenido / Course Creator**, no con "Full Stack
Developer". Sección clara de píldoras y videotutoriales. Dev stack condensado
como credibilidad técnica para labs y ejercicios de código.

Guardar: `video content cv/output/CV_Video_Pildoras_ATS_<YYYY-MM-DD>.md`.

### 9. Resultados y mensajes

**Tres bloques**, ordenados por `ROI_app` descendente:

- **A** — tarifa confirmada (`fee_fijo` o `mixto`, ≥ suelo)
- **B** — tarifa no publicada (**nunca** estimar la cifra)
- **C** — royalty puro (informativo, sin cifra de ingresos)

| Empresa | Puesto | Tarifa (orig. → EUR/h ef.) | Modelo | Residencia | Idioma | Publicado | Link | Match | C (h) | ROI |

Mensaje por empresa en `output/<empresa-slug>/mensaje-solicitud.md`, 150–220
palabras, vocabulario de la empresa, tono humano, sin frases-IA prohibidas.
Si piden vídeo demo, dejar checklist explícito. Nota de veracidad al final.
Continuidad (no frío) si ya colabora, p. ej. Imagina.

**En toda empresa con `residency_verified: false`, el mensaje debe incluir la
pregunta de residencia** — es lo que habría evitado el caso ADR:

> ¿Aceptáis a un colaborador que factura desde Portugal, o el remoto es solo
> para residentes en España?

### 10. Auditoría, caché e informe

- Auditoría ATS igual que `/cv-judge` (`scoring-rubric.md` §1–3). Banda
  **75–85**, nunca forzar >90. Guardar `output/ats-informe.md`.
- Caché según `references/companies-cache-schema.md`, con `apply_links` triado
  en `1_aplicar_ya` / `2_verificar_antes` / `3_no_aplicar`. El usuario lee
  `apply_links`; `companies` es el registro técnico.
- **Reportar siempre `excluded_breakdown`**, aunque vaya a ceros. Cuánto del
  mercado se cae por residencia y cuánto por ser marketing disfrazado es parte
  del resultado en este nicho.
- **Informe de mercado a fichero**:
  `video content cv/output/Informe_Mercado_Video_<YYYY-MM-DD>.md`, siempre que
  haya alguna observación — **incluida la corrida con tabla vacía**, que es
  cuando más vale. Seguir
  `.claude/skills/cv-job-studio/references/market-insights.md` (contar sobre
  los JD realmente leídos, `n/N`, porcentajes solo con N≥5, ≥1 URL de evidencia
  por fila, nunca recomendar poner en el CV una skill que no se tiene).

  Cubos propios de este nicho: **herramienta de autor exigida** (Articulate,
  Rise, Captivate, SCORM) · **residencia exigida por geografía** ·
  **modelo de pago** (fee vs royalty) · **tarifa por minuto acabado observada** ·
  **requisito de vídeo demo** · **qué proporción resultó ser marketing en redes**.

## Reglas heredadas

Veracidad, no auto-envío, `ats_score` heurístico 75–85, frases prohibidas, ATS
una columna: `.claude/skills/cv-job-studio/references/scoring-rubric.md` §5–6.
Salida siempre **español**.

## Recursos

- [references/video-scoring.md](references/video-scoring.md) — gates (residencia,
  anti-marketing, suelo de tarifa, modelo de pago), fórmula, caps, `ROI_app`.
- [references/companies-playbook.md](references/companies-playbook.md) — tiers
  geográficos, vocabulario por país, keywords y negativas, queries, cascada.
- [references/companies-cache-schema.md](references/companies-cache-schema.md) — caché v2.
- `.claude/skills/cv-job-studio/references/scoring-rubric.md`
- `.claude/skills/cv-job-studio/references/market-insights.md`
