# companies-cache.json schema — video content cv

Caché en `video content cv/companies-cache.json`. Independiente de
`formador cv/companies-cache.json` y de los `job-search-cache.json` de
`fullstack cv/` / `other cv/` / `gap cv/`.

**v2 (2026-08-26).** La v1 registraba `modality` pero **no la residencia
exigida**, y por eso ADR Formación quedó cacheada como viable hasta que la
empresa contestó que el remoto era solo para residentes en España. Los campos
de residencia, contrato, pago y scoring de abajo son la corrección.

```json
{
  "version": 2,
  "mode": "video-content-es",
  "updated_at": "2026-08-26T12:00:00+01:00",
  "params": {
    "pay_floor_eur_hour": 15,
    "hours_per_finished_minute": null,
    "fx_rates": { "USD_EUR": 0.92, "as_of": "2026-08-26", "source": "asumido" }
  },
  "apply_links": {
    "1_aplicar_ya": [],
    "2_verificar_antes": [
      {
        "url": "https://example.com",
        "name": "Empresa Ejemplo",
        "ask": "¿Aceptáis colaborador que factura desde Portugal, o el remoto es solo para residentes en España?"
      }
    ],
    "3_no_aplicar": [
      {
        "url": "https://www.adrformacion.com/nosotros/redTalento.html",
        "name": "ADR Formación",
        "reason": "G1 FAIL — confirmado por la empresa: remoto solo para residentes en España"
      }
    ]
  },
  "companies": {
    "https://example.com": {
      "name": "Empresa Ejemplo",
      "country": "México",
      "entry_type": "vacante",
      "posted": "2026-08-24",
      "channel_checked": null,
      "application_channel": "hola@example.com / formulario",

      "content_needs": ["grabar cursos", "escribir cursos", "crear laboratorios", "crear ejercicios", "revisar contenido"],
      "tech_focus": ["Claude AI", "Microsoft 365 Copilot", "Cursor"],
      "content_language": "es",

      "hiring_residency": "contractor-global",
      "residency_evidence": "El anuncio dice 'contractor, pago vía Deel, sin restricción de país'",
      "residency_verified": false,
      "contract_vehicle": "contractor",

      "pay_model": "fee_fijo",
      "pay_amount_original": "25 USD/h",
      "pay_currency": "USD",
      "pay_unit": "hour",
      "pay_eur_hour_effective": 23,
      "pay_assumptions": "1 USD ≈ 0,92 EUR, tipo asumido 2026-08-26",

      "match_score": 84,
      "score_breakdown": { "H": 70, "S": 85, "D": 93 },
      "cap_reason": null,
      "raw_score": 83.9,
      "apply_cost_hours": 2,
      "roi_app": 28.0,
      "block": "A",

      "notes": "Hallazgos de la investigación. extract: firecrawl.",
      "also_in_formador_cache": false,
      "status": "researched",
      "safe_to_apply": "verify_first",
      "first_seen": "2026-08-26",
      "last_seen": "2026-08-26"
    }
  },
  "explored_no_channel": {},
  "runs": [
    {
      "date": "2026-08-26",
      "queries": ["..."],
      "geographies": ["España", "México", "Centroamérica", "LatAm sur", "global-USD"],
      "companies_found": 0,
      "target_role": "Course Creator / Creador de píldoras y videotutoriales e-learning (español)",
      "ats_score": null,
      "excluded_breakdown": {
        "residencia_locked": 0,
        "rol_marketing": 0,
        "idioma_contenido": 0,
        "frescura": 0,
        "suelo_tarifa": 0,
        "impago": 0
      },
      "market_insights": null,
      "notes": "Resumen de la corrida."
    }
  ]
}
```

## Campos nuevos en v2

| Campo | Obligatorio | Notas |
|---|---|---|
| `hiring_residency` | **sí** | `contractor-global` \| `remote-eu` \| `remote-pt` \| `remote-pt-es` \| `remote-es-locked` \| `remote-latam-locked` \| `remote-country-locked` \| `residency-unclear`. **El campo cuya ausencia causó el fallo de ADR** |
| `residency_evidence` | **sí** | Cita textual del anuncio que sostiene la clasificación. Si no hay cita → `residency-unclear`, no se deduce |
| `residency_verified` | **sí** | `true` solo si la empresa lo confirmó por escrito. Nunca por inferencia del marketing |
| `contract_vehicle` | sí | `nomina` \| `mercantil` \| `contractor` \| `no_especificado`. Es lo que decide si la residencia es un muro real |
| `pay_model` | sí | `fee_fijo` \| `mixto` \| `royalty_puro` \| `impago` \| `no_publicado` |
| `pay_amount_original` / `pay_currency` / `pay_unit` | sí si hay cifra | Guardar **siempre** el original junto al convertido |
| `pay_eur_hour_effective` | sí si hay cifra | EUR/h. Si la tarifa es por minuto acabado, aplicar `R` y declararlo en `pay_assumptions` |
| `pay_assumptions` | sí si hay conversión | Tipo de cambio + fecha, y `R` si se usó. Nunca una cifra sin su supuesto |
| `entry_type` | sí | `vacante` (lleva `posted`, frescura ≤7 días) \| `canal_abierto` (lleva `channel_checked`) |
| `match_score` / `score_breakdown` / `raw_score` / `cap_reason` | sí si se puntuó | `raw_score` sin capar, para análisis de alcance |
| `apply_cost_hours` / `roi_app` | sí si se puntuó | `C` y `match_score/(1+C)` |
| `block` | sí si se puntuó | `A` tarifa confirmada · `B` sin publicar · `C` royalty puro |
| `safe_to_apply` | sí | `yes` \| `verify_first` \| `no` — espejo del bucket de `apply_links` |
| `content_language` | sí | `es` \| `pt-PT`. Es el idioma del **entregable**, no del país de residencia |

`modality` de la v1 queda **obsoleto**: mezclaba modalidad, vehículo y
residencia en una frase libre. Los tres se registran ahora por separado.

## `apply_links`

El usuario lee **solo** `apply_links`; `companies` es el registro técnico.
Toda entrada de `2_verificar_antes` **debe** traer `ask` con la pregunta
concreta. La pregunta por defecto de este nicho es la que habría evitado el
caso ADR:

> ¿Aceptáis a un colaborador que factura desde Portugal, o el remoto es solo
> para residentes en España?

## `status` y `outcome` — dos campos, no uno

Misma disciplina que career mode: **`status` = lo que hizo el usuario ·
`outcome` = lo que hizo la empresa**. Meterlos en un solo campo pierde
información (ADR quedó como `dismissed`, lo que borraba el hecho de que sí se
había postulado).

| Campo | Valores |
|---|---|
| `status` | `researched` → `applied` (**solo** cuando el usuario confirma el envío) → `dismissed` |
| `outcome` | `unknown` · `no_reply` · `rejected` · `interview` · `offer` |

- El agente **nunca** pone `applied` por su cuenta ni **adivina un `outcome`**.
  Si el usuario no lo ha dicho, se queda en `unknown`.
- `applied_date`: si el usuario no indica fecha, `"unknown"` — **no se deduce**
  de la fecha del borrador del mensaje.
- Entradas con más de **21 días** sin respuesta cuentan como `no_reply` **solo
  para análisis**; el valor guardado no se sobrescribe.
- El `outcome` **nunca** revisa un `match_score`: la puntuación es CV-vs-oferta,
  el outcome es señal de mercado.

`safe_to_apply` es independiente de ambos: describe si es seguro postular tal
cual, no lo que se hizo ni lo que contestaron.

## Procedimiento de actualización

1. Leer o crear vacío (`companies: {}`, `explored_no_channel: {}`, `runs: []`,
   `apply_links` con las tres listas, `params`).
2. Canonicalizar la URL igual que `CanonicalUrlNormalizer` (sin fragmento, sin
   `utm_*`/`ref`/`fbclid`/`gclid`, sin barra final) antes de usarla como clave.
3. Añadir/actualizar empresas; recolocar en el bucket de `apply_links` que
   corresponda.
4. Añadir entrada en `runs` con `excluded_breakdown` **siempre**, aunque sea
   todo ceros.
5. Escribir JSON válido; actualizar `updated_at`.

## Migración de v1 a v2

Las 6 empresas de la v1 no tienen campos de residencia. Al primer uso de la v2:
poner `hiring_residency: "residency-unclear"` y `residency_verified: false` en
todas **salvo ADR**, que pasa a `remote-es-locked` / `verified: true` /
`safe_to_apply: "no"` porque la empresa ya lo confirmó. Ninguna de ellas debe
presentarse como `1_aplicar_ya` hasta que se verifique.
