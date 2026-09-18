<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\ValueObjects;

use Modules\LeadScout\Domain\Enums\RoleCategory;

/**
 * Decisor taxonomy PT/ES/EN (spec US-11, FR-23): allowed titles per
 * category, an exclusion list, everything else is ambiguous → discarded
 * (never persisted). Extraction stays deterministic, without IA (Q17).
 */
final readonly class RoleTaxonomy
{
    /**
     * @var array<string, list<string>>
     */
    private const array ALLOWED = [
        'founder' => [
            'fundador', 'fundadora', 'cofundador', 'cofundadora', 'co-founder', 'cofounder',
            'sócio-fundador', 'sócia-fundadora', 'socio fundador', 'socia fundadora',
        ],
        'executive' => [
            'ceo', 'managing director', 'director general', 'directora general',
            'diretor geral', 'diretora geral', 'sócio-gerente', 'sócia-gerente',
            'socio-gerente', 'socia-gerente', 'partner', 'socio', 'sócio', 'socia', 'sócia',
            'president', 'presidente', 'presidenta',
        ],
        'technical_lead' => [
            'cto', 'director técnico', 'directora técnica', 'diretor técnico', 'diretora técnica',
            'head of engineering', 'head of development', 'head of delivery',
            'vp engineering', 'vp of engineering', 'engineering manager',
        ],
    ];

    /**
     * @var list<string>
     */
    private const array EXCLUDED = [
        'recruiter', 'recruitment', 'reclutador', 'reclutadora', 'talent acquisition',
        'talent', 'rrhh', 'recursos humanos', 'people', 'pessoas', 'rh',
        'developer', 'programador', 'programadora', 'desenvolvedor', 'desenvolvedora',
        'designer', 'diseñador', 'marketing', 'sales', 'comercial', 'vendas',
        'intern', 'becario', 'becaria', 'estagiário', 'estagiária',
        'assistant', 'asistente', 'assistente', 'tech lead', 'team lead',
        'support', 'soporte', 'suporte', 'account manager', 'office manager',
    ];

    public static function classify(string $title): ?RoleCategory
    {
        $normalized = mb_strtolower(trim($title));

        if ($normalized === '') {
            return null;
        }

        foreach (self::EXCLUDED as $excluded) {
            if (str_contains($normalized, $excluded)) {
                return null;
            }
        }

        foreach (self::ALLOWED as $category => $titles) {
            foreach ($titles as $allowed) {
                if (str_contains($normalized, $allowed)) {
                    return RoleCategory::from($category);
                }
            }
        }

        return null;
    }

    /**
     * Contact priority by company size (spec US-11 CA-7).
     *
     * @return list<RoleCategory>
     */
    #[\NoDiscard]
    public static function preferredOrder(?int $teamSize): array
    {
        if ($teamSize !== null && $teamSize > 25) {
            return [RoleCategory::TechnicalLead, RoleCategory::Executive, RoleCategory::Founder];
        }

        return [RoleCategory::Founder, RoleCategory::Executive, RoleCategory::TechnicalLead];
    }
}
