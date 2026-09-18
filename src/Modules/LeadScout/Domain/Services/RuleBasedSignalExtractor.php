<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\LeadScout\Domain\Enums\SignalNature;

/**
 * Pure deterministic extractor (spec FR-6, FR-10, T031): posting + page
 * text → `fact` signals with literal excerpts. PT/ES/EN patterns.
 *
 * Covers technologies, remote/contract/language, active vacancies, prices,
 * maintenance/SLA, vitality dates (content, sitemap `lastmod`, copyright),
 * team size, dead/absorbed webs, freelancer cues and B1 communication cues.
 * Anything ambiguous (company type, commercial disposition, async proof) is
 * left for the LLM pass (Phase G) — rules never guess.
 *
 * Inputs are plain arrays so Domain never touches Eloquent (layer rule).
 */
final readonly class RuleBasedSignalExtractor
{
    /**
     * @param  array{country: ?string, canonical_domain: string}  $company
     * @param  array<int, array{url: string, markdown: ?string, fetched_at: string}>  $pages
     * @param  array<int, array{title: string, body: ?string, remote_mode: string, contract_type: string, language: ?string, published_at: ?string, status: string, source_url: string}>  $postings
     * @param  array{sitemap_lastmod: ?string, final_domain: ?string, now: ?CarbonImmutable}  $context
     * @return array<int, array{dimension: string, signal_key: string, value_text: ?string, nature: string, confidence: int, evidence_url: ?string, evidence_excerpt: ?string, captured_at: string}>
     */
    public function extract(array $company, array $pages, array $postings, array $context = []): array
    {
        $now = $context['now'] ?? CarbonImmutable::now();
        $texts = [];

        foreach ($pages as $page) {
            if (($page['markdown'] ?? null) !== null && trim($page['markdown']) !== '') {
                $texts[] = ['text' => $page['markdown'], 'url' => $page['url']];
            }
        }

        foreach ($postings as $posting) {
            $body = trim(($posting['title'] ?? '')."\n".($posting['body'] ?? ''));

            if ($body !== '') {
                $texts[] = ['text' => $body, 'url' => $posting['source_url']];
            }
        }

        $signals = [];
        $push = static function (array $signal) use (&$signals, $now): void {
            $signal['nature'] ??= SignalNature::Fact->value;
            $signal['captured_at'] = $now->toDateTimeString();
            $signals[] = $signal;
        };

        $this->extractTechnical($texts, $push);
        $this->extractCommercial($texts, $postings, $push);
        $this->extractRecurrent($texts, $postings, $push);
        $this->extractVitality($texts, $postings, $context, $now, $push);
        $this->extractCommunication($texts, $postings, $push);
        $this->extractGeo($company, $texts, $push);
        $this->extractRemote($texts, $postings, $push);

        return $signals;
    }

    /**
     * @param  array<int, array{text: string, url: string}>  $texts
     * @param  callable(array): void  $push
     */
    private function extractTechnical(array $texts, callable $push): void
    {
        $joined = implode("\n", array_column($texts, 'text'));

        if ($this->findCue($joined, ['laravel'], $excerpt, $url, $texts)) {
            $push($this->fact('technical', 'laravel', 'Laravel', 90, $url, $excerpt));
        } elseif ($this->findCue($joined, ['\bphp\b'], $excerpt, $url, $texts)) {
            $push($this->fact('technical', 'php_plain', 'PHP', 80, $url, $excerpt));
        }

        if ($this->findCue($joined, ['\bvue\b', 'inertia'], $excerpt, $url, $texts)) {
            $push($this->fact('technical', 'vue_inertia', 'Vue/Inertia', 85, $url, $excerpt));
        }

        if ($this->findCue($joined, ['livewire'], $excerpt, $url, $texts)) {
            $push($this->fact('technical', 'livewire', 'Livewire', 85, $url, $excerpt));
        }

        $stackHits = 0;
        foreach (['postgresql', 'postgres', '\bmysql\b', 'redis', 'docker', '\bci\b', 'github actions', 'gitlab ci'] as $cue) {
            if ($this->findCue($joined, [$cue], $excerpt, $url, $texts)) {
                $stackHits++;
            }
        }

        if ($stackHits > 0) {
            $push($this->fact('technical', 'stack_db', "{$stackHits} stack items", 75, null, null));
        }

        if ($this->findCue($joined, ['\bapi\b', '\brest\b', 'integra', 'openai', 'inteligencia artificial', '\bia\b'], $excerpt, $url, $texts)) {
            $push($this->fact('technical', 'api_ai', 'APIs/integrations', 70, $url, $excerpt));
        }
    }

    /**
     * @param  array<int, array{text: string, url: string}>  $texts
     * @param  array<int, array{title: string, body: ?string, remote_mode: string, contract_type: string, language: ?string, published_at: ?string, status: string, source_url: string}>  $postings
     * @param  callable(array): void  $push
     */
    private function extractCommercial(array $texts, array $postings, callable $push): void
    {
        $joined = implode("\n", array_column($texts, 'text'));

        if ($this->findCue($joined, ['freelance', 'contractor', 'autónomo', 'autonomo', 'recibos verdes', 'por proyecto'], $excerpt, $url, $texts)) {
            $push($this->fact('commercial', 'freelance_contract', 'Freelance/contract', 85, $url, $excerpt));
        }

        if ($this->findCue($joined, ['colaboradores externos', 'colaborador externo', 'trabajamos con freelancers', 'freelancers welcome', 'partner program', 'white label', 'marca blanca', 'subcontrata', 'parceiros'], $excerpt, $url, $texts)) {
            $push($this->fact('commercial', 'accepts_external', 'Accepts external capacity', 85, $url, $excerpt));
        }

        if ($this->findCue($joined, ['agencia de software', 'software agency', 'agência de software', 'agencia digital'], $excerpt, $url, $texts)) {
            $push($this->fact('commercial', 'agency_type', 'Software agency', 80, $url, $excerpt));
        } elseif ($this->findCue($joined, ['consultora', 'consultancy', 'nearshore'], $excerpt, $url, $texts)) {
            $push($this->fact('commercial', 'consultancy_type', 'Consultancy/nearshore', 75, $url, $excerpt));
        } elseif ($this->findCue($joined, ['\bproducto\b', '\bproduct\b', '\bsaas\b'], $excerpt, $url, $texts)) {
            $push($this->fact('commercial', 'product_type', 'Product company', 70, $url, $excerpt));
        }

        if ($this->findCue($joined, ['recruiting', 'reclutamiento', 'talent acquisition', 'recursos humanos', 'trabaja con nosotros.*oferta'], $excerpt, $url, $texts)) {
            $push($this->fact('commercial', 'recruiter', 'Recruiter/intermediary signal', 65, $url, $excerpt));
        }

        $active = array_filter($postings, static fn (array $p): bool => ($p['status'] ?? '') === 'active');

        if (count($active) >= 2) {
            $push($this->fact('commercial', 'multi_vacancies', count($active).' open dev vacancies', 85, null, null));
        }

        foreach ($postings as $posting) {
            if (($posting['contract_type'] ?? '') === 'employment') {
                $push($this->fact('commercial', 'fixed_job', 'Fixed employment offer', 80, $posting['source_url'], mb_substr($posting['title'], 0, 200)));

                break;
            }
        }

        if ($this->findCue($joined, ['low.?cost', 'barato', 'desde \d{1,2}€', 'preços baixos', 'precios bajos'], $excerpt, $url, $texts)) {
            $push($this->fact('commercial', 'low_prices', 'Public low prices', 60, $url, $excerpt));
        }
    }

    /**
     * @param  array<int, array{text: string, url: string}>  $texts
     * @param  array<int, array{title: string, body: ?string, remote_mode: string, contract_type: string, language: ?string, published_at: ?string, status: string, source_url: string}>  $postings
     * @param  callable(array): void  $push
     */
    private function extractRecurrent(array $texts, array $postings, callable $push): void
    {
        $joined = implode("\n", array_column($texts, 'text'));

        if ($this->findCue($joined, ['staff augmentation', 'team extension', 'dedicated team', 'ampliaci.n de equipo', 'equipo dedicado'], $excerpt, $url, $texts)) {
            $push($this->fact('recurrent', 'staff_augmentation', 'Staff augmentation', 85, $url, $excerpt));
        }

        if ($this->findCue($joined, ['mantenimiento', 'manuten..o', 'soporte', 'suporte', 'support plans?', '\bsla\b', 'retainer'], $excerpt, $url, $texts)) {
            $push($this->fact('recurrent', 'maintenance_sla', 'Maintenance/support/SLA', 85, $url, $excerpt));
        }

        if ($this->findCue($joined, ['long.?term', 'largo plazo', 'ongoing', 'continuad', 'relaci.n duradera'], $excerpt, $url, $texts)) {
            $push($this->fact('recurrent', 'long_term', 'Long-term wording', 70, $url, $excerpt));
        }

        $caseHits = preg_match_all('/caso de .xito|case stud|nuestros clientes|our clients|clientes:/i', $joined);

        if ($caseHits >= 5) {
            $push($this->fact('recurrent', 'many_cases', "{$caseHits} case/client mentions", 65, null, null));
        }

        if ($this->findCue($joined, ['varios a.os', 'several years', 'desde 20\d\d.*cliente', 'client since'], $excerpt, $url, $texts)) {
            $push($this->fact('recurrent', 'long_clients', 'Multi-year client relations', 65, $url, $excerpt));
        }

        foreach ($postings as $posting) {
            if (($posting['status'] ?? '') === 'active') {
                $push($this->fact('recurrent', 'active_vacancy', 'Active dev vacancy', 90, $posting['source_url'], mb_substr($posting['title'], 0, 200)));

                break;
            }
        }

        if ($this->findCue($joined, ['proyecto puntual', 'one.?off', 'proyecto .nico'], $excerpt, $url, $texts)) {
            $push($this->fact('recurrent', 'one_off', 'One-off project wording', 60, $url, $excerpt));
        }
    }

    /**
     * @param  array<int, array{text: string, url: string}>  $texts
     * @param  array<int, array{title: string, body: ?string, remote_mode: string, contract_type: string, language: ?string, published_at: ?string, status: string, source_url: string}>  $postings
     * @param  array{sitemap_lastmod: ?string, final_domain: ?string, now: ?CarbonImmutable}  $context
     * @param  callable(array): void  $push
     */
    private function extractVitality(array $texts, array $postings, array $context, CarbonImmutable $now, callable $push): void
    {
        $joined = implode("\n", array_column($texts, 'text'));

        // Dead or absorbed web first: nothing else matters if the site is gone.
        if ($this->findCue($joined, ['domain (is )?for sale', 'buy this domain', 'dominio en venta', 'dom.nio . venda', 'parked courtesy of', 'p.gina aparcada'], $excerpt, $url, $texts)) {
            $push($this->fact('vitality', 'dead_web', 'Parked/for-sale domain', 90, $url, $excerpt));

            return;
        }

        $latest = $this->latestContentDate($joined);

        if ($latest !== null) {
            $months = $latest->diffInMonths($now);

            if ($months <= 6) {
                $push($this->fact('vitality', 'recent_content', "Content from {$latest->toDateString()}", 90, null, null));
            } elseif ($months <= 12) {
                $push($this->fact('vitality', 'recent_content', "Content from {$latest->toDateString()}", 80, null, null));
            } elseif ($months <= 24) {
                $push($this->fact('vitality', 'stale_content', "Content from {$latest->toDateString()}", 70, null, null));
            } else {
                $push($this->fact('vitality', 'stale_content', "Content from {$latest->toDateString()}", 85, null, null));
            }
        }

        if (($context['sitemap_lastmod'] ?? null) !== null) {
            try {
                $lastmod = CarbonImmutable::parse($context['sitemap_lastmod']);
                $months = $lastmod->diffInMonths($now);

                if ($months <= 6) {
                    $push($this->fact('vitality', 'sitemap_fresh', "Sitemap {$lastmod->toDateString()}", 75, null, null));
                } elseif ($months > 24) {
                    $push($this->fact('vitality', 'old_sitemap', "Sitemap {$lastmod->toDateString()}", 70, null, null));
                }
            } catch (\Exception) {
                // Unparseable sitemap date: no signal, never a crash.
            }
        }

        if (preg_match_all('/©\s?(20\d\d)/', $joined, $years) > 0) {
            $year = (int) max($years[1]);
            $current = $now->year;

            if ($year >= $current - 1) {
                $push($this->fact('vitality', 'copyright_recent', "© {$year}", 60, null, null));
            } elseif ($year <= $current - 3) {
                $push($this->inference('vitality', 'old_copyright', "© {$year}", 60, null, null));
            }
        }

        foreach ($postings as $posting) {
            if (($posting['status'] ?? '') === 'active') {
                $push($this->fact('vitality', 'vacancy_vitality', 'Active dev vacancy', 90, $posting['source_url'], mb_substr($posting['title'], 0, 200)));

                break;
            }
        }

        $this->extractTeamSize($joined, $texts, $push);
    }

    /**
     * @param  array<int, array{text: string, url: string}>  $texts
     * @param  callable(array): void  $push
     */
    private function extractTeamSize(string $joined, array $texts, callable $push): void
    {
        if ($this->findCue($joined, ['\bsoy\b', '\bsou\b', "i'm a freelance", 'freelance developer.*portfolio', 'desarrollador freelance.*portfolio'], $excerpt, $url, $texts)) {
            $push($this->fact('vitality', 'solo_freelancer', 'First-person freelancer site', 85, $url, $excerpt));

            return;
        }

        $max = 0;
        foreach (['team of (\d+)', 'somos (\d+)', 'equipa de (\d+)', 'equipo de (\d+)', '(\d+) (people|personas|pessoas|colaboradores)'] as $pattern) {
            if (preg_match_all('/'.$pattern.'/i', $joined, $matches) > 0) {
                foreach ($matches[1] as $number) {
                    $max = max($max, (int) $number);
                }
            }
        }

        if ($max >= 5 && $max <= 50) {
            $push($this->fact('vitality', 'team_5_50', "Team of {$max}", 80, null, null));
        } elseif ($max > 200) {
            $push($this->fact('vitality', 'team_over_200', "Team of {$max}", 80, null, null));
        } elseif ($max >= 51 && $max <= 200) {
            $push($this->fact('vitality', 'team_51_200', "Team of {$max}", 80, null, null));
        } elseif ($max >= 2 && $max <= 4) {
            $push($this->fact('vitality', 'team_2_4', "Team of {$max}", 80, null, null));
        } elseif ($max === 1) {
            $push($this->fact('vitality', 'solo_freelancer', 'Team of 1', 80, null, null));
        } else {
            $push($this->fact('vitality', 'team_unknown', 'No team size found', 50, null, null));
        }
    }

    /**
     * @param  array<int, array{text: string, url: string}>  $texts
     * @param  array<int, array{title: string, body: ?string, remote_mode: string, contract_type: string, language: ?string, published_at: ?string, status: string, source_url: string}>  $postings
     * @param  callable(array): void  $push
     */
    private function extractCommunication(array $texts, array $postings, callable $push): void
    {
        $joined = implode("\n", array_column($texts, 'text'));
        $postingText = implode("\n", array_map(static fn (array $p): string => ($p['title'] ?? '').' '.($p['body'] ?? ''), $postings));

        $postingLangEsPt = false;

        foreach ($postings as $posting) {
            $lang = mb_strtolower(trim((string) ($posting['language'] ?? '')));

            if (in_array($lang, ['es', 'es-es', 'pt', 'pt-pt', 'pt-br'], true)) {
                $postingLangEsPt = true;

                break;
            }
        }

        $hasEsPt = $postingLangEsPt;
        $excerpt = null;
        $url = null;

        if (! $hasEsPt) {
            $hasEsPt = $this->findCue($joined."\n".$postingText, ['\bespañol\b', '\bespaña\b', '\bportuguês\b', '\bportugal\b', 'idioma: es', 'idioma: pt'], $excerpt, $url, $texts);
        }

        if ($hasEsPt) {
            $push($this->fact('communication', 'lang_es_pt', 'ES/PT working language', 95, $url, $excerpt));
        }

        if ($this->findCue($joined, ['remote.?first', 'async', 'asíncrono', 'assíncrono', 'distributed team', 'equipo distribuido', 'equipa distribuída', 'por escrito', 'written communication', 'trabajo por tickets'], $excerpt, $url, $texts)) {
            $push($this->fact('communication', 'async_english', 'Written/async English proof', 80, $url, $excerpt));

            return;
        }

        if ($this->findCue($joined."\n".$postingText, ['fluent english', 'native english', 'inglés fluido', 'inglés nativo', 'inglês fluente', 'daily client calls', 'trato directo con el cliente', 'client-facing'], $excerpt, $url, $texts)) {
            $push($this->fact('communication', 'english_fluent_required', 'Spoken/native English required', 80, $url, $excerpt));

            return;
        }

        if (! $hasEsPt && $this->findCue($joined."\n".$postingText, ['\benglish\b', 'inglés', 'inglês'], $excerpt, $url, $texts)) {
            $push($this->fact('communication', 'english_unknown', 'English without further cues', 55, $url, $excerpt));
        }
    }

    /**
     * @param  array{country: ?string, canonical_domain: string}  $company
     * @param  array<int, array{text: string, url: string}>  $texts
     * @param  callable(array): void  $push
     */
    private function extractGeo(array $company, array $texts, callable $push): void
    {
        $country = $company['country'];
        $joined = implode("\n", array_column($texts, 'text'));

        if ($country === 'PT' || $country === 'ES') {
            $push($this->fact('geo_contract', 'country_pt_es', $country, 95, null, null));
        } elseif ($country !== null && in_array($country, ['DE', 'FR', 'IT', 'NL', 'BE', 'AT', 'IE', 'DK', 'SE', 'FI', 'PL', 'CZ', 'GR', 'RO', 'LT', 'LV', 'EE'], true)) {
            $push($this->fact('geo_contract', 'country_eu', $country, 90, null, null));
        } elseif ($country === 'GB' || $country === 'UK') {
            $push($this->fact('geo_contract', 'country_uk_ie', $country, 90, null, null));
        } elseif ($country === 'US' || $country === 'CA') {
            $push($this->fact('geo_contract', 'country_us_ca', $country, 90, null, null));
        } elseif ($country !== null) {
            $push($this->fact('geo_contract', 'country_other', $country, 85, null, null));
        }

        if ($this->findCue($joined, ['contrato local', 'residencia en', 'must reside', 'only .* residents', 'local contract'], $excerpt, $url, $texts)) {
            $push($this->fact('geo_contract', 'local_contract_required', 'Local contract/residence required', 80, $url, $excerpt));
        }
    }

    /**
     * @param  array<int, array{text: string, url: string}>  $texts
     * @param  array<int, array{title: string, body: ?string, remote_mode: string, contract_type: string, language: ?string, published_at: ?string, status: string, source_url: string}>  $postings
     * @param  callable(array): void  $push
     */
    private function extractRemote(array $texts, array $postings, callable $push): void
    {
        foreach ($postings as $posting) {
            if (($posting['remote_mode'] ?? '') === 'remote') {
                $push($this->fact('remote', 'remote', 'Remote posting', 90, $posting['source_url'], mb_substr($posting['title'], 0, 200)));

                return;
            }
        }

        $joined = implode("\n", array_column($texts, 'text'));

        if ($this->findCue($joined, ['100% remot', 'fully remote', 'totalmente remoto', 'remote.?first'], $excerpt, $url, $texts)) {
            $push($this->fact('remote', 'remote', 'Remote wording', 80, $url, $excerpt));

            return;
        }

        foreach ($postings as $posting) {
            if (($posting['remote_mode'] ?? '') === 'hybrid') {
                $push($this->fact('remote', 'hybrid', 'Hybrid posting', 85, $posting['source_url'], mb_substr($posting['title'], 0, 200)));

                return;
            }
        }

        foreach ($postings as $posting) {
            if (($posting['remote_mode'] ?? '') === 'onsite') {
                $push($this->fact('remote', 'onsite', 'Onsite posting', 85, $posting['source_url'], mb_substr($posting['title'], 0, 200)));

                return;
            }
        }

        $push($this->fact('remote', 'remote_unknown', 'No remote cues', 40, null, null));
    }

    /**
     * Finds the first cue across texts and returns a bounded literal excerpt.
     *
     * @param  list<string>  $cues
     * @param  array<int, array{text: string, url: string}>  $texts
     */
    private function findCue(string $joined, array $cues, ?string &$excerpt, ?string &$url, array $texts): bool
    {
        foreach ($cues as $cue) {
            if (preg_match('/'.$cue.'/i', $joined, $matches, PREG_OFFSET_CAPTURE) === 1) {
                $at = (int) $matches[0][1];
                // Phones never reach stored evidence (FR-32).
                $excerpt = PersonalDataScrubber::maskPhones(mb_substr($joined, max(0, $at - 120), 300));
                $url = $this->urlAtOffset($texts, $at);

                return true;
            }
        }

        $excerpt = null;
        $url = null;

        return false;
    }

    /**
     * @param  array<int, array{text: string, url: string}>  $texts
     */
    private function urlAtOffset(array $texts, int $offset): ?string
    {
        $cursor = 0;

        foreach ($texts as $entry) {
            $cursor += mb_strlen($entry['text']) + 1;

            if ($offset < $cursor) {
                return $entry['url'];
            }
        }

        return null;
    }

    private function latestContentDate(string $joined): ?CarbonImmutable
    {
        $months = [
            'janeiro' => 1, 'fevereiro' => 2, 'março' => 3, 'marco' => 3, 'abril' => 4, 'maio' => 5, 'junho' => 6,
            'julho' => 7, 'agosto' => 8, 'setembro' => 9, 'outubro' => 10, 'novembro' => 11, 'dezembro' => 12,
            'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril_es' => 4, 'mayo' => 5, 'junio' => 6,
            'julio' => 7, 'agosto_es' => 8, 'septiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12,
            'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4, 'may' => 5, 'june' => 6,
            'july' => 7, 'august' => 8, 'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
        ];

        $best = null;
        $consider = static function (?CarbonImmutable $date) use (&$best): void {
            if ($date !== null && ($best === null || $date->gt($best))) {
                $best = $date;
            }
        };

        foreach ($months as $name => $month) {
            $plain = str_replace('_es', '', $name);

            if (preg_match_all('/(\d{1,2}) de '.$plain.' de (20\d\d)/i', $joined, $found, PREG_SET_ORDER) > 0) {
                foreach ($found as $row) {
                    try {
                        $consider(CarbonImmutable::create((int) $row[2], $month, min(28, (int) $row[1])));
                    } catch (\Exception) {
                    }
                }
            }

            if (preg_match_all('/'.$plain.' (\d{1,2}), (20\d\d)/i', $joined, $found, PREG_SET_ORDER) > 0) {
                foreach ($found as $row) {
                    try {
                        $consider(CarbonImmutable::create((int) $row[2], $month, min(28, (int) $row[1])));
                    } catch (\Exception) {
                    }
                }
            }
        }

        if (preg_match_all('/(20\d\d)-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])/', $joined, $found, PREG_SET_ORDER) > 0) {
            foreach ($found as $row) {
                try {
                    $consider(CarbonImmutable::create((int) $row[1], (int) $row[2], (int) $row[3]));
                } catch (\Exception) {
                }
            }
        }

        return $best;
    }

    /**
     * @return array{dimension: string, signal_key: string, value_text: ?string, nature: string, confidence: int, evidence_url: ?string, evidence_excerpt: ?string}
     */
    private function fact(string $dimension, string $key, ?string $value, int $confidence, ?string $url, ?string $excerpt): array
    {
        return [
            'dimension' => $dimension,
            'signal_key' => $key,
            'value_text' => $value,
            'nature' => SignalNature::Fact->value,
            'confidence' => $confidence,
            'evidence_url' => $url,
            'evidence_excerpt' => $excerpt === null ? null : trim($excerpt),
        ];
    }

    /**
     * @return array{dimension: string, signal_key: string, value_text: ?string, nature: string, confidence: int, evidence_url: ?string, evidence_excerpt: ?string}
     */
    private function inference(string $dimension, string $key, ?string $value, int $confidence, ?string $url, ?string $excerpt): array
    {
        $signal = $this->fact($dimension, $key, $value, $confidence, $url, $excerpt);
        $signal['nature'] = SignalNature::Inference->value;

        return $signal;
    }
}
