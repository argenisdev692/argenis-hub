# Scoring del modo video-content (Course Creator / píldoras, ES)

Tercer miembro de la familia GUIDE, junto a `cv-job-studio` (career) y
`cv-gap-remote-es` (gap). Toda cifra es **heurística**, nunca un score de ATS
de proveedor.

Hasta 2026-08-26 este modo **no tenía scoring ni gates de residencia**: solo un
gate de idioma del contenido. Eso es lo que produjo el fallo de ADR Formación
(ver § "El fallo de ADR"). Este fichero define la capa que faltaba.

## Los dos ejes que este modo confundía

El error de diseño original fue tratar **un solo eje** ("solo español") cuando
en realidad hay dos, y son ortogonales:

| Eje | Pregunta | Dónde se decide |
|---|---|---|
| **Idioma del contenido** | ¿En qué idioma se graba/escribe el curso? | G3 — debe ser español |
| **Residencia exigida** | ¿Dónde tiene que vivir la persona? | **G1 — nuevo** |

El candidato **entrega en español** y **reside en Covilhã (Portugal)**. Una
oferta española puede querer las dos cosas en España. Confundir los ejes es
exactamente lo que costó la candidatura de ADR.

## Fórmula

```
gates (G1…G6)  →  H, S, D  →  match_score = round( min( 0.30·H + 0.25·S + 0.45·D , caps ) )
                              →  tabla si ≥ 70, ordenada por ROI_app
```

| Modo | H | S | D | Qué decide el puesto |
|---|---|---|---|---|
| Career (`fullstack cv/`) | **0.45** | 0.25 | 0.30 | el stack |
| **Video (`video content cv/`)** | **0.30** | **0.25** | **0.45** | **residencia, tarifa y modelo de pago** |
| Gap (`gap cv/`) | 0.25 | 0.25 | 0.50 | la logística |

**Por qué 0.30 para H, y no 0.45 ni 0.25.** Las herramientas de este nicho
(Articulate Storyline/Rise, Camtasia, SCORM, LMS, guion/storyboard, locución,
FFmpeg/Premiere) **sí** discriminan — el propio caché ya excluyó a Tradumedia
por el hueco de Articulate y marcó gaps en Revolutia. Pero, a diferencia de
Laravel en career mode, son herramientas que se aprenden en semanas y que
varias empresas enseñan ellas mismas (ADR ofrece formación en su herramienta de
autor). Ni muralla ni irrelevante: 0.30.

**Por qué 0.45 para D.** Residencia, tarifa efectiva, modelo de pago y contrato
son donde este nicho se gana o se pierde, y son donde el pipeline falló de
verdad. La cuota más grande va al fallo que realmente ocurrió.

### Reparto interno de D

```
D = 0.35·residencia + 0.25·pago + 0.20·modelo_pago + 0.20·idioma
```

Renormalizar sobre los componentes **legibles** — misma regla que career y gap:
si uno no se puede leer del anuncio, se cae y su peso se reparte entre los
demás. **Nunca puntuar 0 un desconocido.**

## Gates

Corren **antes** de puntuar. Un gate fallado no entra en la tabla y no se
cachea como match puntuado (va a `runs.notes` + `excluded_breakdown`).

### G1 — Residencia exigida (el gate que faltaba)

Base del candidato: **Covilhã, Portugal (UE)**. Entrega en español desde
Portugal.

**La palabra "remoto" no decide nada aquí.** Lo que decide es el **vehículo
contractual**, porque es lo que determina si la empresa necesita una entidad
legal donde el candidato vive:

| Vehículo | Efecto sobre la residencia |
|---|---|
| **Nómina / contrato laboral / payroll / W2 / EOR en país** | La empresa necesita entidad y alta en la seguridad social **donde vive el candidato** → la residencia es un muro real |
| **Mercantil / freelance / factura / contractor / B2B** | El candidato factura desde Portugal → la residencia normalmente es irrelevante |

Taxonomía:

| Etiqueta | Señales | Gate |
|---|---|---|
| `contractor-global` | "contractor", "factura", "freelance sin restricción", "work from anywhere", pago vía Deel/Payoneer/Wise | **PASS** |
| `remote-eu` | UE / EEE / Europa / EMEA | **PASS** |
| `remote-pt` · `remote-pt-es` | Portugal, o Iberia | **PASS** |
| `remote-es-locked` | "remoto **dentro de España**", "residencia en España", "alta en RETA/Seguridad Social española", "imprescindible residir en España" | **FAIL** |
| `remote-latam-locked` | "desde cualquier país de Latinoamérica", agencia que vende talento LatAm a clientes US, lista cerrada de países LatAm | **FAIL** |
| `remote-country-locked` | "anywhere in the U.S.", W2, "must be based in \<país ≠ PT/UE\>", contrato atado a un país concreto | **FAIL** |
| `residency-unclear` | Solo "remoto", sin vehículo contractual ni país legibles | **PASS** con `residencia ≤ 60` y bucket `2_verificar_antes` |

**`residency-unclear` no capa el score final.** Se probó capar a 69 y **vaciaba
la tabla**: 5 de las 6 empresas reales del caché caían al mismo tiempo. Un
canal freelance español sin letra pequeña publicada no es un rechazo, es una
pregunta pendiente. Por eso la penalización vive en el sub-score (techo 60) y
la advertencia vive en el bucket de triaje, no en un cap.

#### El fallo de ADR — por qué existe este gate

ADR Formación estaba cacheada como
`modality: "colaboración mercantil / red de autores (remoto posible)"`. Se
envió candidatura y la respuesta fue **remoto solo para residentes en España**.

Dos lecciones, las dos codificadas arriba:

1. El caché registraba la *modalidad* pero **nunca la residencia**. Campo nuevo
   obligatorio: `hiring_residency`.
2. "Relación mercantil" en el marketing **no garantiza** que no haya muro de
   residencia. Por eso existe `residency_verified` y el bucket
   `2_verificar_antes`: la clasificación se saca del texto, pero hasta que no
   está confirmada por escrito no se presenta como vía libre.

#### La expansión a LatAm agravó este gate — y por eso se revirtió

> **LatAm salió del pipeline el 2026-08-26 (tarde), por decisión del usuario.**
> Esta sección se conserva como **registro de por qué**, no como ámbito vigente.
> No sembrar México, Centroamérica ni LatAm sur. Una oferta LatAm hallada de
> rebote sigue siendo `remote-latam-locked` → G1 FAIL.
>
> Lo que se midió antes de cerrarlo, sobre 27 JD leídos: **22 (81%) con muro de
> residencia · 0 con vehículo válido desde Portugal · las 2 únicas tarifas
> publicadas fueron 4,12 y 5,29 EUR/h** contra un suelo que entonces era 15.

Hallazgo de la ronda Tavily del 2026-08-26: el mercado "LatAm que paga en USD"
está dominado por intermediarios que exigen **residencia en LatAm** — Virtual
Talent LATAM ("work from anywhere in Latin America"; su propia FAQ pregunta
"Can I apply if I live outside Colombia?"), Freelance Latin America (vacantes
etiquetadas México/Argentina/Honduras/Nicaragua/Colombia/Chile), ProLatamWork,
eVirtualAssistants, Pros Marketplace. Su producto ante el cliente
estadounidense **es** el arbitraje geográfico LatAm; un residente en Portugal
es justo lo que no pueden vender.

Y no es solo el intermediario: INFUSE publica el mismo puesto de Instructional
Designer (Contract, Remote) por separado para Letonia, Serbia, Eslovenia y
Croacia — empresa global, contrato atado a país.

**Sin G1, ampliar a LatAm multiplicaría el fallo de ADR por un continente.**

#### El hallazgo espejo — la pared cambia de naturaleza según la geografía

Ronda de canal directo del 2026-08-26. Con LatAm ya fuera, el ámbito quedó en
España + UE + `contractor-global`, y apareció una simetría que conviene tener
presente **antes** de gastar extract:

| Geografía | Vehículo contractual | Idioma del entregable | Dónde cae |
|---|---|---|---|
| **España** | ✘ nómina | ✔ español | **G1** |
| **Resto de la UE** | ✔ contractor | ✘ inglés | **G3 + cap 55** |
| **Intersección buscada** | ✔ mercantil | ✔ español | — |

Evidencia:

- **Lemon Learning** — *"Contract: Freelance / Independent Contractor"*,
  *"Location: Europe or United States (remote)"*. Es el **primer `remote-eu` +
  contractor de todo el caché**: exactamente el vehículo que se buscaba. Y exige
  *"Language: Native English required"* → G3 FAIL + cap 55.
- **Codeway** (Barcelona) — produce contenido **en español**, y ofrece
  *"Full-time contract"*, seguro privado, tarjeta de comida y reubicación a
  Barcelona: nómina española pese a admitir *"fully remote"*. → G1 FAIL.
- Igual **ThePowerMBA** (*"Contrato indefinido a tiempo completo"* + *"Oficinas
  en Madrid"*) y **The Globe Formación** (indefinido + 80% presencial).

**Consecuencia operativa:** en el tier UE, verificar el **idioma del entregable
antes que la residencia** — allí es el gate que más cae. En España, al revés:
verificar el **vehículo** antes que nada. La intersección existe y vive en los
canales de colaborador de las edtech españolas, que es justo de donde salieron
los tres canales vivos del caché (OpenWebinars, Nanfor, Imagina).

### G2 — Rol: producción instruccional, no marketing de contenidos

**PASS** — el trabajo es producir contenido **formativo**: grabar cursos,
escribir cursos, guion/storyboard instruccional, crear laboratorios, crear
ejercicios, revisar/curar contenido, diseño instruccional, locución de curso,
edición de píldoras formativas.

**FAIL — lista negativa.** "Creador de contenido" es un homónimo y en LatAm su
sentido por defecto es **marketing en redes**:

`UGC` · `redes sociales` · `TikTok` · `Reels` · `Shorts` · `community manager` ·
`influencer` · `social media` · `brand content` · `marketing de contenidos` ·
`copywriter de marca` · `streaming`

Excepción: pasa si el anuncio **además** trae señal formativa explícita
(`formación`, `curso`, `capacitación`, `e-learning`, `alumnos`, `LMS`).

**Por qué es gate y no sub-score.** La ronda Tavily de México
("creador de contenido / cápsulas / videotutoriales capacitación") devolvió
casi solo puestos de marketing: TikTok Content Specialist, Social Media
Manager, Community Manager, Editor Creativo. Si esto se dejara a `S` (25%), una
vacante de marketing con buena tarifa y residencia limpia superaría 70. Es la
misma lección que career mode ya aprendió con el idioma en G4b: *lo que en la
práctica es un filtro duro no puede vivir como el 25% de una media ponderada.*

En España `formación` / `píldoras formativas` desambigua solo. En LatAm no.

### G3 — Idioma del contenido

El contenido a grabar/escribir debe ser **español** (España o LatAm neutro).

| Caso | Acción |
|---|---|
| Contenido en español, mercado ES o LatAm | PASS |
| Bilingüe ES+EN, brief principal en español | PASS (anotar EN como plus; no inventar nivel) |
| Contenido a producir en inglés como requisito principal | FAIL |
| Contenido en portugués (PT-PT) | PASS — nivel residente. Marcar `content_language: pt-PT` |

Nótese que esto es sobre el **idioma del entregable**, no sobre dónde vive
nadie. Ese es G1.

### G4 — Calidad y frescura del anuncio, por tipo de entrada

Este nicho mezcla dos cosas que no se pueden datar igual, así que se separan:

| `entry_type` | Qué es | Regla |
|---|---|---|
| `vacante` **con fecha de cierre futura publicada** | El anuncio dice hasta cuándo admite candidaturas | **La fecha de cierre manda.** Abierta si el cierre aún no ha pasado, sin importar cuándo se publicó |
| `vacante` sin fecha de cierre | Oferta concreta con fecha de publicación | **≤ 3 días**. Sin fecha visible → excluir. Misma lección que gap mode aprendió con el anuncio de Majorel de 2023 |
| `canal_abierto` | Página evergreen de "colabora con nosotros" / red de autores / bolsa | La frescura no aplica. En su lugar **verificar que el canal sigue vivo** (formulario abierto, dirección que no rebota) y anotar `channel_checked` con fecha |

Nunca listar un `canal_abierto` como si fuera una vacante abierta.

#### Por qué la fecha de cierre gana a la ventana de días

Decisión del usuario, **2026-08-26**. La ventana de días es un *proxy* de la
pregunta que de verdad importa: ¿sigue abierta? Una fecha de cierre futura
publicada por la propia empresa **responde esa pregunta directamente**, así que
es evidencia más fuerte que "publicado hace 3 días", no más débil.

El caso que lo destapó: Revolutia.ai, publicada hace ~1 mes pero con cierre
declarado el 30/8/2026. Bajo la regla anterior se habría excluido una vacante
demostrablemente viva.

Guardar en `posted` **las dos** cosas cuando existan: fecha de publicación y
fecha de cierre. La segunda es la que decide.

**La contrapartida es que la ventana sin fecha de cierre se estrecha de 7 a 3
días** (misma decisión). La lógica es simétrica: si la empresa dice hasta cuándo
admite, se confía en ella; si no dice nada, se exige que el anuncio sea
prácticamente de hoy. La holgura de 7 días existía para absorber esa
incertidumbre y ya no hace falta cargarla sobre los anuncios que sí se datan.

**Un cierre ya pasado es FAIL**, igual que un "ya no se aceptan solicitudes"
(caso Levelab Technologies, misma corrida).

### G5 — Suelo de tarifa efectiva (arbitraje invertido)

```
PAY_FLOOR = 19,5 EUR/h efectivos      ← ACTUALIZADO por el usuario, 2026-08-26 (tarde)
                                        (antes 15; subido a "tarifa española + 4,5")
```

Tarifa efectiva confirmada **por debajo del suelo → FAIL**, por bueno que sea
el encaje.

**Por qué hace falta un gate y no basta un sub-score bajo.** En la verificación
numérica, un marketplace LatAm a 6 USD/h con residencia e idioma limpios
puntuaba **71** y entraba en la tabla. La tarifa mala se diluía en la media.

**Actualización del 2026-08-26 (tarde): el suelo pasa de 15 a 19,5 EUR/h.**
Decisión del usuario, formulada como *"mi tarifa será 4-5 euros superior a la de
Europa español"*. Como ninguna empresa española ha publicado tarifa en tres
corridas, no hay una "tarifa España" medida a la que sumar, así que se aplicó
sobre el suelo vigente: 15 + 4,5. Si en el futuro se mide una tarifa española
real, revisar este número contra ella.

**Por qué el suelo se calibra en Portugal y no en el mercado de origen.** Los
intermediarios LatAm publican tarifas construidas sobre el coste de vida
LatAm — eVirtualAssistants anuncia "$5–$18 avg rates", Pros Marketplace lista
perfiles a 12–20 USD/h y vende "Save 40–70% vs US Hires". El candidato vive con
costes de la UE: **está en el lado equivocado de ese arbitraje**. La tarifa
LatAm es un descuento diseñado para la estructura de costes de otra persona.

La expansión a LatAm merece la pena por el **cliente que paga en USD a tarifa
internacional**, no por la agencia que revende mano de obra barata. G5 es lo
que separa las dos.

### G6 — Modelo de pago

| `pay_model` | Qué es | Trato |
|---|---|---|
| `fee_fijo` | Por hora, por minuto acabado, por módulo o por proyecto | **Bloque A** |
| `mixto` | Anticipo fijo + royalties | **Bloque A**, anotando el reparto |
| `royalty_puro` | El ingreso depende solo de matrículas/visualizaciones | **Cap 69** → nunca en la tabla; se cachea. **Nunca** se le asigna una cifra de € |
| `impago` | "visibilidad", "portfolio", "exposición" | **FAIL duro** |

**Por qué `royalty_puro` se capa en vez de fallar.** Es el equivalente en este
nicho a las colas de micro-tareas de gap mode: el título es correcto, el modelo
no paga el alquiler. Evidencia recogida: entrenarme.com ("modelo de pago por
estudiante"), Tutellus (reparto 70-30), ADR (~40% royalties, según su propio
LinkedIn); y sobre Udemy, el 90% de los cursos se vende por debajo de 50 € y la
mayoría no factura ni 100 €/mes. Pero **no** es fraude y un royalty encima de
un fee fijo es normal y sano en la industria — por eso se separa en bloque en
lugar de rechazarse, al contrario que G2b en gap mode.

Nunca proyectar ingresos de un `royalty_puro`. No hay base para estimarlos.

## Normalización económica

### Divisa

Llevar todo a **EUR**. USD, MXN, COP, ARS y CLP son todos esperables tras la
expansión.

Regla heredada de gap mode, sin cambios: **aplicar un tipo reciente y
declararlo junto a la fecha** ("1 USD ≈ 0,92 EUR, tipo asumido 2026-08-26").
Nunca inventar un tipo con precisión falsa. Guardar siempre la cifra original
y la divisa original junto a la convertida.

### La unidad real del nicho: el minuto acabado

En producción e-learning la tarifa se publica muchas veces **por minuto de
vídeo acabado**, no por hora trabajada. Las dos no son comparables sin conocer
la productividad:

```
tarifa_efectiva (EUR/h) = tarifa_por_minuto_acabado / R

R = horas de trabajo por minuto de vídeo acabado
    (guion + grabación + edición + revisión)
```

**R no se inventa.** El CV da volumen de contenido (curso de Claude: 7 h en 48
vídeos), no horas de producción, así que R **no es derivable** de la evidencia
del CV.

```
R = 4 a 6 h por minuto acabado     ← CONFIRMADO por el usuario, 2026-08-26
```

Es una **banda, no un punto**, y se trata como tal:

- Calcular siempre los **dos extremos** y presentar el resultado como banda:
  `tarifa_efectiva ∈ [tarifa_min / 6 , tarifa_min / 4]`.
- Imprimir el supuesto junto al resultado, igual que el tipo de cambio.
- El sub-score de pago usa el **punto medio** (R = 5); G5 usa la regla de los
  tres tramos de abajo.

### Umbrales derivados (suelo 19,5 EUR/h, R ∈ [4,6])

| Tarifa publicada por minuto acabado | Tramo | Acción |
|---|---|---|
| **< 78 EUR/min** | Ni en el mejor caso (R=4) llega al suelo | **G5 FAIL** |
| **78 – 116 EUR/min** | La banda cruza el suelo: cumple si R=4, no si R=6 | **PASS** + bucket `2_verificar_antes`, preguntando alcance real del encargo |
| **≥ 117 EUR/min** | Cumple incluso en el peor caso (R=6) | **PASS** limpio |

*(19,5 × 4 = 78 · 19,5 × 6 = 117. Umbrales anteriores con suelo 15: 60 / 90.)*

Nunca resolver el tramo intermedio eligiendo el extremo que conviene. Se
declara que la viabilidad depende de R y se pregunta.

Misma disciplina que el `× 160 h` de gap mode: una tarifa por minuto convertida
a EUR/h es una **equivalencia bajo un supuesto declarado**, no un ingreso
prometido.

Misma disciplina que el `× 160 h` de gap mode: una tarifa por minuto convertida
a EUR/h es una **equivalencia bajo un supuesto declarado**, no un ingreso
prometido.

### Sub-score de pago (25% de D)

Sobre tarifa efectiva en EUR/h, calibrada a coste de vida en Portugal:

| EUR/h efectivos | sub |
|---|---|
| ≥ 52 | 100 |
| 39 – 51 | 90 |
| 29 – 38 | 78 |
| 23 – 28 | 65 |
| 19,5 – 22 | 50 |
| < 19,5 | **G5 FAIL** |
| No publicado | 50 (y va a bloque B) |

Reescalado proporcional del suelo anterior (×1,3), conservando la forma: el
tramo pegado al suelo puntúa 50 y el 100 llega en torno a 2,7× el suelo.

**Efecto retroactivo comprobado: ninguno.** Las dos entradas puntuadas del
caché (Revolutia 67, Sala Conectada 63) tienen el pago **no publicado**, y esa
fila vale 50 con independencia del suelo. Sus D siguen en 68,5 y sus
`match_score` no se mueven. La batería de § "Verificación numérica" tampoco
cambia de veredicto: su único caso con tarifa publicada era el marketplace LatAm
a 6 USD/h, que sigue siendo **G5 FAIL** (ahora con más margen).

## Sub-scores restantes de D

**Residencia (35%)**

| Situación | sub |
|---|---|
| `contractor-global` con pago internacional confirmado | 100 |
| `remote-eu` / `remote-pt` / `remote-pt-es` | 100 |
| Español entregable desde fuera de España, confirmado por escrito | 95 |
| `residency-unclear` tras la cascada | **≤ 60** (techo) + bucket `2_verificar_antes` |
| Cualquier `*-locked` fuera de PT/UE | G1 FAIL — no llega a D |

**Modelo de pago (20%)**

| `pay_model` | sub |
|---|---|
| `fee_fijo` con importe publicado | 100 |
| `fee_fijo` sin importe | 75 |
| `mixto` (anticipo + royalty) | 80 |
| `royalty_puro` | 10 (+ cap 69) |

**Idioma (20%)** — se reutiliza G4b de
`.claude/skills/cv-job-studio/references/job-match-scoring.md` sin cambios.
Base: **ES nativo · PT nivel residente · EN B1**. Leer el piso, no el techo.

| Situación | sub |
|---|---|
| Español principal | 100 |
| Portugués principal | 100 |
| ES + inglés "valorable" | 85 |
| Inglés B2 requerido | 50 (+ cap 65) |
| Inglés C1+/nativo requerido | 20 (+ cap 55) |

Ojo con el mercado estadounidense de instructional design: paga bien
(30–105 USD/h observados) pero pide inglés nativo **y** residencia en EE.UU.
Suele caer por G1 antes de llegar al cap de idioma.

## Componentes H y S

**H — skills duras (30%).** Misma mecánica que career: presencia exacta/alias,
`required` 1.0 · `preferred` 0.6 · `bonus` 0.25, soft ≤ 15% del denominador,
`credit` 1.0 / 0.5 / 0.0. Si el denominador es 0, `H = 50` y marcar "skills del
anuncio poco claras".

Skills del nicho: Articulate Storyline · Articulate Rise · Camtasia · Adobe
Captivate · SCORM · xAPI · LMS (Moodle, TalentLMS, Docebo) · guion
instruccional · storyboard · locución · screencast · FFmpeg · Premiere ·
DaVinci · Descript · iluminación y audio · diseño instruccional (ADDIE, SAM).

Evidencia real del CV que cuenta fuerte: **formador de IA por contrato** para
Imagina Formación (aulas de Claude AI y Microsoft 365 Copilot + grabación de
píldoras), producción 1080p/30fps, pipeline propio de edición con FFmpeg,
7 h / 48 vídeos, 25 h / 30 alumnos, y el stack de desarrollo como credibilidad
para laboratorios y ejercicios de código.

**Precisión obligatoria** (regla del proyecto): fueron **contratos de
impartición y grabación**, no certificaciones. Nunca escribir "certificado" ni
listarlo bajo Certificaciones. OWASP no aplica a contenido de vídeo.

**S — encaje de rol (25%).** Título y responsabilidades contra
`Course Creator / creador de píldoras y videotutoriales e-learning`. Con G2 ya
filtrando el homónimo de marketing, S hace el trabajo fino: producción vs
docencia en vivo, tema técnico vs genérico, autoría vs mera edición. No premiar
por que ambos textos digan "vídeo" u "online".

## Caps

Se aplican al raw; **gana el más bajo**. Redondear una sola vez, al final.

| Cap | Valor | Disparador |
|---|---|---|
| Idioma C1+/nativo (G4b) | **55** | Inglés nativo o tercer idioma local requerido |
| Idioma B2 requerido (G4b) | **65** | Cae en la banda `skipped` |
| **Evidencia** | **65** | `extract: snippet_only` **y** `H = 50` por skills ilegibles |
| **Royalty puro** (G6) | **69** | El ingreso depende solo de matrículas |

El cap de evidencia es **más estricto aquí (65) que en career (69)** a
propósito: con D al 45% el agujero es más ancho. Una página evergreen de
"colabora con nosotros" tiene residencia e idioma limpios y ninguna lista de
skills legible — sin este cap, `D ≈ 75` y `H = 50` fabrican un 70 con la sola
fuerza de la ubicación. Verificado numéricamente: ese caso da **66 raw → 65**,
que es donde debe estar.

## Priorización: coste de postulación y ROI

Este nicho tiene un coste por candidatura que career y gap no tienen. Evidencia
del propio caché: Revolutia exige vídeo de 4–6 min, Be-skiller vídeo de 2–3 min
("sin vídeo no revisan"), OpenWebinars un proceso de 4 fases que acaba en prueba
audiovisual grabada. Son **horas de producción no pagada por candidatura**.

Dos ofertas con el mismo 76 no valen lo mismo si una cuesta 20 minutos y la
otra 6 horas.

```
C = horas estimadas para postular
ROI_app = match_score / (1 + C)
```

`C` se construye **solo con requisitos observables en el anuncio** — minutos de
vídeo demo pedidos, módulo de muestra, prueba en herramienta de autor, longitud
del formulario. Banda orientativa:

| Requisito | C (h) |
|---|---|
| Enviar CV / email breve | 0,5 |
| Formulario largo o carta específica | 1 |
| Portfolio o enlace a muestra ya existente | 1,5 |
| Vídeo demo 2–3 min | 3 |
| Vídeo demo 4–6 min | 5 |
| Módulo/lección de muestra a medida | 6 |
| Prueba en su herramienta de autor | 8 |

**`ROI_app` es una heurística de priorización — con qué empezar cuando las
horas son finitas. No es una probabilidad de ser contratado.** No se introduce
ningún término de tasa de respuesta: nada en los datos disponibles lo funda, e
inventarlo sería exactamente el tipo de cifra fabricada que prohíbe la regla de
veracidad. `match_score` se sigue mostrando sin tocar, como columna propia.

## Presentación

Tabla solo con `match_score ≥ 70`. 60–69 al caché como `status: skipped`.

**Orden: `ROI_app` descendente.** Es una desviación deliberada del orden por
`match_score` de career mode y del orden por sueldo de gap mode, y responde a
la característica propia de este nicho: aquí postular cuesta horas de estudio
de grabación.

| Empresa | Puesto | Tarifa (orig. → EUR/h ef.) | Modelo | Residencia | Idioma | Publicado | Link | Match | C (h) | ROI |

**Tres bloques:**

- **A — Tarifa confirmada** (`fee_fijo` o `mixto`, importe publicado, ≥ suelo)
- **B — Tarifa no publicada** (mismos gates; **nunca** estimar la cifra)
- **C — Royalty puro** (informativo; sin cifra de ingresos, no es la tabla de aplicar)

Y siempre la línea de exclusiones, aunque vaya a cero:
`excluded_breakdown` = `residencia_locked` · `rol_marketing` (G2 negativa) ·
`idioma_contenido` · `frescura` · `suelo_tarifa` · `impago`.

Cuánta parte del mercado se cae por residencia y cuánta por ser marketing
disfrazado **es parte del resultado** en este nicho, no un detalle interno de
pipeline — misma lógica que el reporte de fraude en gap mode.

## Triaje de enlaces

Igual que gap mode, el caché lleva `apply_links` con tres listas:

- `1_aplicar_ya` — todos los gates pasados y `residency_verified: true`
- `2_verificar_antes` — encaje real, pero falta confirmar algo concreto.
  **Siempre decir qué preguntar.** El caso por defecto aquí:
  *"¿Aceptáis colaborador que factura desde Portugal, o el remoto es solo para
  residentes en España?"* — literalmente la pregunta que habría evitado lo de
  ADR.
- `3_no_aplicar` — descartada, con motivo.

## Verificación numérica

La fórmula, los caps y los gates se validaron sobre las 6 empresas reales del
caché más 6 casos adversarios de la expansión LatAm/USD (2026-08-26):

| Caso | Resultado esperado | Obtenido |
|---|---|---|
| ADR Formación (residencia solo España) | fuera | **58** ✔ |
| Virtual Talent LATAM (solo residentes LatAm) | fuera | **61** ✔ |
| US Instructional Designer (anywhere in U.S. + EN nativo) | fuera | **55** (cap idioma) ✔ |
| Marketplace LatAm 6 USD/h | fuera | **G5 FAIL** ✔ |
| Royalty puro por alumno | fuera de tabla, cacheado | **69** (cap) ✔ |
| Canal evergreen sin skills legibles | banda skipped | **65** (cap evidencia) ✔ |
| Imagina (continuidad) | arriba | **89** ✔ |
| Agencia MX contractor global 25 USD/h | arriba | **84** ✔ |
| Nanfor · Be-skiller · OpenWebinars · Revolutia | tabla, con verificación | **78 · 76 · 75 · 70** ✔ |

Dos defectos se encontraron **por la verificación, no por la teoría**, y están
corregidos arriba:

1. Capar `residency-unclear` a 69 **vaciaba la tabla** (5 de 6 empresas reales
   caían a la vez). Por eso la penalización es de sub-score, no un cap.
2. El marketplace LatAm a 6 USD/h **colaba a 71** con un simple sub-score bajo.
   Por eso el suelo de tarifa es un gate previo (G5).

El orden por ROI también quedó validado como no redundante: Nanfor (78, C=1)
da ROI 39 y OpenWebinars (75, C=6) da ROI 10,7 — puntuaciones casi iguales,
prioridad muy distinta.

Al recalibrar cualquier peso, **volver a correr esta batería** antes de
adoptarlo.
