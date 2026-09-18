# Decisión — ¿cambiar de stack o probar el pipeline B2B?

> Fecha: 2026-09-10
> Estado: **decidido** — probar el pipeline primero, no migrar
> Entradas: `PIPELINE-FREELANCER-CLAUDE.md`, `PIPELINE-FREELANCER-GEMINI.md`,
> `PIPELINE-FREELANCER-GROK.md`, `ANALISIS-ARGENIS-DEV.md`,
> `ANALISIS-WEB-VS-PIPELINE-B2B.md`
>
> Local: `/specs` está en `.gitignore`. Este documento no se publica.

---

## 1. La pregunta

> «Debido al poco empleo Laravel, ¿debería cambiar de stack a .NET o NestJS, o
> probar primero el pipeline B2B a ver si es rentable?»

---

## 2. Decisión

**Probar el pipeline. No migrar de stack.**

Y hacer el test de forma que su resultado signifique algo — que es la parte que
normalmente se hace mal y convierte el experimento en ruido.

---

## 3. El argumento central: asimetría

No son dos opciones comparables. Tienen coste, reversibilidad y retorno de
información radicalmente distintos.

| | Probar el pipeline | Migrar a .NET / Nest |
|---|---|---|
| Coste | ~3-4 semanas de tu tiempo | 6-12 meses hasta ser contratable al mismo nivel |
| Si sale mal | Aprendes que Laravel no vende **en tu mercado alcanzable** | Has quemado un año y sigues sin distribución |
| Tus activos actuales | Servispin, AquaShield, Vidula, testimonios → **todos cuentan** | **Todos valen cero.** Entras como junior |
| Reversible | Sí | No, en la práctica |
| Genera información | Sí, sobre la propia decisión de migrar | No |

La migración es la decisión cara e irreversible. El test es barato y **produce
exactamente el dato que la decisión de migrar necesita**. Hacerlos en ese orden
es gratis; al revés, no.

Hoy migrarías por corazonada. Después del test, migrarías (o no) con datos.

---

## 4. Revisión de la premisa

«Poco empleo Laravel» es cierto en volumen y engañoso como conclusión.

- `PIPELINE-FREELANCER-CLAUDE.md`: en UK, PHP/Laravel es **~0.05% de las
  vacantes permanentes**, mediana £45k. Volumen bajo — **y competencia baja**.
- El mismo doc sobre la alternativa: en Node/Python una vacante junior acumula
  cientos de candidatos en horas, con seniors despedidos aplicando dos niveles
  por debajo.

Pasar de «pocas ofertas, poca gente» a «muchas ofertas, competencia brutal, y yo
sin experiencia productiva» no es obviamente una mejora. Probablemente es peor.

### El punto incómodo

**El cuello de botella seguramente no es el stack.**

`PIPELINE-FREELANCER-CLAUDE.md` ordena los bloqueantes así:

1. **Inglés B1** — elimina del ~60-70% del mercado remoto UE antes de la
   entrevista técnica.
2. **Tramo mid-level** — el peor del mercado ahora mismo.
3. **Laravel** — bajo volumen, baja competencia. El tercero, no el primero.

A eso hay que sumar la **distribución**: nadie sabe que existes.

Un stack nuevo no arregla el inglés ni la distribución. Te dejaría con los mismos
dos problemas, en un stack donde además no tienes pruebas.

---

## 5. Diseño del test

Un test mal hecho es peor que no hacerlo: te da una conclusión falsa sobre la que
decidirás un año de tu vida.

### 5.1 Tamaño de muestra

Tasa de respuesta realista según los specs — **GEMINI es el único que corrige el
optimismo de los demás, y es la base de planificación**:

| Fuente | Tasa estimada |
|---|---|
| CHATGPT-MAIN | 5-20% |
| CLAUDE | 5-10% |
| **GEMINI** | **4-8% en frío sin señal previa** |
| GROK | 5-15% «en el mejor de los casos» |

Planifica con **5%**.

> Con 5%, enviar 30 mensajes y recibir 0 **no significa nada** — esperabas 1,5.
> Hacen falta **~100 contactos bien investigados** para que un cero sea
> informativo. Por debajo de eso, no concluyas nada.

### 5.2 Aislar la variable

`ANALISIS-WEB-VS-PIPELINE-B2B.md` lo remata:

> **El pipeline no falla por el código. Falla por la landing.**

Si mandas 100 emails a una web cuyo H1 dice «Laravel Developer & AI Automation
Specialist», no estás midiendo si Laravel vende — **estás midiendo tu landing**.

`/agencies` cuesta un día. El pipeline cuesta semanas. **La página va antes**, o
el experimento mide la variable equivocada.

Estructura de `/agencies` (ya definida en `ANALISIS-WEB-VS-PIPELINE-B2B.md` §5,
Fase 1): problema → cómo trabajo en 4 pasos → **no-solicitación por escrito** →
modalidades → a quién sirvo y a quién no → prueba (Servispin, AquaShield,
Vidula) → un solo formulario con «¿cómo me has conocido?».

> El bloque de no-solicitación es el que más fricción elimina y el que casi
> ningún freelance menciona: **el miedo nº1 de una agencia al subcontratar es
> que le robes el cliente.**

### 5.3 Regla de decisión — fijarla ANTES de empezar

100 agencias Tier A (stack Laravel/Vue confirmado, España + LatAm), mensajes
personalizados uno a uno, ventana de 4 semanas.

| Resultado | Lectura | Acción |
|---|---|---|
| **≥ 5 conversaciones** | Funciona | Escala el canal. No migres |
| **2-4** | Señal tibia | Problema de mensaje o de ICP, **no de stack**. Itera el copy y el targeting |
| **0-1** | Dato real sobre demanda Laravel en tu mercado alcanzable | Ahí sí se abre la conversación de migrar |

**Escríbela antes de mandar el primer email.** Si no, dentro de seis semanas la
reinterpretarás según cómo te sientas ese día. Ese es el fallo más común de este
tipo de test y es el único que no tiene arreglo a posteriori.

### 5.4 Sobre el ICP — no repitas el error de volumen

`ANALISIS-WEB-VS-PIPELINE-B2B.md` §7 ya lo señaló y sigue valiendo:

- **No** «500 agencias españolas» de marketing/SEO/branding. Una agencia de SEO
  no tiene dolor de capacidad de ingeniería Laravel.
- Tu ICP real son **~10-30 agencias** de software a medida con stack
  Laravel/Vue confirmado. 40-60 leads muy investigados baten a 200 genéricos.
- Prioriza las que **ya venden staff augmentation** (p. ej. Evolved Ideas /
  Secret Source): ya entienden y ya compran el modelo. No hay que educarlas.

---

## 6. Si algún día migras: Nest, no .NET

No ahora. Pero que conste el criterio, para no tener que rehacer el análisis:

**NestJS** está mucho más cerca de ti — TypeScript (que ya tocas vía
Inertia/Vue), decoradores, inyección de dependencias, estructura modular. Un dev
Laravel lee Nest y lo reconoce. Curva de meses, no de años, y es **aditiva**:
sigues siendo full-stack PHP/TS, no empiezas de cero.

**.NET** es el salto grande: ecosistema, tooling y cultura de contratación
distintos, y su mercado fuerte es enterprise, donde piden años de C#
demostrables. Es el peor camino para quien necesita ingresos pronto.

> `PIPELINE-FREELANCER-CLAUDE.md`: «Guarda la migración de stack para cuando
> tengas nómina. Se hace desde dentro de un trabajo, nunca desde el paro.»

### El movimiento que sí es gratis hoy

No es una migración: es **reposicionamiento**. Dejar de venderte como «Laravel
developer» y venderte como **Full-Stack PHP/TypeScript** (Laravel + Inertia +
Vue/React). Es lo que ya eres, y multiplica coincidencias sin coste de
aprendizaje.

---

## 7. Escenarios según runway

**Dato que falta y que cambia el orden: cuántos meses de colchón tienes.**

| Runway | Qué hacer |
|---|---|
| **3+ meses** | Pipeline B2B tal como está diseñado arriba. Es el plan |
| **4-6 semanas** | Pipeline igualmente primero, **y en paralelo** consultoras nearshore portuguesas (Kwan, Noesis, Xpand IT, Devoteam, Multivision, Innowave). Los specs las sitúan en 2-6 semanas hasta contratar, con procesos donde un B1 pasa. Es tu vía más rápida a caja y no es incompatible con nada |

Un stack nuevo **no da de comer en ninguno de los dos escenarios**.

---

## 8. Qué invalidaría esta decisión

Revisar si ocurre alguna de estas:

- [ ] El test da **0-1 conversaciones sobre 100 contactos bien investigados**,
      con `/agencies` publicada y mensajes personalizados de verdad.
- [ ] Sale una oferta concreta y firmada que exige otro stack.
- [ ] Consigues nómina estable → entonces sí, migración desde dentro.
- [ ] El inglés sube a B2 y el mercado remoto UE se abre: cambia el diagnóstico
      entero, porque el bloqueante nº1 desaparece.

---

## 9. Supuestos y límites de este análisis

- Se apoya en las tasas de respuesta de los specs (4-8%), que son **estimaciones
  de modelos, no datos tuyos**. El test existe precisamente para sustituirlas por
  datos reales.
- **No conozco tu situación financiera.** El §7 es condicional por eso.
- Hay una contradicción de perfil sin resolver del todo entre
  `PIPELINE-FREELANCER-CLAUDE.md` (mid-level, 4 años, inglés B1) y
  `ANALISIS-ARGENIS-DEV.md` (9/10 técnico, testimonios, clientes
  internacionales). La web ya se alineó en **4+ años** (commit `00693c0`), pero
  el posicionamiento comercial debería partir de la misma cifra.
- No conozco tu tolerancia real al riesgo ni cuánto te pesa el trabajo Laravel
  frente al interés por otro stack. Si la motivación es «Laravel me aburre» y no
  «Laravel no paga», este análisis responde a la pregunta equivocada — y merece
  la pena decirlo en voz alta antes de decidir.

---

## 10. Resumen en una frase

El test del pipeline es barato, reversible, aprovecha todo lo que ya tienes y
produce el dato que la decisión de migrar necesita. La migración es cara,
irreversible, tira tus pruebas a la basura y no arregla ni el inglés ni la
distribución, que es donde probablemente está el problema real.

**Siguiente acción concreta:** publicar `/agencies` (un día). Es la pieza que
bloquea el test.
