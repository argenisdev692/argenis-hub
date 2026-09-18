# companies-cache.json schema

Un caché por proyecto en `formador cv/companies-cache.json`. Independiente de los
`job-search-cache.json` de `fullstack cv/` y `other cv/` (esquema distinto: aquí la unidad es
"empresa/academia investigada", no "URL de oferta de empleo").

```json
{
  "version": 1,
  "mode": "formador-fundae",
  "updated_at": "2026-08-04T01:28:00+01:00",
  "companies": {
    "https://example.com": {
      "name": "Empresa Ejemplo",
      "country": "España",
      "application_channel": "https://example.com/ser-formador",
      "tech_focus": ["Inteligencia Artificial", "Python"],
      "modality": "online / in-company",
      "language": "es",
      "fundae": true,
      "notes": "Texto libre con hallazgos relevantes de la investigación.",
      "status": "researched",
      "first_seen": "2026-08-04",
      "last_seen": "2026-08-04"
    }
  },
  "runs": [
    {
      "date": "2026-08-04",
      "queries": ["..."],
      "companies_found": 4,
      "target_job_title": "Formador de Inteligencia Artificial / Formador Tecnológico freelance (España, FUNDAE)",
      "ats_score": 81,
      "notes": "Resumen de la corrida: cuántas empresas nuevas, cuáles quedaron pendientes, etc."
    }
  ]
}
```

## Campos

| Campo | Obligatorio | Notas |
|---|---|---|
| `application_channel` | sí | URL de formulario, email, o "ya colaborador" / "no se encontró canal público" |
| `tech_focus` | sí | Áreas/tecnologías reales que imparte la empresa (de su propia web) |
| `fundae` | sí | `true` si la empresa se presenta como entidad de formación bonificada FUNDAE |
| `status` | sí | `researched` \| `applied` \| `dismissed` |

## `status` values

`researched` (investigada, mensaje generado) → `applied` (el usuario confirma que envió la solicitud
él mismo) → `dismissed` (el usuario decide no aplicar). El agente **nunca** pone `applied` por su cuenta
— solo el usuario puede confirmar que un mensaje fue enviado de verdad (regla "no auto-envío" del
proyecto).

## Update procedure

1. Leer el archivo (crear uno vacío con `companies: {}` y `runs: []` si no existe).
2. Por cada empresa nueva investigada: añadir/actualizar su entrada (no duplicar por dominio).
3. Añadir un objeto a `runs` con las queries usadas y un resumen.
4. Escribir el archivo de vuelta como JSON válido (sin comentarios), actualizar `updated_at`.
