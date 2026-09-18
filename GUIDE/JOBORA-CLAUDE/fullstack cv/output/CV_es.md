<!-- Formato ATS: Calibri cuerpo 11 pt · encabezados 13 pt negrita · nombre 20 pt · una columna · máx. 2 páginas -->

# **Argenis Carrillo Gonzalez**

Desarrollador Full Stack  |  PHP · Laravel · Vue.js · Inertia.js · TypeScript

Covilhã, Portugal   |   WhatsApp (+351) 963 490 414   |   argenis692@gmail.com   |   argenis.dev   |   linkedin.com/in/argenisdev692   |   github.com/argenisdev692

Redes: instagram.com/argenis.dev   |   tiktok.com/@argenisdev692   |   facebook.com/argenisdev692

## **RESUMEN PROFESIONAL**

**Desarrollador Full Stack PHP / Laravel con más de 4 años** entregando CRMs, plataformas de reservas y webs de captación en producción, **en remoto desde Covilhã (Portugal)** (~**8** landings y CRMs de cliente entregados de principio a fin). Trabajo con **Laravel**, **Inertia.js**, **Vue.js 3**, **Livewire**, **APIs REST**, **PostgreSQL** / **MySQL**, **Redis**, **Docker** y **CI/CD** sobre **GitHub Actions**. Mi experiencia va desde un CRM heredado en **PHP puro**, mantenido y ampliado durante dos años, hasta una base de código de **24 módulos con Laravel 13 + Inertia + Vue 3**, con arquitectura hexagonal, workers de colas y difusión por WebSockets. Me hago cargo de una funcionalidad desde el esquema de base de datos hasta el despliegue, con hardening **OWASP** y tests automatizados como parte del criterio de terminado. También imparto formación por contrato sobre herramientas de IA: Claude AI, Microsoft 365 Copilot y GitHub Copilot.

## **HABILIDADES TÉCNICAS**

**Core (ATS): PHP 8.x** · **Laravel 10-13** · **Vue.js 3** · **Inertia.js** · **Livewire** · **TypeScript** · **APIs REST** · **PostgreSQL** · **MySQL** · **Redis** · **Docker** · **CI/CD** · **GitHub Actions**

**Back-end:** Eloquent ORM · Colas y workers asíncronos (Laravel Horizon) · WebSockets y broadcasting (Reverb) · Sanctum · Jetstream · Fortify · Spatie Permission (RBAC) · Spatie Activity Log · OpenAPI / documentación de API · Python (FastAPI)

**Front-end:** Vue 3 (Composition API, script setup) · Pinia · React 19 · Tailwind CSS · Astro · Zod

**Bases de datos y rendimiento:** PostgreSQL 17 · MySQL 8 · Caché y sesiones con Redis · Prevención de N+1 y disciplina de eager loading · Optimización de consultas

**Cloud y DevOps:** Docker Compose / Laravel Sail · GitHub Actions · Cloudflare Workers / R2 · Railway · VPS · Laravel Pint · **PHPUnit** · OWASP

**Integraciones e IA:** Google Calendar / OAuth · Google Meet · Resend · SumUp · DocuSign · Google Maps · Supabase · laravel/ai · Claude AI · GitHub Copilot

## **EXPERIENCIA PROFESIONAL**

### **Desarrollador Full Stack - Vidula (MVP propio)**

**Proyecto independiente** _|   En remoto desde Covilhã, Portugal   |   May 2026 - Jul 2026_

- **Desarrollé** un MVP de operaciones de 24 módulos para mi propia actividad docente (gestión de aulas, agenda, CRM, contenido con IA, producción de vídeo), aplicando arquitectura hexagonal (puertos y adaptadores, DDD, CQRS ligero) en todos los módulos.

- **Implementé** la pila completa: **Laravel 13**, **Inertia.js v3**, **Vue 3.5** con **TypeScript** estricto, **PostgreSQL 17**, **Redis**, Horizon, Reverb, laravel/ai y Cloudflare R2.

- **Reduje unas 3 horas de edición manual por vídeo** con un pipeline automatizado (**FFmpeg** más un microservicio en **Python/FastAPI** con faster-whisper y Silero VAD) que detecta muletillas, tomas repetidas y silencios largos, y exporta un máster en **1080p/30fps**.

- **Saqué las subidas de vídeo pesadas del servidor de aplicación** mediante URLs de subida prefirmadas a Cloudflare R2, con workers de cola gestionados por **Horizon** ejecutando los trabajos de renderizado y **Reverb** enviando el progreso en tiempo real al navegador.

- **Bloqueé cada merge** tras un pipeline de **GitHub Actions** con Laravel Pint, **PHPUnit** contra un contenedor de servicio **PostgreSQL 17**, verificación de tipos con vue-tsc y build de producción con Vite, alcanzando **96 ficheros de test en 24 módulos**.

- **Impuse disciplina en las consultas** en todo el proyecto con el modo estricto de Eloquent y eager loading explícito, de forma que las consultas N+1 fallan de manera visible en desarrollo en lugar de llegar a producción.

- **Reforcé** la autenticación y la entrega según prácticas OWASP: hash con Argon2id, doble factor TOTP, CSP basada en nonce, HSTS y bloqueo de fuerza bruta respaldado por Redis.

- **Generé documentación OpenAPI** automáticamente a partir de controladores y form requests tipados, de modo que la documentación de la API nunca se desincroniza del código.

### **Desarrollador Front-end - Landing corporativa**

**AquaShield Restoration USA** _|   Houston, EE. UU. - En remoto   |   Mar 2026_

- **Construí** un portal público de captación (registro de leads y agenda de inspección gratuita) con **Astro 5**, **React 19**, **TypeScript**, **Tailwind CSS v4** y **Supabase** (PostgreSQL).

- **Alcancé Lighthouse 98/100** (FCP 0,8 s, TTI 1,2 s, CLS 0,001, bundle total de 435 KB) sirviendo HTML estático por defecto e hidratando solo las islas interactivas.

- **Reduje el spam en formularios un 97%** con siete capas de defensa OWASP 2025: campos honeypot, límite de peticiones por IP, análisis de contenido, Cloudflare Turnstile, verificación de user-agent y detección de duplicados, con validación de esquemas mediante **Zod** en cada endpoint.

- **Desplegué** en **Cloudflare Workers** con **CI/CD** en **GitHub Actions**.

### **Formador de IA - Claude AI (Freelance)**

**Imagina Web & Mobile Technologies** _|   Valencia, España - En remoto   |   Ene 2026 - Actualidad_

- **Diseñé e impartí** "**Claude AI** para Usuarios": **7 horas / 48 vídeos** sobre prompting profesional, Proyectos, Research, conectores de Workspace y gobernanza corporativa.

- **Grabé** "**Microsoft 365 Copilot** para Usuarios": **7 horas / 32 vídeos** de vídeo de formato corto para la misma plataforma.

- **Creé** "**Microsoft 365 Copilot** para Tareas Administrativas": **6 horas / 10 módulos** sobre Word, Excel, PowerPoint, Outlook, Teams, Planner y SharePoint.

- **Produje** contenido e-learning y píldoras de vídeo en 1080p/30fps por contrato, entregados en plazo para plataformas de formación españolas.

### **Desarrollador Full Stack - Plataforma de citas y asistencia remota**

**Servispin (Reparación de electrodomésticos)** _|   Las Palmas de Gran Canaria, España - En remoto   |   Dic 2025_

- **Entregué** una plataforma de reservas para reparaciones a domicilio y un embudo de videoasistencia remota de pago (**Laravel 13**, **PHP 8.4**, **Livewire 3**, **Jetstream**, **Sanctum**, **MySQL**), con una media de ~**25 clientes/mes**.

- **Di soporte a un embudo de Meta Ads que captó ~42 clientes nuevos en dos meses** automatizando confirmaciones, cambios de cita y cancelaciones con **FullCalendar** y la **API de Google Calendar**, incluidas reglas de disponibilidad para días no laborables y festivos.

- **Automaticé** las sesiones remotas de pago de principio a fin: verificación de pago con QR de SumUp, creación automática del enlace de Google Meet, confirmaciones en PDF/QR y correo transaccional con Resend.

- **Evité perder reservas ya pagadas** cuando Google Calendar no devuelve el enlace de Meet, confirmando igualmente la cita y derivándola a una bandeja de administración para seguimiento manual.

- **Protegí** el panel de administración con control de acceso por roles de **Spatie Permission** y limité la frecuencia de envío de los formularios públicos frente a abusos.

### **Formador de herramientas de IA para Desarrolladores Web (Freelance)**

**Imagina Web & Mobile Technologies** _|   Online - En remoto   |   Nov 2025 - Dic 2025_

- **Impartí** un curso en directo por **Zoom** a **30 alumnos** en **8 sesiones / 25 horas**, sobre **GitHub Copilot** aplicado al desarrollo web con **.NET** y **Angular**.

### **Desarrollador Full Stack - Smart Finance 365 (MVP)**

**Freelance** _|   En remoto desde Covilhã, Portugal   |   2024_

- **Construí** un MVP de gestión de ingresos y gastos con **Laravel** y **Livewire**, con registro de movimientos, categorización e informes de saldo.

### **Desarrollador PHP - Mantenimiento y nuevos módulos de CRM**

**Restoration Control (restorationcontrol.com)** _|   EE. UU. - En remoto   |   2022 - 2024_

- **Mantuve y amplié** durante dos años un CRM en producción escrito en **PHP puro** (sin framework), añadiendo nuevos módulos sobre una base de código heredada y sin necesidad de reescribirla.

- **Entregué** nuevos módulos de CRM y landings sobre un sistema **PHP / MySQL** en producción, definiendo cada cambio directamente con el cliente estadounidense.

- **Mantuve la plataforma en funcionamiento** mediante mantenimiento y correcciones continuas; el mismo cliente pasó después a operar como AquaShield, lo que dio lugar al encargo de la landing en 2026 descrito más arriba.

## **FORMACIÓN ACADÉMICA**

### **Licenciatura en Informática (Ingeniería en Ciencias de la Computación)**

_Universidad Bolivariana de Venezuela (UBV)  |  Ciudad Bolívar, Venezuela  |  Sep 2010 - Jul 2017_

## **FORMACIÓN IMPARTIDA (POR CONTRATO)**

- "**Cursor AI** Desarrollo Web" - Imagina Formación, España (2025) - curso grabado, 6 h / 12 vídeos

- "**Claude AI** para Usuarios" - Imagina Formación, España (2026) - curso grabado, 7 h / 48 vídeos

- "**Microsoft 365 Copilot** para Usuarios" - Imagina Formación, España (2026) - curso grabado, 7 h / 32 vídeos

- "**Microsoft 365 Copilot** para Tareas Administrativas" - Imagina Formación, España (2026) - curso grabado, 6 h / 10 módulos

- "**Tabnine** para Desarrolladores Web (**.NET** y **React**)" - Imagina Formación, España (2025) - curso en directo, 25 h / 5 sesiones, 25 alumnos

- "**GitHub Copilot** para Desarrolladores Web (**.NET** y **Angular**)" - Imagina Formación, España (2025) - curso en directo, 25 h / 8 sesiones, 30 alumnos

## **PROYECTOS**

**Vidula - MVP de operaciones para aulas y producción de vídeo** _|   vidula.up.railway.app  |  github.com/argenisdev692/vidula_

**Laravel 13** · **Inertia.js v3** · **Vue 3.5** · **TypeScript** estricto · **PostgreSQL 17** · **Redis** · Horizon · Reverb · Cloudflare R2. Monolito de 24 módulos sobre arquitectura hexagonal: CRM, agenda con sincronización de Google Calendar, generación de contenido con IA, pipeline de exportación de vídeo con FFmpeg, administración con RBAC y microservicio de transcripción en FastAPI. **96 ficheros de test con PHPUnit** tras un pipeline de **GitHub Actions** de cuatro etapas (Pint, PHPUnit sobre PostgreSQL 17, vue-tsc, build de Vite), con prevención de N+1 impuesta en todo el proyecto.

**AquaShield Restoration - Landing de captación de leads** _|   aquashieldrestorationusa.com  |  github.com/argenisdev692/aquashield-web_

**Astro 5** · **React 19** · **TypeScript** · **Tailwind CSS v4** · **Supabase** · **Cloudflare Workers**. **Lighthouse 98/100** con un bundle total de 435 KB, **97% menos de envíos de spam** mediante siete capas de defensa OWASP 2025, y captación automatizada de leads con validación Zod y entrega por Resend.

**Servispin - Citas y asistencia remota de pago** _|   servispin.net  |  github.com/argenisdev692/servispin_

**Laravel 13** · **Livewire 3** · **Jetstream** · **Sanctum** · **MySQL**. Reserva de visitas a domicilio y embudo de asistencia remota de pago (verificación con QR de SumUp, enlaces automáticos de Google Meet), confirmaciones en PDF/QR, reglas de disponibilidad que contemplan festivos y panel de administración con RBAC. Media de ~**25 clientes/mes**.

**Tutorial Cleanup API - Microservicio de procesamiento de vídeo** _|   github.com/argenisdev692/video-cleanup-api_

**Python 3.12** · **FastAPI** · **Docker** · faster-whisper · Silero VAD · **FFmpeg** · Cloudflare R2. El servicio que hay detrás del pipeline de vídeo de Vidula: transcribe con marcas de tiempo por palabra, detecta muletillas, repeticiones y silencios mediante detección de actividad de voz, aplica el plan de cortes y devuelve un máster limpio. Autenticación por token Bearer, contenerizado y con arquitectura de servicios por capas.

## **IDIOMAS**

**Español:** Nativo  **Portugués:** Nivel residente (Covilhã, Portugal)  **Inglés:** B1 Intermedio
