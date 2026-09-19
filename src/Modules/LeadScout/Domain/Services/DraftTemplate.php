<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Services;

/**
 * Versioned draft skeletons (spec FR-41: `template_key` + `template_version`
 * frozen on every outreach). The AI writes ONLY the opener; greeting,
 * angle, proof, CTA and signature are deterministic here — including the
 * art. 14 notice and opt-out line, which are never removable (FR-26).
 */
final readonly class DraftTemplate
{
    /**
     * @var array<string, array<string, string>>
     */
    private const array ANGLES = [
        'vacancy' => [
            'es' => 'Vi vuestra oferta y encajo con lo que buscáis.',
            'pt' => 'Vi o vosso anúncio e encaixo no que procuram.',
            'en' => 'I saw your posting and I match what you are looking for.',
        ],
        'stack' => [
            'es' => 'Trabajáis con mi stack principal a diario.',
            'pt' => 'Trabalham com a minha stack principal diariamente.',
            'en' => 'You work daily with my core stack.',
        ],
        'legacy' => [
            'es' => 'Mantengo sistemas legacy en producción sin dramas.',
            'pt' => 'Faço manutenção de sistemas legacy em produção.',
            'en' => 'I keep legacy systems running in production.',
        ],
        'sector' => [
            'es' => 'Conozco vuestro sector por proyectos entregados.',
            'pt' => 'Conheço o vosso setor por projetos entregues.',
            'en' => 'I know your sector from delivered projects.',
        ],
    ];

    /**
     * @var array<string, array<string, string>>
     */
    private const array PROOF_LEAD = [
        'es' => 'Prueba pública relacionada:',
        'pt' => 'Prova pública relacionada:',
        'en' => 'Related public proof:',
    ];

    /**
     * @var array<string, array<string, string>>
     */
    private const array CTA = [
        'es' => '¿Hablamos 15 minutos esta semana sobre vuestra capacidad?',
        'pt' => 'Falamos 15 minutos esta semana sobre a vossa capacidade?',
        'en' => 'Open to a 15-minute call this week about your capacity needs?',
    ];

    /**
     * @param  array{title: string, summary: string, url: ?string}|null  $proof
     * @return array{subject: string, body: string}
     */
    #[\NoDiscard]
    public static function render(
        string $variant,
        string $language,
        string $decisorFirstName,
        string $opener,
        ?array $proof,
        string $companyName,
        string $art14,
        string $bajaEmail,
    ): array {
        $angle = self::ANGLES[$variant][$language] ?? self::ANGLES[$variant]['es'];
        $greeting = self::greeting($language, $decisorFirstName);
        $proofBlock = $proof === null
            ? self::genericCapacity($language)
            : self::PROOF_LEAD[$language]." {$proof['title']} — {$proof['summary']}".($proof['url'] !== null ? " ({$proof['url']})" : '');
        $cta = self::CTA[$language] ?? self::CTA['es'];
        $sign = self::signature($language);

        $body = "{$greeting}\n\n{$opener}\n\n{$angle}\n\n{$proofBlock}\n\n{$cta}\n\n{$sign}\n\n---\n{$art14}\nBaja: responda a {$bajaEmail} con «BAJA».";

        return [
            'subject' => self::subject($language, $companyName),
            'body' => $body,
        ];
    }

    private static function greeting(string $language, string $firstName): string
    {
        return match ($language) {
            'pt' => "Olá {$firstName},",
            'en' => "Hi {$firstName},",
            default => "Hola {$firstName},",
        };
    }

    private static function genericCapacity(string $language): string
    {
        return match ($language) {
            'pt' => 'Trabalho como contractor Laravel externo, em marca branca, com faturação própria.',
            'en' => 'I work as an external Laravel contractor, white-label, invoicing on my own.',
            default => 'Trabajo como contractor Laravel externo, en marca blanca, facturando por mi cuenta.',
        };
    }

    private static function signature(string $language): string
    {
        return match ($language) {
            'pt' => "Cumprimentos,\nArgenis — Contractor Laravel (recibos verdes)",
            'en' => "Best regards,\nArgenis — Laravel contractor",
            default => "Un saludo,\nArgenis — Contractor Laravel",
        };
    }

    private static function subject(string $language, string $companyName): string
    {
        return match ($language) {
            'pt' => "Capacidade Laravel externa para {$companyName}",
            'en' => "External Laravel capacity for {$companyName}",
            default => "Capacidad Laravel externa para {$companyName}",
        };
    }
}
