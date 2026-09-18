# Cover letter — REIZ TECH UAB

**Role:** Senior PHP Developer (Laravel or Symfony) — fully remote, Worldwide
**Company:** REIZ TECH UAB (Lithuania) · fintech product
**Posting:** https://remotive.com/remote/jobs/software-development/senior-php-developer-5575745
**Apply via:** reiztech.recruitee.com
**Draft date:** 2026-08-25 · *draft for human review — not sent*

---

Hello,

I am writing about the Senior PHP Developer role. I build and maintain Laravel systems remotely from Portugal, and three parts of your requirement list match what I actually do day to day.

**Architecture and code quality.** You ask for SOLID, design patterns and clean architecture. My most recent project, Vidula, is a 24-module Laravel 13 codebase built on ports and adapters with DDD and light CQRS: the domain layer imports no Eloquent, no HTTP and no third-party SDK. It has 96 PHPUnit test files, and every merge is gated by GitHub Actions running Pint, the full test suite against a PostgreSQL 17 service container, type checking and a production build.

**Performance and databases.** Strict-mode Eloquent and explicit eager loading are enforced across the whole project, so N+1 queries fail loudly in development instead of reaching production. Redis backs cache, sessions and queues, with Horizon supervising long-running jobs.

**Security.** Argon2id hashing, TOTP two-factor, nonce-based CSP, HSTS and a Redis-backed brute-force lockout — built to OWASP practice, which matters more in fintech than anywhere else.

Two things you should know before we talk. I have four-plus years with PHP and Laravel, not the five you list, and I have not worked in fintech before. My English is B1: I read technical documentation and work in written English without difficulty, but I am not fluent in spoken English.

Code for the projects above is public on my GitHub.

Best regards,
**Argenis Carrillo Gonzalez**
argenis692@gmail.com · (+351) 963 490 414 · argenis.dev · github.com/argenisdev692

---

*Body: ~250 words. To shorten, cut the Security paragraph — it is the one whose evidence is already visible in the CV skills line.*

*Honesty note: this posting asks for "strong written communication skills in async environments" and quotes €5,500–7,000/month, a senior fintech band. The years gap and the English level are stated plainly above rather than left to be discovered. If you would rather not raise them first, delete that paragraph — but do not replace it with a claim of five years.*
