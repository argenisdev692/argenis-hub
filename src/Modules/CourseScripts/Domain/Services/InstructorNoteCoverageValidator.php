<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Services;

/**
 * FR-36c: the instructor note must explain every designed contrast. A dimension
 * counts as explained when its significant words appear in the note — the
 * sample's note names "precio, cobertura y condiciones", the very dimensions
 * the two proposals differ on.
 */
final readonly class InstructorNoteCoverageValidator
{
    private const int MIN_WORD_LENGTH = 4;

    /**
     * @param  list<array<string, mixed>>  $contrasts
     * @return list<string>
     */
    #[\NoDiscard]
    public function violations(string $instructorNote, array $contrasts): array
    {
        $note = $this->normalise($instructorNote);
        $violations = [];

        foreach ($contrasts as $contrast) {
            $dimension = (string) ($contrast['dimension'] ?? '');
            $words = array_filter(
                explode(' ', $this->normalise($dimension)),
                static fn (string $word): bool => mb_strlen($word) >= self::MIN_WORD_LENGTH,
            );

            if ($words === []) {
                continue;
            }

            $mentioned = array_filter($words, static fn (string $word): bool => str_contains($note, mb_substr($word, 0, max(self::MIN_WORD_LENGTH, mb_strlen($word) - 2))));

            if ($mentioned === []) {
                $violations[] = sprintf('The instructor note does not explain the designed contrast "%s".', $dimension);
            }
        }

        return $violations;
    }

    private function normalise(string $text): string
    {
        $ascii = TextNormaliser::ascii($text);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower($ascii)) ?? '');
    }
}
