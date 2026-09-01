<?php

declare(strict_types=1);

namespace Modules\Campaigns\Domain\Enums;

/**
 * Output language the user picks for AI generation — never inferred, always
 * explicit (the same niche can be worked in either market).
 * pt-PT = European Portuguese (Portugal), not Brazilian.
 */
enum CampaignLanguage: string
{
    case Spanish = 'es';
    case English = 'en';
    case PortuguesePortugal = 'pt-PT';

    public function label(): string
    {
        return match ($this) {
            self::Spanish => 'Español (LatAm neutro)',
            self::English => 'English',
            self::PortuguesePortugal => 'Português (Portugal)',
        };
    }

    /** Instruction injected into AI prompts so pt-PT is never confused with pt-BR. */
    public function outputInstruction(): string
    {
        return match ($this) {
            self::Spanish => 'OUTPUT LANGUAGE (mandatory): Write all copy in Spanish (neutral Latin American).',
            self::English => 'OUTPUT LANGUAGE (mandatory): Write all copy in English.',
            self::PortuguesePortugal => 'OUTPUT LANGUAGE (mandatory): Write all copy in European Portuguese (Portugal / pt-PT). Use Portugal orthography and vocabulary (e.g. "telemóvel", "equipa", "utilizador") — NOT Brazilian Portuguese.',
        };
    }
}
