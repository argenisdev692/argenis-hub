<!-- ATS export: Calibri body 11 pt · section headers 13 pt bold · name 20 pt · single column · max 2 pages -->

# **Argenis Carrillo Gonzalez**

Full Stack Developer  |  PHP · Laravel · Vue.js · Inertia.js · TypeScript

Covilha, Portugal   |   WhatsApp (+351) 963 490 414   |   argenis692@gmail.com   |   argenis.dev   |   linkedin.com/in/argenisdev692   |   github.com/argenisdev692

Social: instagram.com/argenis.dev   |   tiktok.com/@argenisdev692   |   facebook.com/argenisdev692

## **PROFESSIONAL SUMMARY**

**Full Stack PHP / Laravel Developer with 4+ years** delivering production CRMs, booking platforms, and lead-generation sites **remote from Covilha, Portugal** (~**8** client landings and CRMs shipped end to end). Works across **Laravel**, **Inertia.js**, **Vue.js 3**, **Livewire**, **REST APIs**, **PostgreSQL** / **MySQL**, **Redis**, **Docker**, and **CI/CD** on **GitHub Actions**. Experience spans a legacy **vanilla PHP** CRM maintained and extended over two years through to a **24-module Laravel 13 + Inertia + Vue 3** codebase with hexagonal architecture, queue workers, and WebSocket broadcasting. Owns features from database schema to deploy, with **OWASP** hardening and automated tests as part of the definition of done. Also delivers contracted AI-tooling training on Claude AI, Microsoft 365 Copilot, and GitHub Copilot.

## **TECHNICAL SKILLS**

**Core (ATS): PHP 8.x** · **Laravel 10-13** · **Vue.js 3** · **Inertia.js** · **Livewire** · **TypeScript** · **REST APIs** · **PostgreSQL** · **MySQL** · **Redis** · **Docker** · **CI/CD** · **GitHub Actions**

**Back-end:** Eloquent ORM · Queues and async workers (Laravel Horizon) · WebSockets and broadcasting (Reverb) · Sanctum · Jetstream · Fortify · Spatie Permission (RBAC) · Spatie Activity Log · OpenAPI / API documentation · Python (FastAPI)

**Front-end:** Vue 3 (Composition API, script setup) · Pinia · React 19 · Tailwind CSS · Astro · Zod

**Databases & performance:** PostgreSQL 17 · MySQL 8 · Redis caching and sessions · N+1 prevention and eager-loading discipline · query optimisation

**Cloud & DevOps:** Docker Compose / Laravel Sail · GitHub Actions · Cloudflare Workers / R2 · Railway · VPS · Laravel Pint · **PHPUnit** · OWASP

**Integrations & AI:** Google Calendar / OAuth · Google Meet · Resend · SumUp · DocuSign · Google Maps · Supabase · laravel/ai · Claude AI · GitHub Copilot

## **WORK EXPERIENCE**

### **Full Stack Developer - Vidula (Self-Built MVP)**

**Independent project** _|   Remote from Covilha, Portugal   |   May 2026 - Jul 2026_

- **Built** a 24-module operations MVP for my own teaching business (classroom management, scheduling, CRM, AI content, video production), applying hexagonal architecture (Ports and Adapters, DDD, CQRS-light) across every module.

- **Implemented** the full stack: **Laravel 13**, **Inertia.js v3**, **Vue 3.5** with strict **TypeScript**, **PostgreSQL 17**, **Redis**, Horizon, Reverb, laravel/ai, and Cloudflare R2.

- **Cut roughly 3 hours of manual editing per video** with an automated pipeline (**FFmpeg** plus a **Python/FastAPI** microservice running faster-whisper and Silero VAD) that detects fillers, repeated takes, and long silences, then exports a **1080p/30fps** master.

- **Moved large video uploads off the application server** using presigned Cloudflare R2 upload URLs, with **Horizon**-managed queue workers running the render jobs and **Reverb** streaming live progress to the browser.

- **Gated every merge** behind **GitHub Actions** running Laravel Pint, **PHPUnit** against a **PostgreSQL 17** service container, vue-tsc type-checking, and a production Vite build, reaching **96 test files across 24 modules**.

- **Enforced query discipline** project-wide with strict-mode Eloquent and explicit eager loading, so N+1 queries fail loudly in development instead of reaching production.

- **Hardened** authentication and delivery to OWASP practice: Argon2id hashing, TOTP two-factor, nonce-based CSP, HSTS, and a Redis-backed brute-force lockout.

- **Generated OpenAPI documentation** automatically from typed controllers and form requests, so the API docs never drift from the code.

### **Front-end Developer - Corporate Landing Page**

**AquaShield Restoration USA** _|   Houston, USA - Remote   |   Mar 2026_

- **Built** a public acquisition portal (lead capture plus free-inspection scheduling) with **Astro 5**, **React 19**, **TypeScript**, **Tailwind CSS v4**, and **Supabase** (PostgreSQL).

- **Reached Lighthouse 98/100** (FCP 0.8s, TTI 1.2s, CLS 0.001, total bundle 435KB) by shipping static HTML by default and hydrating only the interactive islands.

- **Reduced spam submissions by 97%** with a seven-layer OWASP 2025 defence: honeypot fields, per-IP rate limiting, content analysis, Cloudflare Turnstile, user-agent checks, and duplicate detection, backed by **Zod** schema validation on every endpoint.

- **Deployed** to **Cloudflare Workers** with **CI/CD** on **GitHub Actions**.

### **AI Trainer - Claude AI (Freelance)**

**Imagina Web & Mobile Technologies** _|   Valencia, Spain - Remote   |   Jan 2026 - Present_

- **Designed and delivered** "**Claude AI** for Users": **7 hours / 48 videos** covering professional prompting, Projects, Research, Workspace connectors, and corporate governance.

- **Recorded** "**Microsoft 365 Copilot** for Users": **7 hours / 32 videos** of short-form video for the same platform.

- **Created** "**Microsoft 365 Copilot** for Administrative Tasks": **6 hours / 10 modules** across Word, Excel, PowerPoint, Outlook, Teams, Planner, and SharePoint.

- **Produced** e-learning content and video pills at 1080p/30fps under contract, delivered on schedule for Spanish training platforms.

### **Full Stack Developer - Appointment & Remote Assistance Platform**

**Servispin (Appliance Repair Services)** _|   Las Palmas de Gran Canaria, Spain - Remote   |   Dec 2025_

- **Delivered** a booking platform for in-home repairs plus a paid remote video-assistance funnel (**Laravel 13**, **PHP 8.4**, **Livewire 3**, **Jetstream**, **Sanctum**, **MySQL**), serving an average of ~**25 clients/month**.

- **Supported a Meta Ads funnel that brought in ~42 new clients in two months** by automating confirmations, rescheduling, and cancellations with **FullCalendar** and the **Google Calendar API**, including availability rules for non-working days and public holidays.

- **Automated** paid remote sessions end to end: SumUp QR payment verification, automatic Google Meet link creation, PDF/QR confirmations, and Resend transactional email.

- **Kept paid bookings from being lost** when Google Calendar fails to return a Meet link, by confirming the appointment anyway and routing it to an admin queue for manual follow-up.

- **Secured** the admin panel with **Spatie Permission** role-based access control and throttled the public forms against abuse.

### **AI Coding-Tools Trainer for Web Developers (Freelance)**

**Imagina Web & Mobile Technologies** _|   Online - Remote   |   Nov 2025 - Dec 2025_

- **Taught** a live **Zoom** course to **30 students** over **8 sessions / 25 hours**, covering **GitHub Copilot** for web development with **.NET** and **Angular**.

### **Full Stack Developer - Smart Finance 365 (MVP)**

**Freelance** _|   Remote from Covilha, Portugal   |   2024_

- **Built** an income and expense management MVP with **Laravel** and **Livewire**, covering transaction entry, categorisation, and balance reporting.

### **PHP Developer - CRM Maintenance & New Modules**

**Restoration Control (restorationcontrol.com)** _|   USA - Remote   |   2022 - 2024_

- **Maintained and extended** a production CRM written in **vanilla PHP** (no framework) across two years, adding new modules on top of a legacy codebase without a rewrite.

- **Shipped** new CRM modules and landing pages against a live **PHP / MySQL** system, scoping each change directly with the US client.

- **Kept the platform running** through routine maintenance and fixes; the same client later rebranded as AquaShield, which led to the 2026 landing-page engagement above.

## **EDUCATION**

### **Bachelor of Science in Computer Science**

_Universidad Bolivariana de Venezuela (UBV)  |  Ciudad Bolivar, Venezuela  |  Sep 2010 - Jul 2017_

## **COURSES DELIVERED (UNDER CONTRACT)**

- "**Cursor AI** for Web Development" - Imagina Formacion, Spain (2025) - recorded course, 6h / 12 videos

- "**Claude AI** for Users" - Imagina Formacion, Spain (2026) - recorded course, 7h / 48 videos

- "**Microsoft 365 Copilot** for Users" - Imagina Formacion, Spain (2026) - recorded course, 7h / 32 videos

- "**Microsoft 365 Copilot** for Administrative Tasks" - Imagina Formacion, Spain (2026) - recorded course, 6h / 10 modules

- "**Tabnine** for Web Developers (**.NET** and **React**)" - Imagina Formacion, Spain (2025) - live course, 25h / 5 sessions, 25 students

- "**GitHub Copilot** for Web Developers (**.NET** and **Angular**)" - Imagina Formacion, Spain (2025) - live course, 25h / 8 sessions, 30 students

## **PROJECTS**

**Vidula - Operations MVP for Classroom & Video Production** _|   vidula.up.railway.app  |  github.com/argenisdev692/vidula_

**Laravel 13** · **Inertia.js v3** · **Vue 3.5** · strict **TypeScript** · **PostgreSQL 17** · **Redis** · Horizon · Reverb · Cloudflare R2. A 24-module monolith on a hexagonal architecture: CRM, scheduling with Google Calendar sync, AI content generation, an FFmpeg video-export pipeline, RBAC admin, and a FastAPI transcription microservice. **96 PHPUnit test files** behind a four-stage **GitHub Actions** gate (Pint, PHPUnit on PostgreSQL 17, vue-tsc, Vite build), with N+1 prevention enforced project-wide.

**AquaShield Restoration - Lead Generation Landing Page** _|   aquashieldrestorationusa.com  |  github.com/argenisdev692/aquashield-web_

**Astro 5** · **React 19** · **TypeScript** · **Tailwind CSS v4** · **Supabase** · **Cloudflare Workers**. **Lighthouse 98/100** at a 435KB total bundle, **97% fewer spam submissions** across seven OWASP 2025 defence layers, automated lead capture with Zod validation and Resend delivery.

**Servispin - Appointments & Paid Remote Assistance** _|   servispin.net  |  github.com/argenisdev692/servispin_

**Laravel 13** · **Livewire 3** · **Jetstream** · **Sanctum** · **MySQL**. Home-visit booking plus a paid remote-assistance funnel (SumUp QR verification, automatic Google Meet links), PDF/QR confirmations, holiday-aware availability rules, and an RBAC admin panel. Averages ~**25 clients/month**.

**Tutorial Cleanup API - Video Processing Microservice** _|   github.com/argenisdev692/video-cleanup-api_

**Python 3.12** · **FastAPI** · **Docker** · faster-whisper · Silero VAD · **FFmpeg** · Cloudflare R2. The service behind Vidula's video pipeline: it transcribes with word-level timestamps, detects fillers, repetitions, and silences through voice-activity detection, then applies the cut plan and returns a clean master. Bearer-token authenticated, containerised, layered service architecture.

## **LANGUAGES**

**Spanish:** Native  **Portuguese:** Resident level (Covilha, Portugal)  **English:** B1 Intermediate
