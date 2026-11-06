## Argenis Carrillo Gonzalez

Full Stack Developer | PHP, Laravel, Vue.js, Inertia.js, TypeScript

Covilha, Portugal | +351 963 490 414 | argenis692@gmail.com | argenis.dev | linkedin.com/in/argenisdev692 | github.com/argenisdev692

## PROFESSIONAL SUMMARY

Full Stack PHP / Laravel Developer with 4+ years of experience delivering production CRMs, booking platforms, and lead-generation websites remotely from Covilha, Portugal, including approximately 8 client landing pages and CRM systems shipped end to end. Experienced across Laravel, Inertia.js, Vue.js 3, Livewire, REST APIs, PostgreSQL, MySQL, Redis, Docker, and CI/CD with GitHub Actions. Background spans a legacy vanilla PHP CRM maintained and extended over two years through to a 24-module Laravel 13 and Inertia.js and Vue 3 codebase built on hexagonal architecture with queue workers and WebSocket broadcasting. Applies OWASP security hardening and automated testing as part of every feature, from database schema through deployment. Also delivers contracted AI-tooling training on Claude AI, Microsoft 365 Copilot, GitHub Copilot, Cursor, and Tabnine.

## TECHNICAL SKILLS

Programming Languages and Frameworks: PHP 8.x, Laravel 10-13, Vue.js 3, Inertia.js, Livewire, TypeScript, Python,

FastAPI, React 19

Databases: PostgreSQL 17, MySQL 8, Redis

Front-End: Vue 3 Composition API, Pinia, Tailwind CSS, Astro, Zod

Back-End and APIs: REST APIs, Eloquent ORM, Laravel Horizon, Laravel Reverb (WebSockets), Sanctum, Jetstream, Fortify, Spatie Permission (RBAC), Spatie Activity Log, OpenAPI documentation

Cloud and DevOps: Docker, Docker Compose, Laravel Sail, CI/CD, GitHub Actions, Cloudflare Workers, Cloudflare R2, Railway, AWS, VPS hosting, Laravel Pint, PHPUnit, OWASP security practices

Integrations and AI Tools: Google Calendar API, Google Meet, Resend, SumUp, DocuSign, Google Maps API, Supabase, laravel/ai, Claude AI, GitHub Copilot, Cursor, Tabnine

## WORK EXPERIENCE

## Full Stack Developer — Vidula (Self-Built MVP, Independent Project)

Remote from Covilha, Portugal | 05/2026 - 07/2026

- Built a 24-module operations MVP for an independent teaching business (classroom management, scheduling, CRM, AI content, video production), applying hexagonal architecture (Ports and Adapters, DDD, CQRS-light) with Laravel 13, Inertia.js v3, Vue 3.5, strict TypeScript, PostgreSQL 17, Redis, Horizon, Reverb, laravel/ai, and Cloudflare R2.

- Cut roughly 3 hours of manual editing per video with an automated FFmpeg + Python/FastAPI pipeline (faster-whisper, Silero VAD) that detects fillers, repeated takes, and long silences, then exports a 1080p/30fps master.

- Moved large video uploads off the application server using presigned Cloudflare R2 upload URLs, with Horizon-managed queue workers running render jobs and Reverb streaming live progress to the browser.

- Gated every merge behind GitHub Actions (Laravel Pint, PHPUnit on PostgreSQL 17, vue-tsc, production Vite build), reaching 96 test files across 24 modules, with strict-mode Eloquent and eager loading enforced to prevent N+1 queries.

- Hardened authentication and delivery to OWASP practice: Argon2id hashing, TOTP two-factor, nonce-based CSP, HSTS, and a Redis-backed brute-force lockout; generated OpenAPI documentation automatically from typed controllers and form requests.

## Front-End Developer — AquaShield Restoration USA (Corporate Landing Page)

Houston, USA - Remote | 03/2026

- Built a public acquisition portal (lead capture plus free-inspection scheduling) with Astro 5, React 19, TypeScript, Tailwind CSS v4, and Supabase (PostgreSQL).

- Reached a Lighthouse score of 98/100 (FCP 0.8s, TTI 1.2s, CLS 0.001, total bundle 435KB) by shipping static HTML by default and hydrating only the interactive islands.

- Reduced spam submissions by 97% with a seven-layer OWASP 2025 defense (honeypot fields, per-IP rate limiting, content analysis, Cloudflare Turnstile, user-agent checks, duplicate detection) backed by Zod schema validation; deployed to Cloudflare Workers with CI/CD on GitHub Actions.

## AI Trainer, Claude AI (Freelance) — Imagina Web & Mobile Technologies

Valencia, Spain - Remote | 01/2026 - Present

- Designed and delivered "Claude AI for Users" (7h / 48 videos) covering professional prompting, Projects, Research, Workspace connectors, and corporate governance, plus "Microsoft 365 Copilot for Users" (7h / 32 videos) and "Microsoft 365 Copilot for Administrative Tasks" (6h / 10 modules covering Word, Excel, PowerPoint, Outlook, Teams, Planner, and SharePoint).

- Recorded "Cursor AI for Web Development" (6h / 12 videos) on AI-assisted coding workflows, and produced e-learning content and short-form video at 1080p/30fps under contract, delivered on schedule for Spanish training platforms.


## Full Stack Developer — Servispin, Appliance Repair Services (Appointment and Remote Assistance Platform)

Las Palmas de Gran Canaria, Spain - Remote | 12/2025

- Delivered a booking platform for in-home repairs plus a paid remote video-assistance funnel (Laravel 13, PHP 8.4, Livewire 3, Jetstream, Sanctum, MySQL), serving an average of about 25 clients per month.

- Supported a Meta Ads funnel that brought in approximately 42 new clients in two months by automating confirmations, rescheduling, and cancellations with FullCalendar and the Google Calendar API, including availability rules for non-working days and public holidays.

- Automated paid remote sessions end to end: SumUp QR payment verification, automatic Google Meet link creation, PDF/QR confirmations, and Resend transactional email, with paid bookings routed to an admin queue for manual follow-up whenever Google Calendar failed to return a Meet link.

- Secured the admin panel with Spatie Permission role-based access control and throttled the public forms against abuse.

## AI Coding-Tools Trainer for Web Developers (Freelance) — Imagina Web & Mobile Technologies

Online - Remote | 2025

- Taught two live Zoom cohorts (25 hours each): 30 students over 8 sessions on GitHub Copilot for web development with .NET and Angular, and 25 students over 5 sessions on Tabnine for web development with .NET and React.

## Full Stack Developer — Smart Finance 365 (MVP, Freelance)

Remote from Covilha, Portugal | 2024

- Built an income and expense management MVP with Laravel and Livewire, covering transaction entry, categorization, and balance reporting.

## PHP Developer — Restoration Control (CRM Maintenance and New Modules)

USA - Remote | 2022 - 2024

- Maintained and extended a production CRM written in vanilla PHP (no framework) across two years, shipping new modules and landing pages against a live PHP/MySQL system and scoping each change directly with the US-based client.

- Kept the platform running through routine maintenance and fixes; the same client later rebranded as AquaShield, leading to the 2026 landing-page engagement above.

## EDUCATION

## Bachelor of Science in Computer Science

Universidad Bolivariana de Venezuela (UBV), Ciudad Bolivar, Venezuela | 09/2010 - 07/2017

## PROJECTS

## Vidula — Operations MVP for Classroom and Video Production

vidula.up.railway.app | github.com/argenisdev692/vidula

Laravel 13, Inertia.js v3, Vue 3.5, TypeScript, PostgreSQL 17, Redis, Horizon, Reverb, Cloudflare R2. 24-module hexagonal-architecture monolith: CRM, scheduling, AI content generation, an FFmpeg video-export pipeline, RBAC admin, and a FastAPI transcription microservice; 96 PHPUnit test files behind a four-stage GitHub Actions gate.

## AquaShield Restoration — Lead Generation Landing Page

aquashieldrestorationusa.com | github.com/argenisdev692/aquashield-web

Astro 5, React 19, TypeScript, Tailwind CSS v4, Supabase, Cloudflare Workers. Lighthouse 98/100 at a 435KB bundle, 97% fewer spam submissions across seven OWASP 2025 defense layers.

## Servispin — Appointments and Paid Remote Assistance

servispin.net | github.com/argenisdev692/servispin

Laravel 13, Livewire 3, Jetstream, Sanctum, MySQL. Home-visit booking plus a paid remote-assistance funnel with SumUp QR verification, automatic Google Meet links, and an RBAC admin panel.

## Tutorial Cleanup API — Video Processing Microservice

github.com/argenisdev692/video-cleanup-api

Python 3.12, FastAPI, Docker, faster-whisper, Silero VAD, FFmpeg, Cloudflare R2. Bearer-token authenticated microservice behind Vidula's pipeline: transcribes with word-level timestamps, detects fillers/repetitions/silences, then applies the cut plan and returns a clean master.

## TRAINING PROGRAMS DELIVERED (CONTRACT WORK)

All delivered under contract for Imagina Formacion, Spain. Recorded courses: Claude AI for Users (7h / 48 videos, 2026); Microsoft 365 Copilot for Users (7h / 32 videos, 2026); Microsoft 365 Copilot for Administrative Tasks (6h / 10 modules, 2026); Cursor AI for Web Development (6h / 12 videos, 2025). Live courses: Tabnine for .NET and React (25h, 25 students, 2025); GitHub Copilot for .NET and Angular (25h, 30 students, 2025).

## LANGUAGES

Spanish: Native | Portuguese: Resident level (Covilha, Portugal) | English: B1 Intermediate
