# Especificación: LeadScout — descubrimiento de oportunidades de contractor / marca blanca

> Fase 1 · SPECIFY — Define QUÉ se construye y POR QUÉ. Sin stack técnico.

**Feature ID:** 003-lead-scout
**Fecha:** 2026-09-16
**Estado:** In review — 7ª pasada (17-09-2026): datos públicos básicos de empresa y correcciones
de la verificación con Tavily/Firecrawl (`clarify.md` A16), sobre la 6ª pasada (16-09-2026:
descubrimiento de agencias en el MVP, alcance recortado, perfil desde el CV guardado, límites de
obtención sin evasión y aportes de la variante MUSE-SPARK, A1-A15)
**Análisis base:** `ANALISIS-LEADSCOUT.md`

## 1. Resumen

LeadScout es un sistema de uso personal que descubre **ofertas de empleo o contrato
Laravel/PHP** y **agencias de software** en Portugal y España, en el resto de la UE, Reino Unido
e Irlanda, y en el resto del mundo donde el trabajo en inglés escrito y asíncrono encaje con un
nivel B1. Busca agencias **vivas, con equipo real y trabajo recurrente**, y descarta freelancers
individuales, agencias paradas y webs abandonadas. Cada oferta se trata como una **señal de compra sobre la empresa que la
publica** que sube su prioridad; las agencias **sin vacante** también entran, por descubrimiento
sistemático y por importación de la lista ICP del operador. Solo usa **fuentes públicas o
programáticas oficiales**: no accede a redes profesionales ni sortea CAPTCHAs, anti-bots o muros
de login. El sistema califica a la empresa contra el perfil del CV con un score explicable,
respaldado por evidencia y con un nivel de confianza, y prepara borradores de contacto para
ofrecer capacidad como **contractor independiente en marca blanca** (facturación con recibos
verdes). No envía nada por su cuenta: mide el funnel completo para validar con datos si el
modelo genera clientes recurrentes.

> **Principio rector — DESCUBRIMIENTO AUTOMÁTICO ≠ CONTACTO AUTOMÁTICO** (`clarify.md` A18):
> descubrimiento, enriquecimiento, scoring y borrador son automáticos; **revisar, enviar y
> registrar el envío es siempre humano**. El sistema no tiene capacidad de envío por ningún canal.

## 2. Motivación / contexto de negocio

- El operador está desempleado, factura desde hace 3 años como trabajador independiente en
  Portugal y necesita ingresos recurrentes pronto.
- El volumen de ofertas Laravel publicadas en PT/ES es bajo (4 en el principal portal
  portugués y ~7 vigentes en uno español el 16-09-2026), así que buscarlas a mano es lento y
  se pierden señales. Por otro lado, las agencias que compran capacidad externa rara vez lo
  anuncian.
- Los 28 documentos de análisis coinciden en que el canal con mejor retorno son las agencias de
  5-50 personas que compran capacidad, y en que las **señales verificables** (vacante abierta,
  stack confirmado, crecimiento) pesan más que el volumen de contactos.
- Sin este sistema: prospección manual e inconsistente, sin evidencia guardada, sin medición,
  y sin forma de decidir con datos si seguir, iterar el mensaje o cambiar de estrategia.

## 3. Actores

- **Operador (Argenis):** único usuario. Define su perfil y su presupuesto, revisa los leads,
  valida la evidencia, envía los contactos por su cuenta y registra los resultados.
- **Planificador del sistema:** ejecuta periódicamente la ingesta, el enriquecimiento y el
  scoring dentro del presupuesto.
- **Fuentes externas de datos:** portales de empleo con acceso público o programático,
  directorios de empresas, búsqueda web y las webs públicas de las empresas. No son usuarios;
  se consultan respetando sus términos.
- **Empresas objetivo** (agencias, consultoras, empresas de producto): no interactúan con el
  sistema; son el objeto del análisis.

## 4. Historias de usuario

### US-1: Perfil de matching desde el CV (Prioridad: Alta)
**Como** operador, **quiero** que el sistema derive mi perfil de matching del CV que ya tengo
guardado en la plataforma (markdown ATS), **para que** el score se base en capacidades reales y no
inventadas, sin volver a subir el CV.

**Criterios de aceptación:**
- [ ] Dado que tengo CVs guardados en la plataforma, cuando importo el perfil, entonces puedo
      elegir uno de **mis** CVs (por defecto, el principal en markdown) y el sistema lo lee sin
      copiar su texto completo ni mostrarlo en otra pantalla.
- [ ] Dado el CV elegido, cuando se importa, entonces el sistema produce una lista de
      habilidades **confirmadas** (presentes en el CV) separada de las **potenciales /
      requieren verificación**, y solo las confirmadas suman en el score técnico.
- [ ] Dado el CV elegido, cuando se importa, entonces cada proyecto se guarda como **prueba**
      (título, resumen público, tecnologías, sector, resultado, URL) para citar la más afín en los
      borradores.
- [ ] Dado un CV que cambió después de importarlo, cuando abro la bandeja, entonces el sistema
      avisa de que el perfil está desactualizado.
- [ ] Dado un CV sin texto extraído (p. ej. un PDF escaneado) o de otro usuario, cuando intento
      importarlo, entonces se rechaza con el motivo.
- [ ] Dado un perfil existente, cuando el operador edita pesos, idiomas, tarifa mínima o países
      objetivo, entonces se guarda una nueva versión del perfil y los scores posteriores
      registran con qué versión se calcularon.
- [ ] Dado un CV que no menciona una tecnología, cuando una oferta la exige, entonces el sistema
      la marca como «brecha», nunca como coincidencia.

### US-2: Ingesta de ofertas desde fuentes registradas (Prioridad: Alta)
**Como** operador, **quiero** que el sistema recoja periódicamente ofertas relevantes de un
registro de fuentes, **para** no revisar cada portal a mano.

**Criterios de aceptación:**
- [ ] Dada una fuente activa del registro, cuando se ejecuta la ingesta, entonces cada oferta
      queda normalizada (título, empresa, país, ciudad, modalidad remota, tipo de contrato,
      idioma de la oferta, fecha de publicación, URL de origen, texto íntegro).
- [ ] Dada la misma oferta en dos fuentes, cuando se ingesta, entonces se guarda una sola
      oferta con ambas fuentes enlazadas.
- [ ] Dada una oferta cuya fecha de publicación supera la antigüedad máxima configurada (p. ej.
      30 días), cuando se ingesta, entonces se marca como caducada y no genera leads.
- [ ] Dada una fuente que falla o agota su cuota, cuando se ejecuta la ingesta, entonces las
      demás fuentes continúan y el fallo queda registrado con su motivo.

### US-3: Resolución y clasificación de la empresa (Prioridad: Alta)
**Como** operador, **quiero** que cada oferta se vincule con la empresa que la publica y que
esta se clasifique, **para** distinguir una agencia que necesita capacidad de una reclutadora
o de un outsourcer grande.

**Criterios de aceptación:**
- [ ] Dada una oferta, cuando se procesa, entonces queda vinculada a una empresa identificada
      por su dominio canónico; si no se puede resolver, queda como «empresa no resuelta» y no
      consume enriquecimiento de pago.
- [ ] Dada una empresa, cuando se clasifica, entonces obtiene un tipo (agencia de software,
      consultora/nearshore, empresa de producto, reclutadora/intermediario, outsourcer grande,
      otro) con evidencia y confianza.
- [ ] Dada una oferta publicada por una reclutadora o intermediario, cuando se clasifica,
      entonces se registra como señal de mercado, pero la empresa no se trata como comprador
      directo salvo que se identifique al cliente final con evidencia.
- [ ] Dados varios dominios o marcas de la misma empresa, cuando se detectan, entonces se
      consolidan en una sola empresa sin perder las fuentes.

### US-4: Score explicable con evidencia y confianza (Prioridad: Alta)
**Como** operador, **quiero** un score por dimensiones con su evidencia, **para** poder
responder «¿por qué 87?» y «¿por qué solo un 58 % de confianza?».

**Criterios de aceptación:**
- [ ] Dada una empresa con señales, cuando se puntúa, entonces se obtienen subscores de 0-100
      para: Ajuste técnico, Ajuste comercial, Ajuste remoto, Ajuste de comunicación (B1), Potencial
      recurrente, **Vitalidad y tamaño** y Ajuste geográfico/contractual, más un **Lead Score**
      global y una
      **Confianza de la evidencia** separada.
- [ ] Dado un score, cuando el operador lo consulta, entonces ve cada razón con puntos (+/−),
      URL de origen, fragmento textual, fecha de captura, método de extracción y si es
      **hecho extraído** o **inferencia**.
- [ ] Dadas dos señales idénticas, una como hecho y otra como inferencia, cuando se puntúan,
      entonces la inferencia pesa menos que el hecho.
- [ ] Dados el mismo perfil, las mismas señales y la misma versión de reglas, cuando se
      recalcula, entonces el resultado es idéntico (determinista).
- [ ] Dado un score, cuando se clasifica en tier (A / B / C / Descartar), entonces el tier sale
      de reglas explícitas y documentadas (p. ej. A exige un umbral de Lead Score **y** un
      umbral de confianza **y** al menos una señal comercial verificada), y los umbrales son
      configurables.
- [ ] Dado un Lead Score alto con poca confianza, cuando se clasifica, entonces **no** sube a A:
      queda marcado «necesita investigación» y recibe una sola ronda adicional de enriquecimiento
      barato antes de re-puntuarse.
- [ ] Dado el tamaño de la empresa, cuando se clasifica, entonces: **1 persona** (freelancer
      individual) → Descartar; **2-4 personas** → como máximo Tier B salvo una señal de compra fuerte
      verificada (vacante activa o llamada a freelancers/partners); **5-50** → la opción preferida;
      **más de 200** (outsourcer grande) → Descartar; **sin dato** → no se descarta, pero baja la
      confianza.
- [ ] Dada una empresa sin actividad visible, cuando se clasifica, entonces: sin ninguna señal de
      actividad en 24 meses (contenido fechado, actualizaciones de la web, vacantes) → Descartar
      como «agencia parada»; web aparcada, en venta o que redirige a otra empresa → Descartar como
      «web muerta o absorbida»; **sin ninguna fecha encontrada** → no se descarta y se marca
      «necesita investigación».
- [ ] Dado un lead descartado, cuando lo consulto, entonces veo el **motivo** del descarte.
- [ ] Dado un lead de Tier A, cuando se revisa, entonces tiene actividad reciente y equipo real
      (≥ 5 personas, o 2-4 con señal de compra fuerte).

### US-5: Bandeja de revisión y borrador de contacto (Prioridad: Alta)
**Como** operador, **quiero** una bandeja priorizada con un borrador de contacto por lead,
**para** personalizar y enviar 10-15 contactos al día en pocos minutos cada uno.

**Criterios de aceptación:**
- [ ] Dados leads puntuados, cuando abro la bandeja, entonces los veo ordenados por tier, Lead
      Score y confianza, con filtros por país, tipo de empresa, tipo de señal y estado.
- [ ] Dado un lead, cuando pido un borrador, entonces este tiene una primera frase basada en una
      señal verificable con su evidencia visible, sigue la variante de mensaje que corresponde
      a la señal (vacante, stack, legacy, sector) y está en el idioma y registro adecuados.
- [ ] Dado un lead de un país cuya normativa no permite email comercial no solicitado a
      empresas sin consentimiento, cuando se genera el borrador, entonces el sistema recomienda
      un canal permitido (responder a la oferta publicada, formulario de contacto, red
      profesional) y advierte del motivo.
- [ ] Dado cualquier borrador, cuando se genera, entonces **no se envía automáticamente**; solo
      el operador lo envía fuera del sistema y marca el contacto como enviado.
- [ ] Dado un borrador, cuando contiene una capacidad que no figura como confirmada en el
      perfil, entonces el sistema la señala antes de permitir marcarlo como listo.
- [ ] Dado un lead de Tier C o descartado, cuando pido un borrador, entonces se rechaza con el
      motivo (solo A/B).
- [ ] Dado un canal cuya regla del país está **pendiente de verificación legal**, cuando se muestra
      o se genera el borrador, entonces aparece marcado «⚠️ verificación legal pendiente» y no se
      recomienda como permitido; si el operador lo marca como enviado, debe confirmarlo de forma
      explícita y la confirmación queda registrada.

### US-6: Registro del funnel y regla de decisión (Prioridad: Alta)
**Como** operador, **quiero** registrar cada etapa y ver métricas contra una regla de decisión
fijada de antemano, **para** decidir con datos si escalar, iterar o parar.

**Criterios de aceptación:**
- [ ] Dado un lead contactado, cuando cambia de etapa (contactado → respondió → respuesta
      positiva → llamada → prueba → ganado → recurrente | descartado | no molestar), entonces se
      guarda la fecha, el canal, la variante de mensaje y la señal usada.
- [ ] Dado un periodo, cuando consulto métricas, entonces veo las tasas por etapa, por canal,
      por variante, por país y por tipo de señal, además de horas y euros facturados.
- [ ] Dada una regla de decisión registrada **antes** del primer contacto (tamaño de muestra,
      ventana y umbrales), cuando se alcanza la muestra, entonces el sistema muestra qué rama de
      la regla se cumple, sin permitir editar la regla retroactivamente.
- [ ] Dado un periodo con una muestra menor que la de la regla, cuando consulto métricas, entonces
      el sistema muestra las respuestas positivas **esperadas** según el rango de planificación
      (2-5 %) frente a las observadas y la marca «no concluyente» (p. ej. «0 de 30: esperado
      0,6-1,5 → no concluyente»), nunca «fracaso».
- [ ] Dada una empresa que pidió no ser contactada, cuando aparece en nuevas fuentes, entonces
      queda suprimida permanentemente de la bandeja.
- [ ] Dado un contacto que envié a mano, cuando lo marco como enviado, entonces se guardan el lead,
      el medio (`email`, `contact_form`, `job_posting`, `linkedin_manual`), el canal detectado, la
      fecha, el operador, la plantilla y su versión, y la variante.
- [ ] Dada una respuesta, cuando la registro, entonces elijo `interested`, `not_interested` o
      `unsubscribe`, y la etapa cambia en consecuencia con su historial.
- [ ] Dada una baja (`unsubscribe`), cuando se registra, entonces la empresa queda suprimida en
      todos los canales y ni el descubrimiento, ni la importación, ni el alta manual, ni una oferta
      nueva, ni un re-score pueden volver a proponerla; la supresión no se puede levantar desde la
      web.

### US-7: Descubrimiento de agencias sin vacante (Prioridad: Alta — **MVP**, clarify A1)
**Como** operador, **quiero** descubrir agencias de software de Portugal y España que no tienen
ofertas publicadas, **para** no depender del poco volumen de ofertas Laravel.

**Criterios de aceptación:**
- [ ] Dado un país objetivo y una familia de consultas, cuando se ejecuta el discovery, entonces
      se generan consultas sistemáticas (servicio × tecnología × país × modelo comercial) sin
      repetir combinaciones ya ejecutadas dentro de la ventana de caché.
- [ ] Dados los resultados de una consulta, cuando se procesan, entonces se descartan portales de
      empleo, directorios, marketplaces de freelancers y redes sociales/profesionales, y solo se
      crean empresas nuevas (por dominio canónico) que no estén suprimidas, con su **origen**
      (consulta) registrado.
- [ ] Dado el presupuesto de búsqueda agotado o el proveedor sin cuota, cuando se ejecuta el
      discovery, entonces se detiene con motivo y las demás entradas (ofertas, importación) siguen.
- [ ] Dadas varias ejecuciones, cuando consulto métricas, entonces veo la **efectividad** de cada
      familia de consultas (resultados → empresas nuevas válidas → leads A/B) y de la extracción
      (% de empresas sin páginas leídas, % de bloqueos), para ajustar consultas con datos.
- [ ] Dado un registro de contactos enviados a mano antes de tener el sistema, cuando lo importo,
      entonces esos contactos cuentan en el funnel y en la muestra de la regla de decisión.
- [ ] Dada una lista de agencias preparada por el operador (CSV), cuando se importa, entonces cada
      fila válida se crea o se fusiona por dominio, y las inválidas se reportan sin abortar.
- [ ] Dado un dominio candidato, cuando se enriquece, entonces se consultan primero las páginas
      públicas relevantes (servicios, sobre nosotros, equipo, empleo, contacto, casos) usando el
      método más barato disponible y respetando las exclusiones del sitio.
- [ ] Dada una agencia que ya tiene evidencia suficiente para su tier, cuando se planifica el
      enriquecimiento, entonces no se consultan páginas adicionales (enriquecimiento
      progresivo).

### US-8: Presupuesto mensual y degradación controlada (Prioridad: Media)
**Como** operador, **quiero** fijar un presupuesto mensual por categoría de gasto, **para**
que el sistema nunca gaste de más y priorice los leads con más probabilidad.

**Criterios de aceptación:**
- [ ] Dados presupuestos de búsqueda, extracción e IA, cuando una ejecución los alcanzaría,
      entonces se detiene esa categoría, se registra el motivo y se siguen usando las fuentes
      gratuitas.
- [ ] Dada una cuota agotada en un proveedor externo, cuando se detecta, entonces el sistema no
      reintenta en bucle, marca el proveedor como no disponible hasta la ventana configurada y
      usa la alternativa si existe.
- [ ] Dado cualquier intento de obtención de datos, cuando termina, entonces queda registrado el
      método, los intentos, el estado, la duración, el coste estimado y el motivo de fallo.

### US-9: Olas geográficas y encaje con inglés B1 (Prioridad: Alta — **MVP**, clarify A14)
**Como** operador con inglés B1, **quiero** buscar agencias en Portugal y España primero, y
también en el resto de la UE, Reino Unido, Irlanda y el resto del mundo donde el trabajo sea escrito
y asíncrono, **para** tener más volumen sin perder tiempo con agencias que exigen inglés hablado
nativo.

**Criterios de aceptación:**
- [ ] Dadas tres olas configurables (1: PT/ES; 2: resto de la UE + UK/IE en inglés; 3: resto del
      mundo), cuando se ejecuta el descubrimiento, entonces las consultas se reparten según el peso
      de cada ola (por defecto ~60/30/10 %) y cada empresa guarda su ola.
- [ ] Dada una lista de países con fase y estado, cuando se activa un país, entonces sus fuentes
      y consultas entran en el ciclo sin cambios de diseño.
- [ ] Dada una empresa que trabaja en español o portugués, cuando se puntúa, entonces el Ajuste de
      comunicación es máximo.
- [ ] Dada una empresa que demuestra trabajo en inglés escrito y asíncrono (remote-first, equipo
      distribuido, procesos documentados), cuando se puntúa, entonces el Ajuste de comunicación es
      alto aunque el idioma sea inglés.
- [ ] Dada una oferta o empresa que exige inglés hablado C1/nativo o mucha interacción síncrona con
      el cliente, cuando se puntúa, entonces se penaliza el Ajuste de comunicación sin descartarla
      automáticamente, y la penalización se explica.
- [ ] Dada una empresa con poco solape horario con Portugal, cuando se puntúa, entonces se
      penaliza el ajuste geográfico y se explica.
- [ ] Dada una empresa de un país sin regla de email comercial verificada, cuando se genera el
      borrador, entonces **no** se propone email en frío (solo oferta publicada, formulario o red
      profesional a mano).
- [ ] Dadas las métricas, cuando las consulto, entonces veo por ola el % de descartadas por motivo,
      el % de leads A/B y la tasa de respuesta, para reajustar el reparto.

### US-10: Elegir proveedor y modelo de IA (Prioridad: Media — clarify A2, ampliado por A15)
**Como** operador, **quiero** elegir desde la web el proveedor y modelo de IA por defecto de cada
propósito (extracción, borradores) y, además, cambiarlo en un selector para cada borrador,
**para** equilibrar calidad y coste sin tocar la configuración del servidor.

**Criterios de aceptación:**
- [ ] Dada la pantalla de ajustes de IA, cuando la abro, entonces veo por propósito el proveedor,
      el modelo y el respaldo actuales, y puedo cambiarlos entre las opciones permitidas.
- [ ] Dado un propósito sin elección explícita, cuando se ejecuta la extracción o se genera un
      borrador, entonces se usa el proveedor/modelo por defecto guardado, con su respaldo.
- [ ] Dado el editor de un borrador, cuando lo abro, entonces veo un **selector** con las opciones
      permitidas (proveedor, modelo y coste estimado por cada 100 usos).
- [ ] Dado un proveedor sin credenciales configuradas, cuando se listan las opciones, entonces
      aparece deshabilitado con el motivo.
- [ ] Dado un valor que no está en la lista de opciones permitidas, cuando se envía, entonces se
      rechaza (no se aceptan modelos escritos a mano).
- [ ] Dado un borrador, cuando elijo otro proveedor/modelo, entonces solo afecta a ese borrador.
- [ ] Dada una señal o un borrador generado con IA, cuando se consulta, entonces queda registrado
      qué proveedor y modelo lo produjeron.
- [ ] Dado el proveedor principal caído o sin cuota, cuando hay un respaldo configurado, entonces
      se usa el respaldo y se registra el cambio; el presupuesto mensual de IA se aplica igual.

### US-11: Identificar a los decisores de la empresa (Prioridad: Alta)
**Como** operador, **quiero** que el sistema identifique en la web pública de cada empresa a sus
decisores (fundador, cofundador, CEO, CTO, socio, director técnico, head of engineering/delivery)
y **no** a reclutadores ni trabajadores, **para** dirigir cada contacto a quien siente el dolor
de capacidad y puede decir que sí.

**Criterios de aceptación:**
- [ ] Dadas las páginas públicas de equipo, nosotros o contacto, los datos estructurados de la web
      o la propia oferta, cuando se procesan, entonces solo se guardan las personas con un cargo de
      la **lista de decisores**, con nombre, cargo, categoría, URL y fragmento de evidencia, y
      fecha.
- [ ] Dada una persona con un cargo de la **lista de exclusión** (reclutamiento, talento, RRHH /
      people, desarrollo, diseño, marketing, ventas, prácticas, asistentes) o con un cargo
      ambiguo, cuando se procesa, entonces **no** se guarda ningún dato suyo.
- [ ] Dada una página de equipo con N personas, cuando se procesa, entonces solo se guarda el
      recuento como señal de tamaño; los nombres de quienes no son decisores no se guardan.
- [ ] Dado un email que la propia empresa publica junto al nombre de un decisor y en su dominio,
      cuando se procesa, entonces se guarda como «email publicado». Los emails **nunca** se
      adivinan ni se completan, y los buzones genéricos (info@, geral@, hola@) no se asignan a
      personas.
- [ ] Dado un email publicado, cuando se genera el borrador, entonces el sistema indica si puede
      usarse para contacto en frío según la **regla configurada** del país y el tipo de buzón, con
      su estado legal (FR-44). Punto de partida de la investigación, **pendiente de verificación
      legal**: nominativo en España → bloquear (se aplica por ser restrictiva); nominativo en
      Portugal → zona gris, bloquear; buzón genérico de empresa en Portugal → permitir con baja
      **solo cuando se verifique** (ver `NORMATIVA-RGPD.md` §3-4).
- [ ] Dado un lead que no llega a Tier A o B, cuando se procesa, entonces los decisores se usan en
      memoria (para ocultar nombres) pero **no se guardan**.
- [ ] Dados varios decisores, cuando se elige el contacto principal, entonces se prioriza por
      tamaño (≤ 25 personas: fundador/CEO; 26-50: CTO/head of engineering o delivery), y el
      operador puede cambiarlo.
- [ ] Dada una empresa sin decisor identificable, cuando se puntúa, entonces el lead **no** se
      descarta: queda marcado «sin decisor» con canal de formulario u oferta, y el operador puede
      añadir la persona a mano.
- [ ] Dado un perfil en una red profesional, cuando se procesa, entonces el sistema **no** accede
      a esa red; solo guarda la URL si la propia web de la empresa la enlaza.
- [ ] Dada una persona que pide no ser contactada, cuando se registra, entonces sus datos se
      anonimizan de inmediato y no se vuelven a extraer.
- [ ] Dado cualquier contenido que incluya nombres de personas, cuando se usa la IA, entonces los
      nombres **no** se envían al proveedor de IA.

### US-12: Detectar los canales de contacto de la empresa (Prioridad: Alta)
**Como** operador, **quiero** que el sistema detecte por dónde invita cada empresa a escribirle
(oferta publicada, llamada a freelancers o partners, página de partners o marca blanca,
formulario de contacto, formulario de «Trabaja con nosotros», buzón genérico, página de empresa en
una red profesional) y me recomiende el mejor canal **legal y comercial**, **para** no depender
de emails en frío y no perder el contacto en un buzón de RRHH.

**Criterios de aceptación:**
- [ ] Dadas las páginas públicas y las ofertas de una empresa, cuando se procesan, entonces cada
      canal detectado se guarda con su tipo, URL, fragmento de evidencia, fecha y destinatario
      probable (dirección/ventas, RRHH/reclutamiento, desconocido).
- [ ] Dado un texto que invita explícitamente a colaborar («trabajamos con freelancers»,
      «buscamos colaboradores externos», «partner program», «white label»), cuando se detecta,
      entonces se guarda como canal **y** como señal comercial verificada.
- [ ] Dado un formulario o sección de «Trabaja con nosotros / Carreiras / Join us», cuando existe,
      entonces **se extrae siempre** como canal, pero con **prioridad baja** y el aviso «suele
      llegar a RRHH o recruiters: preséntalo como colaboración freelance, no como candidatura».
- [ ] Dados varios canales, cuando se genera el borrador, entonces el sistema los ordena así: oferta
      publicada / llamada a freelancers → página de partners → formulario de contacto → red
      profesional (a mano) → buzón genérico (solo donde sea legal) → «Trabaja con nosotros» →
      email nominativo (no en frío). Descarta los canales no permitidos en el país y explica por
      qué.
- [ ] Dado cualquier formulario, cuando se detecta, entonces el sistema **nunca** lo rellena ni lo
      envía, no llama a su URL de envío y no intenta resolver CAPTCHAs; solo guarda que existe,
      sus nombres de campo y si tiene CAPTCHA.
- [ ] Dada una empresa sin ningún canal permitido en su país, cuando se muestra en la bandeja,
      entonces aparece marcada «sin canal permitido» y no se genera borrador de email.
- [ ] Dado un contacto enviado, cuando se registra, entonces queda asociado al canal usado, para
      medir la tasa de respuesta por tipo de canal.
- [ ] Dado un canal que ya no existe al re-verificar, cuando se detecta, entonces se marca como
      roto y deja de recomendarse.

## 5. Requisitos funcionales

- **FR-1**: El sistema DEBE mantener un perfil de matching versionado derivado de un CV que el
  operador ya tiene guardado en la plataforma (leído sin duplicar su texto), separando capacidades
  confirmadas de potenciales, guardando las pruebas (proyectos) citables y detectando cuándo el CV
  de origen ha cambiado. El derivado DEBE ser determinista (sin IA ni búsqueda semántica).
- **FR-2**: El sistema DEBE mantener un registro de fuentes (tipo, país, método de acceso,
  frecuencia, prioridad, estado, última ejecución, términos de uso revisados sí/no).
- **FR-3**: El sistema DEBE priorizar las fuentes con acceso programático oficial o público
  sobre la obtención de páginas web.
- **FR-4**: El sistema DEBE normalizar y deduplicar ofertas (por URL canónica, empresa + título
  + ubicación + ventana temporal) y empresas (por dominio canónico, alias y redirecciones).
- **FR-5**: El sistema DEBE ampliar la búsqueda de ofertas a términos equivalentes configurables
  (p. ej. PHP, full-stack PHP, Vue + PHP) y no solo al término exacto «Laravel».
- **FR-6**: El sistema DEBE clasificar el tipo de empresa y detectar señales comerciales
  (acepta freelancers/subcontratistas, marca blanca, staff augmentation, vacantes múltiples,
  crecimiento, mantenimiento de proyectos existentes) con evidencia.
- **FR-7**: El sistema DEBE guardar para cada señal: URL de origen, fragmento textual, marca
  temporal, confianza, método de extracción y naturaleza (hecho | inferencia).
- **FR-8**: El sistema DEBE calcular los subscores, el Lead Score y la Confianza de la evidencia
  de forma determinista a partir de las señales guardadas y de una versión de reglas.
- **FR-9**: El sistema DEBE asignar el tier según reglas explícitas y umbrales configurables.
- **FR-10**: El sistema DEBE usar interpretación automática del lenguaje solo para tareas que no
  resuelve una regla determinista (clasificación, extracción de señales ambiguas, resumen,
  borrador), y NO DEBE usarla para aritmética de scores, deduplicación, normalización de
  dominios ni parsing de rangos de empleados.
- **FR-11**: El sistema DEBE aplicar una estrategia de obtención de coste creciente (caché →
  evidencia existente → acceso oficial/feed → mapa del sitio → página directa → servicio de
  extracción externo) y detenerse en cuanto tenga evidencia suficiente.
- **FR-12**: El sistema DEBE cachear las consultas y páginas obtenidas con una antigüedad máxima
  configurable, y no repetir consultas idénticas dentro de esa ventana.
- **FR-13**: El sistema DEBE respetar las exclusiones de rastreo de cada sitio y los términos de
  uso de cada fuente, y NO DEBE intentar evadir mecanismos anti-bot, CAPTCHAs, muros de login
  ni límites de acceso. En concreto: una respuesta de bloqueo (acceso denegado, límite de
  peticiones, desafío anti-bot o CAPTCHA, login) DEBE registrarse como bloqueo y NO DEBE
  reintentarse por otra vía (otro proveedor, proxies, navegador automatizado, identidad
  falseada); el sistema DEBE identificarse con un agente de usuario honesto; y NO DEBE acceder a
  redes profesionales (p. ej. LinkedIn) ni usar cuentas, cookies o extensiones para obtener datos.
  Tampoco DEBE lanzar consultas de búsqueda dirigidas a redes profesionales ni a intermediarios de
  datos de contacto (p. ej. `site:linkedin.com`, «linkedin», Apollo, ZoomInfo): se rechazan antes
  de llamar al proveedor (`clarify.md` A17). Cuando use un servicio de extracción externo, DEBE pedir su modo de proxy **básico**, DEBE
  comprobar en la respuesta que ese fue el modo usado (si no, descarta el contenido y desactiva el
  servicio) y DEBE pedir que la página **no se guarde en la caché o índice del proveedor**.
- **FR-14**: El sistema DEBE aplicar presupuestos mensuales por categoría y degradar a fuentes
  gratuitas al agotarlos.
- **FR-15**: El sistema DEBE generar borradores de contacto con la variante según la señal, en
  el idioma adecuado, y con la recomendación de canal según el país del destinatario.
- **FR-16**: El sistema NO DEBE enviar mensajes a terceros de forma automática. El operador envía
  cada mensaje **a mano, uno por uno** (10-15 al día como máximo) y lo marca como enviado con el
  canal usado. Buzón por fases (`clarify.md` A19): en desarrollo y pruebas, cuenta personal
  (p. ej. Gmail); en el outreach real, buzón del **dominio propio** con cualquier proveedor de
  correo empresarial. Ni el envío manual ni el tipo de cuenta eximen de las reglas de canal por
  país, del aviso del art. 14 ni de la baja (FR-15, FR-17, FR-26, FR-44).
- **FR-17**: El sistema DEBE mantener una lista de supresión (no contactar) que prevalece sobre
  cualquier score, y DEBE poder importar la **lista nacional de personas colectivas que se oponen
  al marketing directo de la DGC (Portugal, Lei 41/2004 art. 13.º-B)**, que se consulta
  obligatoriamente antes de proponer un email a una empresa portuguesa.
- **FR-18**: El sistema DEBE registrar las etapas del funnel y calcular métricas por periodo,
  canal, variante, país y tipo de señal, incluido el volumen semanal de señales nuevas por
  fuente.
- **FR-19**: El sistema DEBE permitir registrar una regla de decisión antes del primer contacto
  y NO DEBE permitir modificarla después de iniciado el periodo de medición (solo crear una
  nueva versión para un periodo nuevo).
- **FR-20**: El sistema DEBE permitir alta manual de leads (empresa + URL + nota) para la fase de
  prospección manual y para las referencias.
- **FR-21**: El sistema DEBE exportar la bandeja y el funnel a un formato tabular.
- **FR-22**: El sistema DEBE usar, por propósito (extracción, borradores), un proveedor y modelo
  de IA por defecto de una lista cerrada, **editable desde la web**, con respaldo opcional;
  DEBE ofrecer un selector de esa lista al generar cada borrador; y DEBE registrar el
  proveedor/modelo usado en cada resultado.
- **FR-23**: El sistema DEBE identificar decisores de forma **determinista, sin IA**, con listas de
  cargos permitidos y excluidos en PT/ES/EN, guardando evidencia por persona y descartando al
  resto de personas.
- **FR-24**: El sistema DEBE guardar solo los emails que la empresa publica en su propio dominio
  junto al decisor, NO DEBE adivinarlos ni verificarlos con servicios externos, y DEBE indicar
  por país si son utilizables para contacto en frío.
- **FR-25**: El sistema DEBE anonimizar de inmediato a la persona que se oponga y suprimir su
  re-extracción; NO DEBE enviar nombres de personas a proveedores de IA **ni incluirlos en consultas
  a proveedores de búsqueda**, ni acceder a redes profesionales. Antes de cualquier prompt DEBE
  eliminar del contenido los emails, los teléfonos y los bloques de persona (testimonios, autores,
  firmas, miembros del equipo), además de ocultar los nombres de decisores detectados.
- **FR-26** (RGPD art. 14): Todo borrador dirigido a una persona DEBE incluir un aviso breve de
  privacidad (quién trata sus datos, de qué fuente pública se obtuvieron, para qué, base legal,
  plazo de conservación, derecho de oposición y enlace a la política completa) y una **línea de
  baja con una dirección de email válida para oponerse** (LSSI art. 21.2); el sistema DEBE
  registrar cuándo se informó a la persona.
- **FR-27** (RGPD art. 5.1.c-e): El sistema DEBE guardar decisores solo de leads Tier A/B y DEBE
  anonimizar automáticamente a los no contactados a los **30 días** de su captura, y a los
  contactados a los **12 meses** de la última interacción.
- **FR-28** (RGPD art. 5.1.d): Antes de generar un borrador, si los datos del decisor tienen más de
  90 días, el sistema DEBE re-verificar la fuente y descartar los datos que ya no aparezcan.
- **FR-29** (RGPD arts. 15-17 y 21): El sistema DEBE permitir al operador (basta una herramienta
  de administración, no hace falta pantalla web) buscar a una persona por nombre o email, exportar
  sus datos, suprimirlos o registrar su oposición, y DEBE guardar un registro de cada solicitud y
  de su resolución sin copiar los datos personales.
- **FR-30**: El sistema NO DEBE extraer categorías especiales de datos (art. 9 RGPD) ni guardar
  fotografías de personas, y NO DEBE republicar las ofertas ni el contenido de las fuentes
  (derecho sui generis sobre bases de datos).
- **FR-31**: El sistema DEBE detectar de forma determinista (sin IA) los canales de contacto de cada
  empresa, con tipo, URL, evidencia, destinatario probable y estado, **incluido el formulario de
  «Trabaja con nosotros» con prioridad baja**, y DEBE recomendar el canal según un orden legal y
  comercial configurable por país.
- **FR-32**: El sistema NO DEBE rellenar, enviar ni invocar formularios de terceros, ni resolver
  CAPTCHAs; NO DEBE extraer teléfonos. Todo envío por cualquier canal lo hace el operador a mano
  e incluye el aviso del art. 14 y la línea de baja (FR-26).
- **FR-33** (lectura de muestra): Las métricas del funnel DEBEN mostrar, junto a cada tasa, el
  valor esperado según el rango de planificación y marcar «no concluyente» mientras no se alcance
  la muestra de la regla de decisión.
- **FR-34** (alojamiento de datos): Los datos DEBEN alojarse en la UE; la base de datos NO DEBE
  ser accesible por interfaces automáticas de la plataforma de alojamiento que no use el sistema
  (solo desde el backend); DEBE haber copias de seguridad periódicas si la plataforma no las
  incluye; y las pruebas automáticas NO DEBEN ejecutarse contra la base de datos real.
- **FR-35** (texto del CV): El texto completo del CV NO DEBE enviarse a proveedores de IA, ni
  aparecer en registros, exportaciones o respuestas del sistema; a la IA solo llega el resumen
  público de la prueba elegida.
- **FR-36** (vitalidad y tamaño): El sistema DEBE evaluar de forma determinista, a partir de las
  páginas públicas ya obtenidas, la **actividad** de la empresa (fecha del contenido más reciente,
  actualizaciones de la web, vacantes, año del copyright) y su **tamaño** (equipo observado,
  menciones de plantilla, web personal en primera persona); DEBE descartar con motivo a los
  freelancers individuales, las agencias sin actividad en 24 meses y las webs muertas o absorbidas;
  y DEBE exigir actividad reciente y equipo real para el Tier A.
- **FR-37** (olas y B1): El sistema DEBE repartir el descubrimiento en olas geográficas
  configurables **en paralelo por peso** (no secuencialmente) a partir de un **catálogo de países
  por ola** con su zona horaria IANA, calcular el solape horario con Portugal a partir de esa zona
  horaria y la fecha (no con valores fijos), puntuar la comunicación según el idioma y la forma de
  trabajo (escrita/asíncrona frente a inglés hablado nativo exigido) y el solape horario, NO DEBE
  enriquecer una empresa de descubrimiento cuyo dominio ya existe o está suprimido, y NO DEBE
  proponer email en frío en países sin regla verificada (`clarify.md` A17).
- **FR-38** (datos públicos básicos de la empresa, `clarify.md` A16): El sistema DEBE extraer de
  forma determinista (sin IA), de las páginas públicas ya obtenidas y de sus datos estructurados,
  los **datos de la empresa como persona jurídica** de la allowlist de `NORMATIVA-RGPD.md` §11:
  URLs públicas por tipo de página (inicio, servicios, nosotros, equipo, empleo, contacto, casos,
  blog, partners, aviso legal, privacidad), denominación social, forma jurídica, NIF/NIPC, datos
  registrales, ciudad y país de la sede, año de fundación, servicios, sectores, tecnologías,
  idiomas de la web, clientes y casos **como empresas**, programas de partners o certificaciones
  de empresa, buzones genéricos, URL del formulario de contacto y URL de páginas de empresa en
  redes enlazadas desde su web. Cada dato DEBE guardar su evidencia (URL, fragmento, fecha, método).
  NO DEBE guardar nada fuera de la allowlist.
- **FR-39** (empresa = persona física): Si los datos de identificación indican que la empresa es
  una **persona física** (autónomo, empresário em nome individual, freelancer: denominación con
  nombre propio, NIF de persona física o web en primera persona del singular), el sistema NO DEBE
  guardar esos datos de identificación y DEBE descartar el lead con motivo `solo_freelancer`.
- **FR-40** (descubrimiento automático ≠ contacto automático, A18): Descubrimiento,
  enriquecimiento, detección de decisores y canales, scoring y borrador PUEDEN ser automáticos; el
  envío NO. El módulo NO DEBE contener capacidad de envío por ningún medio (email, formularios,
  redes profesionales, mensajería) y DEBE comprobarse con un test de arquitectura. Los borradores
  solo se generan para leads Tier A/B.
- **FR-41** (registro de envío, A18): Al marcar un contacto como enviado, el sistema DEBE guardar
  la empresa (lead), el medio (`email` \| `contact_form` \| `job_posting` \| `linkedin_manual`), el
  canal detectado usado, `sent_at`, el **operador** que lo envió, la **plantilla y su versión**, la
  variante de mensaje, el tipo de contacto (oferta de contractor o candidatura a empleo), el
  **tipo de buzón remitente** (`personal_mailbox` \| `business_domain`, A19) y el estado legal de la
  regla del canal en ese momento. Las métricas DEBEN poder segmentarse por tipo de buzón.
- **FR-42** (respuestas, A18): El sistema DEBE registrar el resultado de cada respuesta
  (`interested` \| `not_interested` \| `unsubscribe`) con fecha y operador, y mover la etapa en
  consecuencia (`positive`, `lost`, `do_not_contact`) guardando el historial.
- **FR-43** (baja con precedencia absoluta, A18): Una baja DEBE suprimir a la empresa en todos los
  canales (y registrar la oposición de la persona si el buzón era nominativo) y DEBE prevalecer
  sobre score, tier, ola, descubrimiento, importación, alta manual, ofertas nuevas, re-score y
  cualquier nueva ronda de contactos. NO DEBE poder levantarse desde la web; solo mediante la
  herramienta de administración de privacidad cuando la propia empresa vuelva a iniciar el
  contacto, con la evidencia registrada.
- **FR-44** (reglas por país pendientes de verificación, A18): Las reglas de contacto por país,
  medio y tipo de buzón DEBEN vivir en configuración con su decisión (permitir/bloquear), su
  **estado legal** (`pending_verification` \| `verified`), fuente, fecha y responsable de la
  verificación. Todas empiezan pendientes. Las reglas que **bloquean** se aplican aunque estén
  pendientes; las que **permiten** NO DEBEN aplicarse hasta estar verificadas. Un canal con regla
  pendiente se muestra con aviso y marcarlo como enviado exige una confirmación explícita
  registrada.

## 6. Requisitos no funcionales

- **Coste:** operación del MVP ≤ presupuesto mensual definido por el operador (ver
  `clarify.md` Q3); coste por lead cualificado visible en las métricas.
- **Rendimiento:** procesar hasta 1.000 empresas/mes en ejecución programada sin intervención;
  la bandeja carga en < 2 s con 5.000 leads **con la base de datos en la nube** (pocas consultas
  por página).
- **Capacidad de almacenamiento:** el módulo no debe acercarse al límite del plan de la base de
  datos, que comparte con el resto de la plataforma (poda de contenido obtenido antiguo).
- **Tiempo del operador:** revisar un lead Tier A y dejar listo su borrador en ≤ 5 min de media.
- **Seguridad:** acceso solo para el operador autenticado; las credenciales de proveedores nunca
  se exponen en la interfaz ni en los registros; la entrada externa (texto de páginas y ofertas)
  se trata como no confiable (incluida la inyección de instrucciones hacia la interpretación
  automática).
- **Disponibilidad / tolerancia a fallos:** el fallo o la cuota agotada de un proveedor no
  detiene el ciclo; hay reintentos con espera creciente y un corte temporal por proveedor.
- **Auditabilidad:** cada score es reproducible y trazable hasta su evidencia y su versión de
  reglas.
- **Escalabilidad:** activar nuevos países o fuentes no requiere rediseño; el volumen objetivo a
  12 meses es de ≤ 5.000 empresas/mes.
- **Cumplimiento:** RGPD — los nombres y cargos de personas de contacto son datos personales:
  minimización (solo datos profesionales públicos y necesarios), interés legítimo documentado,
  retención limitada (p. ej. 12 meses sin interacción → anonimizar), derecho de oposición
  mediante la lista de supresión. Comunicaciones comerciales electrónicas: reglas por país en
  configuración con estado legal (FR-44); **notas de investigación pendientes de verificación**
  (España: consentimiento previo, incluso B2B; Portugal: opt-out para personas colectivas).
  Términos de uso de cada fuente.
  **Detalle y matriz de cumplimiento en `NORMATIVA-RGPD.md`**: los datos públicos siguen siendo
  personales; interés legítimo con LIA documentada; informar según el art. 14 en el primer
  contacto; registro de tratamientos (art. 30); cribado de DPIA; acuerdos de encargo (DPA) y
  transferencias con los proveedores externos; LOPDGDD art. 19 no ampara la prospección; en
  Portugal, el email nominativo es zona gris.

## 7. Entidades de datos (conceptuales)

- **Perfil de matching:** versión, CV de origen (referencia + huella), capacidades
  confirmadas/potenciales, pruebas citables, idiomas, tarifa mínima, países y fases, pesos. Se
  relaciona con los resultados de score.
- **CV (existente en la plataforma, no se duplica):** registro del operador con su texto extraído,
  tipo (markdown/PDF) y si es el principal.
- **Fuente:** tipo (portal de empleo, feed, directorio, búsqueda, web de empresa), país, método,
  frecuencia, estado, términos revisados.
- **Oferta:** datos normalizados, fechas, estado (vigente/caducada), fuentes enlazadas; pertenece
  a una empresa.
- **Empresa:** nombre, dominio canónico, alias/dominios, país, tipo, rango de empleados, estado,
  **origen** (oferta, descubrimiento, importación, alta manual), marca «necesita investigación» y
  **datos públicos básicos** de persona jurídica (FR-38): denominación social, forma jurídica,
  NIF/NIPC, datos registrales, ciudad, año de fundación, servicios, sectores, tecnologías, idiomas
  de la web, clientes/casos como empresas, URLs públicas por tipo de página, con evidencia.
- **Página obtenida:** URL, tipo de página, contenido limpio (se poda con el tiempo), huella para
  detectar cambios, fecha.
- **Intento de obtención:** método, intentos, estado (incluido «bloqueado»), duración, coste
  estimado, motivo de fallo.
- **Consulta de búsqueda:** texto, propósito (descubrimiento o resolución), ola y familia,
  resultados guardados (caché), empresas nuevas obtenidas, estado, coste y fecha.
- **Señal:** dimensión, tipo, valor, naturaleza (hecho | inferencia), confianza, evidencia (URL,
  fragmento, fecha, método).
- **Resultado de score:** subscores, Lead Score, confianza, tier, versión de reglas y de perfil;
  contiene razones.
- **Razón de score:** puntos, señal de origen, explicación.
- **Persona de contacto (decisor):** nombre, cargo, categoría (fundador / dirección / liderazgo
  técnico), si es el principal, email publicado (opcional), URL de perfil enlazada desde la web,
  evidencia (URL, fragmento, fecha, método), base legal, fecha de retención, oposición, fecha en
  que se le informó (art. 14), fecha de última verificación.
  Pertenece a una empresa. Solo existen decisores de leads A/B; nunca reclutadores ni trabajadores.
- **Canal de contacto:** tipo (oferta publicada, llamada a freelancers, página de partners,
  formulario de contacto, formulario de empleo, buzón genérico, página de empresa en red
  profesional), URL, email genérico (solo buzones de empresa), nombres de campos del formulario,
  si tiene CAPTCHA, destinatario probable, evidencia, estado (activo, roto, usado). Pertenece a una
  empresa; un contacto enviado referencia el canal usado.
- **Solicitud de privacidad:** tipo (acceso, supresión, oposición, rectificación), fecha de
  recepción, fecha de resolución, resultado (sin copiar los datos personales de la persona).
- **Contacto (outreach):** lead, medio de envío (`email`, `contact_form`, `job_posting`,
  `linkedin_manual`), canal detectado, tipo de buzón remitente (personal o dominio propio), tipo
  (oferta de contractor o candidatura), variante,
  plantilla y versión, señal usada, borrador, fecha de envío, operador, estado legal de la regla y
  confirmación del operador si estaba pendiente, resultado de la respuesta (`interested`,
  `not_interested`, `unsubscribe`) y etapa.
- **Evento de etapa:** contacto, etapa anterior, etapa nueva, operador, fecha (historial completo).
- **Regla de contacto por país (configuración, no tabla):** país, medio, tipo de buzón, decisión,
  estado legal, fuente, fecha y responsable de la verificación.
- **Oportunidad:** tipo (prueba, proyecto, retainer, staff augmentation), horas, tarifa, importe,
  estado; un contacto puede tener varias.
- **Presupuesto y consumo:** categoría, periodo, límite (editable), gasto acumulado.
- **Configuración de IA:** propósito, proveedor, modelo, proveedor/modelo de respaldo (editable).
- **Regla de decisión:** muestra, ventana, umbrales, fecha de fijación, resultado.
- **Supresión:** empresa o dominio (incluidos NIF/NIPC y nombre para la lista DGC), origen (manual,
  oposición, lista DGC), motivo, fecha.
- **Oposición de persona:** huella de la persona (sin datos en claro), fecha.

## 8. Fuera de alcance (esta versión)

- Producto SaaS, multi-tenant o white-label para terceros (la idea de
  `PIPELINE-A-B-COMPARATION.md`). El sistema es de **uso personal**.
- Envío automático de emails, secuencias o seguimientos automáticos.
- Scraping de redes profesionales o portales que lo prohíben en sus términos; uso de proxies
  para evadir bloqueos.
- Reescritura automática del CV por oferta o simulación de ATS (posible fase 3).
- Enriquecimiento de emails personales mediante servicios de adivinación o verificación.
- Acceso a redes profesionales (p. ej. perfiles de LinkedIn) para buscar personas; la persona que
  falte la añade el operador a mano.
- Guardar datos de trabajadores que no son decisores (reclutadores, RRHH, desarrolladores, etc.).
- Crawl masivo de sitios completos; motor de rastreo propio de alto volumen (Crawl4AI/FastAPI,
  evaluado también en la variante MUSE-SPARK); navegadores automatizados, rotación de IPs y
  cualquier servicio de resolución de CAPTCHAs o de evasión anti-bot.
- Rastreo de directorios de agencias (Sortlist, Clutch, partners.laravel.com): el operador los
  consulta a mano e importa la lista.
- Búsqueda semántica / RAG sobre el CV y embeddings (posible fase 3).
- Pantallas web de solicitudes de privacidad y de importación de la lista DGC (en el MVP son
  herramientas de administración; sus registros sí se guardan).
- La landing `/agencies` de argenis.dev (proyecto aparte; **prerrequisito** de la operación,
  no del desarrollo).
- Aplicación móvil y notificaciones push.

## 9. Supuestos y decisiones abiertas

- **Decidido (Q1, revisado por A1):** el MVP combina **ofertas como señal**, **descubrimiento de
  agencias** PT/ES (US-7) e importación de la lista ICP. (`clarify.md` Q1, A1)
- **Decidido (Q24 → A12, sustituye a Q2):** colchón de **2 meses** (hasta ~16-11-2026) → sistema
  operable al final de la semana 2, bandeja web en la semana 3, prospección manual desde el día 1
  (registrada para que cuente en la muestra) y dos vías de ingresos en paralelo (empleo/freelance
  directo y consultoras nearshore PT). (`clarify.md` Q24, A12)
- **Decidido (Q25):** base de datos en el plan **gratuito** de Supabase → copias de seguridad
  propias y poda de contenido obligatorias.
- **Decidido (OP-22):** CV de origen = **`Argenis_Gonzalez_CV_2026.md`**.
- **Decidido (A13):** la extracción usa HTTP directo + servicio externo gestionado; **sin**
  microservicio propio de rastreo ni proxies residenciales; la efectividad se mejora en la
  búsqueda y se mide.
- **Decidido (A2-A11, A15):** selector de IA en el borrador y ajustes de IA editables desde la web;
  presupuestos editables; privacidad y lista DGC como herramientas de administración; base de
  datos alojada en la nube en la UE (Supabase); **modelo de datos completo** (el operador pidió
  conservar todas las entidades); perfil leído del CV guardado en la plataforma sin RAG; obtención
  sin evasión y sin LinkedIn. (`clarify.md` A1-A15)
- **Decidido (A16):** se extraen los datos públicos básicos de la empresa (FR-38) con la allowlist
  de `NORMATIVA-RGPD.md` §11; Firecrawl con `proxy: "basic"` verificado y `storeInCache: false`;
  Tavily con `exclude_domains` y sin nombres de personas en las consultas; limpieza de datos de
  personas antes de la IA; transferencias a EE. UU. con DPF **y** cláusulas tipo.
- **Decidido (Q3):** presupuesto de herramientas **≤ €30/mes** (búsqueda ≤ €8, extracción ≤ €15,
  IA ≤ €7), configurable. (`clarify.md` Q3)
- Supuesto: tarifa de entrada €30-35/h, consolidada €35-45/h, urgente/legacy €45+/h (CHATGPT-8).
- Supuesto: planificación con respuesta positiva del 2-5 % en frío (benchmarks 2026).
- Supuesto: el operador firma un acuerdo de no captación (confirmado en `ATRIBUCIONES.md`).
- Supuesto: idioma de contacto = el de la oferta/empresa; en España y LatAm, español con
  «ustedes» (`EMAIL-OUTREACH-AGENCIAS.md` §0).

## 10. Criterios de éxito (medibles)

- En la **semana 2** desde el inicio: el sistema entrega ≥ 30 leads Tier A/B con evidencia en
  PT/ES (entre ofertas, descubrimiento e importación) y el operador ya envió los primeros
  contactos. Tasas medidas **por origen** del lead.
- 0 peticiones a redes profesionales y 0 reintentos por otra vía tras un bloqueo (verificable en
  el registro de llamadas).
- **Calidad de los leads:** 0 freelancers individuales, agencias paradas o webs absorbidas en
  Tier A/B en la revisión manual de los primeros 50 leads; 100 % de Tier A con actividad en los
  últimos 12 meses; % de leads A/B y tasa de respuesta medidos **por ola**.
- En **8 semanas** de operación: ≥ 150 contactos cualificados registrados, con tasas por etapa
  medidas (sustituyen a los supuestos del análisis).
- Tiempo medio de revisión + borrador por lead Tier A ≤ 5 min.
- Coste mensual real ≤ presupuesto fijado; coste por lead cualificado ≤ €0,50.
- 100 % de los scores Tier A con al menos una señal comercial **verificada** (hecho, no
  inferencia).
- Tasa de respuesta **por tipo de canal** medida desde el primer contacto (confirma o descarta con
  datos que «Trabaja con nosotros» pierde el contacto); % de leads A/B con al menos un canal
  permitido.
- Porcentaje de leads Tier A/B con decisor identificado medido semanalmente (hipótesis inicial
  ≥ 50 %); 0 personas guardadas con cargos de la lista de exclusión.
- La regla de decisión emite un resultado inequívoco al completar la muestra (escalar / iterar
  mensaje e ICP / parar).
- Objetivo de negocio (no del software): 1 cliente recurrente en ≤ 12 semanas en el escenario
  base.
