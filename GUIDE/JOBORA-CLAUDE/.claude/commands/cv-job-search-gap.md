Use the `cv-gap-remote-es` skill (read
`.claude/skills/cv-gap-remote-es/SKILL.md` and its `references/` files) —
modo **gap**: empleo remoto de tecnología pero **no de programación**, como
ingreso puente. Carpeta `gap cv/`. Salida siempre en **español**.

Pasos:

1. Lee el CV fuente (`fullstack cv/Argenis_Gonzalez_CV_2026.md`, nunca lo
   edites) y la caché `gap cv/job-search-cache.json`.
2. Judge pass con `sequentialthinking`, pero con ojos de reclutador de
   BPO/soporte, no de tech: idiomas, disponibilidad, trato con cliente,
   equipo y conexión, y riesgo de fuga. Dime sin rodeos si mi CV actual
   grita "se va en tres meses" y qué hay que arreglar. Pregúntame las
   métricas que necesites antes de reescribir.
3. Escribe el CV del nicho en español →
   `gap cv/output/CV_Gap_Remoto_ES_<fecha>.md`. Destaca el trabajo de
   **formador de IA por contrato** para Imagina Formación (aulas Claude AI y
   M365 Copilot + grabación de píldoras de vídeo) — **nunca como
   "certificado", no me dieron credencial**, fue contrato. Más la formación
   a 30 alumnos, la producción e-learning, y el trato con
   clientes freelance e idiomas (ES nativo · PT residente · EN B1). Baja el
   volumen del stack sin borrarlo. Incluye el párrafo honesto de por qué
   quiero este trabajo. Dame el `ats_score` heurístico.
4. Busca en Tavily ofertas publicadas en las **últimas 24-72 horas**
   (`time_range: "day"`, luego `"week"` filtrando por fecha visible), en
   **español** como idioma principal y en inglés para cobertura.

   Filtros obligatorios:
   - **100% remoto** — sin híbrido ni presencial, tampoco Lisboa.
   - Acepta residentes en **Portugal o cualquier país de la UE**.
   - **Español** principal; inglés básico aceptable.
   - **Sin experiencia previa** o perfil junior.
   - **Entre 1.000 y 3.000 €/mes** o equivalente.
   - **Solo contrato**: empleo remoto permanente o freelance con contrato.
     Nada de colas de micro-tareas, encuestas remuneradas ni pago por clic.


   Puestos prioritarios: asistente virtual · customer support · chat support
   · email support · data entry · AI data trainer · AI rater · evaluador de
   IA · moderador de contenido · community moderator · transcriptor ·
   administrativo remoto.

5. **Antes de gastar cualquier extract**, pasa el gate **G4 anti-fraude**
   sobre título y snippet (`gap-scoring.md` § G4): pago por adelantado o
   cuota de formación, datos bancarios/documento antes de contrato, reenvío
   de paquetes o "agente financiero", pago por reclutar (MLM), solo
   WhatsApp/Telegram sin dominio propio, pago solo en cripto, "sin
   entrevista empiezas hoy", venta de formación disfrazada de empleo.
   Ante la duda, fuera. Dime cuántas descartaste y por qué.
6. Aplica G1 (remoto+residencia), G2 (rol — nada de programación ni venta a
   comisión), **G2b (modelo de contratación — fuera colas de micro-tareas,
   encuestas, clics y gig sin contrato, aunque el puesto sea de la lista)**,
   G3 (anuncio único ≤72h) y G5 (banda 1.000–3.000 €; por encima de 3.000 €
   en un puesto de entrada, revísalo como posible fraude). Corre la cascada
   de extract sobre las supervivientes, normaliza el sueldo a €/mes
   equivalente (declarando tarifa original y tipo de cambio asumido) y marca
   `hours_guarantee` y `contract_type`.
7. Puntúa con `match_score = round(0.25·H + 0.25·S + 0.50·D)` + caps de
   idioma (EN C1+ → 55, EN B2 requerido → 65). Tabla solo ≥70; cachea 60–69
   como `skipped`. Salta las URLs ya vistas en el caché.
8. Preséntamelo en **dos bloques**, cada tabla ordenada por **sueldo
   descendente y, a igualdad, primero las que no piden experiencia**:

   - **A** — sueldo confirmado dentro de la banda 1.000–3.000 €.
   - **B** — mismos filtros, sueldo no publicado (no inventes cifras).

   Columnas: Empresa | Puesto | Sueldo | Países aceptados | Requisitos |
   Publicado | Link | Match | Horas garantizadas | Contrato.

9. Actualiza `gap cv/job-search-cache.json` (`excluded_breakdown` incluido —
   **reporta siempre cuántas cayeron por fraude y cuántas por modelo de
   contratación, aunque sean 0**).
10. Cierra con el informe de mercado (`market-insights.md`): requisitos
    recurrentes, nivel de idioma exigido, herramientas de soporte, turnos y
    husos, tipo de contrato, y qué proporción de lo descubierto resultó ser
    fraude. Luego ofréceme traducir el CV, redactar un mensaje de
    aplicación, o marcar `status`/`outcome`.

Nunca envíes nada ni afirmes que aplicaste. Esto produce borradores y tablas
para que yo decida.

Instrucciones extra mías (puestos concretos, país, sueldo, tono), si las hay,
van debajo de esta línea y se aplican sobre lo anterior:
