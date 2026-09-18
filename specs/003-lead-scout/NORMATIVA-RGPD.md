# Notas normativas — RGPD (UE) + España + Portugal · LeadScout

> Fecha: 2026-09-16 · Investigación con WebSearch/WebFetch y Firecrawl (Tavily sin cuota). La §10
> («¿es legal? LinkedIn, CAPTCHAs, anti-bots, subirlo a la web») se añadió en la 6ª pasada con
> Tavily. La **§11 (datos públicos básicos de la empresa)** y las correcciones de N11 y §1 se
> añadieron el **17-09-2026** (7ª pasada, verificadas con Tavily y Firecrawl; `clarify.md` A16).
> **No es asesoría legal.** Son notas de diseño para que el sistema nazca alineado con la normativa;
> los puntos marcados ⚠️ deben validarse con un abogado de protección de datos antes de operar.
> Fuentes al final (§9). Detalle técnico en `research.md` R8 y R10.

---

## 1. La idea clave: «público» no significa «libre de usar»

- **Datos públicos siguen siendo datos personales.** Que el nombre y el cargo de un CTO aparezcan
  en la web de su agencia no es consentimiento ni base legal. El RGPD se aplica en cuanto se
  **recogen, guardan, organizan o usan** (EDPB, Guidelines 03/2026 sobre web scraping: **borrador**
  adoptado el 07-07-2026 y en consulta pública hasta el 30-10-2026; puede cambiar antes de la
  versión final). *Matiz (6ª pasada):* esas Guidelines tratan el scraping **para entrenar IA
  generativa**; LeadScout no entrena modelos, así que se aplican **por analogía** (público ≠
  consentimiento; la falta de `robots.txt` tampoco lo es). *7ª pasada:* entre las salvaguardas que
  recomienda el borrador están criterios de recogida acotados, exclusión de fuentes, **respeto de
  las medidas anti-scraping** y mecanismos de oposición: coinciden con FR-13, FR-17 y FR-25.
- **Mirar** una web pública no es tratamiento. **Guardar** a esa persona en una base de datos
  para contactarla, sí lo es → todo lo de abajo aplica.
- Base legal realista: **interés legítimo (art. 6.1.f RGPD)** con el test de tres pasos de las
  EDPB Guidelines 1/2024 (interés legítimo → necesidad → ponderación), **documentado**. El
  consentimiento no es viable para descubrir leads.

## 2. Obligaciones que aplican al proyecto

| # | Obligación | Norma | Qué exige en LeadScout |
|---|---|---|---|
| N1 | Base legal documentada | RGPD art. 6.1.f · EDPB 1/2024 | Evaluación de interés legítimo (LIA) escrita **antes** de operar |
| N2 | **Informar a la persona** cuyos datos no se obtuvieron de ella | RGPD **art. 14** | Informar en un plazo razonable (máx. 1 mes) o, a más tardar, **en la primera comunicación** (art. 14.3, texto verificado). Publicar un aviso en una web **no basta**: en el caso Bisnode (Polonia) los tribunales **anularon la multa** de ~220.000 €, pero **confirmaron** que el aviso web no cumplía el art. 14 y había que informar directamente (casación desestimada) |
| N3 | Minimización | RGPD art. 5.1.c | Solo decisores; solo nombre, cargo, canal profesional publicado; del resto del equipo, nada |
| N4 | Limitación del plazo de conservación | RGPD art. 5.1.e | No guardar personas que nunca vas a contactar |
| N5 | Exactitud | RGPD art. 5.1.d | Fecha de captura y re-verificación antes de contactar si el dato es antiguo |
| N6 | **Oposición absoluta** al marketing directo | RGPD art. 21.2-3 · Lei 41/2004 art. 13.º-A n.º 3 | Atender siempre; ofrecer la baja **en cada mensaje** (CNPD); no volver a tratar |
| N7 | Acceso, supresión, rectificación | RGPD arts. 15-17 | Poder encontrar a una persona, exportar lo que hay y borrarlo |
| N8 | Registro de actividades de tratamiento | RGPD art. 30 | La exención de < 250 empleados **no aplica** si el tratamiento no es ocasional (y este es continuo) → RoPA de 1 página |
| N9 | Evaluación de impacto (DPIA) | RGPD art. 35 | Probablemente no obligatoria (no hay gran escala, categorías especiales ni vigilancia sistemática de personas; se puntúan **empresas**) → dejar documentado el cribado |
| N10 | Categorías especiales | RGPD art. 9 | Nunca extraerlas (salud, opiniones, etc.); no guardar fotos |
| N11 | Encargados y transferencias fuera de la UE | RGPD art. 28 y cap. V | Tavily, Firecrawl, Google (Gemini) y Anthropic procesan contenido con datos personales, y **Supabase** aloja la base de datos (decisores, canales, funnel; región UE) → acuerdo de encargo (DPA) + certificación en el EU-US Data Privacy Framework **y además** cláusulas tipo. El DPF sigue vigente (el Tribunal General lo confirmó en sept. 2025; recurso Latombe **C-703/25 P** pendiente en el TJUE), pero **el 31-07-2026 la presidenta del EDPB pidió a la Comisión reevaluarlo** tras *Trump v. Slaughter* (el Supremo de EE. UU. permite destituir a los comisionados de la FTC sin causa, y la decisión de adecuación se apoya en su independencia). No lo suspende; por eso no se depende solo del DPF. **Tavily** (7ª pasada): adquirida por **Nebius** (Ámsterdam) en feb. 2026; responsable según su política: AlphaAI Technologies Inc. (Nueva York); SOC 2 Type II (informe de junio de 2026) e ISO 27001; lista de subencargados y DPA en su *trust center*; **su política permite usar las consultas para mejorar el servicio** → nunca nombres de personas en las consultas (FR-25). **Firecrawl**: SOC 2 Type II, DPA disponible, *zero data retention* solo en Enterprise; por defecto guarda las páginas en su índice/caché (`storeInCache: true`) → LeadScout envía `storeInCache: false` (FR-13) |
| N12 | Decisiones automatizadas | RGPD art. 22 | No aplica: el score es de empresas y no produce efectos jurídicos sobre personas; decide el operador |
| N13 | Seguridad | RGPD art. 32 | Ya cubierto (plan §8) |

> **Estado de §3 y §4 (A18, 17-09-2026): NOTAS DE INVESTIGACIÓN, NO REGLAS DEFINITIVAS.** Cada fila
> se traduce en una regla de config con `legal_status: pending_verification` (FR-44). Las que
> **bloquean** (p. ej. España sin email en frío) se aplican ya por prudencia; las que **permiten**
> (p. ej. Portugal, buzón genérico con baja) **no se aplican** hasta que el abogado (OP-15) o la
> verificación por país (OP-26) las marque `verified` con fuente, fecha y responsable.

## 3. España

| Tema | Regla | Consecuencia |
|---|---|---|
| **LOPDGDD art. 19** | Presume interés legítimo para datos de contacto y cargo de quien trabaja en una persona jurídica **solo** si (a) son los datos necesarios para su localización profesional y (b) la finalidad es **mantener relaciones con la persona jurídica** | La doctrina y la AEPD entienden que **no ampara el marketing ni las comunicaciones comerciales**. Para prospectar hace falta la LIA general (N1), no la presunción |
| **LSSI art. 21** (texto verificado en el BOE) | 21.1: prohibido el email publicitario «que previamente no hubieran sido solicitadas o expresamente autorizadas por los destinatarios» (salvo relación contractual previa). **21.2: en cada comunicación, un procedimiento sencillo y gratuito para oponerse; por email, «una dirección de correo electrónico u otra dirección electrónica válida»** | Sin email en frío en España · el aviso de baja de **todos** los mensajes incluye un email válido de oposición (FR-26) |
| AEPD, criterio sobre profesionales | Los datos recogidos para un fin no pueden reutilizarse para fines comerciales que la persona no espera razonablemente | Refuerza N3/N4: guardar solo lo que se va a usar y solo para contactar a la empresa |

## 4. Portugal

| Tema | Regla | Consecuencia |
|---|---|---|
| **Lei 41/2004, arts. 13.º-A y 13.º-B** (republicada por la Lei 46/2012) | Personas singulares: consentimiento previo y expreso. Personas colectivas: régimen propio (lista de oposición) | — |
| **CNPD, Diretriz/2022/1, punto 2** | La directriz **no aborda** el envío de marketing a personas colectivas. Para personas singulares **sin relación previa: solo con consentimiento previo y expreso** | ⚠️ **Zona gris:** un email **nominativo** publicado (`joao@agencia.pt`) identifica a una persona singular. Tratarlo como opt-out de persona colectiva **no está confirmado** |
| **Lei 41/2004 art. 13.º-B** (texto verificado) | N.º 2: la **Direção-Geral do Consumidor (DGC)** mantiene una **lista nacional de personas colectivas que se oponen** al marketing directo no solicitado. N.º 5: quien envía marketing está **obligado a consultar la lista**, actualizada **cada trimestre** y facilitada por la DGC a petición | Antes de proponer un email a una empresa portuguesa (buzón genérico), comprobar que no está en la lista DGC → importarla a la lista de supresión cada trimestre (FR-17, T074, OP-16) |
| Lei 58/2019 | Ejecución nacional del RGPD | Autoridad: CNPD |

→ **Cambio respecto a Q16:** el email nominativo publicado en Portugal pasa de «usable en frío»
a **«zona gris — no recomendado en frío»**. En su lugar: responder a la oferta publicada, el
formulario de contacto, el buzón **genérico** de la empresa (persona colectiva, con baja) o la red
profesional enviada a mano. El email se sigue guardando (es un dato de localización profesional)
pero no se propone para contacto en frío.

## 5. Datos de las fuentes: bases de datos y términos

- **Derecho sui generis sobre bases de datos** (Directiva 96/9/CE) — TJUE **C-762/19 CV-Online
  Latvia** (03-06-2021), precisamente sobre un buscador que agregaba anuncios de empleo: hay
  infracción cuando la extracción o reutilización de una parte sustancial **pone en riesgo la
  inversión** del creador de la base de datos.
  → Usar **APIs y RSS oficiales** bajo sus términos (ya en FR-3/FR-13), guardar el enlace a la
  fuente, **no republicar** las ofertas y no replicar portales completos.
- Términos de uso y `robots.txt`: ya en FR-13 (no se activa una fuente sin revisar sus términos).
- **Ejemplo verificado — Remotive:** no enviar sus ofertas a otros portales, **enlazar y citar a
  Remotive** como fuente, ofertas con 24 h de retraso, **máximo recomendado 4 peticiones/día**
  (bloqueo si > 2/min). → Frecuencia ≥ 6 h y fuente visible en la bandeja (T020).

## 6. ¿Coincide con la spec? — Matriz de cumplimiento

| # | Requisito normativo | Antes de esta revisión | Ahora |
|---|---|---|---|
| N1 | LIA documentada | ⚠️ Mencionado en NFR, sin tarea | ✅ OP-9 |
| N2 | **Informar (art. 14)** | ❌ **No existía** | ✅ **FR-26**: aviso breve en el primer contacto + enlace a la política; registro `notified_at` · T071, OP-11 |
| N3 | Minimización | ✅ Solo decisores (US-11) | ✅ + las personas solo se **guardan** cuando el lead es Tier A/B (antes: todos los leads) · FR-27 |
| N4 | Conservación | ⚠️ 12 meses para todos | ✅ **FR-27**: anonimizar a los **30 días** si no se contactó; 12 meses tras la última interacción si hubo contacto · T070 |
| N5 | Exactitud | ❌ Sin re-verificación | ✅ **FR-28**: re-verificar la fuente antes del borrador si la captura tiene > 90 días · T072 |
| N6 | Oposición + baja en cada mensaje | ⚠️ Oposición sí; baja solo en emails de PT | ✅ Línea de baja en **todos** los borradores · T071 |
| N7 | Acceso / supresión | ❌ No existía | ✅ **FR-29**: buscar persona, exportar y suprimir + registro de solicitudes (por comando) · T073 |
| N8 | RoPA | ❌ | ✅ OP-10 |
| N9 | Cribado DPIA | ❌ | ✅ OP-13 |
| N10 | Sin categorías especiales ni fotos | ⚠️ Implícito | ✅ FR-30 · T047 (se excluyen las imágenes) |
| N11 | DPA / DPF con los proveedores | ❌ | ✅ OP-12 · nombres ocultados antes de la IA (ya en FR-25) |
| N12 | Art. 22 | ✅ Scoring de empresas; decide el operador | ✅ |
| ES | LOPDGDD 19 / LSSI 21 | ✅ Sin email en frío | ✅ + nota: art. 19 no cubre prospección → LIA general |
| PT | Lei 41/2004 / CNPD 2022/1 | ⚠️ Email nominativo «usable en frío» | ✅ **Corregido a zona gris** (clarify Q16-bis) |
| PT | **Lista DGC de personas colectivas (art. 13.º-B)** | ❌ No existía (hallado en el pase de verificación) | ✅ Importación trimestral a supresión + bloqueo en `ChannelAdvisor` · FR-17 · T074 · OP-16 |
| ES | Email válido de oposición en cada mensaje (LSSI 21.2) | ⚠️ Solo «línea de baja» | ✅ FR-26 exige una dirección de email válida |
| Fuentes | Términos de cada API (p. ej. Remotive: atribución, ≤ 4/día) | ⚠️ Genérico | ✅ T020 + revisión OP-5 |
| Canales | Formularios de terceros («contacto», «Trabaja con nosotros») | ❌ No contemplado | ✅ US-12 / FR-32: solo detección, **nunca envío automático** ni CAPTCHAs; envío manual con aviso del art. 14 y baja. ⚠️ El uso comercial del formulario de contacto se añade a la consulta al abogado (OP-15) |
| BD | Derecho sui generis (C-762/19) | ✅ APIs/RSS oficiales | ✅ + sin republicación (FR-30) |
| EDPB 03/2026 | Scraping de datos públicos | ✅ Marca de tiempo, fuente fiable, minimización | ✅ + OP-14: revisar la versión final (tras el 30-10-2026) |
| ES / PT / UE | **No sortear protecciones** (CP 197 bis.1 · Lei 109/2009 art. 6 · Directiva 2013/40/UE) | ⚠️ FR-13 lo decía, pero Firecrawl por defecto (`proxy: auto`) escalaba a proxies anti-bot | ✅ **A11 (6ª pasada):** bloqueo = parada; Firecrawl `proxy: "basic"`; User-Agent honesto; sin navegadores automatizados ni resolvedores de CAPTCHA · FR-13 · T038, T039, T041, T078 |
| LinkedIn | ToS + KASPR (CNIL) + Proxycurl/ProAPIs | ✅ Sin acceso a redes profesionales | ✅ + denylist también en resultados de búsqueda · T036, T042 |
| Encargado | **Supabase** aloja la base con datos personales | ❌ No contemplado | ✅ Región UE, RLS, DPA y RoPA · FR-34 · OP-10, OP-12, OP-19 · T010, T078 |
| CV del operador | Texto del CV (dato propio, pero el más sensible del módulo Cvs) | ❌ No contemplado | ✅ Solo lectura, no se copia, nunca a la IA · FR-35 · T017 |
| Datos de empresa | Qué datos públicos básicos se pueden guardar sin tratar datos personales | ⚠️ Implícito (dominio, tipo, tamaño) | ✅ **A16:** allowlist de §11 (considerando 14 + LSSI art. 10 + DL 7/2004 art. 10) · autónomo/ENI = persona física → no se guarda y se descarta · FR-38, FR-39 · T082 |
| Caché del extractor | Firecrawl guarda por defecto las páginas (con nombres) en su índice compartido | ❌ No contemplado | ✅ **A16:** `storeInCache: false` en cada petición · FR-13 · T039 |
| Proxy del extractor | API v2: `proxy` por defecto `auto`, parámetro *deprecated* y `enhanced` al **mismo coste** que `basic` | ⚠️ `basic` sin verificar | ✅ **A16:** `proxy: "basic"` + comprobación de `metadata.proxyUsed` en cada respuesta; si no coincide → descartar y desactivar Firecrawl · FR-13 · T039 |
| Búsqueda | Las consultas a Tavily pueden usarse para mejorar su servicio | ❌ No contemplado | ✅ **A16:** sin nombres de personas en las consultas; denylist en `exclude_domains` · FR-25 · T042 |
| Datos de terceros hacia la IA | Testimonios, autores de blog, firmas, emails y teléfonos en las páginas | ⚠️ Solo nombres de decisores ocultados | ✅ **A16:** `PersonalDataScrubber` antes de cada prompt · FR-25 · T083 |
| Envío manual | Buzón con el que el operador envía los contactos (encargado) y reglas de canal | ⚠️ No definido | ✅ **A17 + A19:** por fases. Desarrollo y pruebas → Gmail personal. Outreach real → buzón del dominio propio con cualquier proveedor de correo empresarial con términos de encargo (p. ej. Google Workspace, donde Google actúa como encargado según su Cloud Data Processing Addendum; no es obligatorio). `sender_kind` registrado en cada envío. Ni el envío manual ni el tipo de cuenta eximen de LSSI art. 21 (AEPD A/00008/2019: apercibimiento por un solo correo sin baja), lista DGC, art. 14 ni oposición · FR-16, FR-41 · OP-27 |
| Consultas hacia LinkedIn | Obtener datos de LinkedIn a través de fragmentos de un buscador | ⚠️ Solo se filtraban resultados | ✅ **A17:** guard que rechaza la consulta antes de llamar a Tavily + intermediarios de datos de contacto en la denylist · FR-13 · T036, T042 |
| Contacto humano | Descubrimiento automático ≠ contacto automático | ✅ Sin envío automático (FR-16) | ✅ **A18:** el módulo no tiene capacidad de envío (test de arquitectura); registro de cada envío con operador, medio y plantilla · FR-40, FR-41 · T085 |
| Oposición (N6) | Baja con precedencia absoluta | ✅ Lista de supresión | ✅ **A18:** `SuppressionGate` único en todas las entradas; no se levanta desde la web; respuesta registrada (`interested`/`not_interested`/`unsubscribe`) · FR-42, FR-43 · T084 |
| Reglas por país | §3-§4 tratadas como lógica definitiva | ⚠️ Reglas fijas en código | ✅ **A18:** config con estado legal; bloquear se aplica, permitir solo verificado; canal pendiente con confirmación registrada · FR-44 · T061 |
| Transferencias | DPF cuestionado por el EDPB (31-07-2026) | ⚠️ DPF **o** cláusulas tipo | ✅ **A16:** DPF **y** cláusulas tipo en cada DPA · OP-12 |

## 7. Pendiente de validar con un abogado ⚠️

1. Email nominativo publicado en Portugal: ¿régimen de persona colectiva (opt-out) o singular
   (consentimiento)?
2. Si guardar a un decisor < 30 días sin contactarlo y anonimizarlo después exige igualmente el
   aviso del art. 14 (plazo de 1 mes del art. 14.3.a).
3. Texto final del aviso breve del art. 14 y de la política de privacidad de argenis.dev.
4. (A19) Si se contacta a agencias reales desde una **cuenta de correo personal** (sin acuerdo de
   encargo del art. 28 para los datos que quedan en el buzón y con un remitente distinto del
   responsable de la política), ¿es un incumplimiento o solo un riesgo aceptable en un volumen de
   10-15 al día?

## 8. Revisión de argenis.dev (16-09-2026)

Revisado con Firecrawl: `/privacy` (actualizada el 23-07-2026), `/terms` (28-06-2026) y el
formulario de la home. `/cookies` no se revisó en detalle.

### 8.1 Lo que está bien
- Responsable y contacto (`info@argenis.dev`), derechos, autoridad (CNPD), transferencias con
  DPF/cláusulas tipo, encargados (Cloudflare, Resend), conservación descrita, sin categorías
  especiales, ley aplicable portuguesa.
- Formulario con honeypot + Turnstile; **la casilla de marketing no es obligatoria y viene
  desmarcada** (consentimiento libre); enlaces a privacidad y términos junto al formulario.
- La FAQ de no-captación («the client stays yours… non-solicitation agreement») es coherente con el
  pitch de marca blanca.

### 8.2 Huecos para LeadScout (art. 14) — la política no cubre a los prospectos
La política dice que aplica «when you visit argenis.dev or contact me through it». **No cubre a
las personas cuyos datos obtienes tú de fuentes públicas.** Falta una sección «Prospección B2B /
datos obtenidos de fuentes públicas» (en ES y PT además de EN, anclable como `/privacy#prospeccion`):

| Art. 14 | Qué añadir |
|---|---|
| 1.a Identidad | Nombre completo (hoy solo «Argenis»): trabajador independiente en Portugal + email de contacto |
| 1.c Finalidad y base legal | Prospección B2B: ofrecer servicios de desarrollo a empresas; interés legítimo (art. 6.1.f) |
| 2.b Interés legítimo concreto | Contactar a empresas que publican necesidad de desarrollo o trabajan con el stack Laravel/Vue |
| 1.d Categorías de datos | Nombre, cargo, empresa, email profesional publicado, URL de perfil enlazada desde su web, fragmento de la página donde aparece |
| 2.f Fuente | Web pública de la empresa y ofertas de empleo publicadas (portales/APIs), con la URL concreta en cada contacto |
| 1.e Destinatarios | Encargados de LeadScout: hosting de argenis-hub y su base de datos, Firecrawl y Tavily (obtención/búsqueda), proveedores de IA (Google, Anthropic; **sin nombres de personas**) |
| 1.f Transferencias | EE. UU. con DPF/cláusulas tipo |
| 2.a Plazo | 30 días si no hay contacto; 12 meses desde la última interacción |
| 2.c + **art. 21.4** | Derechos, con la **oposición en un párrafo propio y destacado** («puedes oponerte en cualquier momento… responde BAJA o escribe a…») |
| 2.e | Reclamación ante la CNPD o la autoridad de tu país (ya está) |
| 2.g | Aclarar que se puntúan **empresas** y no hay decisiones automatizadas sobre personas |

### 8.3 Otros hallazgos del sitio (no son de LeadScout, pero conviene corregirlos)
1. **Aviso de «plantilla» visible en `/privacy` y `/terms`** («This template is provided… have it
   reviewed by a qualified lawyer»): quitarlo de la página pública (resta credibilidad y deja
   constancia de que no se revisó).
2. **Casilla de marketing sin respaldo en la política:** el texto ofrece «AI content, updates,
   features, and special offers» y el campo se llama `smsConsent` (parece heredado de otro
   proyecto). La política no menciona el email marketing ni el consentimiento (art. 6.1.a) como
   base. → Si no envías newsletter, quitar la casilla; si la mantienes, añadirla a la política,
   guardar la prueba del consentimiento (fecha, texto, origen) y tener un enlace de baja real.
3. **Teléfono obligatorio** (prefijo por defecto +1 US): para pedir una consulta basta el email →
   hacerlo opcional (minimización, art. 5.1.c).
4. **Destino del formulario:** envía a `/api/appointments`, pero la política solo menciona Resend.
   Esa ruta **no existe en argenis-hub** → confirmar dónde se guardan los datos (¿otra API/base de
   datos?) y listar ese proveedor y su plazo.
5. `/terms` son términos de uso **del sitio web**: correctos para ese fin.

### 8.4 ¿Los términos de argenis.dev aplican a argenis-hub / LeadScout?
**No.** Los términos regulan a quien **visita la web**. LeadScout es una herramienta **interna**
de argenis-hub (panel con login, un solo usuario), y las personas prospectadas nunca la usan. Lo
que cubre LeadScout es:
1. la **política de privacidad** (con la sección de §8.2), que es lo que enlaza el aviso breve de
   cada borrador;
2. la documentación interna (LIA, RoPA, cribado de DPIA, DPA de proveedores: OP-9 a OP-13);
3. los **términos de cada fuente** (APIs de empleo, webs de las agencias).

Si algún día argenis-hub se ofrece a terceros (SaaS), entonces sí harán falta términos de servicio
y un DPA como encargado (fuera de alcance, Q5).

## 9. Fuentes

- EDPB — web scraping y GenAI (Guidelines 03/2026, 07-07-2026):
  https://www.edpb.europa.eu/news/edpb-sheds-light-on-anonymisation-and-web-scraping-for-generative-ai-and-adopts-final-version_en
  · https://www.privacycoreservices.com/en/insights/ai-web-scraping-gdpr
- EDPB Guidelines 1/2024, interés legítimo:
  https://www.edpb.europa.eu/system/files/2024-10/edpb_guidelines_202401_legitimateinterest_en.pdf
  · https://www.timelex.eu/en/blog/three-step-test-practice-edpb-guidelines-legitimate-interest-0
- Art. 14 / Bisnode (UODO):
  https://www.privacy-advice.com/en/news/polish-supervisory-authority-imposes-fine-for-breach-of-information-obligation-under-art-14-gdpr/
  · https://www.lexology.com/library/detail.aspx?g=a10fbec0-8234-41da-9ddb-9cac58c360c6
- Art. 30: https://gdpr-info.eu/art-30-gdpr/ ·
  https://www.dataprotection.ie/sites/default/files/uploads/2023-04/Records%20of%20Processing%20Activities%20(RoPA)%20under%20Article%2030%20GDPR.pdf
- LOPDGDD art. 19: https://www.iberley.es/legislacion/articulo-19-ley-organica-proteccion-datos-personales-garantia-derechos-digitales-lopdgdd
  · https://garciadenovales.es/blog/tratamiento-de-los-datos-profesionales-de-contacto-en-la-normativa-de-proteccion-de-datos-2/
- AEPD, criterio sobre empresarios autónomos:
  https://www.aepd.es/informes-y-resoluciones/criterios-juridicos-aepd/tratamiento-datos-personales-empresarios-autonomos
- LSSI art. 21: https://www.dlapiperdataprotection.com/?t=electronic-marketing&c=ES
- CNPD Diretriz/2022/1 (leída con Firecrawl, punto 2):
  https://www.cnpd.pt/umbraco/surface/cnpdDecision/download/121958 ·
  https://abreuadvogados.com/en/conhecimento/publicacoes/artigos/new-directive-on-electronic-communications-of-direct-marketing/
- Lei 41/2004: https://www.anacom.pt/render.jsp?contentId=944401&languageId=0
- Lei 41/2004 art. 13.º-B (texto, Parlamento):
  https://app.parlamento.pt/webutils/docs/doc.pdf?path=6148523063446f764c324679626d56304c334e706447567a4c31684a5355786c5a79394562324e31625756756447397a5357357059326c6864476c325953396b4e324d325a6a45344d7930305a54466c4c54517a5a6a557459544e6c4d5330315a57497a4e6d55794d6d49315a6a67755a47396a&fich=d7c6f183-4e1e-43f5-a3e1-5eb36e22b5f8.doc&Inline=true
- DGC, lista de personas colectivas:
  https://www.consumidor.gov.pt/informacao-publicidade/lista-de-pessoas-coletivas-para-nao-rececao-de-comunicacao-nao-solicitadas-marketing-direto
- LSSI (BOE consolidado): https://www.boe.es/buscar/act.php?id=BOE-A-2002-13758 · LOPDGDD:
  https://www.boe.es/buscar/act.php?id=BOE-A-2018-16673
- Bisnode en tribunales: https://iapp.org/news/a/polish-court-overturns-dpas-first-gdpr-fine ·
  https://uodo.gov.pl/en/553/1572
- Remotive API (términos): https://github.com/remotive-com/remote-jobs-api
- TJUE C-762/19 CV-Online Latvia: https://ipcuria.eu/case?reference=C-762%2F19 ·
  https://www.twobirds.com/en/insights/2021/uk/cv-online-latvia-cjeu-complicates-the-enforcement-of-database-rights
- EU-US DPF: https://iapp.org/news/a/european-general-court-dismisses-latombe-challenge-upholds-eu-us-data-privacy-framework
  · https://www.wilmerhale.com/en/insights/blogs/wilmerhale-privacy-and-cybersecurity-law/20251201-european-court-of-justice-to-review-challenge-to-eu-us-data-privacy-framework

## 10. ¿Es legal este módulo? ¿Puedo subirlo a la web? (6ª pasada, investigación con Tavily, sept. 2026)

> **No es asesoría legal.** Es el análisis que sostiene el diseño; OP-15 (abogado) sigue siendo
> el gate antes del primer contacto real. Detalle de fuentes en `research.md` R13.

### 10.1 Qué hace y qué no hace LeadScout

| Hace | No hace (y el diseño lo impide) |
|---|---|
| Lee **APIs y RSS oficiales** de portales de empleo, respetando sus términos (p. ej. Remotive ≤ 4/día) | Entrar en **LinkedIn** u otras redes profesionales, con o sin cuenta, cookies o extensiones |
| Busca agencias con un buscador (Tavily) y **descarta** portales, directorios y redes | Rastrear directorios (Sortlist, Clutch, partners.laravel.com): el operador los mira a mano |
| Visita **páginas públicas** de las agencias (home, servicios, equipo, empleo, contacto) respetando `robots.txt`, con un User-Agent honesto y pocas peticiones | **Saltar CAPTCHAs**, anti-bots, muros de login o límites de peticiones; usar proxies, rotación de IPs, navegadores automatizados o identidades falsas |
| Si una web **bloquea** (401/403/429, desafío, login): lo registra y **se detiene** | Reintentar esa URL por otra vía (Firecrawl en modo `auto`/`enhanced`, otro proveedor) |
| Guarda solo **decisores** de leads A/B y datos **publicados por la empresa** | Adivinar o verificar emails; guardar trabajadores que no deciden; fotos; categorías especiales |
| Prepara borradores con aviso del art. 14 y baja | **Enviar** mensajes o rellenar formularios |

### 10.2 Por qué esa línea

1. **Saltar protecciones puede ser delito, no solo una infracción de términos.**
   - **España, Código Penal art. 197 bis.1:** acceder a un sistema de información «vulnerando las
     medidas de seguridad establecidas para impedirlo» y sin autorización → prisión de 6 meses a
     2 años.
   - **Portugal, Lei 109/2009 art. 6:** acceso ilegítimo → hasta 1 año o multa; **hasta 3 años si
     se viola una regla de seguridad** (n.º 3); y la misma pena para quien **produzca o
     distribuya programas destinados** a ese acceso (n.º 2).
   - No se localizó jurisprudencia que diga si un CAPTCHA o un anti-bot es una «medida de
     seguridad» a estos efectos `[UNVERIFIED]`. Precisamente por eso la opción razonable es **no
     sortearlos nunca**.
2. **LinkedIn prohíbe el scraping en su User Agreement y litiga.** En EE. UU., *hiQ* ganó en la
   ley antihacking (CFAA) para datos públicos **pero perdió por incumplimiento de contrato**;
   Proxycurl cerró en julio de 2025 tras la demanda de LinkedIn y ProAPIs llegó a un acuerdo de
   principio en febrero de 2026. En la UE, la **CNIL multó a KASPR con 240.000 €** por extraer
   contactos de LinkedIn que los usuarios habían restringido, conservarlos demasiado y no informar.
3. **«Público» no es «libre».** Aunque el scraping sea técnicamente posible y legal en su acceso,
   guardar y usar datos de personas exige base legal, minimización, plazos, informar (art. 14) y
   atender derechos. LeadScout ya lo cubre (FR-23 a FR-30).
4. **El canal de contacto también tiene reglas.** España: sin email comercial en frío (LSSI art.
   21), aunque el dato sea lícito. Portugal: lista DGC y zona gris del email nominativo (§3-§4).

### 10.3 «Subirlo a la web»: tres escenarios distintos

| Escenario | ¿Legal? | Qué cambia |
|---|---|---|
| **A. Desplegar argenis-hub con LeadScout en un servidor, solo para ti, tras login** | **Sí**, con el mismo análisis que en local: la licitud depende de lo que hace el módulo, no de dónde corre | Seguridad del art. 32 RGPD: HTTPS, login con **2FA**, `APP_DEBUG=false`, rate limiting, servidor y base en la **UE**, secretos fuera del repo, RLS en Supabase. El proveedor del servidor entra en el RoPA como encargado. Ventaja: el scheduler deja de depender de tu PC (D2). OP-21 |
| **B. Abrirlo a otras personas o venderlo (SaaS)** | **No con este diseño** | Pasarías a ser encargado (o corresponsable) de la prospección de terceros: términos de servicio, DPA con cada cliente, control de que ellos cumplan LSSI/DGC, más superficie de riesgo. Fuera de alcance (Q5) |
| **C. Publicar el código (p. ej. GitHub público)** | **Sí**, si no contiene secretos, datos personales ni funciones de evasión | Sin claves ni `.env`, sin fixtures con personas reales, sin opciones de `proxy: enhanced`, CAPTCHA o rotación de IPs (Lei 109/2009 art. 6.2 castiga distribuir programas **destinados** al acceso ilegítimo). La denylist y la parada por bloqueo forman parte del código publicado |

### 10.4 Pendiente para el abogado (se añade a OP-15)

- Confirmar que la **parada ante bloqueo** y el User-Agent honesto bastan como diligencia frente al
  CP 197 bis y la Lei 109/2009.
- Confirmar que buscar agencias con un buscador de terceros (Tavily) y leer sus páginas públicas
  no infringe los términos de esas webs en los casos habituales (sin cláusula anti-scraping
  expresa o con ella).
- Si se elige el escenario A, revisar la política de privacidad de argenis.dev para declarar el
  alojamiento (OP-11).

### 10.5 Fuentes de esta sección (Tavily, 16-09-2026)

- EDPB Guidelines 03/2026: https://www.edpb.europa.eu/system/files/2026-07/edpb_guidelines_2020603_webscraping_v1_en_0.pdf
  · https://www.jdsupra.com/legalnews/edpb-publishes-guidelines-on-web-4574390 ·
  https://www.reedsmith.com/our-insights/blogs/technology-law-dispatch/102nbqu/edpb-web-scraping-guidelines-for-ai-making-the-impossible-possible
  · https://thelens.slaughterandmay.com/post/102nequ/caught-in-the-web-edpb-guidelines-on-scraping-for-generative-ai
- CNIL, KASPR: https://www.cnil.fr/en/data-scraping-kaspr-fined-eu240000 ·
  https://www.edpb.europa.eu/news/data-scraping-french-supervisory-authority-fined-kaspr-eu240-000_en
- LinkedIn v. Proxycurl / ProAPIs: https://news.bloomberglaw.com/artificial-intelligence/linkedins-war-against-bot-scrapers-ramps-up-as-ai-gets-smarter
  · https://www.pin.com/blog/profile-data-apis ·
  https://www.leadsforlinked.com/blog/linkedin-automation-limits-and-safety.html ·
  https://linkedrent.com/is-scraping-linkedin-legal-saas
- Código Penal art. 197 bis: https://www.iberley.es/temas/delito-acceso-ilegal-sistemas-informaticos-art-197-bis-1-cp-65303
  · https://alonsosala.com/blog/acceso-ilicito-datos-interceptacion-comunicaciones-art-197-bis-cp
- Lei 109/2009 art. 6: https://www.pgdlisboa.pt/leis/lei_mostra_articulado.php?artigo_id=1137A0006&nid=1137&tabela=leis&pagina=1&ficha=1&nversao=
  · https://files.dre.pt/1s/2009/09/17900/0631906325.pdf
- Directiva 2013/40/UE (resumen): https://www.lexology.com/library/detail.aspx?g=ab725bff-897f-4b29-924f-4bde536b2fd4
- LSSI art. 21 en 2026: https://cardeseo.com/blog/email-marketing-rgpd-lssi ·
  https://overloop.com/es/blog/b2b-cold-email-espana-rgpd-aepd
- Firecrawl, proxies: https://docs.firecrawl.dev/api-reference/endpoint/scrape ·
  https://docs.firecrawl.dev/features/enhanced-mode · https://docs.firecrawl.dev/features/proxies

## 11. Datos públicos básicos de la empresa: qué se puede extraer sin romper el RGPD (7ª pasada, 17-09-2026)

> **No es asesoría legal.** Decisión A16 (`clarify.md`). Fuentes verificadas con Firecrawl en §11.5.

### 11.1 La regla

- **Considerando 14 RGPD** (texto verificado): el Reglamento «no regula el tratamiento de datos
  personales relativos a personas jurídicas y en particular a empresas constituidas como personas
  jurídicas, incluido el nombre y la forma de la persona jurídica y sus datos de contacto».
  → Los datos **de la agencia como sociedad** no son datos personales.
- Además, la propia empresa está **obligada a publicarlos** en su web:
  - **España, LSSI art. 10.1** (texto verificado, BOE): nombre o denominación social, domicilio,
    dirección de correo electrónico y datos de inscripción en el Registro Mercantil (el mismo
    artículo exige el NIF).
  - **Portugal, DL 7/2004 art. 10.º** «Disponibilização permanente de informações»: entre otros,
    inscripciones en registros públicos y **número de identificação fiscal**.
- **Límite:** cuando el «negocio» es una **persona física** (autónomo, *empresário em nome
  individual*, freelancer), su nombre, NIF y dirección **sí son datos personales** (el considerando 14
  solo excluye a las personas jurídicas). LeadScout ya descarta a los freelancers individuales; con
  FR-39 tampoco guarda sus datos de identificación.
- **Tampoco basta con «es de empresa»** si el dato identifica a una persona: `joao@agencia.pt` o
  una foto del equipo en la web de una sociedad siguen siendo datos personales (§1-§4).

### 11.2 Allowlist — datos de empresa que SE extraen (FR-38)

| Grupo | Datos | De dónde (sin IA) | Uso |
|---|---|---|---|
| **URLs públicas** | Inicio, servicios, nosotros, equipo, empleo, contacto, casos/portfolio, blog, partners/marca blanca, aviso legal, privacidad, `sitemap.xml` | Enlaces de la home, sitemap, palabras clave PT/ES/EN | Evidencia, re-verificación (FR-28), borrador |
| **Identidad legal** | Denominación social, nombre comercial, forma jurídica (S.L., S.A., Lda., Unipessoal Lda., S.A.…), NIF/NIPC, datos del registro mercantil | JSON-LD `Organization` (`legalName`, `taxID`, `vatID`), página de aviso legal / *termos*, pie de página | Deduplicar, cruzar con la **lista DGC** por NIPC (FR-17), detectar persona física (FR-39) |
| **Ubicación** | Ciudad y país de la sede (no la dirección completa) | JSON-LD `address.addressLocality`/`addressCountry`, aviso legal | Olas, huso horario, Geo |
| **Perfil** | Año de fundación, servicios, sectores, tecnologías, idiomas de la web, programa de partners, certificaciones de empresa (p. ej. partner de Laravel) | JSON-LD `foundingDate`, páginas de servicios, `hreflang`/`lang`, reglas de `RuleBasedSignalExtractor` | Score (Técnico, Comercial, Recurrente, B1) |
| **Clientes** | Nombres de **empresas** cliente y sectores de los casos publicados | Página de casos/portfolio | Recurrente, `ProofPointMatcher` |
| **Tamaño y actividad** | Recuento del equipo (solo el número), fechas del contenido, `lastmod`, año del copyright | Ya en FR-36 | Vitalidad y tamaño |
| **Canales de empresa** | Buzones genéricos del dominio (`info@`, `geral@`, `hola@`), URL del formulario de contacto y sus nombres de campo, URL de la página **de empresa** en redes enlazada desde su web (no se visita) | Ya en FR-31 | `ChannelAdvisor` |

Cada dato se guarda con **evidencia** (URL, fragmento literal, fecha de captura, método) y se
re-verifica con la empresa (FR-28).

### 11.3 Lo que NO se extrae nunca

| Dato | Por qué |
|---|---|
| Teléfonos (también los de centralita) | FR-32: el canal telefónico no se usa y evita guardar móviles personales |
| Dirección postal completa | No hace falta para el score; en autónomos es el domicilio personal |
| Fotos, logos y cualquier imagen | FR-30 (fotos de personas); el logo no aporta al score |
| Nombres de trabajadores que no deciden, autores de blog, firmantes de testimonios, contactos de los clientes en los casos | FR-23: minimización; solo decisores de leads A/B |
| Emails nominativos fuera del bloque de un decisor | FR-24 |
| Perfiles **personales** en redes (aunque la web los enlace), salvo el del decisor A/B | FR-23, FR-25 |
| Datos de identificación de un autónomo / ENI / freelancer | FR-39: son datos personales |
| Opiniones, reseñas de personas, categorías especiales | FR-30 |

### 11.4 Cómo se aplica en el pipeline

1. `PublicCompanyDataExtractor` (dominio, puro, **sin IA**) lee las páginas ya descargadas: coste 0.
2. Solo escribe campos de la allowlist; cualquier otra clave se ignora (test).
3. Si la forma jurídica o el NIF indican persona física, o la web está en primera persona → no se
   guardan los datos de identificación y el lead queda `solo_freelancer` (FR-39).
4. Antes de la IA, `PersonalDataScrubber` elimina emails, teléfonos y bloques de persona (FR-25).
5. Firecrawl con `storeInCache: false` y `proxy: "basic"` verificado; Tavily sin nombres de personas
   (FR-13, FR-25).

### 11.5 Fuentes (verificadas el 17-09-2026)

- Considerando 14 RGPD (Firecrawl): https://gdpr-info.eu/recitals/no-14/
- LSSI art. 10.1 (Firecrawl, cita literal del BOE): https://www.boe.es/buscar/act.php?id=BOE-A-2002-13758
- DL 7/2004 art. 10.º (Firecrawl search):
  https://diariodarepublica.pt/dr/legislacao-consolidada/decreto-lei/2004-73199154 ·
  https://www.pgdlisboa.pt/leis/lei_mostra_articulado.php?nid=1399&tabela=leis&so_miolo
- EDPB 03/2026 como borrador en consulta (Tavily): https://www.clarkhill.com/news-events/news/right-to-know-august-2026-vol-44
  · https://www.gibsondunn.com/gibson-dunn-europe-data-protection-september-2026
- Carta del EDPB sobre el DPF (31-07-2026, Tavily):
  https://www.pearlcohen.com/eu-commission-is-asked-to-reassess-the-eu-u-s-data-privacy-framework
- Tavily — Nebius, política y *trust center* (Tavily):
  https://nebius.com/newsroom/nebius-announces-agreement-to-acquire-tavily-to-add-agentic-search-to-its-ai-cloud-platform
  · https://tavily.com/privacy · https://trust.tavily.com
- Firecrawl — `storeInCache`, `proxy`, ZDR (Tavily + Firecrawl): https://docs.firecrawl.dev/api-reference/endpoint/scrape
  · https://docs.firecrawl.dev/features/enhanced-mode · https://docs.firecrawl.dev/features/fast-scraping
  · https://www.firecrawl.dev/enterprise
