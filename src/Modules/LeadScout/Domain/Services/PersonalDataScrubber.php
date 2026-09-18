<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Services;

/**
 * Strips everything a provider must never receive (spec FR-25, clarify
 * A16): emails, phones and person blocks (signed testimonials, post
 * authors/bylines, email signatures, team cards), then replaces known
 * decisor names with `[PERSONA]`. Service pages pass through untouched.
 */
final readonly class PersonalDataScrubber
{
    public const string PERSON = '[PERSONA]';

    public const string EMAIL = '[EMAIL]';

    public const string PHONE = '[TELÉFONO]';

    /**
     * @param  list<string>  $knownNames  decisor names detected upstream
     */
    #[\NoDiscard('Scrubbed text must be captured')]
    public function scrub(string $text, array $knownNames = []): string
    {
        $text = $this->stripPersonBlocks($text);
        $text = $this->stripContacts($text);

        foreach ($knownNames as $name) {
            $name = trim($name);

            if ($name !== '') {
                $text = preg_replace('/'.preg_quote($name, '/').'/iu', self::PERSON, $text) ?? $text;
            }
        }

        return $text;
    }

    /**
     * Masks phone-like sequences while keeping the surrounding text literal.
     * Used for stored evidence excerpts: phones are never persisted (FR-32).
     */
    #[\NoDiscard('Masked text must be captured')]
    public static function maskPhones(string $text): string
    {
        return (string) preg_replace(
            '/(\+\d{1,3}[\s().-]*)?(\(?\d[\d\s().-]{7,}\d)/',
            self::PHONE,
            $text,
        );
    }

    private function stripPersonBlocks(string $text): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $kept = [];
        $skipNext = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Signed testimonial tail ("— María García, CEO de X").
            if (preg_match('/^(\p{Pd}|—|-|»|").{0,120}(CEO|CEO|fundador|fundadora|director|directora|gerente|sócio|socio|Founder|author)/iu', $trimmed) === 1) {
                continue;
            }

            // Bylines and signatures.
            if (preg_match('/^(por|by|autor|author|firma|assinatura|contacto|contact)\s*:/iu', $trimmed) === 1) {
                $skipNext = true;

                continue;
            }

            if ($skipNext) {
                $skipNext = false;

                if ($trimmed !== '' && mb_strlen($trimmed) < 120) {
                    continue;
                }
            }

            // Team cards ("**Ana Ruiz** — CTO" / "Ana Ruiz, CTO").
            if (preg_match('/(\*\*[^*]{2,60}\*\*|^[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+ [A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)\s*(—|-|,)\s*(CEO|CTO|fundador|fundadora|developer|desarrollador|desenvolvedor|designer|manager|director| sóci)/u', $trimmed) === 1) {
                $kept[] = self::PERSON;

                continue;
            }

            $kept[] = $line;
        }

        return implode("\n", $kept);
    }

    private function stripContacts(string $text): string
    {
        $text = (string) preg_replace(
            '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i',
            self::EMAIL,
            $text,
        );

        return (string) preg_replace(
            '/(\+\d{1,3}[\s().-]*)?(\(?\d[\d\s().-]{7,}\d)/',
            self::PHONE,
            $text,
        );
    }
}
