<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\LeadScout\Domain\Enums\SignalNature;

/**
 * Deterministic scoring (plan §3.3, `rules_version = 2026.09.3`): stored
 * signals + profile + required techs → 7 subscores, Lead Score, evidence
 * confidence and point-by-point reasons. Same input always yields the same
 * output (spec US-4 CA-4). Inferences weigh ×0.6 (US-4 CA-3).
 *
 * Pure: plain arrays in, scored arrays out — persistence lives in the
 * handler (layer rule).
 */
final readonly class ScoringEngine
{
    /**
     * @param  array<int, array{signal_key: string, dimension: string, nature: string, confidence: int, evidence_url: ?string, captured_at: string, value_text: ?string}>  $signals
     * @param  list<string>  $confirmedSkills
     * @param  list<string>  $requiredTechs
     * @param  array{weights: array<string, int>, inference_weight: float, overlap_hours?: array<string, int|float>}  $rules  `overlap_hours`: working-hours overlap with Portugal by country
     * @return array{subscores: array<string, int>, leadScore: int, confidence: int, reasons: list<array{signal_key: string, points: int, explanation: string}>, flags: array{solo_freelancer: bool, dead_or_absorbed: bool, inactive_agency: bool, outsourcer_large: bool, remote_zero: bool}}
     */
    #[\NoDiscard]
    public function score(
        array $signals,
        array $confirmedSkills,
        array $requiredTechs,
        array $rules,
        ?string $country,
        ?CarbonImmutable $now = null,
    ): array {
        $now ??= CarbonImmutable::now();
        $facts = [];
        $inferences = [];

        foreach ($signals as $signal) {
            if (($signal['nature'] ?? SignalNature::Fact->value) === SignalNature::Fact->value) {
                $facts[$signal['signal_key']][] = $signal;
            } else {
                $inferences[$signal['signal_key']][] = $signal;
            }
        }

        $weight = static function (array $group) use ($rules): float {
            $factor = $rules['inference_weight'] ?? 0.6;

            return $group === [] ? 0.0 : $factor;
        };

        $reasons = [];
        $subscores = [];

        $subscores['technical'] = $this->technical($facts, $inferences, $confirmedSkills, $requiredTechs, $weight, $reasons);
        $subscores['commercial'] = $this->commercial($facts, $inferences, $weight, $reasons);
        $subscores['recurrent'] = $this->recurrent($facts, $inferences, $weight, $reasons);
        $subscores['vitality'] = $this->vitality($facts, $inferences, $weight, $now, $reasons);
        $subscores['communication'] = $this->communication($facts, $inferences, $weight, $reasons);
        $overlap = $country === null || ! isset($rules['overlap_hours'][$country]) ? null : (float) $rules['overlap_hours'][$country];
        $subscores['geo_contract'] = $this->geoContract($facts, $inferences, $weight, $overlap, $reasons);
        $subscores['remote'] = $this->remote($facts, $inferences, $weight, $reasons);

        $weights = $rules['weights'];
        $lead = 0.0;

        foreach ($subscores as $dimension => $subscore) {
            $lead += $subscore * ($weights[$dimension] ?? 0) / 100;
        }

        $flags = [
            'solo_freelancer' => isset($facts['solo_freelancer']),
            'dead_or_absorbed' => isset($facts['dead_web']),
            'inactive_agency' => isset($facts['stale_content']) && isset($facts['old_copyright']) && ! isset($facts['recent_content']),
            'outsourcer_large' => isset($facts['team_over_200']) || isset($facts['large_outsourcer']),
            'remote_zero' => $subscores['remote'] === 0,
        ];

        return [
            'subscores' => $subscores,
            'leadScore' => (int) round($lead),
            'confidence' => $this->confidence($signals, $rules),
            'reasons' => $reasons,
            'flags' => $flags,
        ];
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $facts
     * @param  array<string, array<int, array<string, mixed>>>  $inferences
     * @param  list<string>  $confirmedSkills
     * @param  list<string>  $requiredTechs
     * @param  callable(array): float  $weight
     * @param  list<array{signal_key: string, points: int, explanation: string}>  $reasons
     */
    private function technical(array $facts, array $inferences, array $confirmedSkills, array $requiredTechs, callable $weight, array &$reasons): int
    {
        $total = 0;
        $total += $this->scoreKey('laravel', 40, 'Laravel detectado', $facts, $inferences, $weight, $reasons);
        $hasLaravel = isset($facts['laravel']) || isset($inferences['laravel']);

        if (! $hasLaravel) {
            $total += $this->scoreKey('php_plain', 20, 'PHP sin Laravel', $facts, $inferences, $weight, $reasons);
        }

        $frontend = 0;
        $frontend += $this->keyPoints('vue_inertia', 20, $facts, $inferences, $weight);
        $frontend += $this->keyPoints('livewire', 15, $facts, $inferences, $weight);
        $frontend = min(20, $frontend);

        if ($frontend > 0) {
            $total += $frontend;
            $reasons[] = ['signal_key' => isset($facts['vue_inertia']) || isset($inferences['vue_inertia']) ? 'vue_inertia' : 'livewire', 'points' => $frontend, 'explanation' => "Frontend (tope +20): {$frontend}"];
        }

        $stack = min(3, count($facts['stack_db'] ?? []) + count($inferences['stack_db'] ?? [])) * 5;

        if ($stack > 0) {
            $total += $stack;
            $reasons[] = ['signal_key' => 'stack_db', 'points' => $stack, 'explanation' => "Stack (tope +15): {$stack}"];
        }

        $api = min(2, count($facts['api_ai'] ?? []) + count($inferences['api_ai'] ?? [])) * 5;

        if ($api > 0) {
            $total += $api;
            $reasons[] = ['signal_key' => 'api_ai', 'points' => $api, 'explanation' => "APIs/integraciones (tope +10): {$api}"];
        }

        $missing = array_values(array_diff(
            array_map(strtolower(...), $requiredTechs),
            array_map(strtolower(...), $confirmedSkills),
        ));

        if ($missing !== []) {
            $penalty = -min(30, count($missing) * 10);
            $total += $penalty;
            $reasons[] = ['signal_key' => 'unconfirmed_tech', 'points' => $penalty, 'explanation' => 'Exigidas y no confirmadas: '.implode(', ', array_slice($missing, 0, 5))];
        }

        return max(0, min(100, $total));
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $facts
     * @param  array<string, array<int, array<string, mixed>>>  $inferences
     * @param  callable(array): float  $weight
     * @param  list<array{signal_key: string, points: int, explanation: string}>  $reasons
     */
    private function commercial(array $facts, array $inferences, callable $weight, array &$reasons): int
    {
        $total = 0;
        $total += $this->scoreKey('freelance_contract', 35, 'Contrato/freelance', $facts, $inferences, $weight, $reasons);
        $total += $this->scoreKey('accepts_external', 35, 'Acepta freelancers/marca blanca', $facts, $inferences, $weight, $reasons);

        $type = 0;
        $typeKey = null;
        foreach (['agency_type' => 25, 'consultancy_type' => 15, 'product_type' => 10] as $key => $points) {
            $candidate = $this->keyPoints($key, $points, $facts, $inferences, $weight);

            if ($candidate > $type) {
                $type = $candidate;
                $typeKey = $key;
            }
        }

        if ($type > 0 && $typeKey !== null) {
            $total += $type;
            $reasons[] = ['signal_key' => $typeKey, 'points' => $type, 'explanation' => "Tipo de empresa: {$type}"];
        }

        if (isset($facts['recruiter']) || isset($inferences['recruiter'])) {
            $reasons[] = ['signal_key' => 'recruiter', 'points' => 0, 'explanation' => 'Reclutadora: señal de mercado, no comprador directo'];
        }

        $total += $this->scoreKey('large_outsourcer', -30, 'Outsourcer grande', $facts, $inferences, $weight, $reasons);
        $total += $this->scoreKey('team_over_200', -30, 'Plantilla > 200', $facts, $inferences, $weight, $reasons);
        $total += $this->scoreKey('multi_vacancies', 15, '≥ 2 vacantes dev', $facts, $inferences, $weight, $reasons);
        $total += $this->scoreKey('fixed_job', 10, 'Oferta de empleo fijo', $facts, $inferences, $weight, $reasons);
        $total += $this->scoreKey('low_prices', -25, 'Precios públicos bajos', $facts, $inferences, $weight, $reasons);

        return max(0, min(100, $total));
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $facts
     * @param  array<string, array<int, array<string, mixed>>>  $inferences
     * @param  callable(array): float  $weight
     * @param  list<array{signal_key: string, points: int, explanation: string}>  $reasons
     */
    private function recurrent(array $facts, array $inferences, callable $weight, array &$reasons): int
    {
        $total = 0;
        $total += $this->scoreKey('staff_augmentation', 35, 'Staff augmentation', $facts, $inferences, $weight, $reasons);
        $total += $this->scoreKey('maintenance_sla', 30, 'Mantenimiento/soporte/SLA', $facts, $inferences, $weight, $reasons);
        $total += $this->scoreKey('long_term', 25, 'Largo plazo', $facts, $inferences, $weight, $reasons);
        $total += $this->scoreKey('many_cases', 20, '≥ 5 casos/clientes', $facts, $inferences, $weight, $reasons);
        $total += $this->scoreKey('long_clients', 15, 'Clientes de varios años', $facts, $inferences, $weight, $reasons);
        $total += $this->scoreKey('active_vacancy', 15, 'Vacante dev activa', $facts, $inferences, $weight, $reasons);
        $total += $this->scoreKey('one_off', 5, 'Proyecto puntual', $facts, $inferences, $weight, $reasons);

        return max(0, min(100, $total));
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $facts
     * @param  array<string, array<int, array<string, mixed>>>  $inferences
     * @param  callable(array): float  $weight
     * @param  list<array{signal_key: string, points: int, explanation: string}>  $reasons
     */
    private function vitality(array $facts, array $inferences, callable $weight, CarbonImmutable $now, array &$reasons): int
    {
        $total = 0;

        $freshness = $this->contentFreshness($facts, $inferences, $now);

        if ($freshness !== null) {
            $total += $freshness['points'];
            $reasons[] = ['signal_key' => $freshness['key'], 'points' => $freshness['points'], 'explanation' => $freshness['explanation']];
        }

        $total += $this->scoreKey('sitemap_fresh', 20, 'Sitemap reciente', $facts, $inferences, $weight, $reasons);
        $total += $this->scoreKey('old_sitemap', -20, 'Sitemap antiguo', $facts, $inferences, $weight, $reasons);
        $total += $this->scoreKey('vacancy_vitality', 20, 'Vacante dev activa', $facts, $inferences, $weight, $reasons);
        $total += $this->scoreKey('copyright_recent', 5, 'Copyright vigente', $facts, $inferences, $weight, $reasons);
        $total += $this->scoreKey('old_copyright', -10, 'Copyright antiguo', $facts, $inferences, $weight, $reasons);
        $total += $this->scoreKey('team_5_50', 30, 'Equipo 5-50', $facts, $inferences, $weight, $reasons);
        $total += $this->scoreKey('team_51_200', 15, 'Equipo 51-200', $facts, $inferences, $weight, $reasons);

        return max(0, min(100, $total));
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $facts
     * @param  array<string, array<int, array<string, mixed>>>  $inferences
     * @return array{key: string, points: int, explanation: string}|null
     */
    private function contentFreshness(array $facts, array $inferences, CarbonImmutable $now): ?array
    {
        foreach (['recent_content', 'stale_content'] as $key) {
            foreach ([$facts[$key] ?? [], $inferences[$key] ?? []] as $group) {
                foreach ($group as $signal) {
                    if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', (string) ($signal['value_text'] ?? ''), $m) === 1) {
                        try {
                            $months = CarbonImmutable::create((int) $m[1], (int) $m[2], (int) $m[3])->diffInMonths($now);

                            if ($months <= 6) {
                                return ['key' => $key, 'points' => 40, 'explanation' => 'Contenido de hace ≤ 6 meses: +40'];
                            }

                            if ($months <= 12) {
                                return ['key' => $key, 'points' => 25, 'explanation' => 'Contenido de hace 6-12 meses: +25'];
                            }

                            if ($months <= 24) {
                                return ['key' => $key, 'points' => 5, 'explanation' => 'Contenido de hace 12-24 meses: +5'];
                            }

                            return ['key' => $key, 'points' => -30, 'explanation' => 'Contenido de hace > 24 meses: −30'];
                        } catch (\Exception) {
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $facts
     * @param  array<string, array<int, array<string, mixed>>>  $inferences
     * @param  callable(array): float  $weight
     * @param  list<array{signal_key: string, points: int, explanation: string}>  $reasons
     */
    private function communication(array $facts, array $inferences, callable $weight, array &$reasons): int
    {
        if (isset($facts['lang_es_pt']) || isset($inferences['lang_es_pt'])) {
            $reasons[] = ['signal_key' => 'lang_es_pt', 'points' => 100, 'explanation' => 'Idioma ES/PT: 100'];

            return 100;
        }

        $async = $this->keyPoints('async_english', 80, $facts, $inferences, $weight);

        if ($async > 0) {
            $reasons[] = ['signal_key' => 'async_english', 'points' => $async, 'explanation' => "Inglés escrito/asíncrono: {$async}"];

            return $async;
        }

        $fluent = $this->keyPoints('english_fluent_required', 25, $facts, $inferences, $weight);

        if ($fluent > 0) {
            $reasons[] = ['signal_key' => 'english_fluent_required', 'points' => $fluent, 'explanation' => "Inglés hablado exigido: {$fluent} (penaliza, no descarta)"];

            return $fluent;
        }

        $unknown = $this->keyPoints('english_unknown', 55, $facts, $inferences, $weight);

        if ($unknown > 0) {
            $reasons[] = ['signal_key' => 'english_unknown', 'points' => $unknown, 'explanation' => "Inglés sin más datos: {$unknown}"];

            return $unknown;
        }

        return 40;
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $facts
     * @param  array<string, array<int, array<string, mixed>>>  $inferences
     * @param  callable(array): float  $weight
     * @param  list<array{signal_key: string, points: int, explanation: string}>  $reasons
     */
    private function geoContract(array $facts, array $inferences, callable $weight, ?float $overlap, array &$reasons): int
    {
        $total = 0;
        $total += $this->scoreKey('country_pt_es', 50, 'Empresa PT/ES', $facts, $inferences, $weight, $reasons);
        $total += $this->scoreKey('country_eu', 35, 'Resto de la UE', $facts, $inferences, $weight, $reasons);
        $total += $this->scoreKey('country_uk_ie', 30, 'UK/IE', $facts, $inferences, $weight, $reasons);
        $total += $this->scoreKey('country_us_ca', 15, 'US/CA', $facts, $inferences, $weight, $reasons);
        $total += $this->scoreKey('country_other', 10, 'Resto del mundo', $facts, $inferences, $weight, $reasons);
        $total += $this->scoreKey('accepts_eu_contractors', 30, 'Acepta contractors UE', $facts, $inferences, $weight, $reasons);

        if ($overlap !== null && $overlap >= 4) {
            $total += 10;
            $reasons[] = ['signal_key' => 'overlap_ok', 'points' => 10, 'explanation' => "Solape horario ≥ 4 h ({$overlap} h)"];
        } elseif ($overlap !== null && $overlap < 1) {
            $total -= 15;
            $reasons[] = ['signal_key' => 'overlap_low', 'points' => -15, 'explanation' => "Solape horario < 1 h ({$overlap} h)"];
        }

        $total += $this->scoreKey('local_contract_required', -40, 'Exige contrato local', $facts, $inferences, $weight, $reasons);

        return max(0, min(100, $total));
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $facts
     * @param  array<string, array<int, array<string, mixed>>>  $inferences
     * @param  callable(array): float  $weight
     * @param  list<array{signal_key: string, points: int, explanation: string}>  $reasons
     */
    private function remote(array $facts, array $inferences, callable $weight, array &$reasons): int
    {
        foreach (['remote' => 100, 'hybrid' => 30, 'onsite' => 0] as $key => $points) {
            if (isset($facts[$key]) || isset($inferences[$key])) {
                $weighted = $this->keyPoints($key, $points, $facts, $inferences, $weight);
                $reasons[] = ['signal_key' => $key, 'points' => $weighted, 'explanation' => "Modalidad {$key}: {$weighted}"];

                return $weighted;
            }
        }

        $unknown = $this->keyPoints('remote_unknown', 40, $facts, $inferences, $weight);
        $reasons[] = ['signal_key' => 'remote_unknown', 'points' => $unknown, 'explanation' => "Sin dato remoto: {$unknown}"];

        return $unknown;
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $facts
     * @param  array<string, array<int, array<string, mixed>>>  $inferences
     * @param  callable(array): float  $weight
     * @param  list<array{signal_key: string, points: int, explanation: string}>  $reasons
     */
    private function scoreKey(string $key, int $points, string $explanation, array $facts, array $inferences, callable $weight, array &$reasons): int
    {
        $weighted = $this->keyPoints($key, $points, $facts, $inferences, $weight);

        if ($weighted !== 0 || isset($facts[$key]) || isset($inferences[$key])) {
            $reasons[] = ['signal_key' => $key, 'points' => $weighted, 'explanation' => "{$explanation}: {$weighted}"];
        }

        return $weighted;
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $facts
     * @param  array<string, array<int, array<string, mixed>>>  $inferences
     * @param  callable(array): float  $weight
     */
    private function keyPoints(string $key, int $points, array $facts, array $inferences, callable $weight): int
    {
        if (isset($facts[$key])) {
            return $points;
        }

        if (isset($inferences[$key])) {
            return (int) round($points * $weight($inferences[$key]));
        }

        return 0;
    }

    /**
     * @param  array<int, array{signal_key: string, dimension: string, nature: string, confidence: int, evidence_url: ?string, captured_at: string, value_text: ?string}>  $signals
     * @param  array{weights: array<string, int>, inference_weight: float}  $rules
     */
    private function confidence(array $signals, array $rules): int
    {
        if ($signals === []) {
            return 0;
        }

        $factor = $rules['inference_weight'] ?? 0.6;
        $sum = 0.0;
        $domains = [];
        $newest = null;

        foreach ($signals as $signal) {
            $weight = ($signal['nature'] ?? SignalNature::Fact->value) === SignalNature::Fact->value ? 1.0 : $factor;
            $sum += ((int) ($signal['confidence'] ?? 50)) * $weight / 100;

            if (($signal['evidence_url'] ?? null) !== null) {
                $host = $this->hostOf($signal['evidence_url']);

                if ($host !== null) {
                    $domains[$host] = true;
                }
            }

            $at = (string) ($signal['captured_at'] ?? '');

            if ($newest === null || $at > $newest) {
                $newest = $at;
            }
        }

        $score = $sum / count($signals) * 100;
        $score += min(20, max(0, count($domains) - 1) * 10);

        if ($newest !== null) {
            try {
                if (CarbonImmutable::parse($newest)->diffInDays(CarbonImmutable::now()) > 90) {
                    $score -= 20;
                }
            } catch (\Exception) {
            }
        }

        return (int) max(0, min(100, round($score)));
    }

    private function hostOf(?string $url): ?string
    {
        if ($url === null || preg_match('~^[a-z][a-z0-9+.-]*://([^/:?#]+)~i', $url, $m) !== 1) {
            return null;
        }

        return mb_strtolower($m[1]);
    }
}
