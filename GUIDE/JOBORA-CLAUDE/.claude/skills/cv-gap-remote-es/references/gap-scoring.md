# Scoring del modo gap (empleo remoto puente, no programación)

Espejo del pipeline GUIDE de `cv-job-studio`, con **pesos distintos** y un
**gate anti-fraude** propio. Toda cifra es **heurística**, nunca un score de
ATS de proveedor.

## Por qué los pesos cambian aquí

En career mode, `H` (presencia exacta de hard skills) manda al 45% porque un
puesto Laravel se gana o se pierde en el stack. En soporte, data entry o AI
rating las herramientas casi no discriminan — casi nadie exige Zendesk como
requisito excluyente, y quien lo exige lo entrena en una semana. Lo que
decide de verdad es **residencia, idioma, sueldo y si piden experiencia**.

Con `H` al 45% casi toda oferta de este nicho caería bajo 70 y la tabla
saldría vacía aunque el candidato califique de sobra. Por eso:

```
match_score = round( min( 0.25·H + 0.25·S + 0.50·D , caps ) )
```

| Modo | Fórmula |
|---|---|
| Career (`fullstack cv/`) | `0.45·H + 0.25·S + 0.30·D` — **sin cambios** |
| **Gap (`gap cv/`)** | **`0.25·H + 0.25·S + 0.50·D`** |

Decisión del usuario del 2026-08-25. Es una desviación consciente de la
fórmula compartida, documentada aquí para que nadie la "corrija" de vuelta.

## Gates (todos deben pasar, antes de puntuar)

### G1 — Remoto + residencia

**100% remoto.** Aquí **no** existe la excepción de híbrido Lisboa que sí
tiene career mode: híbrido o presencial → **FAIL**, sin importar la ciudad.

| Etiqueta | Gate |
|---|---|
| `remote-global` (worldwide / anywhere) | **PASS** |
| `remote-eu` (UE / EEE / Europa) | **PASS** |
| `remote-pt-es` (Portugal y/o España) | **PASS** |
| `remote-country-locked` fuera de PT/UE (solo EE.UU., solo Reino Unido, solo LatAm) | **FAIL** |
| `hybrid-*` / `onsite` | **FAIL** (incluida Lisboa) |
| `remote-unclear` | **PASS** con sub-score de residencia 50; resolver con la cascada de extract |

Ojo con el caso frecuente: "worldwide" que en la letra pequeña resulta ser
contratación solo por entidad estadounidense. Si la cascada lo revela →
`remote-country-locked` → FAIL.

### G2 — Encaje de rol

**PASS** si el puesto está en la lista de prioridad o es adyacente:
asistente virtual, customer/chat/email support, data entry, AI data trainer,
AI rater, evaluador de IA, moderador de contenido, community moderator,
transcriptor, administrativo remoto, anotación de datos, QA de contenido.

**FAIL:**

- Puestos de **programación** — esos van a `fullstack cv/`, no aquí.
- **Ventas a comisión pura**, puerta a puerta, telemarketing de captación
  agresiva.
- Cualquier cosa cuya compensación dependa de **reclutar a otras personas**
  (MLM) — también cae en G4.

### G2b — Modelo de contratación (FAIL duro)

**Solo empleo remoto permanente por contrato, o freelance con contrato.**
El modelo de entrega importa tanto como el puesto: el mismo título puede ser
un contrato estable o una cola de micro-tareas, y aquí solo vale lo primero.

**FAIL — sin excepciones:**

| Modelo rechazado | Cómo se reconoce |
|---|---|
| **Cola de micro-tareas** | "regístrate y empieza a hacer tareas", pago por HIT/tarea/unidad, trabajo disponible según cola, plataformas tipo crowdsourcing |
| **Encuestas remuneradas** | "responde encuestas", paneles de opinión, estudios de mercado pagados por respuesta |
| **Clics / visualizaciones / tráfico** | PTC, "gana por ver anuncios", captchas, granjas de clics |
| **Gig sin contrato** | Marketplaces por pieza sin relación contractual continuada |
| **Programas de afiliación / cashback** | Ingreso por referidos o compras |

Esto **elimina el bloque de "plataformas de registro"** que existía en una
versión anterior de este modo (Outlier, Appen, Clickworker, Toloka,
Remotasks y similares): son colas de tareas, no empleos. Decisión del
usuario del 2026-08-25 — no reintroducirlas.

**Ojo con la distinción que sí importa:** *AI Data Trainer*, *AI Rater*,
*evaluador de IA*, *transcriptor* y *moderador* siguen siendo puestos
válidos de la lista de prioridad — **cuando vienen con contrato**. Una
empresa de localización que contrata evaluadores de IA con jornada y
contrato: **PASS**. La misma tarea ofrecida como cola de tareas en una
plataforma: **FAIL**. Lo que se rechaza es el modelo de entrega, no el
oficio.

### G3 — Calidad y frescura del anuncio

- URL de una vacante concreta (no una página de categoría ni un agregador).
- **Publicada en las últimas 72 horas.** Mucho más estricto que los 7 días
  de career mode. Tavily no tiene rango de 72h: usar `time_range: "day"`
  para el barrido de 24h y `time_range: "week"` filtrando por la fecha
  visible para llegar a 72h. Sin fecha visible y sin señal de "nuevo" →
  excluir.

### G4 — Gate anti-fraude (FAIL duro)

Este nicho es el más denso en fraude del mercado remoto. El gate va **antes**
de gastar presupuesto de extract y **antes** de puntuar. Ante la duda, fuera:
el costo de un falso negativo es una vacante perdida; el de un falso positivo
puede ser dinero, documentos de identidad o una cuenta bancaria usada como
mula.

**FAIL inmediato — basta una señal:**

- Pide **pago por adelantado**: cuota de formación, "kit de inicio", depósito
  por el equipo, cuota de plataforma, pago por la entrevista.
- Pide **datos bancarios, documento de identidad o selfie con documento antes
  de un contrato firmado**.
- **Reenvío de paquetes** ("agente de logística", "gestor de envíos") o
  **movimiento de dinero por cuenta de terceros** ("agente financiero",
  "procesador de pagos") — esquemas de mula.
- Compensación por **reclutar a otras personas** (MLM, "construye tu equipo").
- **Solo WhatsApp / Telegram / Signal** como canal, sin dominio corporativo.
- Pago **exclusivamente en cripto**.
- "**Sin entrevista, empiezas hoy**" combinado con sueldo alto.
- Venta de **formación disfrazada de empleo** ("hazte asistente virtual:
  inscríbete en nuestro programa").

**Sospecha fuerte — con 2 o más → FAIL; con 1 → cap 55 y decirlo:**

- Sin web propia ni presencia verificable de la empresa.
- Correo genérico (gmail/outlook/hotmail) como canal de aplicación.
- Sueldo muy por encima del mercado para la tarea (p. ej. 3.000 €/mes por
  "introducir datos").
- Texto del anuncio copiado tal cual en varios dominios distintos.
- Sin razón social ni país de la entidad contratante.
- Redacción vaga tipo "trabajo online desde casa, horario flexible, ingresos
  garantizados" sin describir tareas concretas.

Registrar siempre el motivo en `runs.notes` (`scam_gate: pago_adelantado`,
`scam_gate: solo_telegram`, …) y contarlo en `excluded_count`. Nunca
presentar una oferta descartada por G4 "por si acaso le sirve".

### G5 — Banda salarial (1.000 – 3.000 €/mes)

Banda objetivo del usuario, fijada el 2026-08-25:

| Sueldo confirmado (€/mes equivalente) | Gate |
|---|---|
| < 1.000 | **FAIL** |
| 1.000 – 3.000 | **PASS** → bloque **A** |
| > 3.000 | **PASS**, pero **cruzar con G4**: para un puesto de entrada sin experiencia, un sueldo muy por encima de la banda es una de las señales de fraude más fiables del nicho. Si además hay cualquier otra señal de sospecha → FAIL. Si la empresa es verificable y el puesto lo justifica, entra normal y se anota `above_band: true` |
| No publicado | **PASS** → bloque **B** (no se inventa cifra ni se asume que cumple) |

El techo de 3.000 € no es un rechazo: es un **disparador de escrutinio**.
"Data entry, sin experiencia, 4.000 €/mes" no es una gran oferta, es un
anuncio falso.

### G4b — Idioma (mismos caps que career)

Se reutiliza tal cual `.claude/skills/cv-job-studio/references/job-match-scoring.md`
§ G4b. Base del candidato: **ES nativo · PT nivel residente · EN B1**.
Leer el piso, no el techo. Inglés C1+/nativo requerido → cap **55**; inglés
B2 requerido → cap **65**. Español como idioma principal → sin cap.

En soporte al cliente el idioma **es** el trabajo, así que aquí el cap muerde
más que en career: un puesto de chat support en inglés nativo no es una
oportunidad con una carencia, es un puesto para otra persona.

## Normalización salarial

Todo se lleva a **€/mes equivalente a jornada completa**:

| Publicado como | Conversión |
|---|---|
| Por hora | `× 160 h/mes` (jornada completa) |
| Por día | `× 21 días` |
| Anual | `÷ 12` |
| USD → EUR | Aplicar un tipo reciente y **declararlo** junto a la fecha (p. ej. "1 USD ≈ 0,92 EUR, tipo asumido"). Nunca inventar un tipo con precisión falsa |
| **Por tarea / por minuto de audio / por HIT** | **G2b FAIL** — es cola de tareas, no contrato. No se convierte ni se lista |

Marcar siempre `salary_basis` (`monthly` | `hourly` | `annual` |
`per_task` | `not_stated`) y `salary_confirmed` (true/false). Una tarifa
horaria convertida es una **equivalencia a jornada completa**, no una
promesa de ingreso: etiquetarla como tal.

### Horas garantizadas

Con G2b fuera las colas de tareas, esto ya no distingue "empleo vs
micro-tarea" — eso lo resuelve el gate. Lo que queda es distinguir un
contrato de jornada fija de uno de horas variables:

| Señal | `hours_guarantee` |
|---|---|
| Contrato con jornada fija / horas garantizadas / salario mensual | **1.0** |
| Contrato con mínimo de horas comprometido pero variable | **0.7** |
| Sin información sobre jornada | **0.6** |
| Pago por tarea / horas sujetas a disponibilidad de trabajo | **G2b FAIL** — no se puntúa |

La razón de la regla: una tarifa de 20 $/h sin horas garantizadas **no** son
3.200 €/mes, son una cifra que depende de que haya cola. El usuario decidió
el 2026-08-25 no perseguir ese modelo en absoluto.

## Componentes

### H — Skills duras (25%)

Misma mecánica que career (presencia exacta/alias, `required` 1.0 ·
`preferred` 0.6 · `bonus` 0.25, soft ≤15% del denominador). Skills típicas
del nicho: Zendesk · Intercom · Freshdesk · CRM · Excel/Sheets · velocidad
de tecleo · herramientas de transcripción · plataformas de anotación ·
Windows/Mac · conexión y equipo · auriculares · espacio silencioso.

Evidencia real del CV que **sí** cuenta aquí: **formador de IA por contrato**
para Imagina Formación (aulas de Claude AI y Microsoft 365 Copilot +
grabación de píldoras de vídeo), producción de contenido e-learning, trato
directo con clientes freelance, documentación técnica. Para AI rater / AI
data trainer esa evidencia es fuerte, no adyacente.

**Precisión obligatoria:** fueron **contratos de impartición y grabación**,
**no certificaciones obtenidas**. Nunca escribir "AI Trainer certificado" ni
listarlo bajo Certificaciones — es experiencia laboral, y presentarla como
credencial es exactamente el tipo de inflado que prohíbe la regla de
veracidad del proyecto.

### S — Encaje de rol (25%)

Título del puesto contra los títulos objetivo del CV gap, más solapamiento
de responsabilidades. No premiar por decir ambos "remoto" u "online".

### D — Determinista (50%)

```
D = 0.30·pago + 0.30·residencia + 0.20·idioma + 0.20·experiencia
```

Renormalizar sobre los componentes que sí se pudieron leer (misma regla que
career): si falta uno, se cae y se reparte, nunca se puntúa 0 por
desconocido.

**Pago (30%)** — `pago = 0.6·importe + 0.4·hours_guarantee·100`

Calibrado a la banda 1.000–3.000 €:

| Importe (€/mes equivalente) | sub |
|---|---|
| 2.500 – 3.000 | 100 |
| 2.000 – 2.499 | 90 |
| 1.500 – 1.999 | 75 |
| 1.200 – 1.499 | 60 |
| 1.000 – 1.199 | 45 |
| > 3.000 (verificado, `above_band`) | 100 |
| No publicado (bloque B) | 50 |

**Residencia (30%)**

| Situación | sub |
|---|---|
| Contrata explícitamente en **Portugal** | 100 |
| UE / EEE completa | 100 |
| Global / anywhere | 95 |
| "Zona horaria europea" sin lista de países | 75 |
| `remote-unclear` tras la cascada | 50 |
| Exige cobertura en huso horario de EE.UU. | −15 sobre lo anterior |

**Idioma (20%)**

| Situación | sub |
|---|---|
| Español principal (con o sin inglés básico) | 100 |
| Bilingüe ES/EN con inglés funcional | 85 |
| Inglés B2 requerido | 50 (+ cap 65) |
| Inglés C1+/nativo requerido | 20 (+ cap 55) |
| Portugués principal | 100 |

**Experiencia exigida (20%)**

| La oferta pide | sub |
|---|---|
| Sin experiencia / entry level / "te formamos" | 100 |
| 1 año valorable (no excluyente) | 85 |
| 1–2 años requeridos | 60 |
| 3+ años requeridos | 30 |

## Presentación

Tabla solo con `match_score ≥ 70`; 60–69 al caché como `status: skipped`.

**Orden: sueldo descendente, y a igualdad de sueldo primero las que no
piden experiencia** — así lo pidió el usuario. Es una desviación deliberada
del orden por ROI de career mode; el `match_score` va igual como columna
para que se vea si un sueldo alto viene con un encaje malo.

Columnas obligatorias (los 7 campos pedidos + 3 del pipeline):

| Empresa | Puesto | Sueldo | Países aceptados | Requisitos | Publicado | Link | Match | Horas garant. | Contrato |

`Contrato` = tipo real de relación (`indefinido` · `temporal` ·
`freelance-contrato` · `no especificado`). Es una columna, no un adorno: es
lo que separa este modo de la versión anterior con colas de tareas.

**Dos bloques**, en este orden:

- **A — Sueldo confirmado en banda (1.000–3.000 €)**
- **B — Sueldo no publicado** (mismos gates, sin cifra verificada)

Y una línea de exclusiones: cuántas cayeron por G4 anti-fraude, cuántas por
**G2b modelo de contratación** (colas de tareas, encuestas, clics), cuántas
por residencia y cuántas por banda salarial.
