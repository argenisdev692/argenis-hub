<!-- Formato ATS: Calibri corpo 11 pt · cabeçalhos 13 pt negrito · nome 20 pt · uma coluna · máx. 2 páginas -->

# **Argenis Carrillo Gonzalez**

Programador Full Stack  |  PHP · Laravel · Vue.js · Inertia.js · TypeScript

Covilhã, Portugal   |   WhatsApp (+351) 963 490 414   |   argenis692@gmail.com   |   argenis.dev   |   linkedin.com/in/argenisdev692   |   github.com/argenisdev692

Redes: instagram.com/argenis.dev   |   tiktok.com/@argenisdev692   |   facebook.com/argenisdev692

## **RESUMO PROFISSIONAL**

**Programador Full Stack PHP / Laravel com mais de 4 anos** a entregar CRMs, plataformas de marcações e sites de angariação em produção, **em regime remoto a partir da Covilhã** (~**8** landing pages e CRMs de cliente entregues de ponta a ponta). Trabalho com **Laravel**, **Inertia.js**, **Vue.js 3**, **Livewire**, **APIs REST**, **PostgreSQL** / **MySQL**, **Redis**, **Docker** e **CI/CD** sobre **GitHub Actions**. A minha experiência vai de um CRM legado em **PHP puro**, mantido e alargado durante dois anos, até uma base de código de **24 módulos com Laravel 13 + Inertia + Vue 3**, com arquitetura hexagonal, workers de filas e difusão por WebSockets. Assumo uma funcionalidade do esquema de base de dados até ao deploy, com hardening **OWASP** e testes automatizados como parte do critério de concluído. Ministro também formação por contrato sobre ferramentas de IA: Claude AI, Microsoft 365 Copilot e GitHub Copilot.

## **COMPETÊNCIAS TÉCNICAS**

**Core (ATS): PHP 8.x** · **Laravel 10-13** · **Vue.js 3** · **Inertia.js** · **Livewire** · **TypeScript** · **APIs REST** · **PostgreSQL** · **MySQL** · **Redis** · **Docker** · **CI/CD** · **GitHub Actions**

**Back-end:** Eloquent ORM · Filas e workers assíncronos (Laravel Horizon) · WebSockets e broadcasting (Reverb) · Sanctum · Jetstream · Fortify · Spatie Permission (RBAC) · Spatie Activity Log · OpenAPI / documentação de API · Python (FastAPI)

**Front-end:** Vue 3 (Composition API, script setup) · Pinia · React 19 · Tailwind CSS · Astro · Zod

**Bases de dados e desempenho:** PostgreSQL 17 · MySQL 8 · Cache e sessões com Redis · Prevenção de N+1 e disciplina de eager loading · Otimização de consultas

**Cloud e DevOps:** Docker Compose / Laravel Sail · GitHub Actions · Cloudflare Workers / R2 · Railway · VPS · Laravel Pint · **PHPUnit** · OWASP

**Integrações e IA:** Google Calendar / OAuth · Google Meet · Resend · SumUp · DocuSign · Google Maps · Supabase · laravel/ai · Claude AI · GitHub Copilot

## **EXPERIÊNCIA PROFISSIONAL**

### **Programador Full Stack - Vidula (MVP próprio)**

**Projeto independente** _|   Remoto a partir da Covilhã, Portugal   |   Mai 2026 - Jul 2026_

- **Desenvolvi** um MVP de operações com 24 módulos para a minha própria atividade de formação (gestão de turmas, marcações, CRM, conteúdo com IA, produção de vídeo), aplicando arquitetura hexagonal (portas e adaptadores, DDD, CQRS ligeiro) em todos os módulos.

- **Implementei** a stack completa: **Laravel 13**, **Inertia.js v3**, **Vue 3.5** com **TypeScript** estrito, **PostgreSQL 17**, **Redis**, Horizon, Reverb, laravel/ai e Cloudflare R2.

- **Reduzi cerca de 3 horas de edição manual por vídeo** com um pipeline automatizado (**FFmpeg** mais um microserviço em **Python/FastAPI** com faster-whisper e Silero VAD) que deteta bordões, repetições de takes e silêncios longos, exportando depois um master em **1080p/30fps**.

- **Retirei os carregamentos de vídeo pesados do servidor aplicacional** através de URLs de upload pré-assinados para a Cloudflare R2, com workers de fila geridos pelo **Horizon** a executar os trabalhos de renderização e o **Reverb** a transmitir o progresso em tempo real para o browser.

- **Bloqueei cada merge** atrás de um pipeline de **GitHub Actions** com Laravel Pint, **PHPUnit** contra um contentor de serviço **PostgreSQL 17**, verificação de tipos com vue-tsc e build de produção com Vite, atingindo **96 ficheiros de teste em 24 módulos**.

- **Impus disciplina nas consultas** em todo o projeto com o modo estrito do Eloquent e eager loading explícito, de forma a que as consultas N+1 falhem de modo visível em desenvolvimento em vez de chegarem a produção.

- **Reforcei** a autenticação e a entrega segundo práticas OWASP: hash com Argon2id, dupla autenticação TOTP, CSP baseada em nonce, HSTS e bloqueio por força bruta suportado em Redis.

- **Gerei documentação OpenAPI** automaticamente a partir de controladores e form requests tipados, para que a documentação da API nunca fique dessincronizada do código.

### **Programador Front-end - Landing page corporativa**

**AquaShield Restoration USA** _|   Houston, EUA - Remoto   |   Mar 2026_

- **Construí** um portal público de angariação (registo de leads e marcação de inspeção gratuita) com **Astro 5**, **React 19**, **TypeScript**, **Tailwind CSS v4** e **Supabase** (PostgreSQL).

- **Atingi Lighthouse 98/100** (FCP 0,8 s, TTI 1,2 s, CLS 0,001, bundle total de 435 KB) ao servir HTML estático por omissão e hidratar apenas as ilhas interativas.

- **Reduzi o spam nos formulários em 97%** com sete camadas de defesa OWASP 2025: campos honeypot, limitação de pedidos por IP, análise de conteúdo, Cloudflare Turnstile, verificação de user-agent e deteção de duplicados, com validação de esquemas através de **Zod** em cada endpoint.

- **Coloquei em produção** na **Cloudflare Workers** com **CI/CD** no **GitHub Actions**.

### **Formador de IA - Claude AI (Freelance)**

**Imagina Web & Mobile Technologies** _|   Valência, Espanha - Remoto   |   Jan 2026 - Atualidade_

- **Concebi e ministrei** "**Claude AI** para Utilizadores": **7 horas / 48 vídeos** sobre prompting profissional, Projects, Research, conectores do Workspace e governação corporativa.

- **Gravei** "**Microsoft 365 Copilot** para Utilizadores": **7 horas / 32 vídeos** de vídeo de formato curto para a mesma plataforma.

- **Criei** "**Microsoft 365 Copilot** para Tarefas Administrativas": **6 horas / 10 módulos** sobre Word, Excel, PowerPoint, Outlook, Teams, Planner e SharePoint.

- **Produzi** conteúdo e-learning e pílulas de vídeo em 1080p/30fps por contrato, entregues dentro do prazo para plataformas de formação espanholas.

### **Programador Full Stack - Plataforma de marcações e assistência remota**

**Servispin (Reparação de eletrodomésticos)** _|   Las Palmas de Gran Canaria, Espanha - Remoto   |   Dez 2025_

- **Entreguei** uma plataforma de marcações para reparações ao domicílio e um funil de videoassistência remota paga (**Laravel 13**, **PHP 8.4**, **Livewire 3**, **Jetstream**, **Sanctum**, **MySQL**), com uma média de ~**25 clientes/mês**.

- **Suportei um funil de Meta Ads que trouxe ~42 novos clientes em dois meses** ao automatizar confirmações, remarcações e cancelamentos com **FullCalendar** e a **API do Google Calendar**, incluindo regras de disponibilidade para dias não úteis e feriados.

- **Automatizei** as sessões remotas pagas de ponta a ponta: verificação de pagamento por QR SumUp, criação automática da ligação Google Meet, confirmações em PDF/QR e correio transacional com Resend.

- **Evitei a perda de marcações já pagas** quando o Google Calendar não devolve a ligação Meet, confirmando a marcação na mesma e encaminhando-a para uma caixa de administração para seguimento manual.

- **Protegi** o painel de administração com controlo de acessos por papéis do **Spatie Permission** e limitei a frequência de submissão dos formulários públicos contra abusos.

### **Formador de ferramentas de IA para Programadores Web (Freelance)**

**Imagina Web & Mobile Technologies** _|   Online - Remoto   |   Nov 2025 - Dez 2025_

- **Lecionei** um curso ao vivo por **Zoom** a **30 formandos** em **8 sessões / 25 horas**, sobre **GitHub Copilot** aplicado ao desenvolvimento web com **.NET** e **Angular**.

### **Programador Full Stack - Smart Finance 365 (MVP)**

**Freelance** _|   Remoto a partir da Covilhã, Portugal   |   2024_

- **Construí** um MVP de gestão de receitas e despesas com **Laravel** e **Livewire**, cobrindo registo de movimentos, categorização e relatórios de saldo.

### **Programador PHP - Manutenção e novos módulos de CRM**

**Restoration Control (restorationcontrol.com)** _|   EUA - Remoto   |   2022 - 2024_

- **Mantive e alarguei** durante dois anos um CRM em produção escrito em **PHP puro** (sem framework), acrescentando novos módulos sobre uma base de código legada e sem a reescrever.

- **Entreguei** novos módulos de CRM e landing pages sobre um sistema **PHP / MySQL** em produção, definindo cada alteração diretamente com o cliente norte-americano.

- **Mantive a plataforma operacional** através de manutenção e correções contínuas; o mesmo cliente passou depois a operar como AquaShield, o que deu origem ao trabalho de landing page de 2026 descrito acima.

## **FORMAÇÃO ACADÉMICA**

### **Licenciatura em Informática (Engenharia em Ciências da Computação)**

_Universidad Bolivariana de Venezuela (UBV)  |  Ciudad Bolívar, Venezuela  |  Set 2010 - Jul 2017_

## **FORMAÇÃO MINISTRADA (POR CONTRATO)**

- "**Cursor AI** Desenvolvimento Web" - Imagina Formación, Espanha (2025) - curso gravado, 6 h / 12 vídeos

- "**Claude AI** para Utilizadores" - Imagina Formación, Espanha (2026) - curso gravado, 7 h / 48 vídeos

- "**Microsoft 365 Copilot** para Utilizadores" - Imagina Formación, Espanha (2026) - curso gravado, 7 h / 32 vídeos

- "**Microsoft 365 Copilot** para Tarefas Administrativas" - Imagina Formación, Espanha (2026) - curso gravado, 6 h / 10 módulos

- "**Tabnine** para Programadores Web (**.NET** e **React**)" - Imagina Formación, Espanha (2025) - curso ao vivo, 25 h / 5 sessões, 25 formandos

- "**GitHub Copilot** para Programadores Web (**.NET** e **Angular**)" - Imagina Formación, Espanha (2025) - curso ao vivo, 25 h / 8 sessões, 30 formandos

## **PROJETOS**

**Vidula - MVP de operações para turmas e produção de vídeo** _|   vidula.up.railway.app  |  github.com/argenisdev692/vidula_

**Laravel 13** · **Inertia.js v3** · **Vue 3.5** · **TypeScript** estrito · **PostgreSQL 17** · **Redis** · Horizon · Reverb · Cloudflare R2. Monólito de 24 módulos sobre arquitetura hexagonal: CRM, marcações com sincronização do Google Calendar, geração de conteúdo com IA, pipeline de exportação de vídeo com FFmpeg, administração com RBAC e microserviço de transcrição em FastAPI. **96 ficheiros de teste em PHPUnit** atrás de um pipeline de **GitHub Actions** de quatro etapas (Pint, PHPUnit sobre PostgreSQL 17, vue-tsc, build de Vite), com prevenção de N+1 imposta em todo o projeto.

**AquaShield Restoration - Landing page de angariação de leads** _|   aquashieldrestorationusa.com  |  github.com/argenisdev692/aquashield-web_

**Astro 5** · **React 19** · **TypeScript** · **Tailwind CSS v4** · **Supabase** · **Cloudflare Workers**. **Lighthouse 98/100** com um bundle total de 435 KB, **97% menos submissões de spam** através de sete camadas de defesa OWASP 2025, e angariação automatizada de leads com validação Zod e entrega via Resend.

**Servispin - Marcações e assistência remota paga** _|   servispin.net  |  github.com/argenisdev692/servispin_

**Laravel 13** · **Livewire 3** · **Jetstream** · **Sanctum** · **MySQL**. Marcação de visitas ao domicílio e funil de assistência remota paga (verificação por QR SumUp, ligações automáticas do Google Meet), confirmações em PDF/QR, regras de disponibilidade que contemplam feriados e painel de administração com RBAC. Média de ~**25 clientes/mês**.

**Tutorial Cleanup API - Microserviço de processamento de vídeo** _|   github.com/argenisdev692/video-cleanup-api_

**Python 3.12** · **FastAPI** · **Docker** · faster-whisper · Silero VAD · **FFmpeg** · Cloudflare R2. O serviço por detrás do pipeline de vídeo do Vidula: transcreve com marcas temporais ao nível da palavra, deteta bordões, repetições e silêncios através de deteção de atividade de voz, aplica o plano de cortes e devolve um master limpo. Autenticação por token Bearer, em contentor e com arquitetura de serviços em camadas.

## **IDIOMAS**

**Espanhol:** Nativo  **Português:** Nível de residente (Covilhã, Portugal)  **Inglês:** B1 Intermédio
