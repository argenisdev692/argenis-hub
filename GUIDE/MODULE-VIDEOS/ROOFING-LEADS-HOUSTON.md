# Roofing / Mitigation / Insurance Leads — Houston (no Meta ads)

> Research date: 2026-09-23 · Sources: Tavily + Firecrawl (links at the end)
> Idioma / Language: [English](#english) · [Español](#español)

---

## English

### Short answer

**Yes, it's doable, and you don't need Meta ads.** One thing to know first: LeadScout is built for **B2B**. It finds companies, pulls signals from job postings, picks decision-makers, and drafts outreach. Houston homeowners are **B2C**, where the data sources and the laws are different. So the plan splits into two parts.

### Option A: Partner leads (fits LeadScout almost 1:1)

Find the businesses that send you roof and mitigation jobs: independent insurance agents, property managers, HOA management companies, realtors, public adjusters, and plumbers (water mitigation). This is the same pipeline you already have:

- `TavilySearchAdapter` searches for them ("property management company Houston").
- `FirecrawlPageFetcher`, `FetchLadder`, `RobotsTxtPolicy` and `OutboundUrlGuard` fetch their sites.
- `ExtractSignalsHandler`, `ScoringEngine` and `TierClassifier` score them.
- `DecisionMakerExtractor`, `ContactChannelDetector` and `GenerateDraftHandler` produce the outreach.

You'd mostly change the signals, taxonomies and prompts. Roughly 70–80% of the code can be reused. One partner, like a property manager with 40 roofs, is worth more than 40 cold homeowners.

### Option B: Storm-triggered homeowner targeting (new domain)

The data sources are free and public:

| Source | What it gives you |
|---|---|
| **NOAA Storm Events DB + NWS/SPC reports** | Hail size, date and location. Houston had 13 hail events in 2026 through August, the largest 1.50". |
| **HCAD public data** (bulk tab-delimited files + GIS shapefiles) | Owner, address, year built, value, homestead flag, parcel polygons |
| Existing `TabularFileReaderPort` / `SimpleExcelTabularReader` | Already imports large CSV/TSV files |

The pipeline would be: storm event → hail swath → HCAD parcels inside it → score each parcel (hail size × roof age × value × homestead) → a ranked list of **streets and neighborhoods**. From there you'd use door-knocking routes and **direct mail**. Neither needs prior consent.

Note: City of Houston permit open data only seems to be **monthly aggregates**. That's not enough to see which homes already re-roofed. Whether per-permit data exists still needs to be checked.

### Legal lines the module must enforce in code

1. **Texas SB 140** (in effect since Sept 1, 2025): a marketing text counts as a "telephone solicitation." Violations are also deceptive trade practices (DTPA), and a consumer on the no-call list can sue for $500 per knowing violation. Texts sent with documented prior consent are exempt from registration (November 2025 settlement). Cold texting skip-traced numbers is exactly where roofers get sued. **Build the channel as mail and door-knock, not SMS/calls without consent.** Calling hours are 9am–9pm Mon–Sat and noon–9pm Sunday.
2. **Texas Insurance Code §4102.163**: a roofer can't act as a public adjuster or *advertise* claim handling. The AI draft writer must never say "we handle your claim" or "we negotiate with your insurer."
3. **Chapter 707**: waiving, rebating or absorbing the deductible is illegal. Put that in `DraftTemplate` as a hard rule.
4. LeadScout's GDPR parts (`Article14Notice`) don't apply to Texas homeowners. Replace them with the Texas rules above, while keeping `SuppressionGate` and `ObjectContactHandler` for do-not-contact requests.

### Also worth adding (inbound, no Meta)

Google Business Profile, Google Local Services Ads, and SEO storm pages. For example, a local roofer's "Houston Hail History from NOAA data" page ranks well. The module could auto-generate a "hail hit ZIP 770xx on date X" landing page after each storm.

### Recommendation

1. **Start with Option A.** It reuses almost everything and has the lowest legal risk.
2. **Then build Option B as a separate module** (e.g. `StormLeads`). Its core entity is a `Property`, not a `Company`. Move the shared adapters (Tavily, Firecrawl fetcher, budget ledger, circuit breaker) out of LeadScout into `src/Shared/` instead of copying them. That fits the "Shared adapter only when it has a consumer" rule, since there would then be two.

---

## Español

### Respuesta corta

**Sí, se puede, y no necesitas anuncios de Meta.** Algo que debes saber primero: LeadScout está hecho para **B2B**. Encuentra empresas, saca señales de ofertas de empleo, identifica a quien toma las decisiones y redacta el mensaje de contacto. Los dueños de casa en Houston son **B2C**, y ahí las fuentes de datos y las leyes son distintas. Por eso el plan se divide en dos partes.

### Opción A: Leads de socios (encaja casi 1:1 con LeadScout)

Busca los negocios que te pueden enviar trabajos de techo y mitigación: agentes de seguros independientes, administradores de propiedades, empresas que administran HOAs, realtors, ajustadores públicos y plomeros (mitigación de agua). Es el mismo pipeline que ya tienes:

- `TavilySearchAdapter` los busca ("property management company Houston").
- `FirecrawlPageFetcher`, `FetchLadder`, `RobotsTxtPolicy` y `OutboundUrlGuard` descargan sus sitios.
- `ExtractSignalsHandler`, `ScoringEngine` y `TierClassifier` les dan puntaje.
- `DecisionMakerExtractor`, `ContactChannelDetector` y `GenerateDraftHandler` generan el mensaje.

Cambiarías sobre todo las señales, las taxonomías y los prompts. Se puede reutilizar entre 70% y 80% del código. Un solo socio, como un administrador con 40 techos, vale más que 40 dueños de casa contactados en frío.

### Opción B: Dueños de casa según tormentas (dominio nuevo)

Las fuentes de datos son gratuitas y públicas:

| Fuente | Qué te da |
|---|---|
| **NOAA Storm Events DB + reportes NWS/SPC** | Tamaño del granizo, fecha y ubicación. Houston tuvo 13 eventos de granizo en 2026 hasta agosto; el mayor fue de 1.50". |
| **Datos públicos de HCAD** (archivos masivos separados por tabulaciones + shapefiles GIS) | Dueño, dirección, año de construcción, valor, exención homestead, polígonos de parcelas |
| `TabularFileReaderPort` / `SimpleExcelTabularReader` existentes | Ya importan archivos CSV/TSV grandes |

El pipeline sería: tormenta → zona de granizo → parcelas de HCAD dentro de esa zona → puntaje por parcela (tamaño del granizo × edad del techo × valor × homestead) → lista ordenada de **calles y vecindarios**. Con eso harías rutas de puerta a puerta y **correo directo**. Ninguno de los dos requiere consentimiento previo.

Nota: los datos abiertos de permisos de la Ciudad de Houston parecen ser solo **totales mensuales**. Eso no alcanza para saber qué casas ya cambiaron el techo. Falta confirmar si existen datos por permiso.

### Límites legales que el módulo debe aplicar en el código

1. **Texas SB 140** (vigente desde el 1 de septiembre de 2025): un texto de marketing cuenta como "solicitud telefónica". Las violaciones también son prácticas comerciales engañosas (DTPA), y un consumidor inscrito en la lista no-call puede demandar $500 por cada violación intencional. Los textos enviados con consentimiento previo documentado están exentos del registro (acuerdo de noviembre de 2025). Mandar textos en frío a números obtenidos por skip tracing es justo lo que lleva a demandas contra roofers. **Construye el canal con correo y puerta a puerta, no con SMS ni llamadas sin consentimiento.** Horario de llamadas: 9am–9pm de lunes a sábado y mediodía–9pm los domingos.
2. **Código de Seguros de Texas §4102.163**: un roofer no puede actuar como ajustador público ni *anunciar* que maneja reclamos. El redactor con IA nunca debe decir "nosotros manejamos tu reclamo" ni "negociamos con tu aseguradora".
3. **Capítulo 707**: es ilegal renunciar al deducible, reembolsarlo o absorberlo. Ponlo como regla fija en `DraftTemplate`.
4. Las partes de GDPR de LeadScout (`Article14Notice`) no aplican a dueños de casa en Texas. Reemplázalas por las reglas de Texas de arriba y conserva `SuppressionGate` y `ObjectContactHandler` para las solicitudes de no contactar.

### También vale la pena agregar (tráfico entrante, sin Meta)

Google Business Profile, Google Local Services Ads y páginas SEO sobre tormentas. Por ejemplo, la página "Houston Hail History from NOAA data" de un roofer local posiciona bien. El módulo podría generar automáticamente una landing tipo "granizo en el ZIP 770xx el día X" después de cada tormenta.

### Recomendación

1. **Empieza con la Opción A.** Reutiliza casi todo y tiene el menor riesgo legal.
2. **Después construye la Opción B como un módulo separado** (por ejemplo `StormLeads`). Su entidad central es una `Property`, no una `Company`. Mueve los adaptadores compartidos (Tavily, el fetcher de Firecrawl, el budget ledger y el circuit breaker) de LeadScout a `src/Shared/` en lugar de copiarlos. Eso cumple la regla de "adaptador en Shared solo cuando tiene quien lo use", porque ya habría dos módulos usándolos.

---

## Sources / Fuentes

- [Varnum — Texas SB 140 after the November settlement](https://www.varnumlaw.com/insights/texas-sb-140)
- [Nixon Peabody — No registration for consent-based texts](https://www.nixonpeabody.com/insights/articles/2025/12/10/texas-telemarketing-update---no-registration-for-consent-based-text-messages)
- [TDI — Roofing and insurance: Know the law](https://www.tdi.texas.gov/consumer/storms/roofing-and-insurance-know-the-law.html)
- [HCAD — Public data downloads](https://hcad.org/hcad-online-services/pdata)
- [NOAA — Storm Events Database](https://www.ncei.noaa.gov/access/storm-events-database/)
- [Motion Roofing — Houston hail history (NOAA data)](https://www.motionroofing.com/blog/houston-hail-history)
- [City of Houston Open Data — Residential building permits](https://data.houstontx.gov/dataset/residential-building-permits)
