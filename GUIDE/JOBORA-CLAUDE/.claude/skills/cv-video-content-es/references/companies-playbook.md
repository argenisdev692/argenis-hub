# Playbook — Course Creator / píldoras / videotutoriales (español, España + UE)

Ámbito vigente desde el **2026-08-26 (tarde)**: **España + resto de la UE +
`contractor-global`**, siempre que la contratación sea posible **residiendo en
Portugal**. **LatAm quedó fuera del pipeline** ese mismo día, tras probarlo y
medirlo (ver Tier 4 eliminado).

**El descubrimiento va por canal directo de empresa, no por portales de empleo.**
Los portales están estructuralmente sesgados hacia la contratación por país.

Los gates y la fórmula viven en
[video-scoring.md](video-scoring.md). Este fichero es descubrimiento:
dónde buscar y con qué palabras.

## La regla que gobierna el ámbito

> Lo que se busca es la **intersección estrecha**: contenido en español +
> vehículo mercantil/contractor + facturable desde Portugal.

El candidato entrega en español desde **Covilhã, Portugal**. Por tanto:

| Se busca | No se busca |
|---|---|
| Academia o edtech española con **relación mercantil** | Academia española con **nómina y alta en España** |
| Estudio de e-learning de la UE que paga **por factura** | Empresa de la UE cuyo entregable es **en inglés** |
| Cliente global que necesita curso **en español** y contrata como contractor | Vacante "anywhere in the U.S." / W2 |
| Canal propio de empresa ("colabora", "sé formador") | Portal de empleo con inventario atado a país |

**Las dos paredes, que son distintas y hay que leer por separado:** en España la
pared es el **vehículo** (nómina); en la UE la pared es el **idioma del
entregable** (inglés).

Esto es G1. Ver el caso ADR en `video-scoring.md` — es el fallo que originó
esta regla.

## Geografías y tiers

### Tier 1 — España (mercado histórico, mayor densidad en español)

Semillas: OpenWebinars · Imagina Formación · KeepCoding · Founderz · Nanfor ·
Be-skiller · Revolutia.ai · isEazy · Tradumedia · thePower · ADR Formación
(*ya descartada por residencia — mantener en caché como `3_no_aplicar` para no
reproponerla*).

Portales: InfoJobs · LinkedIn Jobs ES · Domestika (bolsa) · Tecnoempleo ·
webs propias "trabaja con nosotros" / "colabora" / "sé formador".

**Atención al patrón español:** "remoto" en una oferta con nómina significa casi
siempre *remoto dentro de España* por la Seguridad Social. Priorizar las que
digan **mercantil, freelance, colaborador, factura, autónomo**.

### Tier 2 — Resto de la UE que produce contenido en español

Estudios de e-learning, editoriales formativas y departamentos de L&D en la UE
que localizan o producen en español. Contratan por factura con más naturalidad
que la nómina española.

**Aviso del hallazgo espejo (2026-08-26):** aquí el vehículo contractual suele
ser correcto y **el idioma del entregable se vuelve inglés**. Lemon Learning
ofrece justo `remote-eu` + Independent Contractor y exige *native English*
(G3 FAIL + cap 55). Verificar SIEMPRE el idioma del contenido antes de invertir
extract: en este tier es el gate que más cae, no la residencia.

### Tier 3 — `contractor-global` puro

Cliente de cualquier país que necesita contenido **en español** y contrata
**por factura internacional**. Señales: Deel / Payoneer / Wise / Remote.com,
"contractor", "invoice", "work from anywhere" sin lista cerrada de países.

Es el tier más valioso y el más escaso: en 27 JD leídos el 2026-08-26 no salió
**ninguno** con idioma español. Se busca por canal directo, no por portal.

### ~~Tier 4 — LatAm~~ · ELIMINADO 2026-08-26

**LatAm sale del pipeline por decisión del usuario.** No sembrar México,
Centroamérica, LatAm sur ni intermediarios que revenden talento LatAm. No
reintroducirlos en un refresco de keywords.

Se probó y se midió el mismo día: de 27 JD leídos, **22 (81%) con muro de
residencia, 0 con vehículo válido desde Portugal**, y las dos únicas tarifas
publicadas fueron **4,12 EUR/h** (Instituto Laureana Wright, 13.000 MXN/mes a
tiempo completo) y **5,29 EUR/h** (OTIC Proforma, 900.000 CLP/mes), contra un
suelo de 15 EUR/h. Se cerró por evidencia, no por preferencia.

Una oferta con residencia LatAm encontrada de rebote sigue siendo
`remote-latam-locked` → G1 FAIL, como siempre.

### Tier 4 — Clientes globales / EE.UU. que producen en español

El tramo mejor pagado (30–105 USD/h observados en instructional design), pero
el que más cae por G1.

Vía principal: **la web de la propia empresa** (careers / "work with us" /
"become an instructor"). Los agregadores quedan como comprobación secundaria,
no como punto de partida: Working Nomads · RemoteLeaf · Remote Rocketship ·
We Work Remotely · Jobgether.

**Aviso medido (2026-08-26):** Remote Rocketship etiqueta "Spanish Required" y
en las 4 ofertas observadas las 4 llevaban **bandera de país** (Colombia, Chile,
Brasil, Surinam). En este nicho **"Spanish Required" es señal de país, no de
oportunidad**.

**Filtro de eficiencia:** priorizar `contract`/`contractor`/`freelance`/
`worldwide`/`anywhere`. Descartar en el snippet `W2`, `anywhere in the U.S.`,
`must be authorized to work in`, y toda lista cerrada de países. Ahorra cascada
de extract — misma lógica que el `skipped_g4_snippet` de career mode.

### Plataformas de pago (contexto, no objetivos)

Deel · Payoneer · Wise · Remote.com · Oyster. **No son ofertas**: son la señal
de que el vehículo contractual es de contractor internacional. Que un anuncio
las mencione es evidencia fuerte de `contractor-global` en G1.

## Vocabulario por país — sólo España y UE

> Las columnas de México y LatAm sur se conservan **como referencia de lectura**
> (para reconocer un anuncio LatAm y descartarlo por G1), **no como vocabulario
> de búsqueda**. No sembrar queries con ellas: LatAm salió del pipeline el
> 2026-08-26.

La misma profesión cambia de nombre según el país. **Sembrar sólo con las
columnas de España y Global/EN.** Las de México y LatAm sur se dejan para
*reconocer* un anuncio LatAm y descartarlo por G1, no para buscarlo.

| Concepto | España | México / Centroamérica | LatAm sur | Global / EN |
|---|---|---|---|---|
| Formar | formación | **capacitación** | capacitación, formación | training |
| Vídeo corto | **píldoras formativas** | **cápsulas**, cápsulas de video | microcontenido, micro-learning | microlearning |
| Curso grabado | videotutoriales, teleformación | **cursos en línea**, curso grabado | curso virtual, clases grabadas | recorded course |
| Quien lo diseña | diseñador instruccional, autor de contenidos | **diseñador instruccional** | diseñador instruccional | instructional designer |
| Quien lo escribe | guionista e-learning | **guion instruccional** | guionista de contenidos | scriptwriter, storyboarder |
| Quien lo graba | formador, productor de contenido | **instructor**, tallerista | facilitador, capacitador | course creator, SME |
| Marco | FUNDAE, teleformación | capacitación en línea, **DC-3, STPS** | educación virtual | L&D, LMS |

### Lista de keywords (usar literalmente en las queries)

**Núcleo pedido por el usuario** — mantener siempre:
`Course Creator` · `grabar cursos` · `escribir cursos` · `crear laboratorios` ·
`crear ejercicios` · `revisar contenido`

**Vocabulario ampliado (España / UE / EN):**
`cápsulas de video` · `cápsulas formativas` · `píldoras formativas` ·
`videotutoriales` · `diseñador instruccional` · `diseño instruccional` ·
`guion instruccional` · `guionista e-learning` · `storyboard formativo` ·
`autor de cursos` · `autor de contenidos e-learning` · `productor de contenido
formativo` · `capacitación en línea` · `curso virtual` · `microlearning` ·
`locución de cursos` · `screencast` · `edición de píldoras` ·
`revisión de contenido formativo` · `SME` (subject matter expert) ·
`instructional designer Spanish` · `eLearning content developer Spanish` ·
`bilingual course developer`

Quedan **fuera de las semillas** por la salida de LatAm: `cápsulas de video`,
`cápsulas formativas`, `capacitación en línea`, `curso virtual`. Se mantienen
sólo como vocabulario de lectura.

### Keywords **negativas** (G2) — nuevas y obligatorias

Excluir de discovery salvo que el anuncio traiga además señal formativa
explícita (`curso`, `formación`, `capacitación`, `alumnos`, `LMS`):

`UGC` · `redes sociales` · `TikTok` · `Reels` · `Shorts` · `community manager` ·
`influencer` · `social media` · `brand content` · `marketing de contenidos` ·
`streaming` · `copywriter de marca`

**Por qué (la lección sigue valiendo aunque LatAm ya no se siembre).** La ronda
de prueba en México con "creador de contenido / cápsulas / videotutoriales
capacitación" devolvió casi solo marketing en redes (TikTok Content Specialist,
Social Media Manager, Community Manager, Editor Creativo). En España
`formación` desambigua sola; fuera de España **"creador de contenido" significa
influencer por defecto**. Sin lista negativa, cualquier ampliación
geográfica llena la tabla de otra profesión.

En Tavily, usar `exclude_domains` para las redes y añadir los términos
negativos al texto de la query cuando el motor lo permita.

## Queries Tavily

`search_depth: "advanced"`, `max_results: 8–10`, `country` según el tier,
`time_range: "day"` (o `start_date` a 3 días) para `vacante` — G4 pide **≤ 3
días** cuando el anuncio no publica fecha de cierre; sin `time_range` para
`canal_abierto`.

**Ojo con no estrechar el descubrimiento de más.** El límite de 3 días aplica al
*gate*, no necesariamente a la query. Una vacante con **fecha de cierre futura
publicada** pasa G4 por antigua que sea (regla nueva, 2026-08-26), y esas no
aparecerán si la query se limita a 3 días. Para los portales que publican fecha
de cierre — BeBee, JobLeads, agregadores — merece la pena una pasada con
`time_range: "month"` y dejar que G4 decida por la fecha de cierre.

### Ronda 1 — CANAL DIRECTO DE EMPRESA (prioritaria desde 2026-08-26)

**Se empieza por aquí, no por los portales.** Los portales están sesgados hacia
la contratación por país; los tres canales vivos que hay en caché
(OpenWebinars, Nanfor, Imagina) salieron todos de esta ronda.

1. `site:<dominio> colabora OR "trabaja con nosotros" OR "sé formador" OR "quieres ser profesor" OR "crea cursos con nosotros"`
2. `empresa formación online España "colabora con nosotros" crear grabar cursos colaborador autónomo factura`
3. `("escuela online" OR "academia online" OR edtech OR bootcamp) España "buscamos formadores" OR "buscamos autores" contenido grabado`

Semillas de dominio a barrer: openwebinars.net · imaginaformacion.com ·
nanfor.com · be-skiller.com · revolutia.ai · keepcoding.io · founderz.com ·
tokioschool.com · iebschool.com · structuralia.com · euroinnova.es ·
iseazy.com · tradumedia.com · evolmind.com · thepower.education
(ADR queda excluida: G1 confirmado).

### Ronda 2 — España, oferta concreta con vehículo mercantil

4. `"Course Creator" OR "autor de contenidos eLearning" OR "píldoras formativas" OR videotutoriales España freelance mercantil colaborador`
5. `"grabar cursos" OR "escribir cursos" OR "crear laboratorios" OR "crear ejercicios" OR "revisar contenido" e-learning España colaborador`
6. `"buscamos" (guionista OR "diseñador instruccional" OR "autor de cursos") (e-learning OR píldoras OR videotutorial) España`

**Patrón español, recordatorio:** remoto + nómina = remoto dentro de España.
Priorizar **mercantil, freelance, colaborador, factura, autónomo**.

### Ronda 3 — UE que produce en español

7. `"instructional designer" OR "eLearning content developer" Spanish content freelance contractor Europe remote invoice`
8. `estudio e-learning OR "content factory" formación español Europa colaborador freelance factura`

Verificar **idioma del entregable** antes que nada: es el gate que más cae aquí.

### Ronda 4 — `contractor-global` con contenido en español

9. `"Spanish" ("eLearning content" OR "course creator" OR "curriculum developer") remote freelance contractor invoice worldwide`
10. `contenido formativo español "pago por factura" OR Deel OR Payoneer OR Wise contractor remoto internacional`

Descartar en snippet: `W2`, `anywhere in the U.S.`, `must be authorized to work in`,
y toda lista cerrada de países.

## Triaje en el snippet — antes de gastar extract

Orden obligatorio, para no quemar presupuesto (misma disciplina que gap mode
con su gate anti-fraude):

```
Tavily
  → G2 negativa (¿es marketing en redes?)        ← descarte más barato
  → G1 residencia sobre el snippet               ← "anywhere in the U.S.", W2,
                                                    "residir en España",
                                                    "desde cualquier país de LatAm"
  → G3 idioma del contenido
  → G4 frescura / tipo de entrada
  → cascada de extract solo sobre las supervivientes
  → G5 suelo de tarifa · G6 modelo de pago
  → H / S / D → match_score
```

Señales que en el snippet ya deciden `remote-*-locked` y ahorran el extract:
`W2` · `anywhere in the U.S.` · `must be authorized to work in` ·
`residencia en España` · `alta en Seguridad Social` · `RETA` ·
`from anywhere in Latin America` · lista cerrada de países LatAm.

Anotar `extract: skipped_g1_snippet` en `runs.notes` y contar en
`excluded_breakdown.residencia_locked`.

## Cascada de extract

Igual que `.claude/skills/cv-job-studio/references/search-playbook.md`. Parar en
el primer paso con contenido usable y anotar el paso en `notes`
(`firecrawl` | `tavily_advanced` | `raw_content` | `company_pivot` |
`snippet_only`).

1. **Firecrawl scrape** — `formats: ["markdown","links"]`, `onlyMainContent: true`.
   **Skip** perfiles `linkedin.com/in/*`. Un intento en hosts que bloquean.
2. **Tavily extract** — `extract_depth: "advanced"`, `format: "markdown"`,
   `query` opcional (Course Creator, cápsulas, diseño instruccional…).
3. **Tavily raw content** — `include_raw_content: true`, `search_depth: "advanced"`.
4. **Pivot a web/ATS de la empresa** — careers / colabora. Nunca inventar canal.

**Qué extraer** (ampliado — los 4 últimos son nuevos y obligatorios):

- Qué necesitan: grabar / escribir / labs / ejercicios / revisar
- Temas (IA, Copilot, Cursor, desarrollo, ofimática…)
- Requisitos de portfolio o vídeo demo → alimenta `C` (coste de postulación)
- Modalidad y **`hiring_residency`** ← el campo que faltaba
- **`contract_vehicle`** (nómina / mercantil / contractor / no especificado)
- **`pay_model`** + tarifa, divisa y unidad (hora / minuto acabado / módulo)
- Canal real (email, formulario, URL pública de aplicación)

## Sequential-thinking

1. Antes de cerrar la lista a scrapear.
2. Antes del ranking final H/S/D.
3. Judge pass del CV vs Course Creator / píldoras.
4. Veracidad antes de escribir CV y mensajes.

## Solape con otros nichos

- **formador FUNDAE** (`formador cv/`): si la empresa aparece en ambos, generar
  el mensaje **aquí** centrado en producción, no en aula en vivo, y marcar
  `also_in_formador_cache: true`. No duplicar el mensaje genérico.
- **gap** (`gap cv/`): un puesto de *AI rater* o *revisor de contenido* con
  contrato pertenece a gap, no aquí, salvo que el entregable sea material
  formativo.

Nunca mezclar cachés.
