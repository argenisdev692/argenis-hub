# Email en frío — agencias (España + LatAm)

> Fecha: 2026-09-10
> Contexto: `DECISION-STACK-VS-PIPELINE.md` (el test y la regla de decisión),
> `ANALISIS-WEB-VS-PIPELINE-B2B.md` (el canal), `PIPELINE-FREELANCER-CLAUDE.md`
> (el mensaje base).
>
> Local: `/specs` — no se publica.

---

## 0. Registro: escribe como hablas

Todo este documento usa **ustedes**, no *vosotros*. Eres venezolano y vives en
Portugal: ese es tu español y es el que tienes que usar.

No intentes escribir en castellano peninsular para las agencias de España. Un
español detecta un *vosotros* forzado en la primera línea, y el efecto es el
contrario del que buscas — pareces alguien fingiendo ser de allá. Un «ustedes»
natural, en cambio, no es un problema: le dice que eres latinoamericano, que es
verdad, y a nadie le importa mientras el Laravel funcione.

Además, la mitad de tu mercado objetivo **es** LatAm, donde *vosotros* sería
directamente incorrecto.

Vocabulario: preferí términos que funcionan en ambos lados («vacante» y no
*plaza*, «cubrir una ausencia» y no *cubrir una baja*).

Singular con **tú** (te escribo, guárdame), plural con **ustedes** (su equipo,
si se les desborda). Esa mezcla es la normal en Venezuela y no chirría en
España.

---

## 1. La contradicción que hay que resolver primero

Pides una plantilla para un mensaje que **no puede parecer una plantilla**. Las
dos cosas son compatibles solo de una manera:

> **La estructura se repite. La primera frase, nunca.**

El 90% del resultado se decide en la línea personalizada. Todo lo demás —
credenciales, marca blanca, no-captación, cierre — es andamiaje reutilizable que
solo tiene que no estorbar.

Si mandas la estructura con la primera frase genérica, tienes spam. Con 100
envíos así no aprendes nada excepto que el spam no funciona, cosa que ya sabías,
y habrás quemado 100 agencias de las ~30 que valen la pena.

---

## 2. A quién escribir

**Al fundador o al CTO. Nunca a RRHH ni a `info@`.**

RRHH busca empleados; tú no eres un empleado. El fundador de una agencia de 5-50
personas es quien siente el dolor de capacidad en su propia cuenta de resultados
y quien puede decir que sí sin consultar con nadie.

Dónde encontrarlos: LinkedIn, el directorio de Laravel Partners, Clutch, y
mirando quién patrocina meetups de PHP.

**ICP real**: agencias de software a medida con stack **Laravel/Vue
confirmado**. Son ~10-30 en España, no 500. Una agencia de SEO o de branding no
tiene dolor de capacidad de ingeniería Laravel — no le escribas.

Prioridad máxima: las que **ya venden staff augmentation**. Ya entienden y ya
compran el modelo. No hay que educarlas.

---

## 3. La plantilla

**Asunto** (elige uno, en minúscula, sin marketing):

```
Refuerzo Laravel puntual
Laravel para picos de trabajo
Dev Laravel disponible — marca blanca
```

**Cuerpo:**

```
Hola [NOMBRE],

En la práctica son 2–3 líneas después de tu firma. Borrador para validar con el abogado (OP-15):
 El aviso del art. 14 y la línea de baja también van en los mensajes por formulario y por LinkedIn.

  ▎ P.D. Privacidad: tomé tu nombre y cargo de la web pública de [Agencia] ([URL]) solo para este contacto profesional
  ▎ (interés legítimo, art. 6.1.f RGPD). Si no hay respuesta, los borro en 30 días. Para que no vuelva a escribirte,
  ▎ responde «baja» o escribe a privacy@argenis.dev. Más info: argenis.dev/privacy#prospeccion

[LÍNEA PERSONALIZADA — ver §4. Una frase. Evidencia real de que
miraste lo que hacen.]

Soy desarrollador Laravel, 4 años en producción (Laravel + Inertia +
Vue, y despliegue en AWS). Trabajo desde Portugal con recibos verdes:
facturación UE, sin EOR ni papeleo de su parte.

No busco empleo ni clientes propios. Trabajo en marca blanca para
agencias: bajo su nombre, sin aparecer delante de su cliente si no
quieren, y firmo acuerdo de no captación.

Si alguna vez se les desborda un proyecto o tienen que cubrir una
ausencia, guárdame el contacto.

Mi trabajo: argenis.dev

Un saludo,
Argenis
```

~90 palabras. **No lo alargues.** Cada frase que agregues baja la probabilidad
de que lo lean entero.

---

## 4. La línea personalizada (lo único que importa)

Tres reglas:

1. **Verificable.** Algo que solo puedes saber si miraste su web, su GitHub o su
   LinkedIn.
2. **Sobre ellos, no sobre ti.**
3. **Nada de halagos.** «Me encanta lo que hacen» huele a plantilla a un
   kilómetro. El halago genérico es la señal número uno de envío masivo.

### Variantes según la señal que hayas detectado

**a) Coincidencia de stack** — la más frecuente

```
Vi que en [PROYECTO] trabajan con Laravel e Inertia. Es exactamente
mi combinación del día a día.
```

**b) Señal de contratación** — la más caliente

```
Vi que están buscando un dev Laravel senior. Mientras cubren la vacante,
o si el pico es puntual y no compensa un fijo, puedo entrar sin contrato.
```

> Una oferta de empleo publicada es la mejor señal de compra que existe:
> confirma dolor de capacidad, presupuesto asignado y urgencia, las tres a la
> vez. Prioriza estas.

**c) Mantenimiento / legacy**

```
Vi que mantienen varios proyectos Laravel de hace años. Ese trabajo suele
ser el primero que se cae cuando entra algo nuevo — es justo donde suelo
entrar yo.
```

Formulado como oferta, **no como diagnóstico**. «Su Laravel 8 tiene
vulnerabilidades» es presuntuoso, probablemente inexacto, y ofende.

**d) Sector compartido**

```
Vi que trabajan mucho con [SECTOR]. Mis últimos tres proyectos fueron de
reservas, gestión de citas y captación de leads — misma lógica de negocio.
```

---

## 5. Por qué está cada línea

| Línea | Trabajo que hace |
|---|---|
| Asunto corto y descriptivo | No promete nada. No parece campaña |
| Línea personalizada | Demuestra que no es un envío masivo. **Decide si siguen leyendo** |
| «4 años en producción» + stack | Credencial mínima verificable. Sin adjetivos |
| «recibos verdes, sin EOR» | Elimina la fricción número uno de contratar fuera del país |
| **«No busco empleo ni clientes propios»** | Desactiva las dos sospechas a la vez: que quieres una nómina, y que les vas a robar el cliente |
| **«firmo acuerdo de no captación»** | El miedo número uno, por escrito, antes de que lo formulen |
| «guárdame el contacto» | **CTA de costo cero.** No pide reunión, ni llamada, ni respuesta |
| Enlace | Verificación para quien quiera mirar |

### Sobre el cierre

`guárdame el contacto` parece débil y es deliberado. Pedir una llamada de 30
minutos a alguien que no te conoce tiene un costo alto y una tasa de aceptación
baja. Pedir que te archiven tiene costo cero — y quien te archiva y tiene un
pico dos meses después, escribe.

Muchas respuestas van a llegar como *«justo ahora tenemos algo»*. Ese es el
formato del éxito acá.

---

## 6. Seguimiento

**Uno. Solo uno.** A los 7-10 días, respondiendo sobre tu propio email.

```
Hola [NOMBRE],

Te reescribo por si se quedó abajo. Sigo disponible para picos de trabajo
Laravel, en marca blanca.

Si no les sirve, me avisas y no insisto más.

Argenis
```

Dos líneas. **Sin reclamar** («veo que no respondiste…»). El permiso explícito
para decir que no sube la tasa de respuesta y te limpia la lista.

Después del seguimiento: silencio. Un tercer email quema la relación para
siempre, y estas agencias son ~30, no 500.

---

## 7. El enlace

```
argenis.dev
```

Limpio, sin parámetros. **Decisión deliberada, no un atajo.**

Un `?utm_source=outreach&utm_medium=email&utm_campaign=…` se ve en muchos
clientes de correo, y lo que grita es *campaña de marketing*. Contradice la
premisa entera del mensaje, que es «esto te lo escribí a ti». En un canal donde
todo el valor está en no parecer un envío masivo, el rastreador visible es un
tiro en el pie.

**Y a 100 envíos hechos a mano, la atribución la llevas tú.** Sabes exactamente
a quién escribiste y qué día; cuando alguien responda o rellene el formulario,
lo cruzas con tu registro (§11). El UTM resuelve un problema de escala que
todavía no tienes.

Cuando el canal funcione y mandes cientos al mes, entonces sí: UTM más captura
automática del parámetro en el formulario. Hoy no.

### A qué apunta

A la home, y hace bien su trabajo. En un scroll ve:

1. **Quién eres y qué haces** — hero, y la píldora dice *«project work or
   embedded contracting»*
2. **Portfolio real con demos** — Servispin, AquaShield, Vidula
3. **Servicios y stack**
4. **Experiencia fechada** — timeline desde 2022
5. **Testimonios** de clientes reales, con enlace a sus webs
6. **La cara detrás del código**
7. **FAQ** — n.º 2 los formatos de colaboración, **n.º 3 la no-captación por
   escrito**
8. **Un solo formulario de contacto**

Es exactamente «presentación + portfolio», que es lo que quieres que vean. No
hace falta página aparte.

---

## 8. Cuando respondan: no vendas horas

La primera pregunta suele ser **«¿cuánto cobras la hora?»**. Es la peor
conversación posible: te convierte en una tarifa comparable con cualquiera.

Reconduce a un entregable cerrado:

> «Depende del formato. Suelo trabajar por bloques cerrados — por ejemplo un
> bloque de X semanas dedicado a su proyecto, o un retainer mensual de horas
> fijas. ¿Qué tienen entre manos y les digo qué encaja mejor?»

Tus formatos concretos **los defines después de las primeras 2-3
colaboraciones**, no ahora. Hasta entonces, esta frase basta para salir del
precio por hora.

---

## 9. Qué NO hacer

- **No adjuntes el CV.** Es lenguaje de candidato a empleo. Rompe todo el
  posicionamiento del mensaje.
- **No pongas tarifa** en el primer email.
- **No mandes a `info@`.** Es un buzón que nadie lee.
- **No uses herramienta de envío masivo.** Un email en frío bien hecho se manda
  desde tu correo, uno por uno. Las cabeceras de las plataformas se detectan.
- **No pongas seguimiento de apertura.** Si lo detectan, mata la confianza justo
  cuando estás prometiendo discreción.
- **No mientas sobre disponibilidad.** «Disponibilidad inmediata» sin tenerla se
  descubre en la primera llamada.
- **No escribas en inglés** a una agencia española o latinoamericana. Tu inglés
  B1 es irrelevante en este canal — esa es una de las razones por las que este
  canal es el correcto para ti.
- **No finjas el registro peninsular.** Ver §0.

---

## 10. Checklist antes de darle a enviar

- [ ] ¿Va al fundador o CTO, con nombre propio?
- [ ] ¿La primera frase es verificable y solo aplica a esa agencia?
- [ ] ¿Confirmé que su stack es Laravel/Vue?
- [ ] ¿Menos de 120 palabras?
- [ ] ¿Enlace limpio (argenis.dev), sin parámetros?
- [ ] ¿Sin CV, sin tarifa, sin tracking de apertura?
- [ ] ¿Sin *vosotros* ni *vuestro*?
- [ ] ¿Lo leí en voz alta? Si suena a folleto, reescribe la primera frase.

---

## 11. Volumen, ritmo y medición

**10-15 al día**, no más. Por encima de eso la personalización se degrada y el
canal deja de funcionar.

Registra por cada envío: agencia, persona, fecha, señal detectada, variante
usada, respuesta.

**Tasa de planificación: 5%** (base GEMINI: 4-8% en frío). Regla de decisión:
100 contactos, 4 semanas, umbrales fijados **antes** de empezar.

| Resultado | Acción |
|---|---|
| ≥ 5 conversaciones | Funciona. Escala el canal |
| 2-4 | Itera mensaje e ICP, **no el stack** |
| 0-1 | Dato real sobre demanda Laravel en tu mercado alcanzable |

---

## 12. Confirma antes de enviar

Dos cosas de la plantilla salen de tus specs y no las verifiqué contigo:

1. **Recibos verdes** — que efectivamente puedes facturar así a la UE sin
   fricción para el cliente.
2. **Acuerdo de no captación** — ya confirmaste que lo firmas (por eso está
   publicado en la FAQ). Asegúrate de que la versión del email dice lo mismo que
   la web.

Si alguna no es exacta, quítala. Una credencial falsa en el primer email se
descubre en la primera llamada, y ahí se acaba todo.
