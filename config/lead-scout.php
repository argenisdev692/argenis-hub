<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | LeadScout — spec 003-lead-scout (T006)
    |--------------------------------------------------------------------------
    | Deterministic scoring rules version. Bump when weights/thresholds change;
    | every score_result records the version it was computed with (plan §3.3).
    */
    'rules_version' => '2026.09.3',

    'scoring' => [
        // Lead Score = Σ(subscore × peso) / 100 (plan §3.3).
        'weights' => [
            'technical' => 20,
            'commercial' => 20,
            'recurrent' => 20,
            'vitality' => 15,
            'communication' => 10,
            'geo_contract' => 10,
            'remote' => 5,
        ],
        'tiers' => [
            'a_min_score' => 80,
            'a_min_confidence' => 70,
            'b_min_score' => 65,
            'b_min_confidence' => 50,
        ],
        'needs_research' => [
            'high_score_low_confidence' => ['score' => 80, 'confidence_below' => 70],
            'unknown_activity_min_score' => 65,
        ],
        // Inference weighs less than fact (spec US-4 CA-3).
        'inference_weight' => 0.6,
    ],

    /*
    |--------------------------------------------------------------------------
    | Skill watch list (spec US-1): adjacent skills that count as POTENTIAL
    | (require verification) when absent from the CV. Never score.
    */
    'skills' => [
        'watch_list' => ['aws', 'forge', 'vapor', 'pest', 'filament', 'symfony', 'nestjs', 'next.js'],
    ],
    'ingest' => [
        // Offers older than this never generate leads (spec US-2 CA-3).
        'max_offer_age_days' => 30,
        // Equivalent terms beyond the exact «Laravel» (spec FR-5).
        'expanded_terms' => ['php', 'full-stack php', 'vue + php', 'laravel', 'livewire', 'inertia'],
    ],

    // Job-source wiring (spec US-2, research R5). RSS feed URLs live here —
    // never hardcoded in adapters — so rotating a feed is an ops change.
    'job_sources' => [
        'rss_feeds' => [
            'LaraJobs' => env('LEAD_SCOUT_LARAJOBS_FEED', 'https://larajobs.example.com/feed'),
            'Remotive' => env('LEAD_SCOUT_REMOTIVE_FEED', 'https://remotive.example.com/feed'),
            'We Work Remotely' => env('LEAD_SCOUT_WWR_FEED', 'https://weworkremotely.example.com/feed'),
        ],
        'arbeitnow_url' => env('LEAD_SCOUT_ARBEITNOW_URL', 'https://www.arbeitnow.com/api/job-board-api'),
        'arbeitnow_max_pages' => 4,
    ],

    'fetching' => [
        // Fetched pages older than this are re-fetched (spec FR-12).
        'page_max_age_days' => 14,
        // Markdown older than this is pruned (content_hash + forms_summary kept).
        'markdown_retention_days' => 30,
        'max_pages_per_company' => 4,
        'direct_http_timeout_seconds' => 5,
        'direct_http_max_bytes' => 2_000_000,
        'user_agent' => 'LeadScoutBot/1.0 (+https://argenis.dev/agencies)',
        // Own Firecrawl v2 client (T039): never the shared /v1 adapter.
        'firecrawl_v2_url' => env('LEAD_SCOUT_FIRECRAWL_V2_URL', 'https://api.firecrawl.dev/v2'),
        'firecrawl_timeout' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Discovery waves (spec US-7, US-9 · plan §3.2.1)
    |--------------------------------------------------------------------------
    | Parallel by weight, not sequential. Order inside a wave is the initial
    | priority. Timezone overlap is computed at runtime from the IANA zone.
    */
    'discovery' => [
        'weights' => ['wave1' => 60, 'wave2' => 30, 'wave3' => 10],
        'search_cache_days' => 30,
        'queries_per_run' => 15,
        'countries' => [
            'wave1' => [
                ['iso' => 'PT', 'timezone' => 'Europe/Lisbon', 'language' => 'pt'],
                ['iso' => 'ES', 'timezone' => 'Europe/Madrid', 'language' => 'es'],
            ],
            'wave2' => [
                ['iso' => 'IE', 'timezone' => 'Europe/Dublin', 'language' => 'en'],
                ['iso' => 'GB', 'timezone' => 'Europe/London', 'language' => 'en'],
                ['iso' => 'NL', 'timezone' => 'Europe/Amsterdam', 'language' => 'en'],
                ['iso' => 'DE', 'timezone' => 'Europe/Berlin', 'language' => 'en'],
                ['iso' => 'BE', 'timezone' => 'Europe/Brussels', 'language' => 'en'],
                ['iso' => 'FR', 'timezone' => 'Europe/Paris', 'language' => 'en'],
                ['iso' => 'IT', 'timezone' => 'Europe/Rome', 'language' => 'en'],
                ['iso' => 'AT', 'timezone' => 'Europe/Vienna', 'language' => 'en'],
                ['iso' => 'DK', 'timezone' => 'Europe/Copenhagen', 'language' => 'en'],
                ['iso' => 'SE', 'timezone' => 'Europe/Stockholm', 'language' => 'en'],
                ['iso' => 'PL', 'timezone' => 'Europe/Warsaw', 'language' => 'en'],
                ['iso' => 'CZ', 'timezone' => 'Europe/Prague', 'language' => 'en'],
                ['iso' => 'GR', 'timezone' => 'Europe/Athens', 'language' => 'en'],
                ['iso' => 'LT', 'timezone' => 'Europe/Vilnius', 'language' => 'en'],
                ['iso' => 'LV', 'timezone' => 'Europe/Riga', 'language' => 'en'],
                ['iso' => 'EE', 'timezone' => 'Europe/Tallinn', 'language' => 'en'],
                ['iso' => 'RO', 'timezone' => 'Europe/Bucharest', 'language' => 'en'],
                ['iso' => 'FI', 'timezone' => 'Europe/Helsinki', 'language' => 'en'],
                ['iso' => 'CH', 'timezone' => 'Europe/Zurich', 'language' => 'en'],
                ['iso' => 'NO', 'timezone' => 'Europe/Oslo', 'language' => 'en'],
            ],
            'wave3' => [
                ['iso' => 'US', 'timezone' => 'America/New_York', 'language' => 'en'],
                ['iso' => 'CA', 'timezone' => 'America/Toronto', 'language' => 'en'],
                ['iso' => 'AR', 'timezone' => 'America/Argentina/Buenos_Aires', 'language' => 'es'],
                ['iso' => 'UY', 'timezone' => 'America/Montevideo', 'language' => 'es'],
                ['iso' => 'CL', 'timezone' => 'America/Santiago', 'language' => 'es'],
                ['iso' => 'CO', 'timezone' => 'America/Bogota', 'language' => 'es'],
                ['iso' => 'MX', 'timezone' => 'America/Mexico_City', 'language' => 'es'],
                ['iso' => 'UA', 'timezone' => 'Europe/Kyiv', 'language' => 'en'],
                ['iso' => 'AU', 'timezone' => 'Australia/Sydney', 'language' => 'en'],
                ['iso' => 'NZ', 'timezone' => 'Pacific/Auckland', 'language' => 'en'],
            ],
        ],
        // Query families per wave (service × technology × country × model).
        'families' => [
            'wave1' => [
                'agencia desarrollo Laravel {place}',
                'mantenimiento y soporte Laravel agencia',
                'subcontratación desarrollo Laravel marca blanca',
                'agência desenvolvimento Laravel {place}',
                'outsourcing Laravel white label Portugal',
            ],
            'wave2' => [
                'Laravel development agency {place}',
                'white label Laravel development partner Europe',
                'Laravel maintenance support agency',
                'remote-first Laravel agency Europe',
            ],
            'wave3' => [
                'white label Laravel agency remote contractors',
                'Laravel agency hiring remote contractors Europe timezone',
                'agencia desarrollo Laravel {place} mantenimiento',
            ],
        ],
    ],

    // Rejected BEFORE calling the provider (spec FR-13, clarify A17).
    'query_denylist' => ['linkedin', 'site:linkedin.com', 'xing', 'apollo', 'zoominfo', 'rocketreach', 'lusha', 'kaspr', 'hunter.io'],

    // Result domains discarded locally (second barrier after exclude_domains).
    'result_domain_denylist' => [
        'linkedin.com', 'xing.com', 'indeed.com', 'glassdoor.com', 'sortlist.com', 'clutch.co',
        'goodfirms.co', 'apollo.io', 'zoominfo.com', 'rocketreach.co', 'lusha.com', 'kaspr.io',
        'hunter.io', 'wikipedia.org',
    ],

    'vitality' => [
        'fresh_months' => 6,
        'recent_months' => 12,
        'stale_months' => 24,
    ],

    /*
    |--------------------------------------------------------------------------
    | Timezone overlap with Portugal (spec FR-37, plan §3.2.1)
    |--------------------------------------------------------------------------
    | Orientative overlap in hours for a 09:00-18:00 PT day; the discovery
    | chain computes the exact value from the IANA zone at runtime.
    */
    'geo' => [
        'overlap_hours' => [
            'PT' => 9, 'ES' => 8,
            'IE' => 9, 'GB' => 9, 'NL' => 8, 'DE' => 8, 'BE' => 8, 'FR' => 8,
            'IT' => 8, 'AT' => 8, 'DK' => 8, 'SE' => 8, 'PL' => 8, 'CZ' => 8,
            'GR' => 7, 'LT' => 7, 'LV' => 7, 'EE' => 7, 'RO' => 7, 'FI' => 7,
            'CH' => 8, 'NO' => 8, 'UA' => 7,
            'US' => 4, 'CA' => 4,
            'AR' => 5, 'UY' => 5, 'CL' => 4, 'CO' => 3, 'MX' => 2,
            'AU' => 0, 'NZ' => 0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Contact rules per country (spec FR-44, clarify A18)
    |--------------------------------------------------------------------------
    | ALL start `pending_verification`. Blocking rules apply even while
    | pending; allowing rules apply ONLY once `verified`. Countries without a
    | rule here (waves 2-3 until OP-26) never get cold email.
    */
    'contact_rules' => [
        ['country' => 'ES', 'medium' => 'email', 'mailbox' => 'nominative', 'decision' => 'block', 'legal_status' => 'pending_verification', 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2002-13758', 'reason' => 'LSSI art. 21: consentimiento previo, incluso B2B.'],
        ['country' => 'ES', 'medium' => 'email', 'mailbox' => 'generic', 'decision' => 'block', 'legal_status' => 'pending_verification', 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2002-13758', 'reason' => 'LSSI art. 21: sin email comercial no solicitado.'],
        ['country' => 'PT', 'medium' => 'email', 'mailbox' => 'nominative', 'decision' => 'block', 'legal_status' => 'pending_verification', 'source_url' => null, 'reason' => 'Zona gris (Lei 41/2004 art. 13-A + CNPD Diretriz/2022/1): no recomendado en frío.'],
        ['country' => 'PT', 'medium' => 'email', 'mailbox' => 'generic', 'decision' => 'allow', 'legal_status' => 'pending_verification', 'source_url' => null, 'reason' => 'Opt-out con baja, solo verificado y fuera de la lista DGC vigente.'],
    ],

    // Outreach sender mailbox kind (spec FR-41, clarify A19).
    'sender_kind_default' => 'personal_mailbox',

    // Art. 14 notice + opt-out line (spec FR-26). Real addresses; the
    // template inserts them and they cannot be removed.
    'privacy' => [
        'contact_email' => env('LEAD_SCOUT_PRIVACY_EMAIL', 'privacidad@argenis.dev'),
        'policy_url' => env('LEAD_SCOUT_PRIVACY_URL', 'https://argenis.dev/privacy#prospeccion'),
    ],

    // Versioned draft templates (spec FR-41: template_key + template_version).
    'draft_templates' => [
        'vacancy_reply' => ['version' => 1],
        'stack_match' => ['version' => 1],
        'legacy_maintenance' => ['version' => 1],
        'sector_match' => ['version' => 1],
    ],

    'budgets' => [
        'search' => ['limit_eur' => (float) env('LEAD_SCOUT_SEARCH_BUDGET_EUR', 8)],
        'extraction' => ['limit_eur' => (float) env('LEAD_SCOUT_EXTRACTION_BUDGET_EUR', 15)],
        'ai' => ['limit_eur' => (float) env('LEAD_SCOUT_AI_BUDGET_EUR', 7)],
    ],

    /*
    |--------------------------------------------------------------------------
    | Estimated paid-call costs in EUR (spec US-8, T040)
    |--------------------------------------------------------------------------
    | Provider billing is credit-based; these are CONSERVATIVE estimates so
    | the ledger stops the pipeline before real money moves. Tune as real
    | invoices arrive — changing them is an ops change, not a deploy.
    */
    'costs' => [
        'tavily_basic_eur' => 0.005,
        'tavily_advanced_eur' => 0.01,
        'firecrawl_scrape_eur' => 0.01,
    ],

    /*
    |--------------------------------------------------------------------------
    | Closed AI catalog (spec US-10, FR-22). IDs pinned (LLM03). Frontend never
    | sends free text — Rule::in against these keys. Costs read here so the
    | selector shows the estimate per 100 uses; `price_valid_until` warns.
    */
    'ai_catalog' => [
        'gemini-3.7-flash' => [
            'provider' => 'gemini',
            'label' => 'Gemini 3.7 Flash',
            'input_per_mtok_usd' => 0.75,
            'output_per_mtok_usd' => 3.75,
            'price_valid_until' => '2026-12-31',
        ],
        'claude-sonnet-5' => [
            'provider' => 'anthropic',
            'label' => 'Claude Sonnet 5',
            'input_per_mtok_usd' => 2.0,
            'output_per_mtok_usd' => 10.0,
            'price_valid_until' => null,
        ],
    ],

    'ai_defaults' => [
        'extraction' => [
            'provider' => env('LEAD_SCOUT_AI_EXTRACTION_PROVIDER', 'gemini'),
            'model' => env('LEAD_SCOUT_AI_EXTRACTION_MODEL', 'gemini-3.7-flash'),
            'fallback_provider' => env('LEAD_SCOUT_AI_EXTRACTION_FALLBACK_PROVIDER', 'anthropic'),
            'fallback_model' => env('LEAD_SCOUT_AI_EXTRACTION_FALLBACK_MODEL', 'claude-sonnet-5'),
        ],
        'drafting' => [
            'provider' => env('LEAD_SCOUT_AI_DRAFTING_PROVIDER', 'anthropic'),
            'model' => env('LEAD_SCOUT_AI_DRAFTING_MODEL', 'claude-sonnet-5'),
            'fallback_provider' => env('LEAD_SCOUT_AI_DRAFTING_FALLBACK_PROVIDER', 'gemini'),
            'fallback_model' => env('LEAD_SCOUT_AI_DRAFTING_FALLBACK_MODEL', 'gemini-3.7-flash'),
        ],
    ],

    // Default decision rule (spec US-6, clarify Q9). Locked before measuring.
    'decision_rule_defaults' => [
        'sample_size' => 150,
        'window_days' => 56,
        'thresholds' => ['scale_at' => 0.05, 'stop_below' => 0.01],
    ],

    'rate_limits' => [
        'llm' => 10,
        'export' => 10,
        'list' => 60,
    ],

    'cache_ttl_minutes' => 15,
];
