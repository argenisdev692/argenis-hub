# Cachés del modo gap

Un fichero en `gap cv/`. **Nunca** mezclar con el de `fullstack cv/`.

## `apply_links` — bloque de nivel superior, para el usuario

Decisión del usuario del 2026-08-25: la caché no es solo un registro de
deduplicación, tiene que decirle **a qué es seguro candidatarse** sin que
tenga que interpretar gates. Va al principio del fichero, antes de
`seen_urls`, con un campo `_LEEME` que diga que solo hay que mirar esto.

```json
"apply_links": {
  "1_aplicar_ya":      { "descripcion": "…", "total": 0, "enlaces": [] },
  "2_verificar_antes": { "descripcion": "…", "total": 2, "enlaces": [ … ] },
  "3_no_aplicar":      { "descripcion": "…", "total": 14, "enlaces": [ … ] }
}
```

| Lista | Qué entra |
|---|---|
| `1_aplicar_ya` | Pasó **todos** los gates y está verificada. Sin condiciones |
| `2_verificar_antes` | Encaje real, pero hay algo concreto que confirmar. **Obligatorio** el campo `que_verificar` con las preguntas exactas |
| `3_no_aplicar` | Descartada. **Obligatorio** el campo `motivo` con el gate y la explicación en una línea |

Cada enlace lleva `empresa`, `puesto`, `link`, `publicado` (fecha visible) y,
según la lista, `por_que_encaja` + `que_verificar`, o `motivo`.

Si `1_aplicar_ya` sale vacía, se dice explícitamente en su `nota` que el vacío
es el estado real del mercado y no un fallo de la búsqueda.

Además, **cada entrada de `seen_urls` lleva `safe_to_apply`**:
`"yes"` · `"verify_first"` · `"no"`.

Los descartes por G4 anti-fraude entran en `3_no_aplicar` **sin enlace**
(`"link": null`), agrupados: se nombra el patrón para que se reconozca, pero
no se le da la URL.

## `job-search-cache.json` — vacantes con fecha

Hereda el esquema de
`.claude/skills/cv-job-studio/references/cache-schema.md` (misma
canonicalización de URL, mismos `status`, mismo `outcome` con su regla de
envejecimiento a 21 días). Campos **añadidos** en este modo:

```json
"https://ejemplo.com/jobs/soporte-es-remoto": {
  "title": "Agente de Soporte al Cliente (español)",
  "company": "Ejemplo BPO",
  "source": "tavily",
  "match_score": 82,
  "score_breakdown": { "H": 64, "S": 78, "D": 92, "semantic_note": "proxy" },
  "block": "A",
  "contract_type": "indefinido",
  "salary_eur_month": 1800,
  "salary_basis": "monthly",
  "salary_confirmed": true,
  "salary_note": null,
  "hours_guarantee": 1.0,
  "countries_accepted": ["PT", "ES", "EU"],
  "experience_required": "none",
  "language_primary": "es",
  "modality": "remote-eu",
  "location": "Europa (remoto)",
  "scam_flags": [],
  "status": "new",
  "outcome": null,
  "first_seen": "2026-08-25",
  "last_seen": "2026-08-25"
}
```

| Campo | Notas |
|---|---|
| `block` | `A` (sueldo confirmado en banda 1.000–3.000 €) · `B` (sueldo no publicado) |
| `contract_type` | `indefinido` · `temporal` · `freelance-contrato` · `no_especificado`. Obligatorio: es lo que separa este modo de una cola de tareas |
| `above_band` | `true` cuando el sueldo confirmado supera los 3.000 € y la empresa se verificó (ver G5) |
| `salary_eur_month` | €/mes **equivalente a jornada completa**; `null` si no se publicó |
| `salary_basis` | `monthly` · `hourly` · `annual` · `not_stated`. **`per_task` no existe aquí** — un pago por tarea es G2b FAIL y no llega al caché |
| `salary_confirmed` | `false` cuando la cifra es una conversión, no un mensual publicado |
| `salary_note` | Tarifa original y tipo de cambio asumido, p. ej. `"18 USD/h × 160 h; 1 USD ≈ 0,92 EUR (asumido 2026-08-25)"` |
| `hours_guarantee` | 1.0 / 0.7 / 0.4 / 0.6 según `gap-scoring.md` |
| `countries_accepted` | Lista literal del anuncio; no deducir "UE" de un "Europa" suelto |
| `experience_required` | `none` · `preferred_1y` · `1_2y` · `3y_plus` |
| `scam_flags` | Señales de G4 detectadas aunque la oferta pasara (con 1 sospecha se capa a 55 y se documenta aquí) |

**Descartes por G4 anti-fraude:** no se cachean como match. Van a
`runs.notes` con su motivo y suman a `excluded_count`. Excepción útil:
guardar una entrada fina con `status: "dismissed"` y
`scam_flags: [...]` cuando convenga que ese dominio **no** vuelva a
proponerse nunca.

## Entrada de `runs`

```json
{
  "date": "2026-08-25",
  "queries": ["…"],
  "freshness": "72h",
  "new_matches_a": 3,
  "new_matches_b": 5,
  "excluded_count": 21,
  "excluded_breakdown": {
    "scam_gate": 9,
    "contract_model": 4,
    "residency": 4,
    "salary_band": 2,
    "role_fit": 1,
    "stale": 1
  },
  "notes": "…"
}
```

`excluded_breakdown.scam_gate` y `excluded_breakdown.contract_model` se
reportan **siempre** al usuario, aunque valgan cero. Cuánto fraude se filtró
y cuántas colas de tareas se descartaron es parte del resultado en este
nicho, no un detalle interno del pipeline.
