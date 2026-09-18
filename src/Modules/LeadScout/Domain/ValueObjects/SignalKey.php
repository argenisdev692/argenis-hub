<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\ValueObjects;

/**
 * Closed signal vocabulary (spec FR-10): the LLM may only emit these keys
 * (verified literally in T056), and the rule extractor only emits these.
 * One key aggregates to at most one reason per score run.
 */
final readonly class SignalKey
{
    /**
     * @var list<string>
     */
    public const array ALLOWED = [
        // Technical.
        'laravel', 'php_plain', 'vue_inertia', 'livewire', 'stack_db', 'api_ai', 'unconfirmed_tech',
        // Commercial.
        'freelance_contract', 'accepts_external', 'agency_type', 'consultancy_type',
        'product_type', 'recruiter', 'large_outsourcer', 'multi_vacancies', 'fixed_job', 'low_prices',
        // Recurrent.
        'staff_augmentation', 'maintenance_sla', 'long_term', 'many_cases', 'long_clients',
        'active_vacancy', 'one_off',
        // Vitality & size.
        'recent_content', 'sitemap_fresh', 'vacancy_vitality', 'copyright_recent',
        'team_5_50', 'team_51_200', 'team_over_200', 'team_2_4', 'team_unknown',
        'stale_content', 'old_sitemap', 'old_copyright', 'dead_web', 'solo_freelancer',
        // Communication (B1).
        'lang_es_pt', 'async_english', 'english_unknown', 'english_fluent_required',
        // Geo / contract.
        'country_pt_es', 'country_eu', 'country_uk_ie', 'country_us_ca', 'country_other',
        'accepts_eu_contractors', 'overlap_ok', 'overlap_low', 'local_contract_required',
        // Remote.
        'remote', 'hybrid', 'onsite', 'remote_unknown',
    ];

    public static function allowed(string $key): bool
    {
        return in_array($key, self::ALLOWED, true);
    }
}
