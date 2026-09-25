# Argenis Carrillo Gonzalez
Full Stack Developer — Laravel / Inertia / React / TypeScript
Covilha, Portugal | +351 963 490 414 (WhatsApp) | argenis692@gmail.com | argenis.dev | linkedin.com/in/argenisdev692 | github.com/argenisdev692

## Professional Summary
Full Stack Developer with 4+ years shipping production Laravel systems remotely from Covilha, Portugal, delivering the full product with Inertia and either React or Vue on the front end. Built a 24-module Laravel 13 + Inertia v3 + Vue 3.5 operations platform with 100 test files, Horizon queues and Reverb live updates; shipped a 98/100 Lighthouse acquisition portal on React 19 that cut spam 97%. Delivered a Laravel Livewire booking platform handling about 25 clients per month. PostgreSQL and MySQL, Redis, Docker, GitHub Actions, OWASP hardening, PHPUnit. Contract AI-tooling trainer for Imagina Formacion (Spain).

## Technical Skills
**Backend:** PHP 8.4/8.5, Laravel 10-13, Eloquent, Horizon, Reverb, Sanctum, Jetstream, Fortify, Spatie Permission, Spatie Activity Log, Livewire 3, PHPUnit 12, Pest, Laravel Pint, REST APIs, OpenAPI (Scramble)
**Frontend:** Inertia.js v3, React 19, Vue.js 3.5 Composition API, TypeScript (strict), Pinia, Pinia Colada, TanStack Query, Tailwind CSS v4, Astro 5, Zod, HTML
**Data & Infra:** PostgreSQL 17, MySQL 8, Redis, pgvector, Docker, Laravel Sail, GitHub Actions, Vite, Cloudflare R2, Resend
**Integrations:** Google Calendar API, Google Meet, SumUp, Supabase, Stripe
**AI tooling:** prompt caching (Anthropic/OpenAI), LLM agent pipelines, FFmpeg, faster-whisper, Silero VAD, Python 3.12 / FastAPI

## Experience

### Full Stack Developer — Vidula (Independent pilot, single-tenant operations platform)
Remote, Covilha, Portugal | 05/2026 – 07/2026 | vidula.up.railway.app | github.com/argenisdev692/vidula
- Shipped 24-module pilot covering classroom management, scheduling, CRM and AI content, measured by 100 test files across 24 modules, by building a Laravel 13 + Inertia v3 + Vue 3.5 hexagonal monolith with PostgreSQL 17, Redis, Horizon and Reverb.
- Saved about 3 hours of manual editing per video, measured by before/after edit sessions on pilot recordings, by building an FFmpeg + FastAPI pipeline (faster-whisper, Silero VAD) that detects fillers, repeated takes and long silences and exports a 1080p/30fps master.
- Moved large uploads off the app server, measured by zero binary uploads through PHP during pilot, by issuing presigned Cloudflare R2 URLs and running renders on Horizon queues with Reverb live progress.
- Blocked regressions at merge time, measured by a four-stage GitHub Actions gate (Pint, PHPUnit on PostgreSQL 17, vue-tsc, Vite build), by enforcing strict-mode Eloquent with eager loading to prevent N+1 queries.
- Closed common auth and delivery gaps, measured by passing internal OWASP checklist (Argon2id, TOTP 2FA, nonce CSP, HSTS, Redis lockout), by hardening Fortify auth and documenting APIs with Scramble OpenAPI from typed controllers.

### Front-End Developer — AquaShield Restoration USA (Acquisition portal)
Remote | 03/2026 | aquashieldrestorationusa.com | github.com/argenisdev692/aquashield-web
- Hit 98/100 Lighthouse (FCP 0.8s, TTI 1.2s, CLS 0.001, 435KB total), measured by Lighthouse runs on production, by building Astro 5 static pages with React 19 islands, TypeScript and Tailwind CSS v4 on Supabase PostgreSQL.
- Cut spam submissions 97%, measured by form-log comparison before/after hardening, by adding seven OWASP 2025 layers (honeypot, per-IP rate limit, content analysis, Cloudflare Turnstile, user-agent checks, duplicate detection) with Zod validation, deployed to Cloudflare Workers via GitHub Actions.

### Full Stack Developer — Servispin, Appliance Repair (Booking + paid remote assistance)
Remote, Las Palmas de Gran Canaria, Spain | 12/2025 | servispin.net | github.com/argenisdev692/servispin
- Delivered home-visit booking plus paid video-assistance funnel serving about 25 clients per month, measured by admin calendar records, with Laravel 13, PHP 8.4, Livewire 3, Jetstream, Sanctum and MySQL.
- Added about 42 new clients in two months from a Meta Ads funnel, measured by booking records, by automating confirmations, rescheduling and cancellations with FullCalendar and Google Calendar API including holiday rules.
- Collected payment before video sessions, measured by manual SumUp reference checks clearing without lost paid bookings, by verifying SumUp QR payments, auto-creating Google Meet links via Spatie Google Calendar, and sending PDF/QR confirmations through Resend with a manual-fallback queue.
- Locked admin access down, measured by role-gated admin routes and throttled public forms in production, with Spatie Permission RBAC and request throttling.

### AI Trainer, Claude AI (Freelance) — Imagina Web & Mobile Technologies
Remote, Valencia, Spain | 01/2026 – Present
- Delivered "Claude AI for Users" (7h / 48 videos) on prompting, Projects, Research and Workspace governance, plus "Microsoft 365 Copilot for Users" (7h / 32 videos) and "Microsoft 365 Copilot for Administrative Tasks" (6h / 10 modules), measured by on-schedule delivery of recorded 1080p/30fps content for Spanish training platforms.
- Recorded "Cursor AI for Web Development" (6h / 12 videos) on AI-assisted coding workflows, measured by published course catalog entries.

### AI Coding-Tools Trainer for Web Developers (Freelance) — Imagina Web & Mobile Technologies
Remote | 2025
- Taught two live Zoom cohorts of 25 hours each: 30 students over 8 sessions on GitHub Copilot with .NET and Angular, and 25 students over 5 sessions on Tabnine with .NET and React, measured by attendance records.

### Full Stack Developer — Smart Finance 365 (MVP, Freelance)
Remote, Covilha, Portugal | 2024 | github.com/argenisdev692/smart-finance-365
- Shipped income/expense MVP covering transaction entry, categorization and balance reporting with Laravel and Livewire, measured by working demo delivered to the client.

### PHP Developer — Restoration Control (CRM maintenance and new modules)
Remote, USA | 2022 – 2024
- Maintained and extended a live vanilla PHP/MySQL CRM for two years, shipping new modules and landing pages against production, measured by continuous client sign-off; the same client later rebranded as AquaShield for the 2026 portal above.

## Projects
- Vidula — vidula.up.railway.app | github.com/argenisdev692/vidula — Laravel 13, Inertia v3, Vue 3.5, TypeScript, PostgreSQL 17, Redis, Horizon, Reverb, R2. 24-module hexagonal monolith, 100 test files, FFmpeg video pipeline, FastAPI transcription service.
- AquaShield Restoration — aquashieldrestorationusa.com | github.com/argenisdev692/aquashield-web — React 19, TypeScript, Tailwind v4, Astro 5, Supabase, Cloudflare Workers. 98 Lighthouse, 97% spam reduction, seven OWASP 2025 layers.
- Servispin — servispin.net | github.com/argenisdev692/servispin — Laravel 13, Livewire 3, Jetstream, Sanctum, MySQL. Booking plus paid remote-assistance funnel with SumUp QR and Meet links.
- Tutorial Cleanup API — github.com/argenisdev692/video-cleanup-api — Python 3.12, FastAPI, faster-whisper, Silero VAD, FFmpeg, R2. Bearer-token microservice behind Vidula: word-level transcription, filler/silence detection, clean master render.
- ArgenisHub — github.com/argenisdev692/argenis-hub (private) — Laravel 13, Inertia v3, Vue 3.5, TypeScript, PostgreSQL 17. 22-module hexagonal platform, 265 Pest test files, 402 routes; operations and AI-tooling base.

## Training Programs Delivered (Contract work)
All delivered under contract for Imagina Formacion, Spain. Recorded: Claude AI for Users (7h / 48 videos, 2026); Microsoft 365 Copilot for Users (7h / 32 videos, 2026); Microsoft 365 Copilot for Administrative Tasks (6h / 10 modules, 2026); Cursor AI for Web Development (6h / 12 videos, 2025). Live: Tabnine for .NET and React (25h, 25 students, 2025); GitHub Copilot for .NET and Angular (25h, 30 students, 2025).

## Education
Bachelor of Science in Computer Science — Universidad Bolivariana de Venezuela (UBV), Ciudad Bolivar, Venezuela | 09/2010 – 07/2017

## Languages
Spanish: Native | Portuguese: Resident (Covilha, Portugal) | English: B1 Intermediate
