<?php

declare(strict_types=1);

/*
| CV ATS Job Studio — rules version 2 (fullstack profile defaults).
|
| Every weight, cap, band and sub-score table the scorers read lives here
| (SC-11). No scoring literal may live in a Domain/Services class — the
| arch test asserts it. The seeder writes this array into
| `studio_profiles.rules`, so a second profile is data, never schema (SC-7).
*/
return [
    'rules_version' => 2,

    'blend' => [
        'h' => 0.45,
        's' => 0.25,
        'd' => 0.30,
    ],

    'tag_weights' => [
        'required' => 1.0,
        'preferred' => 0.6,
        'bonus' => 0.25,
    ],

    // Soft requirements may contribute at most 15% of the denominator.
    'soft_cap_ratio' => 0.15 / 0.85,

    // Skill evidenced only in the skills list (not inside a bullet).
    'context_list_factor' => 0.85,

    // First evidence below the top third of rendered order.
    'position_low_factor' => 0.90,

    // Cap ladder: lowest applicable cap wins. `null` reason means no cap.
    'caps' => [
        ['max' => 55, 'reason' => 'unreadable_requirements'],
        ['max' => 65, 'reason' => 'credential_or_floor_or_language'],
        ['max' => 69, 'reason' => 'weak_evidence'],
    ],

    'bands' => [
        ['min' => 85, 'band' => 'strong'],
        ['min' => 75, 'band' => 'good'],
        ['min' => 70, 'band' => 'apply'],
        ['min' => 60, 'band' => 'consider'],
        ['min' => 0, 'band' => 'skip'],
    ],

    // Shortlist: D_disc = 0.35P + 0.25K + 0.20R + 0.10F + 0.10N.
    'shortlist' => [
        'weights' => ['p' => 0.35, 'k' => 0.25, 'r' => 0.20, 'f' => 0.10, 'n' => 0.10],
        'threshold' => 0.55,
        'top_n' => 8,
    ],

    // Semantic calibration: norm(x) = clamp((x - floor) / (ceil - floor)).
    // Provisional until the held-out calibration (T-094, OD-1).
    'semantic' => [
        'floor' => 0.30,
        'ceil' => 0.85,
        'title_weight' => 0.4,
        'responsibility_weight' => 0.6,
    ],

    // Deterministic sub-scores: experience / location / education / language.
    'deterministic' => [
        'weights' => ['experience' => 40, 'location' => 30, 'education' => 15, 'language' => 15],
    ],

    /*
    | Opportunity policy (Q19 option b — mild directional). Every value is a
    | *policy*, never a statistic: grade + source travel with each factor
    | (FR-51, NFR-14). Own outcomes replace a value only past the evidence
    | gate (30 applications, 3 positives).
    */
    'opportunity' => [
        'evidence_gate' => ['min_applications' => 30, 'min_positives' => 3],
        'channel' => [
            'employer_site' => ['value' => 1.0, 'grade' => 'C', 'source' => 'policy(Q19b)'],
            'employer_ats' => ['value' => 1.0, 'grade' => 'C', 'source' => 'policy(Q19b)'],
            'board' => ['value' => 0.9, 'grade' => 'C', 'source' => 'policy(Q19b)'],
            'aggregator' => ['value' => 0.8, 'grade' => 'C', 'source' => 'policy(Q19b)'],
            'agency' => ['value' => 0.8, 'grade' => 'C', 'source' => 'policy(Q19b)'],
            'social' => ['value' => 0.7, 'grade' => 'C', 'source' => 'policy(Q19b)'],
            'unknown' => ['value' => 0.8, 'grade' => 'C', 'source' => 'policy(Q19b)'],
        ],
        'unknown_age_penalty' => 0.9,
        'freshness_half_life_days' => 14.0,
        'freshness_floor' => 0.5,
        'seniority_below_band' => 0.9,
        'seniority_above_band' => 0.7,
        'ghost_per_signal' => 0.1,
        'ghost_floor' => 0.5,
    ],

    // Technical terms that must stay in English in every language variant.
    'untranslatable_terms' => [
        'Laravel', 'Vue.js', 'PHP', 'PostgreSQL', 'Redis', 'Docker',
        'REST', 'API', 'CI/CD', 'GitHub Actions', 'TypeScript', 'Node.js',
    ],

    // Postings unseen for this many days expire via harvest absence (T-016).
    'expiry_days' => 14,

    // Hosts the module must never request (FR-50, SC-16). Fetcher + ladder +
    // tier-3 resolution exclude these; the arch test asserts it.
    'never_fetch_hosts' => [
        'linkedin.com', 'indeed.com', 'glassdoor.com', 'tecnoempleo.com',
        'infojobs.net', 'justjoin.it', 'remoterocketship.com', 'jobleads.com', 'jobgether.com',
    ],

    'access_modes' => ['api_feed', 'search_scoped', 'sitemap', 'link_only', 'resolve_only'],

    /*
    | Monthly spend ceilings per user and category (FR-33, SC-8, LLM10). The
    | ledger provisions the period row from these on first use, so a new
    | user is budgeted, never silently refused. `llm_call_estimate_eur` is a
    | CONSERVATIVE flat per-call charge until T-154 fills measured pricing —
    | it keeps the LLM budget binding instead of never moving.
    */
    'budgets' => [
        'llm' => ['limit_eur' => (float) env('CV_STUDIO_LLM_BUDGET_EUR', 5)],
        'search' => ['limit_eur' => (float) env('CV_STUDIO_SEARCH_BUDGET_EUR', 5)],
        'extraction' => ['limit_eur' => (float) env('CV_STUDIO_EXTRACTION_BUDGET_EUR', 5)],
    ],

    'llm_call_estimate_eur' => (float) env('CV_STUDIO_LLM_CALL_ESTIMATE_EUR', 0.02),

    // Conservative per-scrape estimate (same figure as LeadScout's
    // `costs.firecrawl_scrape_eur`); direct HTTP is free.
    'firecrawl_scrape_eur' => (float) env('CV_STUDIO_FIRECRAWL_SCRAPE_EUR', 0.01),
];
