---
name: cv-gap-remote-es
description: >-
  Pipeline de empleo remoto puente (no programación) para cubrir el hueco de
  ingresos mientras avanza la búsqueda fullstack: asistente virtual, customer
  /chat/email support, data entry, AI data trainer, AI rater, moderación de
  contenido, transcripción y administrativo remoto. Filtros duros de 100%
  remoto permanente por contrato + residencia PT/UE + español + sin
  experiencia + banda de 1.000 a 3.000 €,
  con gate anti-fraude propio y fórmula D-pesada. Genera además un CV del
  nicho en español. Invocado por /cv-job-search-gap.
disable-model-invocation: true
---

# CV Gap Remoto ES (local)

Variante de `cv-job-studio` para **trabajo remoto de tecnología pero no de
programación**, como ingreso puente. Carpeta `gap cv/`, cachés propias,
salida siempre en **español**.

Comparte las reglas no-negociables de `.claude/rules/cv-job-studio.md`
(veracidad, nunca auto-enviar, scores heurísticos, frases prohibidas,
formato ATS de una columna). Lo que este fichero define es lo que cambia.

**Tres diferencias con career mode, todas deliberadas:**

1. **Fórmula D-pesada** — `0.25·H + 0.25·S + 0.50·D`. En soporte y data
   entry las hard skills casi no discriminan; deciden residencia, idioma,
   sueldo y experiencia exigida.
2. **Gate anti-fraude (G4)** que corre **antes** de gastar extract. Es el
   nicho más denso en estafa del mercado remoto.
3. **Gate de modelo de contratación (G2b)** — solo empleo remoto permanente
   por contrato o freelance con contrato. Colas de micro-tareas, encuestas
   remuneradas, pago por clic y gig sin contrato quedan **fuera de
   alcance**, aunque el puesto en sí sea de la lista. Decisión del usuario
   del 2026-08-25.

## Workflow

```
Progreso:
- [ ] 1. Resolver CV fuente + las dos cachés de gap cv/
- [ ] 2. Judge pass del nicho (sequentialthinking): 10s scan de reclutador BPO
- [ ] 3. Reescritura del CV gap en español → gap cv/output/
- [ ] 4. Descubrimiento Tavily 24-72h (ES principal + EN de cobertura)
- [ ] 5. G4 anti-fraude sobre título/snippet — ANTES de cualquier extract
- [ ] 6. G1/G2/G2b/G3/G5 + cascada de extract sobre las supervivientes
- [ ] 7. Score 0.25·H + 0.25·S + 0.50·D + caps de idioma
- [ ] 8. Bloques A y B, ordenados por sueldo y luego por "sin experiencia"
- [ ] 9. Actualizar job-search-cache (+ `apply_links` triado para el usuario)
- [ ] 10. Informe de mercado **a fichero** en `gap cv/output/` + siguientes pasos
```

### 1. CV fuente + cachés

- CV fuente: `fullstack cv/Argenis_Gonzalez_CV_2026.md` (o el que indique el
  usuario). **Nunca se edita.**
- Leer `gap cv/job-search-cache.json`
  (crearlos vacíos según `references/cache-schema.md` si faltan).
- Si ya existe un CV gap en `gap cv/output/`, reutilizarlo salvo que el
  usuario pida rehacerlo.

### 2. Judge pass del nicho

`sequentialthinking` y luego el escaneo de 10 segundos, pero **con los ojos
de un reclutador de BPO/soporte, no de una tech**. Lo que ese perfil busca en
6 segundos: idiomas, disponibilidad horaria, experiencia de cara al cliente,
equipo y conexión, y si el candidato se va a ir en cuanto encuentre algo
mejor.

Ese último punto hay que decirlo sin rodeos: un CV de desarrollador fullstack
enviado a customer support **grita "se va en tres meses"**, y es el motivo
número uno de descarte silencioso en este nicho. El CV gap tiene que
resolverlo, no ignorarlo.

Producir `target_job_title`, `strengths`, `improvements`, `keyword_gaps` del
nicho y hasta 6 `metric_questions`. Preguntar antes de reescribir.

### 3. Reescritura del CV gap (español)

Guardar en `gap cv/output/CV_Gap_Remoto_ES_<YYYY-MM-DD>.md`. Nunca sobre el
CV fuente.

**Qué sí destacar** (todo evidencia real del CV, nada inventado):

- **Formador de IA por contrato** para Imagina Formación: aulas de Claude AI
  y Microsoft 365 Copilot + grabación de píldoras de vídeo. Para AI rater /
  AI data trainer / evaluador de IA esto no es adyacente: es experiencia
  directa y es lo mejor que tiene el perfil.
  **Nunca escribirlo como "certificado" ni bajo Certificaciones** — fue un
  contrato de impartición y grabación, no una credencial obtenida.
- **Formación a 30 alumnos, 25 h en 8 sesiones por Zoom** — trato con
  personas, comunicación, aguante de sesión larga.
- **Producción de contenido e-learning** (7 h / 48 vídeos) — atención al
  detalle, entrega a plazo, control de calidad.
- **Trabajo freelance con clientes en España, EE.UU. y Portugal** — trato
  directo con cliente, autonomía, trabajo remoto probado desde Covilhã.
- **Idiomas:** español nativo, portugués nivel residente, inglés B1.
- Equipo, conexión y espacio de trabajo remoto ya montados.

**Qué bajar de volumen** (no borrar — mentir por omisión también es mentir):
el detalle profundo de stack. Laravel, Inertia, PostgreSQL y OWASP no le
dicen nada a un reclutador de soporte y ocupan el espacio que debería llevar
idiomas y disponibilidad. Una sección técnica breve basta.

**El párrafo de encaje es obligatorio.** Explicar en una o dos líneas
honestas por qué este perfil quiere este trabajo, sin fingir que la
programación no existe. La versión creíble es la real: perfil técnico que
busca trabajo remoto estable y aporta soporte con criterio técnico.

Aplicar las frases prohibidas y el formato ATS de una columna de
`.claude/skills/cv-job-studio/references/scoring-rubric.md`. Reportar
`ats_score` heurístico.

### 4–7. Búsqueda, gates y scoring

Todo el detalle operativo está en
[references/gap-playbook.md](references/gap-playbook.md) y
[references/gap-scoring.md](references/gap-scoring.md).

Orden **obligatorio** — el gate anti-fraude va primero:

```
Tavily (24h → 72h, ES + EN)
   → G4 anti-fraude sobre título/snippet     ← antes de gastar extract
   → G1 remoto+residencia · G2 rol · G2b contrato · G3 frescura · G5 banda
   → cascada de extract sobre las supervivientes
   → normalización salarial + hours_guarantee
   → 0.25·H + 0.25·S + 0.50·D  → caps de idioma (G4b) → tabla si ≥70
```

### 8. Bloques A y B

Orden: **sueldo descendente; a igualdad, primero las que no piden
experiencia.** Columnas:

| Empresa | Puesto | Sueldo | Países aceptados | Requisitos | Publicado | Link | Match | Horas garant. |

- **Bloque A** — sueldo confirmado en banda 1.000–3.000 €/mes equivalente.
- **Bloque B** — mismos gates, sueldo no publicado. Nunca estimar la cifra.

Toda conversión lleva su nota: tarifa original y tipo de cambio asumido. Una
tarifa horaria convertida es **equivalencia a jornada completa**, no un
ingreso prometido.

### 9. Caché

Según `references/cache-schema.md`. Los descartes por fraude van a
`runs.notes` + `excluded_count`, y opcionalmente como entrada `dismissed`
con `scam_flags` para que ese dominio no vuelva a proponerse.

**Reportar siempre `excluded_breakdown.scam_gate`**, aunque sea 0. En este
nicho, cuánto fraude se filtró es parte del resultado.

### 10. Informe de mercado + siguientes pasos

**El informe se escribe a fichero, no solo al chat:**
`gap cv/output/Informe_Mercado_Gap_<YYYY-MM-DD>.md`. Obligatorio siempre que
haya **alguna** observación de mercado — **incluida la corrida con tabla
vacía**, que es justo cuando el informe más vale, porque explica por qué.
Además se sigue persistiendo `runs[].market_insights` en la caché: el fichero
es para el humano, el campo de la caché es para marcar `persistent` en la
corrida siguiente.

Aplicar
`.claude/skills/cv-job-studio/references/market-insights.md` con los mismos
criterios (contar sobre todo lo leído, incluidas las capadas; porcentajes
solo con N≥5; ≥1 URL de evidencia por fila; nunca recomendar poner en el CV
una skill que no se tiene).

Los cubos que importan aquí: nivel de idioma exigido, herramientas de
soporte realmente exigidas, turnos y husos horarios, tipo de contrato,
**qué proporción de lo descubierto resultó ser fraude**, y **qué proporción
resultó ser venta detrás de un título de soporte**.

Si en la sesión corrieron varias rondas (p. ej. una en español y otra en
portugués), el informe cubre **todas**, diciendo de qué ronda sale cada cifra.

Luego ofrecer: traducir el CV gap, redactar un mensaje de aplicación para
una vacante concreta, marcar `status`/`outcome`, o registrar el resultado de
una vacante ya guardada.

## Recursos

- [references/gap-scoring.md](references/gap-scoring.md) — gates (incluido
  el anti-fraude), fórmula D-pesada, normalización salarial.
- [references/gap-playbook.md](references/gap-playbook.md) — semillas
  Tavily, portales, orientación al contrato, cascada de extract.
- [references/cache-schema.md](references/cache-schema.md) — la caché del modo.
- `.claude/skills/cv-job-studio/references/scoring-rubric.md` — frases
  prohibidas, formato ATS, reglas de idioma.
- `.claude/skills/cv-job-studio/references/market-insights.md` — informe de
  requisitos recurrentes.
