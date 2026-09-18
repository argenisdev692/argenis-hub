# Medición de stacks — ¿dónde tienes mejor entrada? (2026-09-11)

Comparación de **Laravel+Vue · Laravel+React · Next.js · NestJS · FastAPI** con la misma vara, para un
candidato en **Covilhã, Portugal**, EN B1, 4+ años PHP/Laravel.

> Todas las puntuaciones son **heurísticas 0–100** (fórmula del proyecto: `0.45·H + 0.25·S + 0.30·D` + topes),
> no un score oficial de ATS. Los recuentos son **fotos de los portales hoy**, no un censo del mercado.

## Método

- **gate_config:** remoto que puedas hacer desde Covilhã (global / UE / PT-ES), híbrido solo Lisboa,
  publicado reciente, búsquedas EN + ES.
- **Demanda:** Tecnoempleo (España), ITJobs.pt (Portugal), RemoteRocketship (remoto global).
- **Competencia:** "CVs inscritos" de Tecnoempleo y "candidaturas/applicants" de LinkedIn en ofertas reales.
- **Tu encaje:** puntuación H/S/D con tu CV real sobre ofertas representativas de cada stack.
- **Herramientas:** Tavily (búsqueda + extracción), Firecrawl (scrape). 
- **Límites honestos:** Firecrawl no soporta LinkedIn (no pude usar sus contadores) y agotó su cuota diaria a mitad
  de la medición; el total de FastAPI en Tecnoempleo no quedó capturado; la búsqueda por palabra clave de ITJobs es
  difusa (p. ej. "vue" devuelve muchas ofertas Java/Angular); la competencia tiene **N = 1–2 ofertas por stack**
  → es **direccional, no concluyente**.

---

## 1. Demanda

| Stack | Tecnoempleo ES (total / 100% remoto) | ITJobs PT (total / remotas vistas) | RemoteRocketship global (total / nuevas esta semana) |
|---|---|---|---|
| Laravel | 10 / 3 (1 es de 2023) | 3 / 1 | 269 / 17 |
| PHP (cualquier framework) | **36** / – | **14** / – | 703 / – |
| Vue | 27 / 9 | 21 / 1 de 16 listadas (búsqueda difusa) | 835 / 65 |
| React | **129** / 18 | **63** / 4 de 16 listadas | 722 / 66 |
| Next.js | 10 / 2 | 9 / 2 empresas (4 anuncios) | 712 / 52 |
| NestJS | 8 / 1 | 5 / 1 empresa (3 anuncios) | sin página |
| FastAPI | no capturado (≥2 remotas con FastAPI: FeverUp, CAS TRAINING) | 8 / 3 | sin página |

**Accesibilidad real desde Portugal** (10 ofertas más recientes de RemoteRocketship por stack):
Laravel **2/10** abiertas a Europa · Vue **1/10** · React **0/10** · Next.js **1/10** (y bloqueada a España).
El resto: EE. UU., LatAm (español requerido), Brasil (portugués BR), India, Alemania (alemán), Canadá (francés).

**Residencia en España:** 4 de 7 ofertas "100% remoto" de Tecnoempleo leídas exigen **"Imprescindible residir:
España"** (IN MANAS, Social You, PANEL, Grupo Sermicro) — da igual el stack.

**Lectura:** React y Next.js tienen 5–10× más volumen bruto, pero la parte **accesible desde Covilhã** es pequeña
en todos los stacks. PHP (no solo Laravel) es **3,6× Laravel en España y 4,7× en Portugal**.

---

## 2. Competencia (candidatos por oferta)

| Oferta | Stack | Portal | Candidatos | Antigüedad | ≈ por día |
|---|---|---|---|---|---|
| PANEL — Frontend React/TS/IA (remoto, residir ES, 3 puestos) | React | Tecnoempleo | **561** | 8 días | ~70 |
| Baoss — Senior Frontend React/TS (remoto) | React/Next | Tecnoempleo | 249 | alta no visible | n/d |
| YTEC — Senior Next.js (remote from Portugal) | Next.js | LinkedIn | **106** | 1 día | ~106 |
| Grupo Digital — Frontend Vue3 (remoto) | Vue | Tecnoempleo | 176 | 8 días | ~22 |
| Social You — Senior Nuxt/Vue3 (remoto, residir ES) | Vue | Tecnoempleo | 62 | alta no visible | n/d |
| Michael Page — PHP Freelance (remoto ES) | PHP/Laravel | Tecnoempleo | 325 | 7 días | ~46 |
| IN MANAS — Full Stack (PHP/Laravel plus, residir ES) | Laravel/Vue | Tecnoempleo | 104 | ~2 días | ~50 |
| Twelve — Full Stack PHP/Laravel (Reino Unido, 1 día/mes oficina) | Laravel | LinkedIn | 145 | 1 día | ~145 |
| Grupo Sermicro — Node/Angular Senior (NestJS), residir ES, 5 puestos | NestJS | Tecnoempleo | 109 | alta no visible | n/d |
| Zero to One — Backend Node.js (remoto, residir PT) | Node/TS | LinkedIn | **54** | 13 horas | ~100 |
| Recann — Platform Engineer Python/FastAPI (UE remoto, senior) | FastAPI | LinkedIn | **92** | 12 horas | ~180 |
| Sopra Steria — Senior Python/React/FastAPI (híbrido Valencia) | FastAPI | Tecnoempleo | 37 | 9 días | ~4 (híbrido) |
| TRIGÉNIUS — Programador PHP/Laravel (presencial Fátima) | Laravel | LinkedIn | 26 | 3 semanas | ~1 (presencial) |

### Tu hipótesis: "React tiene mucha competencia y Vue no tanto"

**Direccionalmente sí.** En remoto España, las dos ofertas React leídas tenían **561 y 249** inscritos; las dos de
Vue, **176 y 62**. Pero:

- Es N = 2 por lado (anecdótico).
- **Vue también tiene ~5× menos ofertas** en España (27 vs 129) y ~3× menos en Portugal (21 vs 63): menos
  competencia **y** menos puertas.
- Todas las ofertas de ambos lados pedían inglés **alto/excelente**, y la mitad residir en España.
- **Cambiar a Next.js, Node o FastAPI no reduce la competencia**: 54–180 candidatos por día donde se pudo medir,
  frente a ~46–50/día en PHP/Laravel remoto España.

---

## 3. Qué exigen (ofertas leídas)

| Stack | Años pedidos | Inglés | Otros muros |
|---|---|---|---|
| Laravel (+Vue/React/Blade) | 1–3 (Tomplay), 1 (Michael Page), 3+ (IN MANAS), 3–5 (ve2), 8+ (LucidHorizon) | bueno / no pedido (Michael Page, LucidHorizon) / fluido (Neotalent, ve2) | residir ES en parte de las españolas |
| Next.js / React | 5+ frontend + 2+ Next.js (YTEC) | excelente / alto (YTEC, Baoss, PANEL) | residir ES (PANEL) |
| NestJS / Node | 5+ (Capitole, Sermicro) | fluido / EN+ES | **+3 años Angular** (Capitole), residir ES (Sermicro), residir PT (Zero to One) |
| FastAPI / Python | "experiencia sólida en Python" (Infoser), senior (Recann, MBN) | bueno profesional | LangGraph/RAG/pytest (Infoser), GCP+Terraform (Recann) |

**Patrón:** los stacks nuevos piden años **en ese stack concreto**. Tus 4 años cuentan como PHP/Laravel;
en Next.js o NestJS empiezas prácticamente de cero en experiencia comercial.

---

## 4. Tu encaje real hoy (heurístico, tu CV actual)

| Stack | Oferta representativa | H | S | D | Bruto | Final | Tope aplicado |
|---|---|---|---|---|---|---|---|
| Laravel | Tomplay (UE remoto) | 81 | 83 | 94 | **85** | **85** | — |
| Laravel (+Vue/React plus) | IN MANAS (remoto ES) | 87 | 72 | 94 | **85** | **85** | — |
| Laravel + Vue o React | ve2.ventures | 92 | 85 | 76 | **85** | 65 | inglés fluido (B2) |
| Laravel + React | LucidHorizon (remoto PT) | 91 | 78 | 75 | **83** | 55 | 8+ años |
| Next.js | YTEC (remoto PT) | 67 | 33 | 87 | 64 | 55 | inglés excelente cara a cliente (C1+) |
| FastAPI | Infoser (remoto ES) | 50 | 45 | 84 | 59 | 59 | — |
| NestJS | Capitole (remoto ES) | 42 | 40 | 94 | 57 | 57 | — |
| NestJS | Grupo Sermicro (remoto ES) | 38 | 40 | 79 | 51 | 51 | — |

**Lectura:** con la misma oferta-tipo, tu encaje en bruto es **83–86 en Laravel** (con Vue **o** con React) y
**51–64 en Next.js, NestJS o FastAPI**. Son **20–35 puntos** de diferencia, y los topes (inglés, años) aplican
igual en todos los stacks.

"No soy tan experto en Vue" **no te frena**: lo que puntúa es el backend PHP/Laravel. En las 16 ofertas Laravel
leídas hoy, Vue apareció solo en 3 e Inertia en 1; Tomplay pide frontend solo como "plus" (Blade/JS).

---

## 5. Conclusión

1. **Mejor entrada hoy: Laravel/PHP con perfil backend y frontend agnóstico (Vue o React).** Tu evidencia vale
   20–35 puntos más ahí, y la competencia por oferta no es menor en los otros stacks.
2. **React vs Vue:** Vue tiene menos candidatos por oferta en la muestra, pero también muchas menos ofertas. No
   compensa hacerse "experto en Vue" ni cambiar a React: preséntate como **"Laravel con Vue o React"**, que es
   verdad (Vidula + AquaShield).
3. **Next.js / NestJS:** el pivote más caro. Piden 2–5+ años en ese stack, inglés alto o C1, y NestJS en España
   suele venir con Angular y residencia española.
4. **FastAPI:** el complemento más razonable (ya tienes un servicio real e integración con LLMs), pero las ofertas
   actuales piden LangGraph/RAG/pytest. Es una segunda línea, no un cambio.
5. **PHP > Laravel:** hay 3,6–4,7× más ofertas PHP que Laravel en España y Portugal. **Symfony** amplía tu mercado
   sin salir de tu evidencia.
6. **El inglés es el muro transversal:** aparece en todos los stacks (YTEC, Baoss, PANEL, Social You, Sermicro,
   ve2, Neotalent). Ningún cambio de framework lo esquiva.
7. **Mercado accesible:** "100% remoto" español suele exigir residir en España. Tu ventaja está en ofertas
   **remotas con residencia en Portugal** (Neotalent PHP, LucidHorizon Laravel, YTEC Next.js, Zero to One Node,
   Jay Digital Hub/KCS/Neotalent Python) y en las de toda la UE.

## 6. Recomendación priorizada

| # | Acción | Por qué (dato) | Esfuerzo |
|---|---|---|---|
| 1 | Seguir en Laravel/PHP, perfil backend, frontend "Vue o React" | Encaje bruto 83–86 vs 51–64 | Solo redacción |
| 2 | Inglés B1 → B2 certificado | Aparece en todos los stacks; ya bloqueó 4 ofertas Laravel en dos búsquedas | 3–6 meses |
| 3 | Symfony + Doctrine (un servicio pequeño) | PHP es 3,6–4,7× Laravel en ES/PT | 4–8 semanas |
| 4 | FastAPI + LLM (RAG, pytest) sobre video-cleanup-api | Segunda línea hacia backend de IA | 4–8 semanas |
| 5 | No pivotar a Next.js/NestJS ahora | Encaje 51–64, años específicos del stack, inglés C1 | Revisar tras el B2 |

---

## Evidencia (URLs leídas)

- Tecnoempleo: /ofertas-trabajo/laravel · /vue · /react · /nestjs · /nextjs · /next.js · /fastapi · /php
- ITJobs: /emprego?q=laravel · vue · react · next.js · nestjs · fastapi · php
- RemoteRocketship: /jobs/laravel · /vue-js · /react · /next-js · /php
- https://www.tecnoempleo.com/frontend-developer-react-typescript-ia-panel-siste/azure-rest-apis/rf-d8d4151b22a543997947
- https://www.tecnoempleo.com/senior-frontend-developer-baoss/react-typescript/rf-38df1e6a12aef3f1c04e
- https://www.tecnoempleo.com/desarrollador-frontend-vue3-remoto-grupo-digital/azure-ci-cd/rf-31bf141cf296b36b6c43
- https://www.tecnoempleo.com/senior-frontend-engineer-nuxtjs-vue-3-social-you/nuxtjs-composition-api/rf-59231099629673896c4e
- https://www.tecnoempleo.com/remote-located-full-stack-dev-in-manas-spain/vue-react/rf-bfc415884276034ad549
- https://www.tecnoempleo.com/nodejs-angular-senior-fullstack-grupo-sermicro/nodejs-mongodb/rf-3f831c9432a2c339f14a
- https://www.tecnoempleo.com/senior-fullstack-developer-python-react-sopra-ster/typescript-fastapi/rf-14db1130a2ab2345cb4b
- https://pt.linkedin.com/jobs/view/senior-next-js-remote-from-portugal-at-ytec-4465630835
- https://pt.linkedin.com/jobs/view/backend-developer-node-js-portugal-remote-at-zero-to-one-search-recruitment-agency-4463961555
- https://www.linkedin.com/jobs/view/platform-engineer-at-recann-4466061295
- https://uk.linkedin.com/jobs/view/full-stack-engineer-at-twelve-4465677341
- https://es.linkedin.com/jobs/view/backend-engineer-%E2%80%93-python-fastapi-llm-pipelines-100%25-remote-at-infoser-technological-solutions-hardware-big-data-experts-4463330791
- https://www.jobleads.com/ph/job/node-engineer-remoto--espana--e32ac846e16c0932d21727c52c1c9e067
- https://www.linkedin.com/jobs/view/full-stack-engineer-at-ve2-ventures-4464485197
- https://pt.linkedin.com/jobs/view/senior-backend-engineer-php-laravel-at-lucidhorizon-4464467476
- https://www.wearedevelopers.com/jobs/ext/2788871-back-end-developer-php-laravel
