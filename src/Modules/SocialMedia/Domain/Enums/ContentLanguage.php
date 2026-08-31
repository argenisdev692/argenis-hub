<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Domain\Enums;

/**
 * Output language the user picks for AI generation — never inferred, always
 * explicit (the same topic/niche can be worked in either market).
 * pt-PT = European Portuguese (Portugal), not Brazilian.
 */
enum ContentLanguage: string
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
            self::Spanish => 'OUTPUT LANGUAGE (mandatory): Write all content in Spanish (neutral Latin American).',
            self::English => 'OUTPUT LANGUAGE (mandatory): Write all content in English.',
            self::PortuguesePortugal => 'OUTPUT LANGUAGE (mandatory): Write all content in European Portuguese (Portugal / pt-PT). Use Portugal orthography and vocabulary (e.g. "telemóvel", "equipa", "utilizador") — NOT Brazilian Portuguese.',
        };
    }
}
