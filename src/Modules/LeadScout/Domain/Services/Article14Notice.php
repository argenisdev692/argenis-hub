<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Services;

/**
 * GDPR art. 14 + LSSI 21.2 notice block (spec FR-26, T071): who processes,
 * which public source, purpose, legal basis, retention, opposition right
 * and a valid reply-to for opt-out. Inserted by the draft template —
 * never removable — and `notified_at` is stamped on `sent`.
 */
final readonly class Article14Notice
{
    #[\NoDiscard('Notice text must be captured')]
    public static function for(
        string $language,
        string $companyName,
        string $sourceUrl,
        string $contactEmail,
        string $policyUrl,
    ): string {
        return match ($language) {
            'pt' => "Aviso de privacidade: a Argenis (trabalhador independente, Portugal) trata os dados profissionais públicos de {$companyName} obtidos em {$sourceUrl} para propor colaboração B2B (contratação de capacidade), com base no interesse legítimo. Conservação máxima de 12 meses sem interação. Pode opor-se a qualquer momento respondendo a {$contactEmail}. Política completa: {$policyUrl}",
            'en' => "Privacy notice: Argenis (independent contractor, Portugal) processes {$companyName}'s public professional data obtained from {$sourceUrl} to propose B2B capacity collaboration, on legitimate-interest grounds. Retained at most 12 months without interaction. You may object any time by replying to {$contactEmail}. Full policy: {$policyUrl}",
            default => "Aviso de privacidad: Argenis (trabajador independiente, Portugal) trata los datos profesionales públicos de {$companyName} obtenidos en {$sourceUrl} para proponer colaboración B2B (contratación de capacidad), con base en el interés legítimo. Conservación máxima de 12 meses sin interacción. Puede oponerse en cualquier momento respondiendo a {$contactEmail}. Política completa: {$policyUrl}",
        };
    }

    /**
     * @return list<string>
     */
    public static function languages(): array
    {
        return ['es', 'pt', 'en'];
    }
}
